<?php
// ============================================================
// VISTA: aviso de descarga no disponible (CompraController::descargar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <h2>Descarga</h2>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <p><a class="volver" href="<?php echo Ruta::h('compra'); ?>">&larr; Ir a Mis compras</a></p>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
