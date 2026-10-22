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

    // Un libro ACTIVO (o null). Para la ficha.
    public function activoPorId(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare('SELECT * FROM libros WHERE id = ? AND activo = 1');
        $consulta->execute([$id]);
        return $this->aObjeto($consulta->fetch());
    }

    // El catálogo: los libros activos con su valoración media y el número de
    // reseñas. Los datos salen de la vista vw_catalogo (que ya hace el LEFT JOIN
    // con resenias); aquí solo se filtra.
    //  - Búsqueda: los % permiten encontrar el texto en cualquier parte del
    //    título o del autor. Con "" queda "%%", que coincide con todos.
    //  - Categoría: "? = ''" hace que con "" (todas) no se filtre por categoría.
    public function catalogo(string $buscar, string $categoria): array
    {
        $texto = '%' . $buscar . '%';
        $consulta = $this->pdo->prepare(
            "SELECT * FROM vw_catalogo
             WHERE (titulo LIKE ? OR autor LIKE ?)
               AND (? = '' OR categoria = ?)
             ORDER BY id");
        $consulta->execute([$texto, $texto, $categoria, $categoria]);
        return array_map([$this, 'aObjeto'], $consulta->fetchAll());
    }

    // ---------- Categorías (salen de los propios libros) ----------

    // Las categorías que hay en la tabla libros, sin repetir y ordenadas.
    // Con $soloActivas = true solo cuenta la de los libros que están en el catálogo.
    public function categorias(bool $soloActivas = false): array
    {
        $donde = $soloActivas ? 'WHERE activo = 1' : '';
        return $this->pdo->query("SELECT DISTINCT categoria FROM libros $donde ORDER BY categoria")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    // Devuelve el nombre con el que ya está escrita esa categoría (la comparación
    // no distingue mayúsculas ni tildes: "ficcion" encuentra "Ficción"), para que
    // no queden dos grafías de la misma categoría. Si es nueva, se guarda con la
    // primera letra en mayúscula.
    public function nombreCategoria(string $texto): string
    {
        if ($texto == '') {
            return '';
        }
        $consulta = $this->pdo->prepare('SELECT categoria FROM libros WHERE categoria = ? LIMIT 1');
        $consulta->execute([$texto]);
        $existente = $consulta->fetchColumn();
        return $existente !== false ? $existente : mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    // Hasta 4 libros activos de la misma categoría, sin contar $id.
    public function relacionados(string $categoria, int $id): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, titulo, autor, portada, formato_fisico, precio, precio_digital
             FROM libros
             WHERE categoria = ? AND id <> ? AND activo = 1
             ORDER BY id
             LIMIT 4');
        $consulta->execute([$categoria, $id]);
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

    public function bloquear(int $id): ?Libro
    {
        $consulta = $this->pdo->prepare(
            'SELECT titulo, precio, stock, activo, formato_fisico, formato_digital, precio_digital, archivo_digital
             FROM libros WHERE id = ? FOR UPDATE');
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

    // Todos los libros, activos e inactivos.
    public function todos(): array
    {
        $filas = $this->pdo->query(
            'SELECT id, titulo, autor, isbn, precio, stock, activo,
                    formato_fisico, formato_digital, precio_digital, categoria
             FROM libros
             ORDER BY titulo')->fetchAll();
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
            'INSERT INTO libros (titulo, autor, anio, portada, descripcion, categoria, isbn, precio, codigo_barras, stock,
                                 formato_fisico, formato_digital, precio_digital, archivo_digital, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
        return $consulta->execute(array_values($d));
    }

    // Cambia los datos de un libro (nunca el id).
    public function actualizar(Libro $libro): bool
    {
        $d = $libro->aFila();
        $consulta = $this->pdo->prepare(
            'UPDATE libros SET titulo = ?, autor = ?, anio = ?, portada = ?, descripcion = ?, categoria = ?,
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
