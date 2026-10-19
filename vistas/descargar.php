<?php
// ============================================================
// VISTA: descargar
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/descargar.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <h2>Descarga</h2>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <p><a class="volver" href="mis_compras.php">&larr; Ir a Mis compras</a></p>
    </main>

<?php include __DIR__ . "/parciales/pie.php"; ?>
