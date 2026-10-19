<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Categoria.php';

// ============================================================
// MODELO: REPOSITORIO DE CATEGORÍAS
// ============================================================
class RepositorioCategorias implements Repositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::getConexion();
    }

    // Todas las categorías, ordenadas por nombre. Sirven para el filtro del
    // catálogo y el formulario de libros.
    public function todos(): array
    {
        $filas = $this->pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
        return array_map(fn($f) => new Categoria((int) $f['id'], $f['nombre']), $filas);
    }

    public function buscar(int $id): ?Categoria
    {
        $consulta = $this->pdo->prepare('SELECT id, nombre FROM categorias WHERE id = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        return $fila ? new Categoria((int) $fila['id'], $fila['nombre']) : null;
    }

    // ¿Existe una categoría con ese id?
    public function existe(int $id): bool
    {
        return $this->buscar($id) !== null;
    }
}
