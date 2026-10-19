<?php
require_once __DIR__ . '/Venta.php';

// ============================================================
// MODELO: COMPRA DIGITAL
// La que hace un cliente desde la web: un libro digital, sin
// vendedor. El stock no se toca (es solo del formato físico) y un
// mismo libro digital no se cobra dos veces al mismo cliente.
// ============================================================
class VentaDigital extends Venta
{
    // Arma la compra de un libro digital para un cliente.
    public static function de(int $clienteId, int $libroId): VentaDigital
    {
        $venta = new VentaDigital();
        $venta->clienteId = $clienteId;
        $venta->vendedorId = null;
        $venta->lineas = [new LineaVenta(['libro_id' => $libroId, 'cantidad' => 1, 'formato' => 'digital'])];
        return $venta;
    }

    public function tipo(): string
    {
        return 'Compra digital';
    }

    public function formato(): string
    {
        return 'digital';
    }

    public function bloquearLibro(RepositorioLibros $libros, LineaVenta $linea): ?Libro
    {
        return $libros->bloquearDigital($linea->getLibroId());
    }

    public function precioDe(Libro $libro): float
    {
        return (float) $libro->getPrecioDigital();
    }

    public function verificarLinea(LineaVenta $linea, ?Libro $libro, RepositorioVentas $ventas): ?ResultadoVenta
    {
        // El libro tiene que existir, estar activo, venderse en formato digital
        // y tener precio digital y archivo.
        if (!$libro || !$libro->estaActivo() || !$libro->esDigital()
            || $libro->getPrecioDigital() === null || $libro->getArchivoDigital() === null) {
            return new ResultadoVenta(ResultadoVenta::NO_DISPONIBLE);
        }

        // Si ya lo compró, no se crea otra venta ni se cobra de nuevo.
        if ($ventas->clienteCompro($this->clienteId, $linea->getLibroId())) {
            return new ResultadoVenta(ResultadoVenta::YA_COMPRADO);
        }

        return null;
    }

    public function afectarStock(LineaVenta $linea, RepositorioLibros $libros): ?ResultadoVenta
    {
        // Un libro digital no tiene stock: no hay nada que descontar.
        return null;
    }
}
