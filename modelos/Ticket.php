<?php
require_once __DIR__ . '/LineaVenta.php';
require_once __DIR__ . '/Libro.php';

// ============================================================
// MODELO: TICKET DE LA CAJA
// La lista temporal de libros que se van escaneando antes de cobrar.
// No se guarda en la base: vive en la sesión del bibliotecario. Cada
// libro aparece en una sola línea (se identifica por su id), así el
// mismo libro nunca está en dos líneas.
// ============================================================
class Ticket
{
    private array $lineas = [];   // LineaVenta, indexadas por el id del libro

    // Recupera el ticket de la sesión (o uno vacío).
    public static function desdeSesion(): Ticket
    {
        $ticket = new Ticket();
        foreach (($_SESSION['ticket'] ?? []) as $id => $datos) {
            $ticket->lineas[(int) $id] = new LineaVenta($datos);
        }
        return $ticket;
    }

    // Guarda el ticket en la sesión.
    public function guardar(): void
    {
        $_SESSION['ticket'] = [];
        foreach ($this->lineas as $id => $linea) {
            $_SESSION['ticket'][$id] = [
                'libro_id' => $linea->getLibroId(),
                'titulo' => $linea->getTitulo(),
                'precio' => $linea->getPrecio(),
                'cantidad' => $linea->getCantidad(),
            ];
        }
    }

    // Cuántas copias de este libro ya hay en el ticket.
    public function cantidadDe(int $libroId): int
    {
        return isset($this->lineas[$libroId]) ? $this->lineas[$libroId]->getCantidad() : 0;
    }

    // Agrega una copia. Si el libro ya estaba, sube la cantidad; si no, crea
    // la línea. El precio se toma siempre de la base de datos.
    public function agregar(Libro $libro): void
    {
        $id = $libro->getId();
        if (isset($this->lineas[$id])) {
            $this->lineas[$id]->cambiarCantidad(1);
            return;
        }
        $this->lineas[$id] = new LineaVenta([
            'libro_id' => $id,
            'titulo' => $libro->getTitulo(),
            'precio' => $libro->getPrecio(),
            'cantidad' => 1,
        ]);
    }

    // Quita una copia. Si la línea se queda sin unidades, se elimina.
    public function quitarUna(int $libroId): void
    {
        if (isset($this->lineas[$libroId])) {
            $this->lineas[$libroId]->cambiarCantidad(-1);
            if ($this->lineas[$libroId]->getCantidad() <= 0) {
                unset($this->lineas[$libroId]);
            }
        }
    }

    // Elimina la línea completa de un libro.
    public function eliminar(int $libroId): void
    {
        unset($this->lineas[$libroId]);
    }

    // Vacía todo el ticket.
    public function vaciar(): void
    {
        $this->lineas = [];
    }

    public function estaVacio(): bool
    {
        return count($this->lineas) == 0;
    }

    public function lineas(): array
    {
        return $this->lineas;
    }

    // Suma el subtotal (precio x cantidad) de todas las líneas.
    public function total(): float
    {
        $total = 0;
        foreach ($this->lineas as $linea) {
            $total = $total + $linea->getSubtotal();
        }
        return $total;
    }
}
