<?php
// ============================================================
// MIS COMPRAS
// Página para cualquier usuario con sesión iniciada: muestra el
// historial de SUS compras y el detalle de cada una. Los libros
// comprados en formato digital tienen un enlace "Descargar".
// Es solo de consulta: no modifica nada de la base.
// Cada usuario ve únicamente las ventas en las que él es el
// cliente. Una venta de mostrador aparece solo si el bibliotecario
// indicó el correo del cliente al finalizarla; si no, queda sin cliente.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_usuario.php";

// El cliente cuyas compras se muestran es SIEMPRE el usuario de la
// sesión. El id nunca se toma de la dirección ni de un formulario.
$id_cliente = $_SESSION["id"];

// ------------------------------------------------------------
// LISTA DE COMPRAS
// Solo las ventas de este cliente, de la más nueva a la más antigua,
// con el nombre del vendedor (LEFT JOIN por si la venta no tiene).
// ------------------------------------------------------------
$consulta = mysqli_prepare($conexion,
    "SELECT ventas.id, ventas.fecha, ventas.total, vendedores.nombre AS vendedor
     FROM ventas
     LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
     WHERE ventas.id_cliente = ?
     ORDER BY ventas.fecha DESC, ventas.id DESC");
mysqli_stmt_bind_param($consulta, "i", $id_cliente);
mysqli_stmt_execute($consulta);
$compras = mysqli_stmt_get_result($consulta);

// ------------------------------------------------------------
// DETALLE DE UNA COMPRA
// Si en la dirección viene ?venta=NÚMERO, se busca esa venta, pero
// solo si pertenece a este cliente: la consulta pide a la vez el
// número de venta Y el id del cliente de la sesión. Así, aunque
// alguien escriba a mano el número de una venta ajena, no la ve.
// Solo se acepta un valor formado por dígitos.
// ------------------------------------------------------------
$venta_id = entero_de($_GET["venta"] ?? null);

// Indica si en la dirección se pidió alguna venta (aunque el valor no sea válido).
$pidio_detalle = isset($_GET["venta"]);
$venta = null;
$lineas = null;

if ($venta_id > 0) {
    // Datos generales de la compra elegida (solo si es de este cliente).
    $venta = buscar_fila($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         WHERE ventas.id = ? AND ventas.id_cliente = ?",
        "ii", $venta_id, $id_cliente);

    if ($venta) {
        // Libros de esa compra, con el precio al que se vendieron (el de
        // venta_lineas, no el precio actual del libro). También aquí se
        // exige que la venta sea de este cliente.
        $consulta = mysqli_prepare($conexion,
            "SELECT libros.titulo, venta_lineas.libro_id, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato,
                    libros.archivo_digital IS NOT NULL AS tiene_archivo,
                    UPPER(SUBSTRING_INDEX(libros.archivo_digital, '.', -1)) AS tipo_archivo
             FROM venta_lineas
             JOIN ventas ON ventas.id = venta_lineas.venta_id
             JOIN libros ON libros.id = venta_lineas.libro_id
             WHERE venta_lineas.venta_id = ? AND ventas.id_cliente = ?
             ORDER BY venta_lineas.id");
        mysqli_stmt_bind_param($consulta, "ii", $venta_id, $id_cliente);
        mysqli_stmt_execute($consulta);
        $lineas = mysqli_stmt_get_result($consulta);
    }
}
?>
<?php
$titulo_pagina = "Mis compras";
include "cabecera.php";
?>

    <main>
        <h2>Mis compras</h2>
        <p class="descripcion-pagina">Historial de tus compras. Los libros digitales se descargan desde el detalle de cada compra.</p>

        <?php if (mysqli_num_rows($compras) == 0) { ?>
            <!-- Aviso si el usuario todavía no compró nada -->
            <p class="sin-resultados">Todavía no tienes compras registradas.</p>
        <?php } else { ?>
        <!-- Tabla con las compras del usuario. El contenedor "tabla-scroll" hace
             que en un móvil la tabla se desplace de lado sin ensanchar la página. -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>N.º de venta</th>
                <th>Fecha</th>
                <th>Vendedor</th>
                <th>Total</th>
                <th></th>
            </tr>
            <!-- Una fila por cada compra -->
            <?php while ($fila = mysqli_fetch_assoc($compras)) { ?>
            <tr>
                <td><?php echo $fila["id"]; ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($fila["fecha"])); ?></td>
                <td><?php echo $fila["vendedor"] ? htmlspecialchars($fila["vendedor"]) : "Sin vendedor"; ?></td>
                <td class="sin-corte">$ <?php echo formatearPrecio($fila["total"]); ?></td>
                <td><a class="btn btn-primary btn-sm" href="mis_compras.php?venta=<?php echo $fila["id"]; ?>#detalle">Ver detalle</a></td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>

        <?php if ($pidio_detalle && !$venta) { ?>
            <!-- El mismo aviso sirve si la venta no existe o si es de otra persona:
                 así no se revela si ese número de venta existe. -->
            <p class="error">No se encontró esa compra.</p>
        <?php } ?>

        <?php if ($venta) { ?>
        <!-- Detalle de la compra elegida -->
        <section class="detalle-venta" id="detalle">
            <h3>Detalle de la compra N.º <?php echo $venta["id"]; ?></h3>
            <p>
                <strong>Fecha:</strong> <?php echo date("d/m/Y H:i", strtotime($venta["fecha"])); ?> &nbsp;
                <strong>Vendedor:</strong> <?php echo $venta["vendedor"] ? htmlspecialchars($venta["vendedor"]) : "Sin vendedor"; ?>
            </p>

            <!-- Libros comprados: el subtotal es cantidad x precio unitario -->
            <div class="tabla-scroll">
            <table class="tabla">
                <tr>
                    <th>Libro</th>
                    <th>Formato</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
                <?php while ($linea = mysqli_fetch_assoc($lineas)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($linea["titulo"]); ?></td>
                    <!-- Formato en que se compró esa línea -->
                    <td>
                        <?php if ($linea["formato"] == "digital") { ?>
                            <span class="badge badge-digital">Digital</span>
                        <?php } else { ?>
                            <span class="badge badge-fisico">Físico</span>
                        <?php } ?>
                    </td>
                    <td><?php echo $linea["cantidad"]; ?></td>
                    <td class="sin-corte">$ <?php echo formatearPrecio($linea["precio"]); ?></td>
                    <td class="sin-corte">$ <?php echo formatearPrecio($linea["cantidad"] * $linea["precio"]); ?></td>
                    <td>
                        <!-- Solo las líneas digitales se pueden descargar (las físicas no
                             muestran nada). El enlace lleva únicamente el id del libro:
                             descargar.php vuelve a comprobar que este usuario lo compró.
                             Del archivo solo se muestra el tipo (PDF o EPUB), nunca su
                             nombre ni su ubicación. -->
                        <?php if ($linea["formato"] == "digital") { ?>
                            <?php if ($linea["tiene_archivo"]) { ?>
                                <a class="btn btn-primary btn-sm sin-corte" href="descargar.php?libro=<?php echo $linea["libro_id"]; ?>">Descargar <?php echo ($linea["tipo_archivo"] == "EPUB") ? "EPUB" : "PDF"; ?></a>
                            <?php } else { ?>
                                No disponible
                            <?php } ?>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
                <!-- Fila final con el total de la compra -->
                <tr class="fila-total">
                    <td colspan="4">TOTAL</td>
                    <td class="sin-corte" colspan="2">$ <?php echo formatearPrecio($venta["total"]); ?></td>
                </tr>
            </table>
            </div>

            <p><a class="volver" href="mis_compras.php">&larr; Cerrar detalle</a></p>
        </section>
        <?php } ?>
    </main>

<?php include "pie.php"; ?>
