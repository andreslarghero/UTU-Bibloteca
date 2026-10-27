<?php
// ============================================================
// CONFIG: CONEXIÓN A LA BASE DE DATOS
// Una clase, una sola conexión para toda la aplicación.
// Los repositorios piden la conexión con Conexion::obtener().
// ============================================================
class Conexion
{
    // La única conexión (se crea la primera vez que alguien la pide).
    private static ?PDO $pdo = null;

    // Datos de acceso a MySQL. La web NO usa "root": usa la cuenta
    // 'nexora_app', que se crea con sql/biblioteca.sql (sección "Usuarios y
    // permisos") y solo puede hacer lo que la aplicación necesita (principio
    // de mínimo privilegio). Esa contraseña es de demostración para uso local:
    // hay que cambiarla (en el .sql con ALTER USER y aquí) si el sitio se
    // instala en un servidor.
    private const SERVIDOR = 'localhost';
    private const BASE = 'biblioteca';
    private const USUARIO = 'nexora_app';
    private const CLAVE = 'Nexora_App_2026';

    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . self::SERVIDOR . ';dbname=' . self::BASE . ';charset=utf8mb4';
            try {
                self::$pdo = new PDO($dsn, self::USUARIO, self::CLAVE, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // los errores son excepciones
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // cada fila es un arreglo asociativo
                    PDO::ATTR_EMULATE_PREPARES => false,              // consultas preparadas reales
                ]);
            } catch (PDOException $e) {
                // Si la conexión falla, se detiene la página con un aviso general. El
                // detalle (que incluye el usuario de la base) solo va al registro de PHP.
                error_log($e->getMessage());
                die('No se pudo conectar con la base de datos.');
            }
        }
        return self::$pdo;
    }
}
