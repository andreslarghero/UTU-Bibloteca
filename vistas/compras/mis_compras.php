<?php
// ============================================================
// VISTA: mis compras (CompraController::index)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

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
                <td><?php echo $fila->getId(); ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($fila->getFecha())); ?></td>
                <td><?php echo $fila->getVendedorNombre() ? htmlspecialchars($fila->getVendedorNombre()) : "Sin vendedor"; ?></td>
                <td class="sin-corte">$ <?php echo formatearPrecio($fila->getTotal()); ?></td>
                <td><a class="btn btn-primary btn-sm" href="<?php echo Ruta::h('compra', 'index', ['venta' => $fila->getId()]); ?>#detalle">Ver detalle</a></td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>

        <?php if ($pidioDetalle && !$venta) { ?>
            <!-- El mismo aviso sirve si la venta no existe o si es de otra persona:
                 así no se revela si ese número de venta existe. -->
            <p class="error">No se encontró esa compra.</p>
        <?php } ?>

        <?php if ($venta) { ?>
        <!-- Detalle de la compra elegida -->
        <section class="detalle-venta" id="detalle">
            <h3>Detalle de la compra N.º <?php echo $venta->getId(); ?></h3>
            <p>
                <strong>Fecha:</strong> <?php echo date("d/m/Y H:i", strtotime($venta->getFecha())); ?> &nbsp;
                <strong>Vendedor:</strong> <?php echo $venta->getVendedorNombre() ? htmlspecialchars($venta->getVendedorNombre()) : "Sin vendedor"; ?>
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
                    <td><?php echo htmlspecialchars($linea->getTitulo()); ?></td>
                    <!-- Formato en que se compró esa línea -->
                    <td>
                        <?php if ($linea->esDigital()) { ?>
                            <span class="badge badge-digital">Digital</span>
                        <?php } else { ?>
                            <span class="badge badge-fisico">Físico</span>
                        <?php } ?>
                    </td>
                    <td><?php echo $linea->getCantidad(); ?></td>
                    <td class="sin-corte">$ <?php echo formatearPrecio($linea->getPrecio()); ?></td>
                    <td class="sin-corte">$ <?php echo formatearPrecio($linea->getSubtotal()); ?></td>
                    <td>
                        <!-- Solo las líneas digitales se pueden descargar (las físicas no
                             muestran nada). El enlace lleva únicamente el id del libro:
                             descargar.php vuelve a comprobar que este usuario lo compró.
                             Del archivo solo se muestra el tipo (PDF o EPUB), nunca su
                             nombre ni su ubicación. -->
                        <?php if ($linea->esDigital()) { ?>
                            <?php if ($linea->tieneArchivo()) { ?>
                                <a class="btn btn-primary btn-sm sin-corte" href="<?php echo Ruta::h('compra', 'descargar', ['libro' => $linea->getLibroId()]); ?>">Descargar <?php echo ($linea->getTipoArchivo() == "EPUB") ? "EPUB" : "PDF"; ?></a>
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
                    <td class="sin-corte" colspan="2">$ <?php echo formatearPrecio($venta->getTotal()); ?></td>
                </tr>
            </table>
            </div>

            <p><a class="volver" href="<?php echo Ruta::h('compra'); ?>">&larr; Cerrar detalle</a></p>
        </section>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
