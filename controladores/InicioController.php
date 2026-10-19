<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';

// ============================================================
// CONTROLADOR: PÁGINA DE INICIO
// Primera página del sitio. La puede ver cualquiera: no hace falta
// haber iniciado sesión. Si el usuario YA inició sesión, la conserva:
// esta página no cierra la sesión ni la modifica. Solo "Cerrar sesión"
// la cierra.
// ============================================================
class InicioController extends Controller
{
    // Muestra el bloque principal con cuatro portadas.
    public function index(): void
    {
        // Si hay sesión, se comprueba en la base que el usuario sigue existiendo y
        // activo y se actualizan su nombre y su rol para el menú.
        $usuario = $this->sesionOpcional();

        $this->render('inicio/inicio', [
            'librosHero' => (new RepositorioLibros())->portadasInicio(),
            'conSesion' => $usuario !== null,
        ], 'inicio', 'Biblioteca Digital');
    }
}
