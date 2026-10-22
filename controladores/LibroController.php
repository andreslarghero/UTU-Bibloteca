<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';
require_once __DIR__ . '/../modelos/RepositorioResenas.php';
require_once __DIR__ . '/../modelos/RepositorioVentas.php';

// ============================================================
// CONTROLADOR: CATÁLOGO Y FICHA DE LOS LIBROS
// Solo pueden verlo los usuarios que han iniciado sesión.
// Acciones:
//   index           catálogo con buscador y filtro por categoría
//   ver             ficha de un libro con sus reseñas
//   guardarResena   guarda (o actualiza) la reseña del usuario (POST)
//   eliminarResena  elimina una reseña (POST)
// ============================================================
class LibroController extends Controller
{
    private RepositorioLibros $libros;
    private RepositorioResenas $resenas;

    public function __construct()
    {
        parent::__construct();
        $this->libros = new RepositorioLibros();
        $this->resenas = new RepositorioResenas();
    }

    // ---------- Catálogo ----------

    // Los dos filtros llegan por la dirección: ?buscar=texto&categoria=Fábula
    // Se pueden usar por separado o juntos.
    public function index(): void
    {
        $this->requerirSesion();

        // Las categorías (las que tienen los libros del catálogo) sirven para el
        // desplegable del filtro y para comprobar la que llega por la dirección.
        $categorias = $this->libros->categorias(true);

        // Texto de búsqueda (vacío = sin búsqueda).
        $buscar = $this->get('buscar');

        // Categoría elegida ("" = todas). Solo se acepta una que exista en el
        // catálogo (sin distinguir mayúsculas); cualquier otra cosa se trata como "todas".
        $categoria = '';
        $pedida = mb_strtolower($this->get('categoria'));
        foreach ($categorias as $nombre) {
            if (mb_strtolower($nombre) == $pedida) {
                $categoria = $nombre;
            }
        }

        // Una sola consulta sirve para todos los casos. Los libros se usan dos
        // veces: en el carrusel de destacados y en la cuadrícula del catálogo.
        $libros = $this->libros->catalogo($buscar, $categoria);

        // Los destacados solo se muestran en el catálogo completo (sin búsqueda ni filtro).
        $destacados = [];
        if ($buscar == '' && $categoria == '' && count($libros) >= 3) {
            $destacados = $this->elegirDestacados($libros);
        }

        $this->render('libros/catalogo', [
            'categorias' => $categorias,
            'buscar' => $buscar,
            'categoria' => $categoria,
            'libros' => $libros,
            'destacados' => $destacados,
        ], 'libro', 'Catálogo');
    }

    // Elige hasta 10 libros de la lista que ya se trajo (no hace otra consulta),
    // mezclando físicos y digitales y variando las categorías.
    private function elegirDestacados(array $libros): array
    {
        // Se separan físicos y digitales y se intercalan: uno y uno.
        $fisicos = [];
        $digitales = [];
        foreach ($libros as $libro) {
            if ($libro->esDigital() && !$libro->esFisico()) {
                $digitales[] = $libro;
            } else {
                $fisicos[] = $libro;
            }
        }
        $intercalados = [];
        for ($i = 0; $i < max(count($fisicos), count($digitales)); $i++) {
            if (isset($fisicos[$i])) { $intercalados[] = $fisicos[$i]; }
            if (isset($digitales[$i])) { $intercalados[] = $digitales[$i]; }
        }

        $destacados = [];

        // Primera pasada: un libro por categoría, para que haya variedad.
        $categoriasUsadas = [];
        foreach ($intercalados as $libro) {
            if (count($destacados) < 10 && !isset($categoriasUsadas[$libro->getCategoria()])) {
                $destacados[$libro->getId()] = $libro;
                $categoriasUsadas[$libro->getCategoria()] = true;
            }
        }
        // Segunda pasada: se completa hasta 10 con los que siguen.
        foreach ($intercalados as $libro) {
            if (count($destacados) < 10 && !isset($destacados[$libro->getId()])) {
                $destacados[$libro->getId()] = $libro;
            }
        }

        return $destacados;
    }

    // ---------- Ficha ----------

    // Toma el id de la dirección y busca ese libro. Si el libro está dado de baja
    // (activo = 0) no se encuentra y la página muestra el aviso de que no existe.
    public function ver(): void
    {
        $usuario = $this->requerirSesion();
        $id = (int) ($_GET['id'] ?? 0);

        $libro = $this->libros->activoPorId($id);
        $valoracion = null;
        $resenas = [];
        $miResena = null;
        $yaComprado = false;
        $relacionados = [];

        if ($libro) {
            // Valoración media y número total de reseñas del libro.
            $valoracion = $this->resenas->valoracion($id);

            // Lista de reseñas con el nombre de quien las escribió, de la más nueva a
            // la más antigua, y la reseña del usuario actual (para rellenar el formulario).
            $resenas = $this->resenas->delLibro($id);
            $miResena = $this->resenas->deUsuario($id, $usuario->getId());

            // ¿El usuario ya compró este libro en digital? Sirve para mostrar
            // "Comprar digital" o "Ya comprado" en la ficha.
            if ($libro->esDigital()) {
                $yaComprado = (new RepositorioVentas())->clienteCompro($usuario->getId(), $id);
            }

            // También podría interesarte: hasta 4 libros de la misma categoría.
            $relacionados = $this->libros->relacionados($libro->getCategoria(), $id);
        }

        $this->render('libros/ver', [
            'libro' => $libro,
            'valoracion' => $valoracion,
            'resenas' => $resenas,
            'miResena' => $miResena,
            'yaComprado' => $yaComprado,
            'relacionados' => $relacionados,
        ], 'libro', $libro ? $libro->getTitulo() : 'Libro no encontrado', 'pagina-libro');
    }

    // ---------- Reseñas ----------

    // Guarda la reseña del usuario. Si ya tenía una en este libro, se actualiza
    // en vez de duplicarse.
    public function guardarResena(): void
    {
        $usuario = $this->requerirSesion();
        $id = (int) ($_GET['id'] ?? 0);

        if (!$this->esPost() || !$this->libros->activoPorId($id)) {
            $this->ver();
            return;
        }

        // La puntuación se limita entre 1 y 5 estrellas (lo hace Resena::nueva).
        $resena = Resena::nueva($id, $usuario->getId(), (int) ($_POST['puntuacion'] ?? 0), $this->post('comentario'));
        $this->resenas->guardar($resena);

        // Recarga la ficha para mostrar los cambios (y evitar reenviar el
        // formulario al refrescar).
        $this->redirigir('libro', 'ver', ['id' => $id]);
    }

    // Elimina una reseña. El bibliotecario puede borrar cualquiera; un usuario
    // normal solo puede borrar la suya.
    public function eliminarResena(): void
    {
        $usuario = $this->requerirSesion();
        $id = (int) ($_GET['id'] ?? 0);

        if (!$this->esPost() || !$this->libros->activoPorId($id)) {
            $this->ver();
            return;
        }

        $resenaId = (int) ($_POST['resena_id'] ?? 0);

        if ($usuario->esBibliotecario()) {
            $this->resenas->eliminar($resenaId);
        } else {
            $this->resenas->eliminar($resenaId, $usuario->getId());
        }

        $this->redirigir('libro', 'ver', ['id' => $id]);
    }
}
