<?php
// ============================================================
// COMPRA DE UN LIBRO DIGITAL
// Página para cualquier usuario con sesión iniciada.
// Primero muestra una pantalla de confirmación y, al confirmar,
// registra la venta a nombre del usuario.
// Es una compra SIMULADA (trabajo académico): no se pide ningún
// medio de pago. Confirmar = registrar la venta.
//
// Lo único que llega del navegador es el id del libro. El
// cliente sale de la sesión, y el precio y el formato salen de
// la base: nada de eso se puede cambiar desde el formulario.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_usuario.php";

// El comprador es SIEMPRE el usuario de la sesión.
$id_cliente = $_SESSION["id"];

// ------------------------------------------------------------
// ID DEL LIBRO
// Llega por la dirección (comprar_digital.php?libro=3) al abrir
// la página, o por el formulario al confirmar. Solo se acepta un
// valor formado por dígitos; cualquier otra cosa se trata como 0
// (un libro que no existe).
// ------------------------------------------------------------
$origen = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST : $_GET;
$libro_id = 0;
if (isset($origen["libro"]) && is_string($origen["libro"]) && ctype_digit($origen["libro"])) {
    $libro_id = (int) $origen["libro"];
}

// ------------------------------------------------------------
// CONFIRMAR LA COMPRA
// Se ejecuta al pulsar "Confirmar compra". Todo va dentro de una
// transacción: o se guardan la venta y su línea (COMMIT) o no se
// guarda nada (ROLLBACK).
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mensaje = "";
    $tipo = "error";

    try {
        mysqli_begin_transaction($conexion);

        // 1. Vuelve a leer el libro de la base. "FOR UPDATE" bloquea esa
        //    fila hasta terminar la transacción: si el mismo usuario envía
        //    la compra dos veces a la vez, la segunda espera a la primera
        //    y entonces ya encuentra el libro comprado.
        $consulta = mysqli_prepare($conexion,
            "SELECT titulo, precio_digital, archivo_digital, activo, formato_digital FROM libros WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($consulta, "i", $libro_id);
        mysqli_stmt_execute($consulta);
        $libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

        // 2. El libro tiene que existir, estar activo, venderse en formato
        //    digital y tener precio digital y archivo.
        if (!$libro || $libro["activo"] != 1 || $libro["formato_digital"] != 1
            || $libro["precio_digital"] === null || $libro["archivo_digital"] === null) {
            $mensaje = "Este libro no está disponible en formato digital.";
        } else {
            // 3. ¿Este usuario ya compró este libro en digital? Si es así,
            //    no se crea otra venta ni se cobra de nuevo.
            $consulta = mysqli_prepare($conexion,
                "SELECT COUNT(*) AS total FROM venta_lineas
                 JOIN ventas ON ventas.id = venta_lineas.venta_id
                 WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'");
            mysqli_stmt_bind_param($consulta, "ii", $id_cliente, $libro_id);
            mysqli_stmt_execute($consulta);

            if (mysqli_fetch_assoc(mysqli_stmt_get_result($consulta))["total"] > 0) {
                $mensaje = "Ya tienes este libro digital en Mis compras.";
                $tipo = "exito";
            } else {
                // 4. Guarda la venta: el cliente es el usuario de la sesión, no hay
                //    vendedor (es una compra por la web) y el total es el precio
                //    digital que tiene el libro en la base en este momento.
                $precio = $libro["precio_digital"];

                $insertar = mysqli_prepare($conexion, "INSERT INTO ventas (id_cliente, id_vendedor, total) VALUES (?, NULL, ?)");
                mysqli_stmt_bind_param($insertar, "id", $id_cliente, $precio);

                if (!mysqli_stmt_execute($insertar)) {
                    $mensaje = "No se pudo registrar la compra.";
                } else {
                    $venta_id = mysqli_insert_id($conexion);

                    // 5. Guarda la línea: una copia, en formato digital. El stock no
                    //    se toca: el stock es solo del formato físico.
                    $insertar_linea = mysqli_prepare($conexion,
                        "INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, 1, ?, 'digital')");
                    mysqli_stmt_bind_param($insertar_linea, "iid", $venta_id, $libro_id, $precio);

                    if (!mysqli_stmt_execute($insertar_linea)) {
                        $mensaje = "No se pudo registrar la compra.";
                    } else {
                        $mensaje = "Compra registrada. Venta N.º " . $venta_id . ". Total: $ " . number_format($precio, 2, ",", ".") . ".";
                        $tipo = "exito";
                    }
                }
            }
        }

        // 6. Si se guardó todo se confirma; si algo falló se deshace todo,
        //    para que nunca quede una venta sin su línea.
        if ($tipo == "exito") {
            if (!mysqli_commit($conexion)) {
                $mensaje = "No se pudo registrar la compra.";
                $tipo = "error";
            }
        } else {
            mysqli_rollback($conexion);
        }
    } catch (Throwable $e) {
        // Cualquier error inesperado de la base: se deshace todo y se
        // muestra un aviso general, sin detalles internos de MySQL.
        mysqli_rollback($conexion);
        $mensaje = "No se pudo registrar la compra.";
        $tipo = "error";
    }

    // Guarda el aviso en la sesión y recarga la página. Así, si se
    // refresca el navegador, no se vuelve a enviar la compra.
    $_SESSION["compra_mensaje"] = $mensaje;
    $_SESSION["compra_tipo"] = $tipo;
    header("Location: comprar_digital.php?libro=" . $libro_id);
    exit;
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
$mensaje = isset($_SESSION["compra_mensaje"]) ? $_SESSION["compra_mensaje"] : "";
$tipo = (isset($_SESSION["compra_tipo"]) && $_SESSION["compra_tipo"] == "exito") ? "exito" : "error";
unset($_SESSION["compra_mensaje"], $_SESSION["compra_tipo"]);

// ------------------------------------------------------------
// DATOS PARA LA PANTALLA
// Busca el libro (solo si está activo y se vende en digital) y
// comprueba si este usuario ya lo compró en formato digital.
// No se pide el nombre del archivo: esta página no lo necesita.
// ------------------------------------------------------------
$consulta = mysqli_prepare($conexion,
    "SELECT id, titulo, autor, precio_digital FROM libros
     WHERE id = ? AND activo = 1 AND formato_digital = 1
       AND precio_digital IS NOT NULL AND archivo_digital IS NOT NULL");
mysqli_stmt_bind_param($consulta, "i", $libro_id);
mysqli_stmt_execute($consulta);
$libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

$ya_comprado = false;

if ($libro) {
    $consulta = mysqli_prepare($conexion,
        "SELECT COUNT(*) AS total FROM venta_lineas
         JOIN ventas ON ventas.id = venta_lineas.venta_id
         WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'");
    mysqli_stmt_bind_param($consulta, "ii", $id_cliente, $libro_id);
    mysqli_stmt_execute($consulta);
    $ya_comprado = (mysqli_fetch_assoc(mysqli_stmt_get_result($consulta))["total"] > 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar libro digital - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

    <main>
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="libros.php">&larr; Volver al catálogo</a>

        <h2>Comprar libro digital</h2>

        <!-- Aviso de la última acción: verde si salió bien, rojo si hubo un problema -->
        <?php if ($mensaje) { ?>
            <p class="<?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <?php if (!$libro) { ?>
            <!-- El libro no existe, está dado de baja o no se vende en digital.
                 Si ya se mostró un aviso arriba, no se repite. -->
            <?php if (!$mensaje) { ?>
                <p class="error">Este libro no está disponible en formato digital.</p>
            <?php } ?>
        <?php } elseif ($ya_comprado) { ?>
            <!-- El usuario ya tiene este libro: no se le ofrece comprarlo de nuevo.
                 Si viene de comprarlo recién, el aviso de arriba ya lo dice. -->
            <?php if (!$mensaje) { ?>
                <p class="exito">Ya tienes este libro digital en Mis compras.</p>
            <?php } ?>
            <p><a class="btn btn-primary" href="mis_compras.php">Ir a Mis compras</a></p>
        <?php } else { ?>
            <!-- Pantalla de confirmación. El formulario solo envía el id del libro. -->
            <form class="formulario" name="form_compra_digital" method="POST" action="comprar_digital.php">
                <h2>Confirmar compra</h2>
                <input type="hidden" name="libro" value="<?php echo $libro["id"]; ?>">
                <p>
                    <strong><?php echo htmlspecialchars($libro["titulo"]); ?></strong><br>
                    <?php echo htmlspecialchars($libro["autor"]); ?>
                </p>
                <p><strong>Formato:</strong> Digital</p>
                <p><strong>Precio:</strong> $ <?php echo number_format($libro["precio_digital"], 2, ",", "."); ?></p>
                <p>Es una compra simulada: no se pide ningún medio de pago.</p>
                <!-- data-enviando: texto que muestra el botón mientras se envía (lo usa main.js) -->
                <button class="btn btn-primary btn-lg" type="submit" data-enviando="Registrando la compra...">Confirmar compra</button>
                <p><a href="libro.php?id=<?php echo $libro["id"]; ?>">Cancelar</a></p>
            </form>
        <?php } ?>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
<?php
// Cierra la conexión con la base de datos al terminar la página.
mysqli_close($conexion);
?>
