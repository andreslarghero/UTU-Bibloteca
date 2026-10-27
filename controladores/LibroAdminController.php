<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';
require_once __DIR__ . '/../modelos/RepositorioCategorias.php';
require_once __DIR__ . '/../modelos/ArchivoDigital.php';

// ============================================================
// CONTROLADOR: GESTIÓN DE LIBROS (ALTA, BAJA Y MODIFICACIÓN)
// Para el bibliotecario. La baja es lógica: nunca se usa DELETE, solo se
// cambia "activo". Un libro se vende en formato físico, digital o en los dos.
// Los archivos digitales (PDF y EPUB) se guardan en la carpeta protegida
// "digitales"; en la base solo se guarda el nombre.
// Acciones:
//   index      lista de libros y formulario de alta
//   editar     lo mismo, con el formulario cargado con un libro (?id=)
//   guardar    alta (id = 0) o edición (id > 0) (POST)
//   baja       da de baja un libro (POST)
//   reactivar  vuelve a activar un libro (POST)
// ============================================================
class LibroAdminController extends Controller
{
    private RepositorioLibros $libros;
    private RepositorioCategorias $categorias;

    public function __construct()
    {
        parent::__construct();
        $this->libros = new RepositorioLibros();
        $this->categorias = new RepositorioCategorias();
    }

    // ---------- Pantalla ----------

    public function index(): void
    {
        $this->requerirBibliotecario();
        $this->mostrar(Libro::nuevo(), false, []);
    }

    // Si en la dirección viene ?id=NÚMERO, se rellenan los campos con ese libro.
    public function editar(): void
    {
        $this->requerirBibliotecario();

        $encontrado = $this->libros->buscar((int) ($_GET['id'] ?? 0));

        if ($encontrado) {
            $this->mostrar($encontrado, $encontrado->tieneArchivo(), []);
        } else {
            $this->mostrar(Libro::nuevo(), false, ['El libro que quieres editar no existe.']);
        }
    }

    // Muestra la lista de libros (activos e inactivos) con el formulario. $libro es
    // el del formulario (id 0 = nuevo) y $tieneArchivo indica si ya tiene un archivo
    // digital guardado (el nombre del archivo nunca se muestra).
    private function mostrar(Libro $libro, bool $tieneArchivo, array $errores): void
    {
        [$mensaje] = $this->leerAviso('libros');

        $this->render('libros/administrar', [
            'libro' => $libro,
            'tieneArchivo' => $tieneArchivo,
            'errores' => $errores,
            'categorias' => $this->categorias->todos(),
            'mensaje' => $mensaje,
            'lista' => $this->libros->todos(),
        ], 'libroadmin', 'Gestión de libros');
    }

    // ---------- Alta y edición ----------

    public function guardar(): void
    {
        $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('libroadmin');
        }

        // Si el archivo enviado supera el límite del servidor, PHP descarta todo el
        // formulario y no llega ningún dato: se avisa del motivo.
        if (count($_POST) == 0) {
            $this->mostrar(Libro::nuevo(), false, ['El archivo es demasiado grande. No se guardó nada.']);
            return;
        }

        // Recoge lo escrito en el formulario. Se acepta el precio con coma o con
        // punto (890,50 o 890.50). Una casilla marcada llega en el formulario; una
        // sin marcar no llega.
        $libro = new Libro([
            'id' => (int) ($_POST['id'] ?? 0),
            'titulo' => $this->post('titulo'),
            'autor' => $this->post('autor'),
            'anio' => $this->post('anio'),
            'portada' => $this->post('portada'),
            'descripcion' => $this->post('descripcion'),
            'categoria_id' => (int) ($_POST['categoria_id'] ?? 0),
            'isbn' => $this->post('isbn'),
            'precio' => str_replace(',', '.', $this->post('precio')),
            'codigo_barras' => $this->post('codigo_barras'),
            'stock' => $this->post('stock'),
            'formato_fisico' => isset($_POST['formato_fisico']) ? 1 : 0,
            'formato_digital' => isset($_POST['formato_digital']) ? 1 : 0,
            'precio_digital' => str_replace(',', '.', $this->post('precio_digital')),
        ]);
        $errores = [];

        // Nombre del archivo digital que el libro ya tiene guardado (si tiene). Se lee
        // de la base, nunca del formulario.
        $archivoActual = null;

        // ---------- Validaciones ----------
        // Si es una edición, el libro tiene que existir.
        if ($libro->getId() > 0) {
            $existente = $this->libros->buscar($libro->getId());
            if ($existente) {
                $archivoActual = $existente->getArchivoDigital();
            } else {
                $errores[] = 'El libro que quieres editar no existe.';
            }
        }

        // Las reglas del libro las revisa la propia clase Libro.
        $errores = array_merge($errores, $libro->validar($this->libros, $this->categorias));

        // ---------- Formato digital: precio y archivo ----------
        $subida = $_FILES['archivo'] ?? null;
        $hayArchivoNuevo = ArchivoDigital::haySubida($subida);

        if ($libro->esDigital()) {
            $errores = array_merge($errores, $libro->validarPrecioDigital());

            if ($hayArchivoNuevo) {
                $error = ArchivoDigital::validarSubida($subida);
                if ($error != '') {
                    $errores[] = $error;
                }
            } elseif ($archivoActual == '') {
                // No se envió archivo y el libro tampoco tenía uno guardado.
                $errores[] = 'Para el formato digital tienes que subir el archivo (PDF o EPUB).';
            }
        }

        // ---------- Guardado ----------
        // Si es digital y no se subió un archivo nuevo, conserva el que tenía.
        $libro->setArchivoDigital($archivoActual);
        $archivoNuevo = '';   // nombre del archivo recién guardado, si hubo subida

        if (count($errores) == 0 && $libro->esDigital() && $hayArchivoNuevo) {
            $archivoNuevo = ArchivoDigital::guardar($subida);

            if ($archivoNuevo != '') {
                $libro->setArchivoDigital($archivoNuevo);
            } else {
                $errores[] = 'No se pudo guardar el archivo digital.';
            }
        }
        if (count($errores) == 0) {
            try {
                if ($libro->getId() == 0) {
                    // Alta: el libro nuevo queda activo.
                    $this->libros->crear($libro);
                    $aviso = 'Libro agregado: ' . $libro->getTitulo() . '.';
                } else {
                    // Edición: se cambian los datos, pero nunca el id.
                    $this->libros->actualizar($libro);
                    $aviso = 'Libro actualizado: ' . $libro->getTitulo() . '.';
                }

                // Guarda el aviso y recarga la página, para que al refrescar el
                // navegador no se repita el guardado.
                $this->guardarAviso('libros', $aviso);
                $this->redirigir('libroadmin');
            } catch (PDOException $e) {
                // Error inesperado de la base: no se muestran detalles internos.
                $errores[] = 'No se pudo guardar el libro.';

                // El archivo recién subido no quedó asociado a ningún libro: se quita.
                if ($archivoNuevo != '') {
                    ArchivoDigital::quitar($archivoNuevo);
                }
            }
        }

        // Con errores, el formulario conserva lo que se escribió.
        $this->mostrar($libro, $archivoActual != '', $errores);
    }

    // ---------- Baja y reactivación ----------

    public function baja(): void
    {
        $this->cambiarEstado(false);
    }

    public function reactivar(): void
    {
        $this->cambiarEstado(true);
    }

    private function cambiarEstado(bool $activar): void
    {
        $this->requerirBibliotecario();

        if (!$this->esPost()) {
            $this->redirigir('libroadmin');
        }

        $this->libros->cambiarEstado((int) ($_POST['id'] ?? 0), $activar);

        $this->guardarAviso('libros', $activar ? 'Libro reactivado.' : 'Libro dado de baja.');
        $this->redirigir('libroadmin');
    }
}
