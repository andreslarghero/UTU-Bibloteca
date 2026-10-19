<?php
// ============================================================
// VISTA: caja
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/caja.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

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
        <table class="tabla">
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
                <td>$ <?php echo formatearPrecio($linea["precio"]); ?></td>
                <td><?php echo $linea["cantidad"]; ?></td>
                <td>$ <?php echo formatearPrecio($linea["precio"] * $linea["cantidad"]); ?></td>
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
                <td colspan="2">$ <?php echo formatearPrecio($total); ?></td>
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
            <p class="total-caja">Total a cobrar <strong data-total="<?php echo number_format($total, 2, ".", ""); ?>">$ <?php echo formatearPrecio($total); ?></strong></p>
            <!-- Guarda la venta en la base y descuenta el stock -->
            <form class="form-finalizar" name="form_caja_finalizar" method="POST" action="caja.php">
                <input type="hidden" name="accion" value="finalizar">
                <!-- Opcional: con el correo del cliente, la compra aparece en su "Mis compras" -->
                <label for="correo_cliente">Correo del cliente (opcional)</label>
                <input type="email" id="correo_cliente" name="correo_cliente" maxlength="150" autocomplete="off" placeholder="cliente@correo.com">
                <button class="btn btn-success btn-lg" type="submit">Finalizar venta</button>
            </form>
        </div>
        <?php } ?>
    </main>

<?php include __DIR__ . "/parciales/pie.php"; ?>
