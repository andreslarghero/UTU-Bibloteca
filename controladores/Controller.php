<?php
require_once __DIR__ . '/../config/Ruta.php';
require_once __DIR__ . '/../modelos/RepositorioUsuarios.php';

// ============================================================
// CONTROLADOR BASE
// Todos los controladores heredan de esta clase abstracta. Aquí está
// lo que tienen en común: comprobar la sesión y el rol, leer lo que
// envía el navegador, guardar avisos entre páginas, redirigir y
// elegir la vista. Cada controlador hijo agrega sus acciones (un
// método público por acción).
// ============================================================
abstract class Controller
{
    protected RepositorioUsuarios $usuarios;

    // Quien tiene la sesión iniciada, ya comprobado en la base (o null).
    protected ?Usuario $usuario = null;

    public function __construct()
    {
        $this->usuarios = new RepositorioUsuarios();
    }

    // ---------- Sesión y permisos ----------

    // El usuario de la sesión, comprobado EN LA BASE en cada petición: no alcanza
    // con mirar la sesión, porque sus datos se guardaron al iniciar sesión y pueden
    // haber quedado viejos si después dieron de baja al usuario o le cambiaron el
    // rol. Devuelve null si no hay sesión; si el usuario ya no existe o está dado
    // de baja, cierra la sesión y también devuelve null.
    protected function sesionOpcional(): ?Usuario
    {
        if (!isset($_SESSION['id'])) {
            return null;
        }

        $usuario = $this->usuarios->buscar((int) $_SESSION['id']);

        if (!$usuario || !$usuario->estaActivo()) {
            $_SESSION = [];
            session_destroy();
            return null;
        }

        // Los datos de la sesión quedan con los que tiene ahora en la base.
        $_SESSION['nombre'] = $usuario->getNombre();
        $_SESSION['rol'] = $usuario->rol();

        $this->usuario = $usuario;
        return $usuario;
    }

    // Exige una sesión iniciada. Si no la hay (o ya no es válida), manda al login
    // y corta la página.
    protected function requerirSesion(): Usuario
    {
        $usuario = $this->sesionOpcional();

        if (!$usuario) {
            $this->redirigir('auth');
        }

        return $usuario;
    }

    // Exige además que siga siendo bibliotecario. Si ya no lo es, pierde el acceso
    // y vuelve al catálogo.
    protected function requerirBibliotecario(): Usuario
    {
        $usuario = $this->requerirSesion();

        if (!$usuario->esBibliotecario()) {
            $this->redirigir('libro');
        }

        return $usuario;
    }

    // ---------- Lo que envía el navegador ----------

    protected function esPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    // Texto enviado por POST, sin espacios sobrantes. Si no existe o no es un texto,
    // devuelve "". Con $recortar en false se devuelve tal cual (para contraseñas).
    protected function post(string $clave, bool $recortar = true): string
    {
        return $this->texto($_POST[$clave] ?? null, $recortar);
    }

    // Texto enviado por la dirección (GET), sin espacios sobrantes.
    protected function get(string $clave): string
    {
        return $this->texto($_GET[$clave] ?? null);
    }

    // Número entero positivo enviado por POST o GET. Solo se acepta un valor
    // formado por dígitos; cualquier otra cosa devuelve 0.
    protected function entero($valor): int
    {
        return (is_string($valor) && ctype_digit($valor)) ? (int) $valor : 0;
    }

    private function texto($valor, bool $recortar = true): string
    {
        if (!is_string($valor)) {
            return '';
        }
        return $recortar ? trim($valor) : $valor;
    }

    // Valida los datos de una cuenta: el nombre, el correo y que el correo no esté
    // repetido en otro usuario. $idExcluir es el usuario que se edita (0 si es una
    // cuenta nueva). Devuelve la lista de errores (vacía si todo está bien).
    protected function validarCuenta(string $nombre, string $correo, int $idExcluir = 0): array
    {
        $errores = Usuario::validarNombreCorreo($nombre, $correo);

        if (Usuario::correoValido($correo) && $this->usuarios->correoEnUso($correo, $idExcluir)) {
            $errores[] = 'Ya existe otro usuario con ese correo.';
        }

        return $errores;
    }

    // ---------- Avisos entre páginas ----------
    // Un aviso se muestra una sola vez después de redirigir (así, al refrescar
    // el navegador, la acción no se repite). $pagina identifica la pantalla.

    protected function guardarAviso(string $pagina, string $mensaje, string $tipo = 'exito'): void
    {
        $_SESSION['aviso_' . $pagina] = [$mensaje, $tipo];
    }

    // Devuelve [mensaje, tipo] del aviso guardado (o ["", "error"]) y lo borra.
    protected function leerAviso(string $pagina): array
    {
        $aviso = $_SESSION['aviso_' . $pagina] ?? ['', 'error'];
        unset($_SESSION['aviso_' . $pagina]);
        return $aviso;
    }

    // ---------- Respuesta ----------

    // Redirige a otra acción y corta la página (así no se ejecuta nada más).
    protected function redirigir(string $controlador, string $accion = 'index', array $parametros = []): void
    {
        header('Location: ' . Ruta::a($controlador, $accion, $parametros));
        exit;
    }

    // Muestra una vista. $vista es "carpeta/archivo" dentro de vistas/ y $datos son
    // las variables que la vista puede usar. $menu es el enlace del menú que se
    // resalta, $titulo el de la pestaña y $claseBody la clase del <body> (opcional).
    protected function render(string $vista, array $datos, string $menu, string $titulo, string $claseBody = ''): void
    {
        // Variables que usa la cabecera de todas las páginas.
        $usuarioMenu = $this->usuario ?? $this->sesionOpcional();
        $menuActivo = $menu;
        $tituloPagina = $titulo;
        $__ruta = __DIR__ . '/../vistas/' . $vista . '.php';

        // Las variables de la vista (EXTR_SKIP: no pisan las de arriba).
        extract($datos, EXTR_SKIP);
        require $__ruta;
    }
}
