<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Texto.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';
require_once __DIR__ . '/../modelos/RepositorioVentas.php';
require_once __DIR__ . '/../modelos/ArchivoDigital.php';

// ============================================================
// CONTROLADOR: COMPRAS POR LA WEB (FÍSICAS Y DIGITALES), MIS COMPRAS Y DESCARGAS
// El comprador (o el dueño de las compras) es SIEMPRE el usuario de
// la sesión: el id nunca se toma de la dirección ni de un formulario.
// Acciones:
//   comprar          muestra la confirmación de la compra de un libro digital
//   confirmar        registra la compra digital (POST)
//   comprarFisico    muestra la compra de un libro físico (se elige la cantidad)
//   confirmarFisico  registra la compra física y descuenta el stock (POST)
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

    // Se ejecuta al pulsar "Confirmar compra". La compra la guarda el repositorio
    // dentro de una transacción; aquí solo se elige el mensaje.
    public function confirmar(): void
    {
        $usuario = $this->requerirSesion();

        if (!$this->esPost()) {
            $this->redirigir('compra', 'comprar');
        }

        $libroId = $this->entero($_POST['libro'] ?? null);

        try {
            $ventaId = $this->ventas->comprarDigital($usuario->getId(), $libroId);

            if ($ventaId == 0) {
                $mensaje = 'Ya tienes este libro digital en Mis compras.';
            } else {
                $mensaje = 'Compra registrada. Venta N.º ' . $ventaId . '. Total: $ ' . Texto::precio($this->ventas->buscar($ventaId)->getTotal()) . '.';
            }
            $tipo = 'exito';
        } catch (Exception $e) {
            $mensaje = $e->getMessage();
            $tipo = 'error';
        }

        // Guarda el aviso y recarga la página. Así, si se refresca el navegador, no
        // se vuelve a enviar la compra.
        $this->guardarAviso('compra', $mensaje, $tipo);
        $this->redirigir('compra', 'comprar', ['libro' => $libroId]);
    }

    // ---------- Comprar un libro físico ----------

    // Igual que la compra digital, pero el libro es de papel: se elige la cantidad
    // (hasta el stock) y el cliente lo retira en el mostrador.
    public function comprarFisico(): void
    {
        $this->requerirSesion();
        $libroId = $this->entero($_GET['libro'] ?? null);
        [$mensaje, $tipo] = $this->leerAviso('compra_fisica');

        $this->render('compras/comprar_fisico', [
            'libro' => (new RepositorioLibros())->fisicoDisponible($libroId),
            'mensaje' => $mensaje,
            'tipo' => $tipo,
        ], 'libro', 'Comprar libro físico');
    }

    // Se ejecuta al confirmar. La cantidad llega del formulario, pero el precio y
    // el stock se leen siempre de la base dentro de la transacción.
    public function confirmarFisico(): void
    {
        $usuario = $this->requerirSesion();

        if (!$this->esPost()) {
            $this->redirigir('compra', 'comprarFisico');
        }

        $libroId = $this->entero($_POST['libro'] ?? null);
        $cantidad = $this->entero($_POST['cantidad'] ?? null);

        try {
            $ventaId = $this->ventas->comprarFisico($usuario->getId(), $libroId, $cantidad);
            $mensaje = 'Compra registrada. Venta N.º ' . $ventaId . '. Total: $ ' . number_format($this->ventas->buscar($ventaId)->getTotal(), 2, ',', '.') . '. Retira tu libro en el mostrador de la biblioteca.';
            $tipo = 'exito';
        } catch (Exception $e) {
            $mensaje = $e->getMessage();
            $tipo = 'error';
        }

        $this->guardarAviso('compra_fisica', $mensaje, $tipo);
        $this->redirigir('compra', 'comprarFisico', ['libro' => $libroId]);
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

        // El mismo aviso sirve si el libro no existe, si no es digital o si es de
        // otra persona: así no se revela cuál es el caso.
        $ruta = $libro ? ArchivoDigital::ruta($libro->getArchivoDigital()) : false;

        if ($ruta === false) {
            http_response_code($libro ? 404 : 403);
            $error = $libro ? 'El archivo de este libro no está disponible en este momento.' : 'No puedes descargar este libro.';
            $this->render('compras/descarga_error', ['error' => $error], 'compra', 'Descarga');
            return;
        }

        // Nombre con el que se guarda en el equipo del usuario: el título del libro
        // (con sus tildes, sin símbolos raros). El nombre real del archivo nunca se
        // muestra. Se envía dos veces: "filename" sin tildes (navegadores viejos) y
        // "filename*" con el título completo en UTF-8.
        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $titulo = trim(preg_replace('/[^\p{L}\p{N} _-]/u', '', $libro->getTitulo()));
        $simple = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', Texto::sinTildes($titulo)));
        if ($simple == '') {
            $titulo = $simple = 'libro';
        }

        // Cabeceras: le dicen al navegador qué tipo de archivo es y que lo descargue
        // ("attachment") en lugar de intentar abrirlo como una página.
        header('Content-Type: ' . (($extension == 'pdf') ? 'application/pdf' : 'application/epub+zip'));
        header('Content-Disposition: attachment; filename="' . $simple . '.' . $extension . "\"; filename*=UTF-8''" . rawurlencode($titulo . '.' . $extension));
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        // Envía el contenido del archivo y termina.
        readfile($ruta);
        exit;
    }
}
