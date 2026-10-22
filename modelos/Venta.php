<?php
// ============================================================
// MODELO: VENTA
// Cada objeto es una fila de la vista vw_ventas_detalle: una venta
// (cabecera) con su total, el cliente y el vendedor, más sus líneas
// (LineaVenta), que se cargan aparte cuando hacen falta.
// Una venta sin vendedor es una compra digital por la web; una con
// vendedor es una venta de mostrador (caja).
// ============================================================
class Venta
{
    private int $id;
    private string $fecha;
    private float $total;
    private ?int $clienteId;
    private ?int $vendedorId;
    private string $clienteNombre;
    private string $vendedorNombre;
    private array $lineas = [];

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

    public function getId(): int { return $this->id; }
    public function getFecha(): string { return $this->fecha; }
    public function getTotal(): float { return $this->total; }
    public function getClienteNombre(): string { return $this->clienteNombre; }
    public function getVendedorNombre(): string { return $this->vendedorNombre; }
    public function getLineas(): array { return $this->lineas; }

    public function setLineas(array $lineas): void { $this->lineas = $lineas; }
}
