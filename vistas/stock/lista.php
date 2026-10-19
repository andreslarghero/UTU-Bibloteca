<?php
// ============================================================
// VISTA: gestión de stock (StockController::index y ::guardar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <h2>Gestión de stock</h2>
        <p class="descripcion-pagina">Copias disponibles de los libros físicos. Los libros digitales no usan stock.</p>

        <!-- Aviso verde después de guardar un cambio -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <!-- Tabla con todos los libros y su stock -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>Portada</th>
                <th>Libro</th>
                <th>Estado</th>
                <th>Copias disponibles</th>
            </tr>
            <!-- Una fila por cada libro -->
            <?php foreach ($libros as $fila) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién guardada -->
            <tr data-fila="<?php echo $fila->getId(); ?>">
                <td>
                    <!-- Miniatura de la portada (o su reemplazo si no tiene imagen) -->
                    <?php echo portada_html($fila->getTitulo(), $fila->getPortada()); ?>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($fila->getTitulo()); ?></strong><br>
                    <?php echo htmlspecialchars($fila->getAutor()); ?>
                </td>
                <td>
                    <!-- Etiqueta verde si hay copias, roja si está agotado -->
                    <?php if ($fila->getStock() > 0) { ?>
                        <span class="disponible">Disponible</span>
                    <?php } else { ?>
                        <span class="agotado">Agotado</span>
                    <?php } ?>
                </td>
                <td>
                    <!-- Formulario para cambiar el stock de este libro -->
                    <!-- La etiqueta del campo no se ve en pantalla (clase "solo-lectores"),
                         pero la leen los lectores de pantalla. El id lleva el número
                         del libro para que no se repita entre filas. -->
                    <form class="form-stock" name="form_stock_<?php echo $fila->getId(); ?>" method="POST" action="<?php echo Ruta::h('stock', 'guardar'); ?>">
                        <input type="hidden" name="id" value="<?php echo $fila->getId(); ?>">
                        <label class="solo-lectores" for="stock_<?php echo $fila->getId(); ?>">Copias disponibles de <?php echo htmlspecialchars($fila->getTitulo()); ?></label>
                        <input type="number" id="stock_<?php echo $fila->getId(); ?>" name="stock" min="0" value="<?php echo $fila->getStock(); ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
