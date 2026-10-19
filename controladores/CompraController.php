<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Texto.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';
require_once __DIR__ . '/../modelos/RepositorioVentas.php';
require_once __DIR__ . '/../modelos/ArchivoDigital.php';

// ============================================================
// CONTROLADOR: COMPRAS DIGITALES, MIS COMPRAS Y DESCARGAS
// El comprador (o el dueño de las compras) es SIEMPRE el usuario de
// la sesión: el id nunca se toma de la dirección ni de un formulario.
// Acciones:
//   comprar     muestra la confirmación de la compra de un libro digital
//   confirmar   registra la compra (POST)
//   index       historial de compras del usuario y detalle de una
//   descargar   entrega el archivo de un libro digital comprado
// ============================================================
class CompraController extends Controller
{
    private RepositorioVentas $ventas;

    public function __construct()
    {
        parent::__construct();
        $this->ventas = new RepositorioVentas();
    }

    // ---------- Comprar un libro digital ----------

    // El id del libro llega por la dirección (?libro=3). Solo se acepta un valor
    // formado por dígitos; cualquier otra cosa se trata como 0 (un libro que no
    // existe). Se muestra el libro (solo si está activo y se vende en digital) y
    // si este usuario ya lo compró.
    public function comprar(): void
    {
        $usuario = $this->requerirSesion();
        $libroId = $this->entero($_GET['libro'] ?? null);
        [$mensaje, $tipo] = $this->leerAviso('compra');

        $libro = (new RepositorioLibros())->digitalDisponible($libroId);
        $yaComprado = $libro ? $this->ventas->clienteCompro($usuario->getId(), $libroId) : false;

        $this->render('compras/comprar', [
            'libro' => $libro,
            'yaComprado' => $yaComprado,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
        ], 'libro', 'Comprar libro digital');
    }

    // Se ejecuta al pulsar "Confirmar compra". La venta (VentaDigital) la guarda el
    // repositorio dentro de una transacción; aquí solo se elige el mensaje.
    public function confirmar(): void
    {
        $usuario = $this->requerirSesion();

        if (!$this->esPost()) {
            $this->redirigir('compra', 'comprar');
        }

        $libroId = $this->entero($_POST['libro'] ?? null);
        $resultado = $this->ventas->registrar(VentaDigital::de($usuario->getId(), $libroId));

        if ($resultado->getEstado() === ResultadoVenta::OK) {
            $mensaje = 'Compra registrada. Venta N.º ' . $resultado->getVentaId() . '. Total: $ ' . Texto::precio($resultado->getTotal()) . '.';
            $tipo = 'exito';
        } elseif ($resultado->getEstado() === ResultadoVenta::YA_COMPRADO) {
            $mensaje = 'Ya tienes este libro digital en Mis compras.';
            $tipo = 'exito';
        } elseif ($resultado->getEstado() === ResultadoVenta::NO_DISPONIBLE) {
            $mensaje = 'Este libro no está disponible en formato digital.';
            $tipo = 'error';
        } else {
            $mensaje = 'No se pudo registrar la compra.';
            $tipo = 'error';
        }

        // Guarda el aviso y recarga la página. Así, si se refresca el navegador, no
        // se vuelve a enviar la compra.
        $this->guardarAviso('compra', $mensaje, $tipo);
        $this->redirigir('compra', 'comprar', ['libro' => $libroId]);
    }

    // ---------- Mis compras ----------

    // Lista de compras: solo las ventas de este cliente, de la más nueva a la más
    // antigua. Si en la dirección viene ?venta=NÚMERO, se muestra el detalle de esa
    // compra, pero solo si pertenece a este cliente: la consulta pide a la vez el
    // número de venta Y el id del cliente de la sesión. Así, aunque alguien escriba
    // a mano el número de una venta ajena, no la ve.
    public function index(): void
    {
        $usuario = $this->requerirSesion();
        $clienteId = $usuario->getId();

        $compras = $this->ventas->delCliente($clienteId);

        $ventaId = $this->entero($_GET['venta'] ?? null);
        $pidioDetalle = isset($_GET['venta']);   // se pidió alguna venta (aunque el valor no sea válido)
        $venta = null;

        if ($ventaId > 0) {
            $venta = $this->ventas->delClientePorId($ventaId, $clienteId);
        }

        $this->render('compras/mis_compras', [
            'compras' => $compras,
            'pidioDetalle' => $pidioDetalle,
            'venta' => $venta,
            'lineas' => $venta ? $venta->getLineas() : [],
        ], 'compra', 'Mis compras');
    }

    // ---------- Descarga ----------

    // Entrega el archivo PDF o EPUB de un libro, pero SOLO si el usuario de la
    // sesión lo compró en formato digital. Lo único que llega del navegador es el
    // id del libro.
    public function descargar(): void
    {
        $usuario = $this->requerirSesion();
        $libroId = $this->entero($_GET['libro'] ?? null);

        // La consulta exige todo a la vez: una venta de este usuario, con una línea de
        // este libro, en formato digital, y que el libro tenga un archivo guardado.
        $libro = $this->ventas->libroDescargable($usuario->getId(), $libroId);

        $error = '';
        $ruta = false;
        $extension = '';

        if (!$libro) {
            // El mismo aviso sirve si el libro no existe, si no es digital o si es de
            // otra persona: así no se revela cuál es el caso.
            $error = 'No puedes descargar este libro.';
        } else {
            $nombre = $libro->getArchivoDigital();
            $extension = strtolower(pathinfo(basename($nombre), PATHINFO_EXTENSION));
            $ruta = ArchivoDigital::ruta($nombre);

            if ($ruta === false) {
                $error = 'El archivo de este libro no está disponible en este momento.';
            }
        }

        if ($error == '') {
            // Nombre con el que se guarda en el equipo del usuario: el título del libro.
            // El nombre real del archivo en el servidor nunca se muestra. Se envía dos
            // veces, como indica el estándar:
            //  - "filename": versión simple, sin acentos ni símbolos;
            //  - "filename*": el título completo con sus acentos (en UTF-8).
            $titulo = trim(preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]/u', '', $libro->getTitulo()));
            $tituloSimple = Texto::sinTildes($titulo);
            $tituloSimple = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $tituloSimple));
            if ($titulo == '' || $tituloSimple == '') {
                $titulo = 'libro';
                $tituloSimple = 'libro';
            }

            // Cabeceras: le dicen al navegador qué tipo de archivo es y que lo descargue
            // ("attachment") en lugar de intentar abrirlo como una página.
            header('Content-Type: ' . (($extension == 'pdf') ? 'application/pdf' : 'application/epub+zip'));
            header('Content-Disposition: attachment; filename="' . $tituloSimple . '.' . $extension . "\"; filename*=UTF-8''" . rawurlencode($titulo . '.' . $extension));
            header('Content-Length: ' . filesize($ruta));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');

            // Envía el contenido del archivo y termina.
            readfile($ruta);
            exit;
        }

        // No se puede descargar: se muestra una página con el aviso, sin ningún dato interno.
        http_response_code($libro ? 404 : 403);
        $this->render('compras/descarga_error', ['error' => $error], 'compra', 'Descarga');
    }
}
