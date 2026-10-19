<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';

// ============================================================
// CONTROLADOR: GESTIÓN DE STOCK
// Para el bibliotecario: muestra todos los libros físicos con sus
// copias disponibles y permite cambiar la cantidad. Los libros solo
// digitales no tienen stock, así que no aparecen aquí.
// Acciones:
//   index    muestra la lista
//   guardar  guarda el nuevo stock de un libro (POST)
// ============================================================
class StockController extends Controller
{
    private RepositorioLibros $libros;

    public function __construct()
    {
        parent::__construct();
        $this->libros = new RepositorioLibros();
    }

    public function index(): void
    {
        $this->requerirBibliotecario();
        $this->mostrar('');
    }

    public function guardar(): void
    {
        $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('stock');
        }

        // max() evita cantidades negativas.
        $id = (int) ($_POST['id'] ?? 0);
        $stock = max(0, (int) ($_POST['stock'] ?? 0));

        // El stock es solo del formato físico: un libro que no es físico no se modifica.
        $this->libros->actualizarStock($id, $stock);

        $this->mostrar('Stock actualizado correctamente.');
    }

    private function mostrar(string $mensaje): void
    {
        $this->render('stock/lista', [
            'libros' => $this->libros->fisicos(),
            'mensaje' => $mensaje,
        ], 'stock', 'Gestión de stock');
    }
}
