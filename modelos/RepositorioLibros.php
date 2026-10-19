<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Libro.php';

// ============================================================
// MODELO: REPOSITORIO DE LIBROS
// Todas las consultas de la tabla "libros". Devuelve objetos Libro.
// Los libros dados de baja (activo = 0) no se borran.
// ============================================================
class RepositorioLibros implements Repositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::getConexion();
    }

    // Convierte una fila (o null) en un objeto Libro (o null).
    private function aObjeto($fila): ?Libro
    {
        return $fila ? new Libro($fila) : null;
    }

    // ---------- Catálogo y ficha ----------

    // Un libro ACTIVO con el nombre de su categoría (o null). Para la ficha.
    public function activoPorId(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT libros.*, categorias.nombre AS categoria FROM libros
             JOIN categorias ON categorias.id = libros.categoria_id
             WHERE libros.id = ? AND libros.activo = 1');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    // El catálogo: libros activos con su categoría, su valoración media y el
    // número de reseñas (LEFT JOIN para incluir libros sin reseñas).
    //  - Búsqueda: los % permiten encontrar el texto en cualquier parte del
    //    título o del autor. Con "" queda "%%", que coincide con todos.
    //  - Categoría: "? = 0" hace que con 0 (todas) no se filtre por categoría.
    public function catalogo(string $buscar, int $categoria): array
    {
        $texto = '%' . $buscar . '%';
        $consulta = $this->pdo->prepare(
            'SELECT libros.*, categorias.nombre AS categoria,
                    ROUND(AVG(resenias.puntuacion), 1) AS media, COUNT(resenias.id) AS total_resenas
             FROM libros
             JOIN categorias ON categorias.id = libros.categoria_id
             LEFT JOIN resenias ON resenias.libro_id = libros.id
             WHERE libros.activo = 1
               AND (libros.titulo LIKE ? OR libros.autor LIKE ?)
               AND (? = 0 OR libros.categoria_id = ?)
             GROUP BY libros.id
             ORDER BY libros.id');
        $consulta->execute([$texto, $texto, $categoria, $categoria]);
        return array_map([$this, 'aObjeto'], $consulta->fetchAll());
    }

    // Hasta 4 libros activos de la misma categoría, sin contar $id.
    public function relacionados(int $categoriaId, int $id): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, titulo, autor, portada, formato_fisico, precio, precio_digital
             FROM libros
             WHERE categoria_id = ? AND id <> ? AND activo = 1
             ORDER BY id
             LIMIT 4');
        $consulta->execute([$categoriaId, $id]);
        return array_map([$this, 'aObjeto'], $consulta->fetchAll());
    }

    // Cuatro libros con portada para la página de inicio.
    public function portadasInicio(): array
    {
        $filas = $this->pdo->query(
            'SELECT titulo, portada FROM libros WHERE activo = 1 AND portada IS NOT NULL ORDER BY id LIMIT 4')->fetchAll();
        return array_map([$this, 'aObjeto'], $filas);
    }

    // ---------- Caja y compras ----------

    // Un libro activo que se vende en formato físico, por su código de barras.
    public function activoFisicoPorCodigo(string $codigo): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, titulo, precio, stock, formato_fisico FROM libros
             WHERE codigo_barras = ? AND activo = 1 AND formato_fisico = 1');
        $consulta->execute([$codigo]);
        return $this->aObjeto($consulta->fetch());
    }

    // Cualquier libro con ese código de barras (o null). El servicio STEAM lo usa
    // para saber si un código pertenece a un libro solo digital.
    public function porCodigo(string $codigo): ?Libro
    {
        $consulta = $this->pdo->prepare('SELECT id, titulo, formato_fisico FROM libros WHERE codigo_barras = ?');
        $consulta->execute([$codigo]);
        return $this->aObjeto($consulta->fetch());
    }

    // Un libro disponible para comprar en digital: activo, con formato digital,
    // precio digital y archivo (o null). No trae el nombre del archivo.
    public function digitalDisponible(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, titulo, autor, precio_digital, formato_digital FROM libros
             WHERE id = ? AND activo = 1 AND formato_digital = 1
               AND precio_digital IS NOT NULL AND archivo_digital IS NOT NULL');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    // ---------- Dentro de una transacción de venta ----------
    // "FOR UPDATE" bloquea la fila hasta terminar la transacción, para que
    // otra venta no pueda gastar el mismo stock al mismo tiempo.

    public function bloquearFisico(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT titulo, precio, stock, activo, formato_fisico FROM libros WHERE id = ? FOR UPDATE');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    public function bloquearDigital(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT titulo, precio_digital, archivo_digital, activo, formato_digital FROM libros WHERE id = ? FOR UPDATE');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    // Descuenta stock. "AND stock >= ?" hace que nunca quede negativo: si no
    // alcanza, la fila no se modifica. Devuelve las filas modificadas (1 si
    // salió bien, 0 si el stock ya no alcanzaba).
    public function descontarStock(int $id, int $cantidad): int
    {
        $consulta = $this->pdo->prepare('UPDATE libros SET stock = stock - ? WHERE id = ? AND stock >= ?');
        $consulta->execute([$cantidad, $id, $cantidad]);
        return $consulta->rowCount();
    }

    // ---------- Gestión (bibliotecario) ----------

    // Todos los libros, activos e inactivos, con el nombre de su categoría.
    public function todos(): array
    {
        $filas = $this->pdo->query(
            'SELECT libros.id, libros.titulo, libros.autor, libros.isbn, libros.precio, libros.stock, libros.activo,
                    libros.formato_fisico, libros.formato_digital, libros.precio_digital,
                    categorias.nombre AS categoria
             FROM libros
             JOIN categorias ON categorias.id = libros.categoria_id
             ORDER BY libros.titulo')->fetchAll();
        return array_map([$this, 'aObjeto'], $filas);
    }

    // Los libros que se venden en formato físico (los digitales no tienen stock).
    public function fisicos(): array
    {
        $filas = $this->pdo->query(
            'SELECT id, titulo, autor, portada, stock, formato_fisico FROM libros WHERE formato_fisico = 1')->fetchAll();
        return array_map([$this, 'aObjeto'], $filas);
    }

    // Un libro con todos sus campos (para el formulario de edición), o null.
    public function buscar(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare('SELECT * FROM libros WHERE id = ?');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    // ¿Hay OTRO libro con ese ISBN? ($id es el libro que se edita; 0 si es nuevo.)
    public function isbnEnUso(string $isbn, int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT id FROM libros WHERE isbn = ? AND id <> ?');
        $consulta->execute([$isbn, $id]);
        return (bool) $consulta->fetch();
    }

    // ¿Hay OTRO libro con ese código de barras?
    public function codigoEnUso(string $codigo, int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT id FROM libros WHERE codigo_barras = ? AND id <> ?');
        $consulta->execute([$codigo, $id]);
        return (bool) $consulta->fetch();
    }

    // Crea un libro nuevo (queda activo).
    public function crear(Libro $libro): bool
    {
        $d = $libro->aFila();
        $consulta = $this->pdo->prepare(
            'INSERT INTO libros (titulo, autor, anio, portada, descripcion, categoria_id, isbn, precio, codigo_barras, stock,
                                 formato_fisico, formato_digital, precio_digital, archivo_digital, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
        return $consulta->execute(array_values($d));
    }

    // Cambia los datos de un libro (nunca el id).
    public function actualizar(Libro $libro): bool
    {
        $d = $libro->aFila();
        $consulta = $this->pdo->prepare(
            'UPDATE libros SET titulo = ?, autor = ?, anio = ?, portada = ?, descripcion = ?, categoria_id = ?,
                    isbn = ?, precio = ?, codigo_barras = ?, stock = ?,
                    formato_fisico = ?, formato_digital = ?, precio_digital = ?, archivo_digital = ?
             WHERE id = ?');
        return $consulta->execute(array_merge(array_values($d), [$libro->getId()]));
    }

    // Baja lógica (activo = 0) o reactivación (activo = 1). Nunca se usa DELETE.
    public function cambiarEstado(int $id, bool $activo): bool
    {
        $consulta = $this->pdo->prepare('UPDATE libros SET activo = ? WHERE id = ?');
        return $consulta->execute([$activo ? 1 : 0, $id]);
    }

    // Cambia las copias disponibles. Solo vale para libros físicos.
    public function actualizarStock(int $id, int $stock): bool
    {
        $consulta = $this->pdo->prepare('UPDATE libros SET stock = ? WHERE id = ? AND formato_fisico = 1');
        return $consulta->execute([$stock, $id]);
    }
}
