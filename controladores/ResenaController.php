<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioResenas.php';

// ============================================================
// CONTROLADOR: GESTIÓN DE RESEÑAS
// Para el bibliotecario: muestra todas las reseñas de todos los libros
// y permite eliminar las que no sean adecuadas.
// Acciones:
//   index     muestra la lista
//   eliminar  elimina una reseña (POST)
// ============================================================
class ResenaController extends Controller
{
    private RepositorioResenas $resenas;

    public function __construct()
    {
        parent::__construct();
        $this->resenas = new RepositorioResenas();
    }

    public function index(): void
    {
        $this->requerirBibliotecario();
        $this->mostrar('');
    }

    public function eliminar(): void
    {
        $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('resena');
        }

        $this->resenas->eliminar((int) ($_POST['resena_id'] ?? 0));

        $this->mostrar('Reseña eliminada correctamente.');
    }

    private function mostrar(string $mensaje): void
    {
        $this->render('resenas/lista', [
            'resenas' => $this->resenas->todos(),
            'mensaje' => $mensaje,
        ], 'resena', 'Gestión de reseñas');
    }
}
