<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioVentas.php';

// ============================================================
// CONTROLADOR: CONSULTA DE OPERACIONES
// Para el bibliotecario: todas las ventas de mostrador y compras
// digitales registradas, y el detalle de cada una. Es solo de consulta:
// no modifica nada de la base.
// ============================================================
class OperacionController extends Controller
{
    // Lista de ventas, de la más nueva a la más antigua. Si en la dirección viene
    // ?venta=NÚMERO, se muestra además el detalle de esa venta.
    public function index(): void
    {
        $this->requerirBibliotecario();
        $repositorio = new RepositorioVentas();

        $ventaId = isset($_GET['venta']) ? (int) $_GET['venta'] : 0;
        $venta = ($ventaId > 0) ? $repositorio->buscar($ventaId) : null;

        $this->render('operaciones/lista', [
            'ventas' => $repositorio->todos(),
            'ventaId' => $ventaId,
            'venta' => $venta,
            'lineas' => $venta ? $venta->getLineas() : [],
        ], 'operacion', 'Operaciones');
    }
}
