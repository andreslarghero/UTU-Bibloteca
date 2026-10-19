<?php
require_once __DIR__ . '/Venta.php';
require_once __DIR__ . '/Ticket.php';

// ============================================================
// MODELO: VENTA DE MOSTRADOR
// La que registra el bibliotecario en la caja: libros físicos, con
// el bibliotecario como vendedor. El cliente es opcional. Descuenta
// el stock de cada libro.
// ============================================================
class VentaMostrador extends Venta
{
    // Arma la venta a partir del ticket de la caja.
    public static function desdeTicket(Ticket $ticket, int $vendedorId, ?int $clienteId): VentaMostrador
    {
        $venta = new VentaMostrador();
        $venta->vendedorId = $vendedorId;
        $venta->clienteId = $clienteId;
        $venta->lineas = $ticket->lineas();
        return $venta;
    }

    public function tipo(): string
    {
        return 'Venta de mostrador';
    }

    public function formato(): string
    {
        return 'fisico';
    }

    public function bloquearLibro(RepositorioLibros $libros, LineaVenta $linea): ?Libro
    {
        return $libros->bloquearFisico($linea->getLibroId());
    }

    public function precioDe(Libro $libro): float
    {
        return (float) $libro->getPrecio();
    }

    public function verificarLinea(LineaVenta $linea, ?Libro $libro, RepositorioVentas $ventas): ?ResultadoVenta
    {
        if (!$libro || !$libro->estaActivo() || !$libro->esFisico()) {
            // El libro se borró, se dio de baja o dejó de venderse en formato
            // físico después de escanearlo.
            return ResultadoVenta::error('Uno de los libros ya no está disponible: ' . $linea->getTitulo() . '.');
        }

        if ($libro->getStock() < $linea->getCantidad()) {
            return ResultadoVenta::error('Stock insuficiente para ' . $libro->getTitulo() . ' (quedan ' . $libro->getStock() . ').');
        }

        return null;
    }

    public function afectarStock(LineaVenta $linea, RepositorioLibros $libros): ?ResultadoVenta
    {
        // Si no se modificó ninguna fila es que el stock ya no alcanzaba.
        if ($libros->descontarStock($linea->getLibroId(), $linea->getCantidad()) != 1) {
            return ResultadoVenta::error('Stock insuficiente para ' . $linea->getTitulo() . '.');
        }
        return null;
    }
}
