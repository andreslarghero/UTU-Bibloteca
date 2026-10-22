<?php
// ============================================================
// MODELO: LÍNEA DE VENTA
// Un libro dentro de una venta (o del ticket de la caja), con la
// cantidad y el precio al que se vendió. Cada objeto es una fila
// de "venta_lineas" (o una línea del ticket, antes de guardarse).
// ============================================================
class LineaVenta
{
    private int $libroId;
    private string $titulo;
    private int $cantidad;
    private float $precio;
    private string $formato;          // "fisico" o "digital"
    private bool $tieneArchivo;       // el libro tiene archivo digital (solo al leer una compra)
    private string $tipoArchivo;      // "PDF" o "EPUB" (solo al leer una compra)

    // $d es un arreglo con los campos de la línea (los que falten quedan vacíos).
    public function __construct(array $d)
    {
        $this->libroId = (int) ($d['libro_id'] ?? 0);
        $this->titulo = (string) ($d['titulo'] ?? '');
        $this->cantidad = (int) ($d['cantidad'] ?? 1);
        $this->precio = (float) ($d['precio'] ?? 0);
        $this->formato = (string) ($d['formato'] ?? 'fisico');
        $this->tieneArchivo = !empty($d['tiene_archivo']);
        $this->tipoArchivo = (string) ($d['tipo_archivo'] ?? '');
    }

    public function getLibroId(): int { return $this->libroId; }
    public function getTitulo(): string { return $this->titulo; }
    public function getCantidad(): int { return $this->cantidad; }
    public function getPrecio(): float { return $this->precio; }
    public function tieneArchivo(): bool { return $this->tieneArchivo; }
    public function getTipoArchivo(): string { return $this->tipoArchivo; }

    public function esDigital(): bool { return $this->formato === 'digital'; }

    // Precio x cantidad.
    public function getSubtotal(): float
    {
        return $this->precio * $this->cantidad;
    }

    // Suma o resta copias (lo usa el ticket de la caja).
    public function cambiarCantidad(int $diferencia): void
    {
        $this->cantidad += $diferencia;
    }
}
