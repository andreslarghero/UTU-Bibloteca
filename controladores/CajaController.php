<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../modelos/RepositorioLibros.php';
require_once __DIR__ . '/../modelos/RepositorioVentas.php';
require_once __DIR__ . '/../modelos/Ticket.php';

// ============================================================
// CONTROLADOR: CAJA DEL MOSTRADOR
// Pantalla para el bibliotecario: se escanea (o se escribe) el código de
// barras de cada libro y se va armando el ticket. El ticket es temporal:
// se guarda en la sesión, no en la base. Al finalizar se guarda la venta
// (RepositorioVentas::registrarMostrador), sus líneas y se descuenta el stock,
// todo junto en una transacción. Solo pueden entrar los bibliotecarios.
// Cada botón de la pantalla es una acción:
//   index      muestra el ticket
//   agregar    agrega un libro por su código de barras (POST)
//   quitar     quita una unidad de una línea (POST)
//   eliminar   elimina una línea completa (POST)
//   vaciar     vacía todo el ticket (POST)
//   finalizar  guarda la venta (POST)
// Después de cada acción se redirige a "index" (así, si se refresca el
// navegador, no se repite el último escaneo).
// ============================================================
class CajaController extends Controller
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
        [$mensaje, $tipo] = $this->leerAviso('caja');
        $ticket = Ticket::desdeSesion();

        $this->render('caja/ticket', [
            'ticket' => $ticket,
            'total' => $ticket->total(),
            'mensaje' => $mensaje,
            'tipo' => $tipo,
        ], 'caja', 'Caja');
    }

    // Agrega un libro por su código de barras.
    public function agregar(): void
    {
        $this->requerirBibliotecario();
        $this->exigirPost();

        $codigo = $this->post('codigo');
        $mensaje = '';
        $tipo = 'error';

        if ($codigo == '') {
            $mensaje = 'Escanea o escribe un código de barras.';
        } else {
            // Solo cuentan los libros activos que se venden en formato físico: la
            // caja no vende libros digitales.
            $libro = $this->libros->activoFisicoPorCodigo($codigo);

            if (!$libro) {
                $mensaje = 'Libro no encontrado (código ' . $codigo . ').';
            } elseif ($libro->getStock() == 0) {
                $mensaje = 'Libro agotado: ' . $libro->getTitulo() . '.';
            } else {
                $ticket = Ticket::desdeSesion();

                if ($ticket->cantidadDe($libro->getId()) + 1 > $libro->getStock()) {
                    // El ticket no puede tener más copias que el stock real.
                    $mensaje = 'No hay más stock disponible de ' . $libro->getTitulo() . ' (stock: ' . $libro->getStock() . ').';
                } else {
                    // Si el libro ya estaba, sube la cantidad; si no, crea la línea.
                    // El precio se toma siempre de la base de datos.
                    $ticket->agregar($libro);
                    $ticket->guardar();
                    $mensaje = 'Agregado: ' . $libro->getTitulo() . '.';
                    $tipo = 'exito';
                }
            }
        }

        $this->terminar($mensaje, $tipo);
    }

    // Quita una unidad de una línea. Si se queda sin unidades, se elimina.
    public function quitar(): void
    {
        $this->requerirBibliotecario();
        $this->exigirPost();

        $ticket = Ticket::desdeSesion();
        $ticket->quitarUna((int) ($_POST['libro_id'] ?? 0));
        $ticket->guardar();

        $this->terminar('', 'error');
    }

    // Elimina una línea completa.
    public function eliminar(): void
    {
        $this->requerirBibliotecario();
        $this->exigirPost();

        $ticket = Ticket::desdeSesion();
        $ticket->eliminar((int) ($_POST['libro_id'] ?? 0));
        $ticket->guardar();

        $this->terminar('', 'error');
    }

    // Vacía todo el ticket.
    public function vaciar(): void
    {
        $this->requerirBibliotecario();
        $this->exigirPost();

        $ticket = Ticket::desdeSesion();
        $ticket->vaciar();
        $ticket->guardar();

        $this->terminar('', 'error');
    }

    // Guarda la venta, sus líneas y descuenta el stock dentro de una transacción
    // (la hace RepositorioVentas::registrar). El bibliotecario es el vendedor. El
    // cliente es opcional: si se escribe su correo, la venta queda a su nombre y
    // aparece en su "Mis compras"; si se deja vacío, queda sin cliente.
    public function finalizar(): void
    {
        $bibliotecario = $this->requerirBibliotecario();
        $this->exigirPost();

        // Cliente de la venta (solo un usuario que exista y esté activo).
        $correoCliente = $this->post('correo_cliente');
        $cliente = ($correoCliente != '') ? $this->usuarios->activoPorCorreo($correoCliente) : null;

        $ticket = Ticket::desdeSesion();
        $mensaje = '';
        $tipo = 'error';

        if ($ticket->estaVacio()) {
            $mensaje = 'El ticket está vacío.';
        } elseif ($correoCliente != '' && !$cliente) {
            // El ticket se conserva para poder corregir el correo.
            $mensaje = 'No hay un usuario activo con ese correo.';
        } else {
            // La venta vuelve a consultar cada libro en la base, sin confiar en lo
            // guardado en la sesión. Si no se puede vender, lanza una Exception.
            $ventas = new RepositorioVentas();
            try {
                $ventaId = $ventas->registrarMostrador($bibliotecario->getId(), $cliente ? $cliente->getId() : null, $ticket->lineas());

                // Venta guardada: se vacía el ticket y se avisa con el número y el total.
                $ticket->vaciar();
                $ticket->guardar();
                $mensaje = 'Venta registrada correctamente. Venta N.º ' . $ventaId . '. Total vendido: $ ' . number_format($ventas->buscar($ventaId)->getTotal(), 2, ',', '.') . '.';
                $tipo = 'exito';
            } catch (Exception $e) {
                // El ticket se conserva para poder corregirlo.
                $mensaje = $e->getMessage();
            }
        }

        $this->terminar($mensaje, $tipo);
    }

    // Las acciones que cambian el ticket solo se aceptan por POST.
    private function exigirPost(): void
    {
        if (!$this->esPost()) {
            $this->redirigir('caja');
        }
    }

    // Guarda el aviso y recarga la página.
    private function terminar(string $mensaje, string $tipo): void
    {
        $this->guardarAviso('caja', $mensaje, $tipo);
        $this->redirigir('caja');
    }
}
