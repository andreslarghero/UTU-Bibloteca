<?php
// ============================================================
// VISTA: consulta de operaciones (OperacionController::index)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <h2>Consulta de operaciones</h2>
        <p class="descripcion-pagina">Ventas de mostrador y compras por la web (físicas y digitales) registradas. Es solo de consulta.</p>

        <?php if (count($ventas) == 0) { ?>
            <!-- Aviso si todavía no se registró ninguna venta -->
            <p class="sin-resultados">Todavía no hay ventas registradas.</p>
        <?php } else { ?>
        <!-- Tabla con todas las ventas -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>N.º de venta</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Total</th>
                <th></th>
            </tr>
            <!-- Una fila por cada venta -->
            <?php foreach ($ventas as $fila) { ?>
            <tr>
                <td><?php echo $fila->getId(); ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($fila->getFecha())); ?></td>
                <!-- Si la venta no tiene cliente, fue una venta de mostrador -->
                <td><?php echo $fila->getClienteNombre() ? htmlspecialchars($fila->getClienteNombre()) : "Mostrador"; ?></td>
                <td><?php echo $fila->getVendedorNombre() ? htmlspecialchars($fila->getVendedorNombre()) : "Compra online"; ?></td>
                <td>$ <?php echo formatearPrecio($fila->getTotal()); ?></td>
                <td><a class="btn btn-primary btn-sm" href="<?php echo Ruta::h('operacion', 'index', ['venta' => $fila->getId()]); ?>#detalle">Ver detalle</a></td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>

        <?php if ($ventaId > 0 && !$venta) { ?>
            <!-- Aviso si el número de venta de la dirección no existe -->
            <p class="error">La venta N.º <?php echo $ventaId; ?> no existe.</p>
        <?php } ?>

        <?php if ($venta) { ?>
        <!-- Detalle de la venta elegida -->
        <section class="detalle-venta" id="detalle">
            <h3>Detalle de la venta N.º <?php echo $venta->getId(); ?></h3>
            <p>
                <strong>Fecha:</strong> <?php echo date("d/m/Y H:i", strtotime($venta->getFecha())); ?> &nbsp;
                <strong>Cliente:</strong> <?php echo $venta->getClienteNombre() ? htmlspecialchars($venta->getClienteNombre()) : "Mostrador"; ?> &nbsp;
                <strong>Vendedor:</strong> <?php echo $venta->getVendedorNombre() ? htmlspecialchars($venta->getVendedorNombre()) : "Compra online"; ?>
            </p>

            <!-- Libros vendidos: el subtotal es cantidad x precio unitario -->
            <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
            <div class="tabla-scroll">
            <table class="tabla">
                <tr>
                    <th>Libro</th>
                    <th>Formato</th>
                    <th>Cantidad</th>
                    <th>Precio unitario</th>
                    <th>Subtotal</th>
                </tr>
                <?php foreach ($lineas as $linea) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($linea->getTitulo()); ?></td>
                    <!-- Formato en que se vendió esa línea -->
                    <td>
                        <?php if ($linea->esDigital()) { ?>
                            <span class="badge badge-digital">Digital</span>
                        <?php } else { ?>
                            <span class="badge badge-fisico">Físico</span>
                        <?php } ?>
                    </td>
                    <td><?php echo $linea->getCantidad(); ?></td>
                    <td>$ <?php echo formatearPrecio($linea->getPrecio()); ?></td>
                    <td>$ <?php echo formatearPrecio($linea->getSubtotal()); ?></td>
                </tr>
                <?php } ?>
                <!-- Fila final con el total de la venta -->
                <tr class="fila-total">
                    <td colspan="4">TOTAL</td>
                    <td>$ <?php echo formatearPrecio($venta->getTotal()); ?></td>
                </tr>
            </table>
            </div>

            <p><a class="volver" href="<?php echo Ruta::h('operacion'); ?>">&larr; Cerrar detalle</a></p>
        </section>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
