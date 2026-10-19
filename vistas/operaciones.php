<?php
// ============================================================
// VISTA: operaciones
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/operaciones.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <h2>Consulta de operaciones</h2>
        <p class="descripcion-pagina">Ventas de mostrador y compras digitales registradas. Es solo de consulta.</p>

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
                <td><?php echo $fila["id"]; ?></td>
                <td><?php echo date("d/m/Y H:i", strtotime($fila["fecha"])); ?></td>
                <!-- Si la venta no tiene cliente, fue una venta de mostrador -->
                <td><?php echo $fila["cliente"] ? htmlspecialchars($fila["cliente"]) : "Mostrador"; ?></td>
                <td><?php echo $fila["vendedor"] ? htmlspecialchars($fila["vendedor"]) : "Sin vendedor"; ?></td>
                <td>$ <?php echo formatearPrecio($fila["total"]); ?></td>
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
                    <td>$ <?php echo formatearPrecio($linea["precio"]); ?></td>
                    <td>$ <?php echo formatearPrecio($linea["cantidad"] * $linea["precio"]); ?></td>
                </tr>
                <?php } ?>
                <!-- Fila final con el total de la venta -->
                <tr class="fila-total">
                    <td colspan="4">TOTAL</td>
                    <td>$ <?php echo formatearPrecio($venta["total"]); ?></td>
                </tr>
            </table>
            </div>

            <p><a class="volver" href="operaciones.php">&larr; Cerrar detalle</a></p>
        </section>
        <?php } ?>
    </main>

<?php include __DIR__ . "/parciales/pie.php"; ?>
