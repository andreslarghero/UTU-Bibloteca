<?php
require_once __DIR__ . '/../config/Conexion.php';
require_once __DIR__ . '/Repositorio.php';
require_once __DIR__ . '/Venta.php';
require_once __DIR__ . '/LineaVenta.php';
require_once __DIR__ . '/RepositorioLibros.php';

// ============================================================
// MODELO: REPOSITORIO DE VENTAS
// Tablas "ventas" y "venta_lineas". Aquí está la operación que
// registra una venta (de mostrador, o por la web en formato físico o
// digital) dentro de una transacción, y las consultas del historial.
// Una venta SIN vendedor es una compra por la web; con vendedor, de mostrador.
//
// IMPORTANTE: las consultas "del cliente" (mis compras y descargas)
// siempre exigen el id del cliente. Las consultas "de todas" son solo
// para el bibliotecario. No se unifican a propósito, para que una
// compra ajena nunca pueda verse por error.
// ============================================================
class RepositorioVentas implements Repositorio
{
    private PDO $pdo;
    private RepositorioLibros $libros;

    public function __construct()
    {
        $this->pdo = Conexion::obtener();
        $this->libros = new RepositorioLibros();
    }

    // ---------- Registrar una venta ----------
    // Cada registro se hace dentro de una transacción: o se guarda todo (commit)
    // o no se guarda nada (rollback). Si algo no se puede vender se lanza una
    // Exception con el mensaje para el usuario; el controlador la atrapa.

    // Venta de la caja: el bibliotecario es el vendedor y el cliente es opcional.
    // $lineas son las líneas del ticket. Cada libro se vuelve a leer de la base
    // (con la fila bloqueada) y se cobra a su precio de la base, no al de la sesión.
    // Devuelve el número de la venta.
    public function registrarMostrador(int $vendedorId, ?int $clienteId, array $lineas): int
    {
        ksort($lineas);   // se bloquean los libros siempre en el mismo orden

        try {
            $this->pdo->beginTransaction();
            $ventaId = $this->crearVenta($clienteId, $vendedorId);

            foreach ($lineas as $linea) {
                $libro = $this->libros->bloquear($linea->getLibroId());

                if (!$libro || !$libro->estaActivo() || !$libro->esFisico()) {
                    throw new Exception('Uno de los libros ya no está disponible: ' . $linea->getTitulo() . '.');
                }
                if ($libro->getStock() < $linea->getCantidad()) {
                    throw new Exception('Stock insuficiente para ' . $libro->getTitulo() . ' (quedan ' . $libro->getStock() . ').');
                }

                $this->libros->descontarStock($linea->getLibroId(), $linea->getCantidad());
                $this->crearLinea($ventaId, $linea->getLibroId(), $linea->getCantidad(), $libro->getPrecio(), 'fisico');
            }

            $this->pdo->commit();
            return $ventaId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception('No se pudo registrar la venta.');   // sin detalles internos de MySQL
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Compra física por la web: un libro en papel y varias copias, sin vendedor.
    // Igual que en la caja, el libro se vuelve a leer con la fila bloqueada, se
    // cobra al precio de la base y se descuenta el stock (que nunca queda negativo).
    // El cliente retira el libro en el mostrador. Devuelve el número de la venta.
    public function comprarFisico(int $clienteId, int $libroId, int $cantidad): int
    {
        if ($cantidad < 1) {
            throw new Exception('La cantidad tiene que ser al menos 1.');
        }

        try {
            $this->pdo->beginTransaction();
            $libro = $this->libros->bloquear($libroId);

            if (!$libro || !$libro->estaActivo() || !$libro->esFisico()) {
                throw new Exception('Este libro no está disponible en formato físico.');
            }
            if ($libro->getStock() < $cantidad) {
                throw new Exception($libro->getStock() > 0
                    ? 'Stock insuficiente para ' . $libro->getTitulo() . ' (quedan ' . $libro->getStock() . ').'
                    : $libro->getTitulo() . ' está agotado.');
            }

            // Un solo descuento de stock por compra, dentro de la misma transacción.
            if ($this->libros->descontarStock($libroId, $cantidad) != 1) {
                throw new Exception('Stock insuficiente para ' . $libro->getTitulo() . '.');
            }

            $ventaId = $this->crearVenta($clienteId, null);
            $this->crearLinea($ventaId, $libroId, $cantidad, $libro->getPrecio(), 'fisico');

            $this->pdo->commit();
            return $ventaId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception('No se pudo registrar la compra.');
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Compra digital por la web: un libro, sin vendedor, sin tocar el stock.
    // Devuelve el número de la venta, o 0 si el cliente ya tenía ese libro (no se
    // cobra dos veces).
    public function comprarDigital(int $clienteId, int $libroId): int
    {
        try {
            $this->pdo->beginTransaction();
            $libro = $this->libros->bloquear($libroId);

            if (!$libro || !$libro->estaActivo() || !$libro->esDigital()
                || $libro->getPrecioDigital() === null || $libro->getArchivoDigital() === null) {
                throw new Exception('Este libro no está disponible en formato digital.');
            }

            if ($this->clienteCompro($clienteId, $libroId)) {
                $this->pdo->rollBack();
                return 0;
            }

            $ventaId = $this->crearVenta($clienteId, null);
            $this->crearLinea($ventaId, $libroId, 1, $libro->getPrecioDigital(), 'digital');

            $this->pdo->commit();
            return $ventaId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception('No se pudo registrar la compra.');
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Guarda la venta (la fecha la pone sola la base) y devuelve su número. El
    // total no se guarda: se calcula con las líneas (vista vw_ventas_detalle).
    private function crearVenta(?int $clienteId, ?int $vendedorId): int
    {
        $consulta = $this->pdo->prepare('INSERT INTO ventas (id_cliente, id_vendedor) VALUES (?, ?)');
        $consulta->execute([$clienteId, $vendedorId]);
        return (int) $this->pdo->lastInsertId();
    }

    // Guarda un libro dentro de la venta, con el precio al que se vendió.
    private function crearLinea(int $ventaId, int $libroId, int $cantidad, $precio, string $formato): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, ?, ?, ?)');
        $consulta->execute([$ventaId, $libroId, $cantidad, $precio, $formato]);
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
    // de mostrador (sin cliente) o una compra física (en caja o por la web) no dan
    // derecho a la descarga.
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
    // nombre del vendedor. Los datos salen de la vista vw_ventas_detalle (que ya
    // une la tabla usuarios para el cliente y el vendedor); el filtro por
    // id_cliente es el que protege las compras de cada persona.
    public function delCliente(int $clienteId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, fecha, total, id_cliente, id_vendedor, vendedor
             FROM vw_ventas_detalle
             WHERE id_cliente = ?
             ORDER BY fecha DESC, id DESC');
        $consulta->execute([$clienteId]);
        return array_map(fn($f) => new Venta($f), $consulta->fetchAll());
    }

    // Una venta, pero solo si pertenece a este cliente (o null), con sus líneas.
    // Así, aunque alguien escriba a mano el número de una venta ajena, no la ve.
    public function delClientePorId(int $ventaId, int $clienteId): ?Venta
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, fecha, total, id_cliente, id_vendedor, vendedor
             FROM vw_ventas_detalle
             WHERE id = ? AND id_cliente = ?');
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

        $venta = new Venta($fila);
        $venta->setLineas(array_map(fn($f) => new LineaVenta($f), $lineas->fetchAll()));
        return $venta;
    }

    // ---------- Todas las operaciones (solo el bibliotecario) ----------

    // Todas las ventas, de la más nueva a la más antigua, con el nombre del
    // cliente y del vendedor (vista vw_ventas_detalle: incluye las ventas sin
    // cliente, de mostrador).
    public function todos(): array
    {
        $filas = $this->pdo->query(
            'SELECT id, fecha, total, id_cliente, id_vendedor, cliente, vendedor
             FROM vw_ventas_detalle
             ORDER BY fecha DESC, id DESC')->fetchAll();
        return array_map(fn($f) => new Venta($f), $filas);
    }

    // Una venta cualquiera con sus líneas (o null).
    public function buscar(int $id): ?Venta
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, fecha, total, id_cliente, id_vendedor, cliente, vendedor
             FROM vw_ventas_detalle
             WHERE id = ?');
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

        $venta = new Venta($fila);
        $venta->setLineas(array_map(fn($f) => new LineaVenta($f), $lineas->fetchAll()));
        return $venta;
    }
}
