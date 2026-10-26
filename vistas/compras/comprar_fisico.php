<?php
// ============================================================
// VISTA: comprar un libro físico (CompraController::comprarFisico)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="<?php echo Ruta::h('libro'); ?>">&larr; Volver al catálogo</a>

        <h2>Comprar libro físico</h2>

        <!-- Aviso de la última acción: verde si salió bien, rojo si hubo un problema -->
        <?php if ($mensaje) { ?>
            <p class="<?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <?php if (!$libro) { ?>
            <!-- El libro no existe, está dado de baja o no se vende en papel.
                 Si ya se mostró un aviso arriba, no se repite. -->
            <?php if (!$mensaje) { ?>
                <p class="error">Este libro no está disponible en formato físico.</p>
            <?php } ?>
        <?php } elseif ($libro->getStock() < 1) { ?>
            <p class="error"><?php echo htmlspecialchars($libro->getTitulo()); ?> está agotado por ahora.</p>
            <p><a class="btn btn-primary" href="<?php echo Ruta::h('libro', 'ver', ['id' => $libro->getId()]); ?>">Volver al libro</a></p>
        <?php } else { ?>
            <!-- Pantalla de confirmación. El formulario envía el id del libro y la
                 cantidad; el precio y el stock se vuelven a leer de la base al comprar. -->
            <form class="formulario" name="form_compra_fisica" method="POST" action="<?php echo Ruta::h('compra', 'confirmarFisico'); ?>">
                <h2>Confirmar compra</h2>
                <input type="hidden" name="libro" value="<?php echo $libro->getId(); ?>">
                <p>
                    <strong><?php echo htmlspecialchars($libro->getTitulo()); ?></strong><br>
                    <?php echo htmlspecialchars($libro->getAutor()); ?>
                </p>
                <p><strong>Formato:</strong> Físico (se retira en el mostrador)</p>
                <p><strong>Precio por copia:</strong> $ <?php echo formatearPrecio($libro->getPrecio()); ?></p>
                <p><strong>Copias disponibles:</strong> <?php echo $libro->getStock(); ?></p>
                <p>
                    <label for="cantidad"><strong>Cantidad:</strong></label>
                    <input type="number" id="cantidad" name="cantidad" value="1" min="1" max="<?php echo $libro->getStock(); ?>" required>
                </p>
                <p>Es una compra simulada: no se pide ningún medio de pago.</p>
                <!-- data-enviando: texto que muestra el botón mientras se envía (lo usa main.js) -->
                <button class="btn btn-primary btn-lg" type="submit" data-enviando="Registrando la compra...">Confirmar compra</button>
                <p><a href="<?php echo Ruta::h('libro', 'ver', ['id' => $libro->getId()]); ?>">Cancelar</a></p>
            </form>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
