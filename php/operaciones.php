<?php
// ============================================================
// CONSULTA DE OPERACIONES
// Página para el bibliotecario: lista las ventas registradas y
// permite ver el detalle de cada una (qué libros se vendieron).
// Es SOLO de consulta: no modifica ni borra nada de la base.
// Solo pueden entrar los bibliotecarios.
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
// LISTA DE VENTAS
// Trae todas las ventas, de la más nueva a la más antigua, con
// el nombre del cliente y del vendedor. La tabla usuarios se usa
// dos veces (una para cada uno), por eso lleva dos nombres.
// LEFT JOIN permite incluir las ventas sin cliente (de mostrador).
// ------------------------------------------------------------
$ventas = mysqli_query($conexion,
    "SELECT ventas.id, ventas.fecha, ventas.total,
            clientes.nombre AS cliente, vendedores.nombre AS vendedor
     FROM ventas
     LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
     LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
     ORDER BY ventas.fecha DESC, ventas.id DESC");

// ------------------------------------------------------------
// DETALLE DE UNA VENTA
// Si en la dirección viene ?venta=NÚMERO, se buscan esa venta y
// sus líneas. (int) convierte el valor a número para evitar datos
// no válidos, y se usan consultas preparadas (el "?").
// ------------------------------------------------------------
$venta_id = isset($_GET["venta"]) ? (int) $_GET["venta"] : 0;
$venta = null;
$lineas = null;

if ($venta_id > 0) {
    // Datos generales de la venta elegida.
    $consulta = mysqli_prepare($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total,
                clientes.nombre AS cliente, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         WHERE ventas.id = ?");
    mysqli_stmt_bind_param($consulta, "i", $venta_id);
    mysqli_stmt_execute($consulta);
    $venta = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

    if ($venta) {
        // Libros vendidos en esa venta, con el precio al que se vendieron.
        $consulta = mysqli_prepare($conexion,
            "SELECT libros.titulo, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato
             FROM venta_lineas
             JOIN libros ON libros.id = venta_lineas.libro_id
             WHERE venta_lineas.venta_id = ?
             ORDER BY venta_lineas.id");
        mysqli_stmt_bind_param($consulta, "i", $venta_id);
        mysqli_stmt_execute($consulta);
        $lineas = mysqli_stmt_get_result($consulta);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operaciones - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

    <main>
        <h2>Consulta de operaciones</h2>
        <p class="descripcion-pagina">Ventas de mostrador y compras digitales registradas. Es solo de consulta.</p>

        <?php if (mysqli_num_rows($ventas) == 0) { ?>
            <!-- Aviso si todavía no se registró ninguna venta -->
            <p class="sin-resultados">Todavía no hay ventas registradas.</p>
        <?php } else { ?>
        <!-- Tabla con todas las ventas -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla-stock">
            <tr>
                <th>N.º de venta</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Total</th>
                <th></th>
            </tr>
            <!-- Una fila por cada venta -->
            <?php while ($fila = mysqli_fetch_assoc($ventas)) { ?>
            <tr>
                <td><?php echo $fila["id"]; ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($fila["fecha"])); ?></td>
                <!-- Si la venta no tiene cliente, fue una venta de mostrador -->
                <td><?php echo $fila["cliente"] ? htmlspecialchars($fila["cliente"]) : "Mostrador"; ?></td>
                <td><?php echo $fila["vendedor"] ? htmlspecialchars($fila["vendedor"]) : "Sin vendedor"; ?></td>
                <td>$ <?php echo number_format($fila["total"], 2, ",", "."); ?></td>
                <td><a class="btn btn-primary btn-sm" href="operaciones.php?venta=<?php echo $fila["id"]; ?>#detalle">Ver detalle</a></td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>

        <?php if ($venta_id > 0 && !$venta) { ?>
            <!-- Aviso si el número de venta de la dirección no existe -->
            <p class="error">La venta N.º <?php echo $venta_id; ?> no existe.</p>
        <?php } ?>

        <?php if ($venta) { ?>
        <!-- Detalle de la venta elegida -->
        <section class="detalle-venta" id="detalle">
            <h3>Detalle de la venta N.º <?php echo $venta["id"]; ?></h3>
            <p>
                <strong>Fecha:</strong> <?php echo date("d/m/Y H:i", strtotime($venta["fecha"])); ?> &nbsp;
                <strong>Cliente:</strong> <?php echo $venta["cliente"] ? htmlspecialchars($venta["cliente"]) : "Mostrador"; ?> &nbsp;
                <strong>Vendedor:</strong> <?php echo $venta["vendedor"] ? htmlspecialchars($venta["vendedor"]) : "Sin vendedor"; ?>
            </p>

            <!-- Libros vendidos: el subtotal es cantidad x precio unitario -->
            <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
            <div class="tabla-scroll">
            <table class="tabla-stock">
                <tr>
                    <th>Libro</th>
                    <th>Formato</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Subtotal</th>
                </tr>
                <?php while ($linea = mysqli_fetch_assoc($lineas)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($linea["titulo"]); ?></td>
                    <!-- Formato en que se vendió esa línea -->
                    <td>
                        <?php if ($linea["formato"] == "digital") { ?>
                            <span class="badge badge-digital">Digital</span>
                        <?php } else { ?>
                            <span class="badge badge-fisico">Físico</span>
                        <?php } ?>
                    </td>
                    <td><?php echo $linea["cantidad"]; ?></td>
                    <td>$ <?php echo number_format($linea["precio"], 2, ",", "."); ?></td>
                    <td>$ <?php echo number_format($linea["cantidad"] * $linea["precio"], 2, ",", "."); ?></td>
                </tr>
                <?php } ?>
                <!-- Fila final con el total de la venta -->
                <tr class="fila-total">
                    <td colspan="4">TOTAL</td>
                    <td>$ <?php echo number_format($venta["total"], 2, ",", "."); ?></td>
                </tr>
            </table>
            </div>

            <p><a class="volver" href="operaciones.php">&larr; Cerrar detalle</a></p>
        </section>
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
