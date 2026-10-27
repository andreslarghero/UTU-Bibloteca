<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Resena.php';

// ============================================================
// MODELO: REPOSITORIO DE RESEÑAS
// ============================================================
class RepositorioResenas implements Repositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::obtener();
    }

    // Todas las reseñas con el título del libro y el nombre del usuario,
    // de la más nueva a la más antigua (para "Gestionar reseñas").
    public function todos(): array
    {
        $filas = $this->pdo->query(
            'SELECT resenias.*, libros.titulo, usuarios.nombre FROM resenias
             JOIN libros ON libros.id = resenias.libro_id
             JOIN usuarios ON usuarios.id = resenias.usuario_id
             ORDER BY resenias.fecha DESC')->fetchAll();
        return array_map(fn($f) => new Resena($f), $filas);
    }

    public function buscar(int $id): ?Resena
    {
        $consulta = $this->pdo->prepare('SELECT * FROM resenias WHERE id = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        return $fila ? new Resena($fila) : null;
    }

    // Guarda la reseña de un usuario en un libro. Si ya tenía una, la
    // actualiza en vez de duplicarla (hay una sola por usuario y libro).
    public function guardar(Resena $resena): bool
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO resenias (libro_id, usuario_id, puntuacion, comentario) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion), comentario = VALUES(comentario), fecha = NOW()');
        return $consulta->execute([
            $resena->getLibroId(), $resena->getUsuarioId(), $resena->getPuntuacion(), $resena->getComentario(),
        ]);
    }

    // Elimina una reseña. Con $usuarioId solo la borra si es de ese usuario;
    // con null la borra sin importar de quién es (el bibliotecario).
    public function eliminar(int $id, ?int $usuarioId = null): bool
    {
        if ($usuarioId === null) {
            $consulta = $this->pdo->prepare('DELETE FROM resenias WHERE id = ?');
            return $consulta->execute([$id]);
        }
        $consulta = $this->pdo->prepare('DELETE FROM resenias WHERE id = ? AND usuario_id = ?');
        return $consulta->execute([$id, $usuarioId]);
    }

    // Valoración media y número total de reseñas de un libro:
    // ["media" => número o null, "total" => cantidad].
    public function valoracion(int $libroId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ROUND(AVG(puntuacion), 1) AS media, COUNT(*) AS total FROM resenias WHERE libro_id = ?');
        $consulta->execute([$libroId]);
        return $consulta->fetch();
    }

    // Las reseñas de un libro con el nombre de quien las escribió, de la
    // más nueva a la más antigua.
    public function delLibro(int $libroId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT resenias.*, usuarios.nombre FROM resenias
             JOIN usuarios ON usuarios.id = resenias.usuario_id
             WHERE resenias.libro_id = ? ORDER BY resenias.fecha DESC');
        $consulta->execute([$libroId]);
        return array_map(fn($f) => new Resena($f), $consulta->fetchAll());
    }

    // La reseña de un usuario en un libro (o null), para rellenar el formulario.
    public function deUsuario(int $libroId, int $usuarioId): ?Resena
    {
        $consulta = $this->pdo->prepare(
            'SELECT puntuacion, comentario FROM resenias WHERE libro_id = ? AND usuario_id = ?');
        $consulta->execute([$libroId, $usuarioId]);
        $fila = $consulta->fetch();
        return $fila ? new Resena($fila) : null;
    }
}
