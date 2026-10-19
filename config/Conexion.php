<?php
// ============================================================
// CONFIG: CONEXIÓN A LA BASE DE DATOS
// Una clase, una sola conexión para toda la aplicación.
// Los repositorios piden la conexión con Conexion::getConexion().
// ============================================================
class Conexion
{
    // La única conexión (se crea la primera vez que alguien la pide).
    private static ?PDO $pdo = null;

    // Datos de acceso a MySQL (los valores por defecto de XAMPP).
    // Si tu MySQL usa otro usuario o contraseña, cámbialos aquí.
    // root sin contraseña es solo para un entorno local: en un servidor
    // público se usa un usuario con permisos limitados.
    private const SERVIDOR = 'localhost';
    private const BASE = 'biblioteca';
    private const USUARIO = 'root';
    private const CLAVE = '';

    public static function getConexion(): PDO
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
                // Si la conexión falla, se detiene la página y se muestra solo el
                // motivo (sin el detalle interno de PHP, que incluiría los datos de acceso).
                die('Error de conexión: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
