<?php
// ============================================================
// VISTA: gestión de reseñas (ResenaController::index y ::eliminar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <h2>Gestión de reseñas</h2>
        <p class="descripcion-pagina">Todas las reseñas publicadas. Se puede eliminar cualquiera.</p>

        <!-- Aviso verde después de eliminar una reseña -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <?php if (count($resenas) == 0) { ?>
            <!-- Aviso si todavía no hay reseñas -->
            <p class="sin-resultados">Todavía no hay reseñas.</p>
        <?php } else { ?>
        <!-- Tabla con todas las reseñas -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>Libro</th>
                <th>Usuario</th>
                <th>Valoración</th>
                <th>Comentario</th>
                <th>Fecha</th>
                <th></th>
            </tr>
            <!-- Una fila por cada reseña -->
            <?php foreach ($resenas as $r) { ?>
            <tr>
                <td><a href="<?php echo Ruta::h('libro', 'ver', ['id' => $r->getLibroId()]); ?>"><?php echo htmlspecialchars($r->getLibroTitulo()); ?></a></td>
                <td><?php echo htmlspecialchars($r->getUsuarioNombre()); ?></td>
                <td class="estrellas"><?php echo $r; ?></td>
                <td><?php echo nl2br(htmlspecialchars($r->getComentario())); ?></td>
                <td><?php echo date("d/m/Y", strtotime($r->getFecha())); ?></td>
                <td>
                    <!-- Botón para eliminar la reseña (pide confirmación) -->
                    <form name="form_eliminar_resenia_<?php echo $r->getId(); ?>" method="POST" action="<?php echo Ruta::h('resena', 'eliminar'); ?>" data-confirmar="¿Eliminar esta reseña? No se puede deshacer.">
                        <input type="hidden" name="resena_id" value="<?php echo $r->getId(); ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
