<?php
// ============================================================
// VISTA: comprar un libro digital (CompraController::comprar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="<?php echo Ruta::h('libro'); ?>">&larr; Volver al catálogo</a>

        <h2>Comprar libro digital</h2>

        <!-- Aviso de la última acción: verde si salió bien, rojo si hubo un problema -->
        <?php if ($mensaje) { ?>
            <p class="<?php echo $tipo; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <?php if (!$libro) { ?>
            <!-- El libro no existe, está dado de baja o no se vende en digital.
                 Si ya se mostró un aviso arriba, no se repite. -->
            <?php if (!$mensaje) { ?>
                <p class="error">Este libro no está disponible en formato digital.</p>
            <?php } ?>
        <?php } elseif ($yaComprado) { ?>
            <!-- El usuario ya tiene este libro: no se le ofrece comprarlo de nuevo.
                 Si viene de comprarlo recién, el aviso de arriba ya lo dice. -->
            <?php if (!$mensaje) { ?>
                <p class="exito">Ya tienes este libro digital en Mis compras.</p>
            <?php } ?>
            <p><a class="btn btn-primary" href="<?php echo Ruta::h('compra'); ?>">Ir a Mis compras</a></p>
        <?php } else { ?>
            <!-- Pantalla de confirmación. El formulario solo envía el id del libro. -->
            <form class="formulario" name="form_compra_digital" method="POST" action="<?php echo Ruta::h('compra', 'confirmar'); ?>">
                <h2>Confirmar compra</h2>
                <input type="hidden" name="libro" value="<?php echo $libro->getId(); ?>">
                <p>
                    <strong><?php echo htmlspecialchars($libro->getTitulo()); ?></strong><br>
                    <?php echo htmlspecialchars($libro->getAutor()); ?>
                </p>
                <p><strong>Formato:</strong> Digital</p>
                <p><strong>Precio:</strong> $ <?php echo formatearPrecio($libro->getPrecioDigital()); ?></p>
                <p>Es una compra simulada: no se pide ningún medio de pago.</p>
                <!-- data-enviando: texto que muestra el botón mientras se envía (lo usa main.js) -->
                <button class="btn btn-primary btn-lg" type="submit" data-enviando="Registrando la compra...">Confirmar compra</button>
                <p><a href="<?php echo Ruta::h('libro', 'ver', ['id' => $libro->getId()]); ?>">Cancelar</a></p>
            </form>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
