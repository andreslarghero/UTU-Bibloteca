<?php
require_once __DIR__ . '/LineaVenta.php';
require_once __DIR__ . '/ResultadoVenta.php';
require_once __DIR__ . '/Libro.php';

// ============================================================
// MODELO: VENTA
// Clase abstracta: una venta siempre es de mostrador (la caja) o
// una compra digital (la web). Lo común está aquí; lo que cambia
// se sobrescribe en cada hija (polimorfismo): de dónde se leen los
// datos del libro, cómo se comprueba cada línea y qué pasa con el
// stock. RepositorioVentas::registrar() llama a estos métodos sin
// preguntar de qué tipo es la venta.
// ============================================================
abstract class Venta
{
    protected int $id;
    protected string $fecha;
    protected float $total;
    protected ?int $clienteId;
    protected ?int $vendedorId;
    protected string $clienteNombre;
    protected string $vendedorNombre;
    protected array $lineas = [];

    // $d es un arreglo con los campos de la venta (los que falten quedan vacíos).
    public function __construct(array $d = [])
    {
        $this->id = (int) ($d['id'] ?? 0);
        $this->fecha = (string) ($d['fecha'] ?? '');
        $this->total = (float) ($d['total'] ?? 0);
        $this->clienteId = isset($d['id_cliente']) ? (int) $d['id_cliente'] : null;
        $this->vendedorId = isset($d['id_vendedor']) ? (int) $d['id_vendedor'] : null;
        $this->clienteNombre = (string) ($d['cliente'] ?? '');
        $this->vendedorNombre = (string) ($d['vendedor'] ?? '');
    }

    // Crea el objeto del tipo que corresponde a una fila de la tabla "ventas":
    // una compra por la web no tiene vendedor; una venta de caja sí.
    public static function desdeFila(array $fila): Venta
    {
        return ($fila['id_vendedor'] === null) ? new VentaDigital($fila) : new VentaMostrador($fila);
    }

    public function getId(): int { return $this->id; }
    public function getFecha(): string { return $this->fecha; }
    public function getTotal(): float { return $this->total; }
    public function getClienteId(): ?int { return $this->clienteId; }
    public function getVendedorId(): ?int { return $this->vendedorId; }
    public function getClienteNombre(): string { return $this->clienteNombre; }
    public function getVendedorNombre(): string { return $this->vendedorNombre; }
    public function getLineas(): array { return $this->lineas; }

    public function setLineas(array $lineas): void { $this->lineas = $lineas; }

    // Se ordenan las líneas por id de libro para que todas las ventas
    // bloqueen los libros en el mismo orden.
    public function ordenarLineas(): void
    {
        usort($this->lineas, fn($a, $b) => $a->getLibroId() <=> $b->getLibroId());
    }

    // "Venta de mostrador" o "Compra digital".
    abstract public function tipo(): string;

    // "fisico" o "digital": el formato que se guarda en cada línea.
    abstract public function formato(): string;

    // Vuelve a leer el libro de la base, con la fila bloqueada, sin confiar en
    // lo que mandó el navegador.
    abstract public function bloquearLibro(RepositorioLibros $libros, LineaVenta $linea): ?Libro;

    // El precio que tiene el libro en la base para este tipo de venta.
    abstract public function precioDe(Libro $libro): float;

    // Comprueba una línea con el libro ya bloqueado. Devuelve null si está bien,
    // o el resultado con el motivo por el que no se puede vender.
    abstract public function verificarLinea(LineaVenta $linea, ?Libro $libro, RepositorioVentas $ventas): ?ResultadoVenta;

    // Qué pasa con el stock después de guardar la línea. Devuelve null si está
    // bien, o el resultado con el problema.
    abstract public function afectarStock(LineaVenta $linea, RepositorioLibros $libros): ?ResultadoVenta;

    // El total de la venta con los precios ya fijados desde la base.
    public function calcularTotal(): float
    {
        $total = 0;
        foreach ($this->lineas as $linea) {
            $total = $total + $linea->getSubtotal();
        }
        return round($total, 2);
    }
}
