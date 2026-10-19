<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Venta.php';
require_once __DIR__ . '/VentaMostrador.php';
require_once __DIR__ . '/VentaDigital.php';
require_once __DIR__ . '/RepositorioLibros.php';

// ============================================================
// MODELO: REPOSITORIO DE VENTAS
// Tablas "ventas" y "venta_lineas". Aquí está la operación que
// registra una venta (de mostrador o digital) dentro de una
// transacción, y las consultas del historial.
//
// IMPORTANTE: las consultas "del cliente" (mis compras y descargas)
// siempre exigen el id del cliente. Las consultas "de todas" son solo
// para el bibliotecario. No se unifican a propósito, para que una
// compra ajena nunca pueda verse por error.
// ============================================================
class RepositorioVentas implements Repositorio
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Conexion::getConexion();
    }

    // ---------- Registrar una venta ----------

    // Guarda la venta y sus líneas dentro de una transacción: o se guarda todo
    // (commit) o no se guarda nada (rollback). Sirve para cualquier tipo de
    // venta: lo que cambia de una a otra lo resuelve cada clase hija.
    public function registrar(Venta $venta): ResultadoVenta
    {
        $libros = new RepositorioLibros();
        $venta->ordenarLineas();
        $resultado = null;      // un problema que impide guardar (null = todo bien)
        $ventaId = 0;
        $total = 0;

        try {
            $this->pdo->beginTransaction();

            // 1. Vuelve a consultar cada libro en la base, sin confiar en lo que
            //    mandó el navegador (con bloqueo de la fila).
            foreach ($venta->getLineas() as $linea) {
                $libro = $venta->bloquearLibro($libros, $linea);
                $resultado = $venta->verificarLinea($linea, $libro, $this);
                if ($resultado) {
                    break;
                }
                // El precio definitivo es el que tiene el libro en la base ahora.
                $linea->fijarPrecio($venta->precioDe($libro), $libro->getTitulo());
            }

            // 2. Guarda la venta (la fecha la pone sola la base).
            if (!$resultado) {
                $total = $venta->calcularTotal();
                $consulta = $this->pdo->prepare('INSERT INTO ventas (id_cliente, id_vendedor, total) VALUES (?, ?, ?)');
                $consulta->execute([$venta->getClienteId(), $venta->getVendedorId(), $total]);
                $ventaId = (int) $this->pdo->lastInsertId();

                // 3. Guarda una línea por cada libro y se ocupa del stock.
                $insertarLinea = $this->pdo->prepare(
                    'INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, ?, ?, ?)');
                foreach ($venta->getLineas() as $linea) {
                    $insertarLinea->execute([
                        $ventaId, $linea->getLibroId(), $linea->getCantidad(), $linea->getPrecio(), $venta->formato(),
                    ]);
                    $resultado = $venta->afectarStock($linea, $libros);
                    if ($resultado) {
                        break;
                    }
                }
            }

            // 4. Si todo salió bien (o la compra ya estaba hecha) se confirma;
            //    si algo falló se deshace todo.
            if (!$resultado) {
                $this->pdo->commit();
                return new ResultadoVenta(ResultadoVenta::OK, '', $ventaId, $total);
            }
            if ($resultado->getEstado() === ResultadoVenta::YA_COMPRADO) {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }
            return $resultado;
        } catch (Throwable $e) {
            // Cualquier error inesperado de la base: se deshace todo y se devuelve
            // un aviso general, sin detalles internos de MySQL.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ResultadoVenta::error('No se pudo registrar la venta.');
        }
    }

    // ---------- Compras de un cliente ----------

    // ¿Este cliente ya compró este libro en formato digital?
    public function clienteCompro(int $clienteId, int $libroId): bool
    {
        $consulta = $this->pdo->prepare(
            "SELECT COUNT(*) AS total FROM venta_lineas
             JOIN ventas ON ventas.id = venta_lineas.venta_id
             WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'");
        $consulta->execute([$clienteId, $libroId]);
        return $consulta->fetch()['total'] > 0;
    }

    // El libro que este cliente compró en digital y puede descargar (o null).
    // Exige a la vez: una venta de este cliente, con una línea de este libro en
    // formato digital, y que el libro tenga un archivo digital guardado. Una venta
    // de mostrador (sin cliente) o una compra física no dan derecho a la descarga.
    public function libroDescargable(int $clienteId, int $libroId): ?Libro
    {
        $consulta = $this->pdo->prepare(
            "SELECT libros.titulo, libros.archivo_digital
             FROM venta_lineas
             JOIN ventas ON ventas.id = venta_lineas.venta_id
             JOIN libros ON libros.id = venta_lineas.libro_id
             WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'
               AND libros.archivo_digital IS NOT NULL
             LIMIT 1");
        $consulta->execute([$clienteId, $libroId]);
        $fila = $consulta->fetch();
        return $fila ? new Libro($fila) : null;
    }

    // Las ventas de ESTE cliente, de la más nueva a la más antigua, con el
    // nombre del vendedor (LEFT JOIN por si la venta no tiene).
    public function delCliente(int $clienteId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ventas.id, ventas.fecha, ventas.total, ventas.id_cliente, ventas.id_vendedor,
                    vendedores.nombre AS vendedor
             FROM ventas
             LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
             WHERE ventas.id_cliente = ?
             ORDER BY ventas.fecha DESC, ventas.id DESC');
        $consulta->execute([$clienteId]);
        return array_map([Venta::class, 'desdeFila'], $consulta->fetchAll());
    }

    // Una venta, pero solo si pertenece a este cliente (o null), con sus líneas.
    // Así, aunque alguien escriba a mano el número de una venta ajena, no la ve.
    public function delClientePorId(int $ventaId, int $clienteId): ?Venta
    {
        $consulta = $this->pdo->prepare(
            'SELECT ventas.id, ventas.fecha, ventas.total, ventas.id_cliente, ventas.id_vendedor,
                    vendedores.nombre AS vendedor
             FROM ventas
             LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
             WHERE ventas.id = ? AND ventas.id_cliente = ?');
        $consulta->execute([$ventaId, $clienteId]);
        $fila = $consulta->fetch();
        if (!$fila) {
            return null;
        }

        // Libros de esa compra, con el precio al que se vendieron (el de
        // venta_lineas, no el precio actual del libro). También aquí se exige
        // que la venta sea de este cliente.
        $lineas = $this->pdo->prepare(
            "SELECT libros.titulo, venta_lineas.libro_id, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato,
                    libros.archivo_digital IS NOT NULL AS tiene_archivo,
                    UPPER(SUBSTRING_INDEX(libros.archivo_digital, '.', -1)) AS tipo_archivo
             FROM venta_lineas
             JOIN ventas ON ventas.id = venta_lineas.venta_id
             JOIN libros ON libros.id = venta_lineas.libro_id
             WHERE venta_lineas.venta_id = ? AND ventas.id_cliente = ?
             ORDER BY venta_lineas.id");
        $lineas->execute([$ventaId, $clienteId]);

        $venta = Venta::desdeFila($fila);
        $venta->setLineas(array_map(fn($f) => new LineaVenta($f), $lineas->fetchAll()));
        return $venta;
    }

    // ---------- Todas las operaciones (solo el bibliotecario) ----------

    // Todas las ventas, de la más nueva a la más antigua, con el nombre del
    // cliente y del vendedor. LEFT JOIN incluye las ventas sin cliente (de mostrador).
    public function todos(): array
    {
        $filas = $this->pdo->query(
            'SELECT ventas.id, ventas.fecha, ventas.total, ventas.id_cliente, ventas.id_vendedor,
                    clientes.nombre AS cliente, vendedores.nombre AS vendedor
             FROM ventas
             LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
             LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
             ORDER BY ventas.fecha DESC, ventas.id DESC')->fetchAll();
        return array_map([Venta::class, 'desdeFila'], $filas);
    }

    // Una venta cualquiera con sus líneas (o null).
    public function buscar(int $id): ?Venta
    {
        $consulta = $this->pdo->prepare(
            'SELECT ventas.id, ventas.fecha, ventas.total, ventas.id_cliente, ventas.id_vendedor,
                    clientes.nombre AS cliente, vendedores.nombre AS vendedor
             FROM ventas
             LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
             LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
             WHERE ventas.id = ?');
        $consulta->execute([$id]);
        $fila = $consulta->fetch();
        if (!$fila) {
            return null;
        }

        // Libros vendidos en esa venta, con el precio al que se vendieron.
        $lineas = $this->pdo->prepare(
            'SELECT libros.titulo, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato
             FROM venta_lineas
             JOIN libros ON libros.id = venta_lineas.libro_id
             WHERE venta_lineas.venta_id = ?
             ORDER BY venta_lineas.id');
        $lineas->execute([$id]);

        $venta = Venta::desdeFila($fila);
        $venta->setLineas(array_map(fn($f) => new LineaVenta($f), $lineas->fetchAll()));
        return $venta;
    }
}
