<?php
require_once __DIR__ . '/Controller.php';

// ============================================================
// CONTROLADOR: GESTIÓN DE USUARIOS (ALTA, BAJA Y MODIFICACIÓN)
// Para el bibliotecario. La baja es lógica: nunca se usa DELETE, solo se
// cambia "activo". Reglas: nadie puede darse de baja a sí mismo y siempre
// tiene que quedar al menos un bibliotecario activo.
// Acciones:
//   index      lista de usuarios y formulario de alta
//   editar     lo mismo, con el formulario cargado con un usuario (?id=)
//   guardar    alta (id = 0) o edición (id > 0) (POST)
//   baja       da de baja a un usuario (POST)
//   reactivar  vuelve a activar a un usuario (POST)
// ============================================================
class UsuarioAdminController extends Controller
{
    // Los únicos roles permitidos.
    private const ROLES = [Cliente::ROL, Bibliotecario::ROL];

    // ---------- Pantalla ----------

    public function index(): void
    {
        $this->requerirBibliotecario();
        $this->mostrar($this->usuarioNuevo(), []);
    }

    // Si en la dirección viene ?id=NÚMERO, se rellenan los campos con ese usuario.
    // La consulta no trae la contraseña cifrada: nunca sale en la página.
    public function editar(): void
    {
        $this->requerirBibliotecario();

        $encontrado = $this->usuarios->buscar((int) ($_GET['id'] ?? 0));

        if ($encontrado) {
            $this->mostrar($encontrado, []);
        } else {
            $this->mostrar($this->usuarioNuevo(), ['El usuario que quieres editar no existe.']);
        }
    }

    // Muestra la lista de usuarios (activos e inactivos) con el formulario.
    private function mostrar(Usuario $usuario, array $errores): void
    {
        [$mensaje, $tipo] = $this->leerAviso('usuarios');

        $this->render('usuarios/administrar', [
            'usuario' => $usuario,          // el del formulario (id 0 = nuevo)
            'errores' => $errores,
            'roles' => self::ROLES,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'lista' => $this->usuarios->todos(),
        ], 'usuarioadmin', 'Gestión de usuarios');
    }

    private function usuarioNuevo(): Usuario
    {
        return new Cliente(0, '', '');
    }

    // ---------- Alta y edición ----------

    public function guardar(): void
    {
        $bibliotecario = $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('usuarioadmin');
        }

        // Recoge lo escrito en el formulario.
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = $this->post('nombre');
        $correo = $this->post('correo');
        $rol = $this->post('rol', false);
        $clave = $this->post('clave', false);
        $errores = [];

        // ---------- Validaciones ----------
        // Datos actuales del usuario, si es una edición.
        $actual = null;
        if ($id > 0) {
            $actual = $this->usuarios->buscar($id);

            if (!$actual) {
                $errores[] = 'El usuario que quieres editar no existe.';
            }
        }

        $errores = array_merge($errores, $this->validarCuenta($nombre, $correo, $id));

        // La contraseña es obligatoria al crear. Al editar puede quedar vacía, y
        // entonces se conserva la que ya tenía.
        if ($id == 0 && $clave == '') {
            $errores[] = 'La contraseña es obligatoria.';
        }

        if (!in_array($rol, self::ROLES)) {
            $errores[] = 'Elige un rol válido.';
        }

        // No se puede pasar a cliente al último bibliotecario activo.
        if ($actual && $actual->esBibliotecario() && $actual->estaActivo() && $rol == Cliente::ROL
            && $this->usuarios->contarBibliotecariosActivos() <= 1) {
            $errores[] = 'Debe existir al menos un bibliotecario activo.';
        }

        // ---------- Guardado ----------
        if (count($errores) == 0) {
            $guardado = false;

            try {
                if ($id == 0) {
                    // Alta: la contraseña se guarda cifrada y el usuario queda activo.
                    $guardado = $this->usuarios->crear($nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $rol);
                    $aviso = 'Usuario agregado: ' . $nombre . '.';
                } elseif ($clave == '') {
                    // Edición sin contraseña nueva: la contraseña actual no se toca.
                    $guardado = $this->usuarios->actualizar($id, $nombre, $correo, $rol);
                    $aviso = 'Usuario actualizado: ' . $nombre . '.';
                } else {
                    // Edición con contraseña nueva: se guarda cifrada.
                    $guardado = $this->usuarios->actualizar($id, $nombre, $correo, $rol, password_hash($clave, PASSWORD_DEFAULT));
                    $aviso = 'Usuario actualizado: ' . $nombre . ' (con contraseña nueva).';
                }
            } catch (Throwable $e) {
                // Error inesperado de la base: no se muestran detalles internos.
                $guardado = false;
            }

            if ($guardado) {
                // Si el bibliotecario editó su propia cuenta, se actualiza su sesión.
                if ($id == $bibliotecario->getId()) {
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['rol'] = $rol;
                }

                // Guarda el aviso y recarga la página, para que al refrescar el
                // navegador no se repita el guardado.
                $this->guardarAviso('usuarios', $aviso);
                $this->redirigir('usuarioadmin');
            }

            $errores[] = 'No se pudo guardar el usuario.';
        }

        // Con errores, el formulario conserva lo que se escribió.
        $this->mostrar(Usuario::desdeFila(['id' => $id, 'nombre' => $nombre, 'correo' => $correo, 'rol' => $rol, 'activo' => 1]), $errores);
    }

    // ---------- Baja y reactivación ----------

    public function baja(): void
    {
        $this->cambiarEstado(false);
    }

    public function reactivar(): void
    {
        $this->cambiarEstado(true);
    }

    private function cambiarEstado(bool $activar): void
    {
        $bibliotecario = $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('usuarioadmin');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $aviso = '';
        $tipo = 'error';

        // Busca al usuario para conocer su rol y su estado actual.
        $encontrado = $this->usuarios->buscar($id);

        if (!$encontrado) {
            $aviso = 'El usuario no existe.';
        } elseif (!$activar && $id == $bibliotecario->getId()) {
            // Regla 1: nadie puede darse de baja a sí mismo.
            $aviso = 'No puedes darte de baja a ti mismo.';
        } elseif (!$activar && $encontrado->esBibliotecario() && $encontrado->estaActivo()
            && $this->usuarios->contarBibliotecariosActivos() <= 1) {
            // Regla 2: no se puede dar de baja al último bibliotecario activo.
            $aviso = 'Debe existir al menos un bibliotecario activo.';
        } else {
            $this->usuarios->cambiarEstado($id, $activar);

            $aviso = $activar ? 'Usuario reactivado.' : 'Usuario dado de baja.';
            $tipo = 'exito';
        }

        $this->guardarAviso('usuarios', $aviso, $tipo);
        $this->redirigir('usuarioadmin');
    }
}
