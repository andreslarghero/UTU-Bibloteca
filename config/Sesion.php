<?php
// ============================================================
// CONFIG: SESIÓN
// Todo lo relacionado con la sesión está en esta clase, en un solo lugar
// (igual que en el proyecto de la clase de sesiones). El resto del sitio
// nunca toca $_SESSION directamente: llama a estos métodos.
// ============================================================
require_once __DIR__ . '/Ruta.php';

class Sesion
{
    // Arranca (o retoma) la sesión antes de mostrar nada.
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // HttpOnly: JavaScript no puede leer la cookie de la sesión.
            // SameSite Lax: el navegador no la envía en pedidos POST de otros sitios.
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    // Guarda en la sesión quién es el usuario (solo datos simples, nunca la contraseña).
    public static function login(Usuario $u): void
    {
        session_regenerate_id(true);   // id nuevo al iniciar sesión: evita el robo de sesión
        $_SESSION['usuario'] = [
            'id' => $u->getId(),
            'nombre' => $u->getNombre(),
            'rol' => $u->rol(),   // "cliente" o "bibliotecario"
        ];
    }

    // Los datos guardados del usuario (id, nombre, rol) o null si no hay sesión.
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function estaLogueado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    // El id del usuario de la sesión (0 si no hay).
    public static function id(): int
    {
        return (int) ($_SESSION['usuario']['id'] ?? 0);
    }

    // Pone al día los datos guardados cuando cambian en la base (nombre o rol).
    public static function actualizar(string $nombre, string $rol): void
    {
        if (self::estaLogueado()) {
            $_SESSION['usuario']['nombre'] = $nombre;
            $_SESSION['usuario']['rol'] = $rol;
        }
    }

    // Cierra la sesión: vacía los datos y la destruye en el servidor.
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    // Para las acciones que exigen haber iniciado sesión: si no la hay, manda al
    // login y corta la página.
    public static function requerirLogin(): void
    {
        if (!self::estaLogueado()) {
            header('Location: ' . Ruta::a('auth', 'login'));
            exit;
        }
    }

    // ---------- Otros datos que se recuerdan entre páginas ----------
    // (el ticket de la Caja y los avisos que se muestran una sola vez)

    public static function dato(string $clave, $porDefecto = null)
    {
        return $_SESSION[$clave] ?? $porDefecto;
    }

    public static function guardar(string $clave, $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function olvidar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }
}
