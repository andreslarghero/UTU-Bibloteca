<?php
require_once __DIR__ . '/Controller.php';

// ============================================================
// CONTROLADOR: INICIO DE SESIÓN, REGISTRO Y CIERRE DE SESIÓN
// Acciones:
//   login      muestra el formulario de inicio de sesión y, al enviarlo (POST),
//              comprueba el correo y la contraseña y abre la sesión
//   registro   muestra el formulario de registro
//   registrar  crea la cuenta (POST)
//   logout     cierra la sesión
// ============================================================
class AuthController extends Controller
{
    // ---------- Inicio de sesión ----------

    // Acción por defecto del controlador: lleva al login.
    public function index(): void
    {
        $this->login();
    }

    // accion: login (muestra el formulario y procesa el envío)
    public function login(): void
    {
        if (!$this->esPost()) {
            $this->mostrarLogin();
            return;
        }

        $correo = $this->post('correo');
        $clave = $this->post('clave', false);

        // Busca al usuario por su correo (con consulta preparada por seguridad).
        $usuario = $this->usuarios->porCorreo($correo);
        $claveCifrada = $usuario ? $this->usuarios->claveDe($usuario->getId()) : null;

        // Comprueba que el usuario exista, que esté activo y que la contraseña
        // coincida con la cifrada. A un usuario dado de baja (activo = 0) se le
        // muestra el mismo aviso que a una contraseña incorrecta, sin dar pistas.
        if ($usuario && $usuario->estaActivo() && $claveCifrada !== null && password_verify($clave, $claveCifrada)) {
            // Guarda los datos del usuario en la sesión y lo lleva al catálogo.
            Sesion::login($usuario);

            $this->redirigir('libro');
        }

        // Datos incorrectos: se muestra un aviso.
        $this->mostrarLogin('Correo o contraseña incorrectos.');
    }

    private function mostrarLogin(string $mensaje = ''): void
    {
        $this->render('auth/login', [
            'mensaje' => $mensaje,
            'registrado' => isset($_GET['registrado']),   // viene desde el registro
        ], 'login', 'Iniciar sesión');
    }

    // ---------- Registro ----------

    public function registro(): void
    {
        $this->mostrarRegistro();
    }

    public function registrar(): void
    {
        if (!$this->esPost()) {
            $this->redirigir('auth', 'registro');
        }

        // Recoge los datos escritos en el formulario. Si algún campo no llega
        // (o no es un texto), se toma como vacío.
        $nombre = $this->post('nombre');
        $correo = $this->post('correo');
        $clave = $this->post('clave', false);
        $mensaje = '';

        // VALIDACIÓN EN EL SERVIDOR (las mismas reglas que "Mi perfil" y "Gestionar
        // usuarios"). El "required" del formulario es solo una ayuda del navegador:
        // la comprobación que vale es esta. Si algo falla, se muestra el primer
        // error y no se guarda nada en la base.
        if ($nombre === '' || $correo === '' || trim($clave) === '') {
            $mensaje = 'Completa el nombre, el correo y la contraseña.';
        } else {
            $errores = $this->validarCuenta($nombre, $correo);
            if (count($errores) > 0) {
                $mensaje = $errores[0];
            }
        }

        if ($mensaje == '') {
            // Cifra la contraseña antes de guardarla y guarda el nuevo usuario. Si la
            // base falla, no se muestran detalles internos.
            try {
                $this->usuarios->crear($nombre, $correo, password_hash($clave, PASSWORD_DEFAULT));

                // Lleva al login con un aviso de que la cuenta se creó.
                $this->redirigir('auth', 'login', ['registrado' => 1]);
            } catch (PDOException $e) {
                $mensaje = 'No se pudo crear la cuenta.';
            }
        }

        $this->mostrarRegistro($mensaje);
    }

    private function mostrarRegistro(string $mensaje = ''): void
    {
        $this->render('auth/registro', ['mensaje' => $mensaje], 'registro', 'Registro');
    }

    // ---------- Cerrar sesión ----------

    // Elimina todos los datos de la sesión (el usuario deja de estar conectado) y
    // lo devuelve al login.
    // accion: logout
    public function logout(): void
    {
        Sesion::logout();
        $this->redirigir('auth', 'login');
    }
}
