<?php
// ============================================================
// CAJA DEL MOSTRADOR
// Pantalla para el bibliotecario: se escanea (o se escribe) el
// código de barras de cada libro y se va armando el ticket.
// El ticket es temporal: se guarda en la sesión, no en la base.
// Al pulsar "Finalizar venta" se guarda la venta en las tablas
// ventas y venta_lineas y se descuenta el stock, todo junto en
// una transacción. Solo pueden entrar los bibliotecarios.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_bibliotecario.php";

// ------------------------------------------------------------
// STEAM (LECTOR FÍSICO CON ARDUINO)
// steam.php trae las funciones que avisan al Arduino el resultado
// de cada escaneo. Es una salida adicional: si STEAM no está
// disponible no hacen nada y la caja funciona igual.
// ------------------------------------------------------------
include "steam.php";

// ------------------------------------------------------------
// TICKET TEMPORAL
// $_SESSION["ticket"] es una lista de líneas. Cada línea guarda
// libro_id, titulo, precio y cantidad, y se identifica por el id
// del libro, así el mismo libro nunca aparece en dos líneas.
// ------------------------------------------------------------
if (!isset($_SESSION["ticket"])) {
    $_SESSION["ticket"] = [];
}

// ------------------------------------------------------------
// ACCIONES DE LA CAJA
// Se ejecuta al enviar cualquiera de los formularios de la página.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = isset($_POST["accion"]) ? $_POST["accion"] : "";

    // Aviso que se mostrará después de la acción y su tipo ("exito" o "error").
    $mensaje = "";
    $tipo = "error";

    // --- Agregar un libro por su código de barras ---
    if ($accion == "agregar") {
        $codigo = trim($_POST["codigo"]);

        if ($codigo == "") {
            $mensaje = "Escanea o escribe un código de barras.";
        } else {
            // Busca el libro por su código de barras. Solo cuentan los libros activos
            // que se venden en formato físico: la caja no vende libros digitales.
            // Se usa una consulta preparada (el "?") para evitar ataques de inyección SQL.
            $consulta = mysqli_prepare($conexion, "SELECT id, titulo, precio, stock FROM libros WHERE codigo_barras = ? AND activo = 1 AND formato_fisico = 1");
            mysqli_stmt_bind_param($consulta, "s", $codigo);
            mysqli_stmt_execute($consulta);
            $libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

            if (!$libro) {
                $mensaje = "Libro no encontrado (código " . $codigo . ").";

                // STEAM: avisa "no encontrado", salvo que el código sea de un
                // libro solo digital (el lector físico no trabaja con esos).
                if (!steam_es_solo_digital($conexion, $codigo)) {
                    steam_avisar_no_encontrado();
                }
            } elseif ($libro["stock"] == 0) {
                $mensaje = "Libro agotado: " . $libro["titulo"] . ".";

                // STEAM: avisa que el libro no tiene stock.
                steam_avisar_agotado($libro["titulo"]);
            } else {
                $id = $libro["id"];

                // Cuántas copias de este libro ya hay en el ticket.
                $en_ticket = isset($_SESSION["ticket"][$id]) ? $_SESSION["ticket"][$id]["cantidad"] : 0;

                if ($en_ticket + 1 > $libro["stock"]) {
                    // El ticket no puede tener más copias que el stock real.
                    $mensaje = "No hay más stock disponible de " . $libro["titulo"] . " (stock: " . $libro["stock"] . ").";
                } else {
                    // Si el libro ya estaba, sube la cantidad; si no, crea la línea.
                    // El precio se toma siempre de la base de datos.
                    $_SESSION["ticket"][$id] = [
                        "libro_id" => $id,
                        "titulo" => $libro["titulo"],
                        "precio" => $libro["precio"],
                        "cantidad" => $en_ticket + 1
                    ];
                    $mensaje = "Agregado: " . $libro["titulo"] . ".";
                    $tipo = "exito";

                    // STEAM: muestra el título y el precio real del libro.
                    steam_avisar_encontrado($libro["titulo"], $libro["precio"]);
                }
            }
        }
    }

    // --- Quitar una unidad de una línea ---
    if ($accion == "quitar") {
        $id = (int) $_POST["libro_id"];

        if (isset($_SESSION["ticket"][$id])) {
            $_SESSION["ticket"][$id]["cantidad"] = $_SESSION["ticket"][$id]["cantidad"] - 1;

            // Si la línea se queda sin unidades, se elimina.
            if ($_SESSION["ticket"][$id]["cantidad"] <= 0) {
                unset($_SESSION["ticket"][$id]);
            }
        }
    }

    // --- Eliminar una línea completa ---
    if ($accion == "eliminar") {
        $id = (int) $_POST["libro_id"];
        unset($_SESSION["ticket"][$id]);
    }

    // --- Vaciar todo el ticket ---
    if ($accion == "vaciar") {
        $_SESSION["ticket"] = [];
    }

    // --- Finalizar la venta ---
    // Guarda la venta, sus líneas y descuenta el stock dentro de una
    // transacción: o se guarda todo (COMMIT) o no se guarda nada (ROLLBACK).
    // Es una venta de mostrador: sin cliente y con el bibliotecario como vendedor.
    if ($accion == "finalizar") {
        if (count($_SESSION["ticket"]) == 0) {
            $mensaje = "El ticket está vacío.";
        } else {
            // Se ordena el ticket por id de libro para que todas las ventas
            // bloqueen los libros en el mismo orden.
            $ticket = $_SESSION["ticket"];
            ksort($ticket);

            $error = "";       // Si queda vacío, todo salió bien
            $total = 0;        // Total recalculado con los precios de la base
            $lineas = [];      // Líneas definitivas de la venta
            $venta_id = 0;
            $vendedor = $_SESSION["id"];

            try {
                mysqli_begin_transaction($conexion);

                // 1. Vuelve a consultar cada libro del ticket en la base, sin
                //    confiar en lo guardado en la sesión. "FOR UPDATE" bloquea
                //    esas filas hasta terminar la transacción, para que otra
                //    venta no pueda gastar el mismo stock al mismo tiempo.
                $consulta = mysqli_prepare($conexion, "SELECT titulo, precio, stock, activo, formato_fisico FROM libros WHERE id = ? FOR UPDATE");

                foreach ($ticket as $linea) {
                    $libro_id = $linea["libro_id"];
                    $cantidad = $linea["cantidad"];

                    mysqli_stmt_bind_param($consulta, "i", $libro_id);
                    mysqli_stmt_execute($consulta);
                    $libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

                    if (!$libro || $libro["activo"] != 1 || $libro["formato_fisico"] != 1) {
                        // El libro se borró, se dio de baja o dejó de venderse
                        // en formato físico después de escanearlo.
                        $error = "Uno de los libros ya no está disponible: " . $linea["titulo"] . ".";
                        break;
                    }

                    if ($libro["stock"] < $cantidad) {
                        $error = "Stock insuficiente para " . $libro["titulo"] . " (quedan " . $libro["stock"] . ").";
                        break;
                    }

                    // El precio definitivo es el que tiene el libro en la base en este momento.
                    $lineas[] = [
                        "libro_id" => $libro_id,
                        "titulo" => $libro["titulo"],
                        "cantidad" => $cantidad,
                        "precio" => $libro["precio"]
                    ];
                    $total = $total + $libro["precio"] * $cantidad;
                }

                // 2. Guarda la venta (la fecha la pone sola la base).
                if ($error == "") {
                    $total = round($total, 2);
                    $insertar = mysqli_prepare($conexion, "INSERT INTO ventas (id_cliente, id_vendedor, total) VALUES (NULL, ?, ?)");
                    mysqli_stmt_bind_param($insertar, "id", $vendedor, $total);

                    if (mysqli_stmt_execute($insertar)) {
                        // Número de la venta recién creada.
                        $venta_id = mysqli_insert_id($conexion);
                    } else {
                        $error = "No se pudo registrar la venta.";
                    }
                }

                // 3. Guarda una línea por cada libro y descuenta su stock.
                if ($error == "") {
                    // Todo lo que se vende en la caja es formato físico.
                    $insertar_linea = mysqli_prepare($conexion, "INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, ?, ?, 'fisico')");

                    // "AND stock >= ?" hace que el stock nunca pueda quedar negativo:
                    // si no alcanza, la fila no se modifica.
                    $descontar = mysqli_prepare($conexion, "UPDATE libros SET stock = stock - ? WHERE id = ? AND stock >= ?");

                    foreach ($lineas as $l) {
                        mysqli_stmt_bind_param($insertar_linea, "iiid", $venta_id, $l["libro_id"], $l["cantidad"], $l["precio"]);

                        if (!mysqli_stmt_execute($insertar_linea)) {
                            $error = "No se pudo registrar la venta.";
                            break;
                        }

                        mysqli_stmt_bind_param($descontar, "iii", $l["cantidad"], $l["libro_id"], $l["cantidad"]);

                        if (!mysqli_stmt_execute($descontar)) {
                            $error = "No se pudo registrar la venta.";
                            break;
                        }

                        // Si no se modificó ninguna fila es que el stock ya no alcanzaba.
                        if (mysqli_stmt_affected_rows($descontar) != 1) {
                            $error = "Stock insuficiente para " . $l["titulo"] . ".";
                            break;
                        }
                    }
                }

                // 4. Si todo salió bien se confirma; si algo falló se deshace todo.
                if ($error == "") {
                    if (!mysqli_commit($conexion)) {
                        $error = "No se pudo registrar la venta.";
                    }
                } else {
                    mysqli_rollback($conexion);
                }
            } catch (Throwable $e) {
                // Cualquier error inesperado de la base: se deshace todo y se
                // muestra un aviso general, sin detalles internos de MySQL.
                mysqli_rollback($conexion);
                $error = "No se pudo registrar la venta.";
            }

            if ($error == "") {
                // Venta guardada: se vacía el ticket y se avisa con el número y el total.
                $_SESSION["ticket"] = [];
                $mensaje = "Venta registrada correctamente. Venta N.º " . $venta_id . ". Total vendido: $ " . number_format($total, 2, ",", ".") . ".";
                $tipo = "exito";
            } else {
                // El ticket se conserva para poder corregirlo.
                $mensaje = $error;
            }
        }
    }

    // Guarda el aviso en la sesión y recarga la página. Así, si se
    // refresca el navegador, no se repite el último escaneo.
    $_SESSION["caja_mensaje"] = $mensaje;
    $_SESSION["caja_tipo"] = $tipo;
    header("Location: caja.php");
    exit;
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
$mensaje = isset($_SESSION["caja_mensaje"]) ? $_SESSION["caja_mensaje"] : "";
$tipo = isset($_SESSION["caja_tipo"]) ? $_SESSION["caja_tipo"] : "error";
unset($_SESSION["caja_mensaje"], $_SESSION["caja_tipo"]);

// ------------------------------------------------------------
// TOTAL DEL TICKET
// Suma el subtotal (precio x cantidad) de todas las líneas.
// ------------------------------------------------------------
$total = 0;
foreach ($_SESSION["ticket"] as $linea) {
    $total = $total + $linea["precio"] * $linea["cantidad"];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

    <main>
        <h2>Caja del mostrador</h2>
        <p class="descripcion-pagina">Escanea cada libro físico para armar el ticket y finaliza la venta.</p>

        <!-- Campo de escaneo. El lector de códigos de barras funciona como un
             teclado: escribe el código y pulsa Enter, lo que envía el formulario.
             "autofocus" deja el cursor listo en el campo al cargar la página. -->
        <form class="form-caja" name="form_caja" method="POST" action="caja.php">
            <input type="hidden" name="accion" value="agregar">
            <label for="codigo">Código de barras</label>
            <input type="text" id="codigo" name="codigo" placeholder="Escanea o escribe el código y pulsa Enter" autofocus autocomplete="off">
            <button class="btn btn-primary btn-lg" type="submit">Agregar</button>
        </form>

        <!-- Aviso de la última acción: verde si se agregó, rojo si hubo un problema -->
        <?php if ($mensaje) { ?>
            <p class="<?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <h3 class="titulo-ticket">Ticket</h3>

        <?php if (count($_SESSION["ticket"]) == 0) { ?>
            <!-- Aviso cuando todavía no se escaneó ningún libro -->
            <p class="sin-resultados">El ticket está vacío. Escanea un libro para empezar.</p>

            <!-- Sin libros en el ticket no se puede finalizar: el botón queda deshabilitado -->
            <div class="acciones-ticket">
                <span></span>
                <button class="btn btn-success btn-lg" type="button" disabled>Finalizar venta</button>
            </div>
        <?php } else { ?>
        <!-- Tabla del ticket: una fila por cada libro escaneado -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla-stock">
            <tr>
                <th>Libro</th>
                <th>Precio</th>
                <th>Cantidad</th>
                <th>Subtotal</th>
                <th></th>
            </tr>
            <!-- data-fila y data-cantidad: main.js los usa para animar la fila que se agregó -->
            <?php foreach ($_SESSION["ticket"] as $linea) { ?>
            <tr data-fila="<?php echo (int) $linea["libro_id"]; ?>" data-cantidad="<?php echo (int) $linea["cantidad"]; ?>">
                <td><?php echo htmlspecialchars($linea["titulo"]); ?></td>
                <td>$ <?php echo number_format($linea["precio"], 2, ",", "."); ?></td>
                <td><?php echo $linea["cantidad"]; ?></td>
                <td>$ <?php echo number_format($linea["precio"] * $linea["cantidad"], 2, ",", "."); ?></td>
                <td>
                    <div class="acciones-linea">
                        <!-- Quita una sola unidad de esta línea -->
                        <form name="form_caja_quitar_<?php echo (int) $linea["libro_id"]; ?>" method="POST" action="caja.php">
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="libro_id" value="<?php echo $linea["libro_id"]; ?>">
                            <button class="btn btn-secondary btn-sm" type="submit">Quitar 1</button>
                        </form>
                        <!-- Elimina la línea completa -->
                        <form name="form_caja_eliminar_<?php echo (int) $linea["libro_id"]; ?>" method="POST" action="caja.php">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="libro_id" value="<?php echo $linea["libro_id"]; ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php } ?>
            <!-- Fila final con el total del ticket -->
            <tr class="fila-total">
                <td colspan="3">TOTAL</td>
                <td colspan="2">$ <?php echo number_format($total, 2, ",", "."); ?></td>
            </tr>
        </table>
        </div>

        <!-- Acciones sobre el ticket completo -->
        <div class="acciones-ticket">
            <!-- data-confirmar: main.js pregunta antes de vaciar el ticket -->
            <form name="form_caja_vaciar" method="POST" action="caja.php" data-confirmar="¿Vaciar el ticket? Se quitan todos los libros escaneados.">
                <input type="hidden" name="accion" value="vaciar">
                <button class="btn btn-danger" type="submit">Vaciar ticket</button>
            </form>
            <!-- Total del ticket, bien visible al lado del botón de finalizar.
                 data-total: el valor sin formato, para que main.js anime el número. -->
            <p class="total-caja">Total a cobrar <strong data-total="<?php echo number_format($total, 2, ".", ""); ?>">$ <?php echo number_format($total, 2, ",", "."); ?></strong></p>
            <!-- Guarda la venta en la base y descuenta el stock -->
            <form name="form_caja_finalizar" method="POST" action="caja.php">
                <input type="hidden" name="accion" value="finalizar">
                <button class="btn btn-success btn-lg" type="submit">Finalizar venta</button>
            </form>
        </div>
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
