<?php
// ============================================================
// VISTA: mis_compras
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/mis_compras.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <h2>Mis compras</h2>
        <p class="descripcion-pagina">Historial de tus compras. Los libros digitales se descargan desde el detalle de cada compra.</p>

        <?php if (count($compras) == 0) { ?>
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
            <?php foreach ($compras as $fila) { ?>
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
                <?php foreach ($lineas as $linea) { ?>
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

<?php include __DIR__ . "/parciales/pie.php"; ?>
