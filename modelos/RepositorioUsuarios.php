<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Cliente.php';
require_once __DIR__ . '/Bibliotecario.php';

// ============================================================
// MODELO: REPOSITORIO DE USUARIOS
// Traduce entre la tabla "usuarios" y los objetos Cliente y
// Bibliotecario. Todas las consultas son preparadas (los valores
// van en los "?" y nunca se pegan en el SQL).
// La contraseña cifrada no viaja en los objetos: solo se pide
// aparte con claveDe(), para el login y para confirmarla en el perfil.
// ============================================================
class RepositorioUsuarios implements Repositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::obtener();
    }

    // Todos los usuarios, activos e inactivos.
    public function todos(): array
    {
        $filas = $this->pdo->query('SELECT id, nombre, correo, rol, activo FROM usuarios ORDER BY id')->fetchAll();
        return array_map([Usuario::class, 'desdeFila'], $filas);
    }

    public function buscar(int $id): ?Usuario
    {
        $consulta = $this->pdo->prepare('SELECT id, nombre, correo, rol, activo FROM usuarios WHERE id = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        return $fila ? Usuario::desdeFila($fila) : null;
    }

    public function porCorreo(string $correo): ?Usuario
    {
        $consulta = $this->pdo->prepare('SELECT id, nombre, correo, rol, activo FROM usuarios WHERE correo = ?');
        $consulta->execute([$correo]);
        $fila = $consulta->fetch();
        return $fila ? Usuario::desdeFila($fila) : null;
    }

    // Un usuario ACTIVO con ese correo (o null).
    public function activoPorCorreo(string $correo): ?Usuario
    {
        $usuario = $this->porCorreo($correo);
        return ($usuario && $usuario->estaActivo()) ? $usuario : null;
    }

    // La contraseña cifrada de un usuario (solo para comprobarla).
    public function claveDe(int $id): ?string
    {
        $consulta = $this->pdo->prepare('SELECT clave FROM usuarios WHERE id = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        return $fila ? $fila['clave'] : null;
    }

    // ¿Ya hay OTRO usuario con ese correo? $excluirId es el usuario que se
    // edita (0 si es una cuenta nueva): así puede conservar su propio correo.
    public function correoEnUso(string $correo, int $excluirId = 0): bool
    {
        $consulta = $this->pdo->prepare('SELECT id FROM usuarios WHERE correo = ? AND id <> ?');
        $consulta->execute([$correo, $excluirId]);
        return (bool) $consulta->fetch();
    }

    // Cuántos bibliotecarios activos hay (para no dejar nunca el sistema sin ninguno).
    public function contarBibliotecariosActivos(): int
    {
        $fila = $this->pdo->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'bibliotecario' AND activo = 1")->fetch();
        return (int) $fila['total'];
    }

    // Crea un usuario activo. $claveCifrada ya viene cifrada con password_hash.
    public function crear(string $nombre, string $correo, string $claveCifrada, string $rol = Cliente::ROL): bool
    {
        $consulta = $this->pdo->prepare('INSERT INTO usuarios (nombre, correo, clave, rol, activo) VALUES (?, ?, ?, ?, 1)');
        return $consulta->execute([$nombre, $correo, $claveCifrada, $rol]);
    }

    // Cambia los datos de un usuario (el bibliotecario edita cualquiera; "Mi perfil"
    // pasa el mismo rol que ya tiene). Con $claveCifrada en null la contraseña no
    // se toca: IFNULL deja la que había.
    public function actualizar(int $id, string $nombre, string $correo, string $rol, ?string $claveCifrada = null): bool
    {
        $consulta = $this->pdo->prepare(
            'UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, clave = IFNULL(?, clave) WHERE id = ?');
        return $consulta->execute([$nombre, $correo, $rol, $claveCifrada, $id]);
    }

    // Baja lógica (activo = 0) o reactivación (activo = 1). Nunca se usa DELETE.
    public function cambiarEstado(int $id, bool $activo): bool
    {
        $consulta = $this->pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
        return $consulta->execute([$activo ? 1 : 0, $id]);
    }
}
