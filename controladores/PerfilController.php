<?php
require_once __DIR__ . '/Controller.php';

// ============================================================
// CONTROLADOR: MI PERFIL
// Para cualquier usuario con sesión iniciada: muestra sus datos y le
// permite cambiar su nombre, su correo y su contraseña. Para cambiar
// el correo o la contraseña tiene que confirmar su contraseña actual;
// para cambiar solo el nombre, no. El rol y el estado solo se muestran.
// Cada usuario solo puede editar SU propia cuenta: el id nunca se toma
// del formulario ni de la dirección, siempre es el de la sesión.
// Acciones:
//   index    muestra el perfil
//   guardar  guarda los cambios (POST)
// ============================================================
class PerfilController extends Controller
{
    public function index(): void
    {
        $usuario = $this->requerirSesion();
        [$mensaje] = $this->leerAviso('perfil');

        $this->mostrar($usuario, $usuario->getNombre(), $usuario->getCorreo(), [], $mensaje);
    }

    public function guardar(): void
    {
        $usuario = $this->requerirSesion();

        if (!$this->esPost()) {
            $this->redirigir('perfil');
        }

        $id = $usuario->getId();

        // Solo se usan el nombre, el correo y la contraseña: cualquier otro dato
        // que llegue en el formulario (id, rol, activo...) se ignora.
        $nombre = $this->post('nombre');
        $correo = $this->post('correo');
        $clave = $this->post('clave', false);
        $claveActual = $this->post('clave_actual', false);

        // Qué quiere cambiar el usuario. Para cambiar solo el nombre no hace falta
        // la contraseña actual; para el correo o la contraseña, sí.
        $cambiaCorreo = ($correo != $usuario->getCorreo());
        $cambiaClave = ($clave != '');

        // ---------- Validaciones ----------
        $errores = $this->validarCuenta($nombre, $correo, $id);

        // ---------- Contraseña actual ----------
        // Si se cambia el correo o la contraseña (o las dos cosas), hay que confirmar
        // la contraseña actual. La cifrada solo se usa aquí para compararla.
        if ($cambiaCorreo || $cambiaClave) {
            $claveGuardada = $this->usuarios->claveDe($id);

            // El mismo aviso sirve si se dejó vacía o si está mal.
            if (!$claveGuardada || !password_verify($claveActual, $claveGuardada)) {
                $errores[] = 'Contraseña actual incorrecta.';
            }
        }

        // ---------- Guardado ----------
        if (count($errores) == 0) {
            $guardado = false;

            try {
                if ($clave == '') {
                    // Sin contraseña nueva: la contraseña actual no se toca.
                    // Nunca se modifican el rol ni el estado.
                    $guardado = $this->usuarios->actualizarPerfil($id, $nombre, $correo);
                    $aviso = 'Perfil actualizado.';
                } else {
                    // Con contraseña nueva: se guarda cifrada con password_hash.
                    $guardado = $this->usuarios->actualizarPerfil($id, $nombre, $correo, password_hash($clave, PASSWORD_DEFAULT));
                    $aviso = 'Perfil actualizado (con contraseña nueva).';
                }
            } catch (Throwable $e) {
                // Error inesperado de la base: no se muestran detalles internos.
                $guardado = false;
            }

            if ($guardado) {
                // Actualiza el nombre de la sesión para que el saludo cambie enseguida.
                $_SESSION['nombre'] = $nombre;

                // Guarda el aviso y recarga la página, para que al refrescar el
                // navegador no se repita el guardado.
                $this->guardarAviso('perfil', $aviso);
                $this->redirigir('perfil');
            }

            $errores[] = 'No se pudo guardar el perfil.';
        }

        $this->mostrar($usuario, $nombre, $correo, $errores, '');
    }

    private function mostrar(Usuario $perfil, string $nombre, string $correo, array $errores, string $mensaje): void
    {
        $this->render('perfil/ver', [
            'perfil' => $perfil,
            'nombre' => $nombre,
            'correo' => $correo,
            'errores' => $errores,
            'mensaje' => $mensaje,
        ], 'perfil', 'Mi perfil');
    }
}
