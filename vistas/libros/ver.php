<?php
// ============================================================
// VISTA: ficha de un libro (LibroController::ver)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="<?php echo Ruta::h('libro'); ?>">&larr; Volver al catálogo</a>

        <?php if ($libro) { ?>
        <!-- Ficha del libro: portada a la izquierda e información a la derecha -->
        <div class="detalle" data-reveal>
            <!-- Portada: la imagen o, si no tiene (o no carga), un reemplazo propio.
                 Con el cursor encima se inclina apenas, en 3D (lo hace main.js).
                 Los textos de la base se muestran con htmlspecialchars para que
                 se vean como texto y el navegador no los interprete como HTML. -->
            <div class="detalle-portada">
                <?php echo portada_html($libro->getTitulo(), $libro->getPortada(), true); ?>
            </div>

            <div class="detalle-info">
                <span class="categoria"><?php echo htmlspecialchars($libro->getCategoria()); ?></span>
                <h2><?php echo htmlspecialchars($libro->getTitulo()); ?></h2>
                <p class="autor"><?php echo htmlspecialchars($libro->getAutor()); ?></p>

                <!-- Valoración media del libro -->
                <p class="valoracion">
                    <?php if ($valoracion["total"] > 0) { ?>
                        <span class="estrellas"><?php echo estrellas($valoracion["media"]); ?></span>
                        <?php echo $valoracion["media"]; ?> de 5 (<?php echo $valoracion["total"]; ?> reseñas)
                    <?php } else { ?>
                        Sin reseñas todavía
                    <?php } ?>
                </p>

                <!-- FORMATOS: un bloque por cada formato en que se vende el libro -->
                <div class="formatos-libro">
                    <!-- FORMATO FÍSICO: precio y disponibilidad (copias en verde o "Agotado" en rojo).
                         Solo se muestra si el libro se vende en papel. -->
                    <?php if ($libro->esFisico()) { ?>
                    <div class="formato-libro">
                        <h3>Formato físico</h3>
                        <p class="precio">$ <?php echo formatearPrecio($libro->getPrecio()); ?></p>
                        <?php if ($libro->getStock() > 0) { ?>
                            <span class="disponible"><?php echo $libro->getStock(); ?> <?php echo ($libro->getStock() == 1) ? "copia disponible" : "copias disponibles"; ?></span>
                        <?php } else { ?>
                            <span class="agotado">Agotado</span>
                        <?php } ?>
                        <?php if ($libro->getStock() > 0) { ?>
                            <!-- Lleva a la página de compra (se elige la cantidad); solo se envía el id del libro -->
                            <p><a class="btn btn-success" data-espera href="<?php echo Ruta::h('compra', 'comprarFisico', ['libro' => $libro->getId()]); ?>">Comprar físico</a></p>
                        <?php } ?>
                        <p class="ayuda">Se compra por la web y se retira en el mostrador de la biblioteca (también se vende en la caja).</p>
                    </div>
                    <?php } ?>

                    <!-- FORMATO DIGITAL: precio y compra. Nunca se muestra el archivo
                         ni dónde está guardado: la descarga se hace desde "Mis compras". -->
                    <?php if ($libro->esDigital()) { ?>
                    <div class="formato-libro">
                        <h3>Formato digital</h3>
                        <p class="precio">$ <?php echo formatearPrecio($libro->getPrecioDigital()); ?></p>
                        <?php if ($yaComprado) { ?>
                            <!-- El usuario ya lo compró: no se le vuelve a ofrecer la compra -->
                            <span class="disponible">Ya comprado</span>
                            <p><a class="btn btn-primary" href="<?php echo Ruta::h('compra'); ?>">Ir a Mis compras</a></p>
                        <?php } else { ?>
                            <!-- Lleva a la página de confirmación; solo se envía el id del libro -->
                            <p><a class="btn btn-success" data-espera href="<?php echo Ruta::h('compra', 'comprar', ['libro' => $libro->getId()]); ?>">Comprar digital</a></p>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <!-- Datos generales del libro. El año solo se muestra si el libro tiene año. -->
                <h3>Información general</h3>
                <dl class="datos-libro">
                    <dt>Autor</dt>
                    <dd><?php echo htmlspecialchars($libro->getAutor()); ?></dd>
                    <?php if ($libro->getAnio()) { ?>
                        <dt>Año de publicación</dt>
                        <dd><?php echo $libro->getAnio(); ?></dd>
                    <?php } ?>
                    <dt>Categoría</dt>
                    <dd><?php echo htmlspecialchars($libro->getCategoria()); ?></dd>
                    <dt>ISBN</dt>
                    <dd><?php echo $libro->getIsbn() ? htmlspecialchars($libro->getIsbn()) : "Sin ISBN"; ?></dd>
                </dl>

                <!-- Resumen del libro -->
                <h3>Descripción</h3>
                <p><?php echo htmlspecialchars((string) $libro->getDescripcion()); ?></p>
            </div>
        </div>

        <!-- ============== TAMBIÉN PODRÍA INTERESARTE ==============
             Libros de la misma categoría. Solo se muestra si hay alguno. -->
        <?php if (count($relacionados) > 0) { ?>
        <section class="relacionados">
            <h2 data-reveal>También podría interesarte</h2>
            <div class="relacionados-lista" data-reveal-hijos>
                <?php foreach ($relacionados as $rel) { ?>
                <a class="libro-mini" href="<?php echo Ruta::h('libro', 'ver', ['id' => $rel->getId()]); ?>">
                    <?php echo portada_html($rel->getTitulo(), $rel->getPortada()); ?>
                    <span>
                        <strong><?php echo htmlspecialchars($rel->getTitulo()); ?></strong>
                        <?php echo htmlspecialchars($rel->getAutor()); ?><br>
                        $ <?php echo formatearPrecio($rel->precioMostrado()); ?>
                    </span>
                </a>
                <?php } ?>
            </div>
        </section>
        <?php } ?>

        <!-- ====================== RESEÑAS ====================== -->
        <section class="resenas" data-reveal>
            <h2>Reseñas</h2>

            <!-- Formulario para escribir (o editar) la reseña del usuario -->
            <!-- El formulario se envía a esta misma ficha (libro.php?id=N) -->
            <form class="form-resena" name="form_resenia" method="POST" action="<?php echo Ruta::h('libro', 'guardarResena', ['id' => $libro->getId()]); ?>">
                <h3><?php echo $miResena ? "Editar tu reseña" : "Escribe tu reseña"; ?></h3>

                <label for="puntuacion">Valoración</label>
                <select id="puntuacion" name="puntuacion" required>
                    <?php for ($i = 5; $i >= 1; $i--) { ?>
                        <option value="<?php echo $i; ?>" <?php if ($miResena && $miResena->getPuntuacion() == $i) echo "selected"; ?>>
                            <?php echo estrellas($i); ?> (<?php echo $i; ?>)
                        </option>
                    <?php } ?>
                </select>

                <label for="comentario">Comentario</label>
                <textarea id="comentario" name="comentario" rows="4" placeholder="¿Qué te pareció este libro?" required><?php echo $miResena ? htmlspecialchars($miResena->getComentario()) : ""; ?></textarea>

                <button class="btn btn-primary" type="submit">Publicar reseña</button>
            </form>

            <!-- Lista de reseñas del libro -->
            <?php if (count($resenas) == 0) { ?>
                <p class="sin-resultados">Todavía nadie ha reseñado este libro. ¡Sé el primero!</p>
            <?php } ?>

            <?php foreach ($resenas as $res) { ?>
            <div class="resena">
                <div class="resena-cabecera">
                    <strong><?php echo htmlspecialchars($res->getUsuarioNombre()); ?></strong>
                    <span class="estrellas"><?php echo estrellas($res->getPuntuacion()); ?></span>
                    <span class="fecha"><?php echo date("d/m/Y", strtotime($res->getFecha())); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($res->getComentario())); ?></p>

                <!-- Botón para eliminar: aparece en la reseña propia o si eres administrador -->
                <?php if ($res->getUsuarioId() == $usuarioMenu->getId() || $usuarioMenu->esBibliotecario()) { ?>
                <!-- data-confirmar: main.js pregunta antes de enviar el formulario -->
                <form name="form_eliminar_resenia_<?php echo $res->getId(); ?>" method="POST" action="<?php echo Ruta::h('libro', 'eliminarResena', ['id' => $libro->getId()]); ?>" data-confirmar="¿Eliminar esta reseña? No se puede deshacer.">
                    <input type="hidden" name="resena_id" value="<?php echo $res->getId(); ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                </form>
                <?php } ?>
            </div>
            <?php } ?>
        </section>

        <?php } else { ?>
        <!-- Aviso si el id no corresponde a ningún libro -->
        <p class="sin-resultados">El libro que buscas no existe.</p>
        <?php } ?>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
