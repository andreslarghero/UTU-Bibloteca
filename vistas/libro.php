<?php
// ============================================================
// VISTA: libro
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/libro.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="libros.php">&larr; Volver al catálogo</a>

        <?php if ($libro) { ?>
        <!-- Ficha del libro: portada a la izquierda e información a la derecha -->
        <div class="detalle" data-reveal>
            <!-- Portada: la imagen o, si no tiene (o no carga), un reemplazo propio.
                 Con el cursor encima se inclina apenas, en 3D (lo hace main.js).
                 Los textos de la base se muestran con htmlspecialchars para que
                 se vean como texto y el navegador no los interprete como HTML. -->
            <div class="detalle-portada">
                <?php echo portada_html($libro["titulo"], $libro["portada"], true); ?>
            </div>

            <div class="detalle-info">
                <span class="categoria"><?php echo htmlspecialchars($libro["categoria"]); ?></span>
                <h2><?php echo htmlspecialchars($libro["titulo"]); ?></h2>
                <p class="autor"><?php echo htmlspecialchars($libro["autor"]); ?></p>

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
                    <?php if ($libro["formato_fisico"] == 1) { ?>
                    <div class="formato-libro">
                        <h3>Formato físico</h3>
                        <p class="precio">$ <?php echo formatearPrecio($libro["precio"]); ?></p>
                        <?php if ($libro["stock"] > 0) { ?>
                            <span class="disponible"><?php echo $libro["stock"]; ?> <?php echo ($libro["stock"] == 1) ? "copia disponible" : "copias disponibles"; ?></span>
                        <?php } else { ?>
                            <span class="agotado">Agotado</span>
                        <?php } ?>
                        <p class="ayuda">Se compra en el mostrador de la biblioteca.</p>
                    </div>
                    <?php } ?>

                    <!-- FORMATO DIGITAL: precio y compra. Nunca se muestra el archivo
                         ni dónde está guardado: la descarga se hace desde "Mis compras". -->
                    <?php if ($libro["formato_digital"] == 1) { ?>
                    <div class="formato-libro">
                        <h3>Formato digital</h3>
                        <p class="precio">$ <?php echo formatearPrecio($libro["precio_digital"]); ?></p>
                        <?php if ($ya_comprado) { ?>
                            <!-- El usuario ya lo compró: no se le vuelve a ofrecer la compra -->
                            <span class="disponible">Ya comprado</span>
                            <p><a class="btn btn-primary" href="mis_compras.php">Ir a Mis compras</a></p>
                        <?php } else { ?>
                            <!-- Lleva a la página de confirmación; solo se envía el id del libro -->
                            <p><a class="btn btn-success" data-espera href="comprar_digital.php?libro=<?php echo $libro["id"]; ?>">Comprar digital</a></p>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <!-- Datos generales del libro. El año solo se muestra si el libro tiene año. -->
                <h3>Información general</h3>
                <dl class="datos-libro">
                    <dt>Autor</dt>
                    <dd><?php echo htmlspecialchars($libro["autor"]); ?></dd>
                    <?php if ($libro["anio"]) { ?>
                        <dt>Año de publicación</dt>
                        <dd><?php echo $libro["anio"]; ?></dd>
                    <?php } ?>
                    <dt>Categoría</dt>
                    <dd><?php echo htmlspecialchars($libro["categoria"]); ?></dd>
                    <dt>ISBN</dt>
                    <dd><?php echo $libro["isbn"] ? htmlspecialchars($libro["isbn"]) : "Sin ISBN"; ?></dd>
                </dl>

                <!-- Resumen del libro -->
                <h3>Descripción</h3>
                <p><?php echo htmlspecialchars((string) $libro["descripcion"]); ?></p>
            </div>
        </div>

        <!-- ============== TAMBIÉN PODRÍA INTERESARTE ==============
             Libros de la misma categoría. Solo se muestra si hay alguno. -->
        <?php if (count($relacionados) > 0) { ?>
        <section class="relacionados">
            <h2 data-reveal>También podría interesarte</h2>
            <div class="relacionados-lista" data-reveal-hijos>
                <?php foreach ($relacionados as $r) { ?>
                <a class="libro-mini" href="libro.php?id=<?php echo $r["id"]; ?>">
                    <?php echo portada_html($r["titulo"], $r["portada"]); ?>
                    <span>
                        <strong><?php echo htmlspecialchars($r["titulo"]); ?></strong>
                        <?php echo htmlspecialchars($r["autor"]); ?><br>
                        $ <?php echo formatearPrecio(($r["formato_fisico"] == 1) ? $r["precio"] : $r["precio_digital"]); ?>
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
            <form class="form-resena" name="form_resenia" method="POST" action="libro.php?id=<?php echo (int) $id; ?>">
                <h3><?php echo $mi_resena ? "Editar tu reseña" : "Escribe tu reseña"; ?></h3>
                <input type="hidden" name="accion" value="guardar">

                <label for="puntuacion">Valoración</label>
                <select id="puntuacion" name="puntuacion" required>
                    <?php for ($i = 5; $i >= 1; $i--) { ?>
                        <option value="<?php echo $i; ?>" <?php if ($mi_resena && $mi_resena["puntuacion"] == $i) echo "selected"; ?>>
                            <?php echo estrellas($i); ?> (<?php echo $i; ?>)
                        </option>
                    <?php } ?>
                </select>

                <label for="comentario">Comentario</label>
                <textarea id="comentario" name="comentario" rows="4" placeholder="¿Qué te pareció este libro?" required><?php echo $mi_resena ? htmlspecialchars($mi_resena["comentario"]) : ""; ?></textarea>

                <button class="btn btn-primary" type="submit">Publicar reseña</button>
            </form>

            <!-- Lista de reseñas del libro -->
            <?php if (count($resenas) == 0) { ?>
                <p class="sin-resultados">Todavía nadie ha reseñado este libro. ¡Sé el primero!</p>
            <?php } ?>

            <?php foreach ($resenas as $r) { ?>
            <div class="resena">
                <div class="resena-cabecera">
                    <strong><?php echo htmlspecialchars($r["nombre"]); ?></strong>
                    <span class="estrellas"><?php echo estrellas($r["puntuacion"]); ?></span>
                    <span class="fecha"><?php echo date("d/m/Y", strtotime($r["fecha"])); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($r["comentario"])); ?></p>

                <!-- Botón para eliminar: aparece en la reseña propia o si eres administrador -->
                <?php if ($r["usuario_id"] == $_SESSION["id"] || $_SESSION["rol"] == "bibliotecario") { ?>
                <!-- data-confirmar: main.js pregunta antes de enviar el formulario -->
                <form name="form_eliminar_resenia_<?php echo $r["id"]; ?>" method="POST" action="libro.php?id=<?php echo (int) $id; ?>" data-confirmar="¿Eliminar esta reseña? No se puede deshacer.">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="resena_id" value="<?php echo $r["id"]; ?>">
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

<?php include __DIR__ . "/parciales/pie.php"; ?>
