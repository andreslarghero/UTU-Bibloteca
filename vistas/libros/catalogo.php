<?php
// ============================================================
// VISTA: catálogo (LibroController::index)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <!-- "ancho-catalogo": esta página usa un poco más de ancho que el resto
         del sitio, para que entren 5 libros por fila. -->
    <main class="ancho-catalogo">
        <h2>Catálogo de libros</h2>
        <p class="descripcion-pagina">Libros físicos y digitales. Busca por título o autor, o filtra por categoría.</p>

        <!-- Buscador y filtro: envían el texto y la categoría a esta misma
             página por la dirección (?buscar=...&categoria=...) -->
        <!-- Cada campo tiene su <label>. Con la clase "solo-lectores" la etiqueta
             no se ve en pantalla (el campo ya se explica con su texto de ayuda),
             pero sí la leen los lectores de pantalla. -->
        <form class="buscador" name="form_busqueda_catalogo" method="GET" action="index.php">
            <input type="hidden" name="controlador" value="libro">
            <label class="solo-lectores" for="buscar">Buscar por título o autor</label>
            <input type="text" id="buscar" name="buscar" placeholder="Buscar por título o autor..." value="<?php echo htmlspecialchars($buscar); ?>">
            <!-- Desplegable con las categorías de la base; queda marcada la elegida -->
            <label class="solo-lectores" for="categoria">Categoría</label>
            <select id="categoria" name="categoria">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $cat) { ?>
                    <option value="<?php echo $cat->getId(); ?>" <?php if ($categoria == $cat->getId()) echo "selected"; ?>><?php echo htmlspecialchars($cat->getNombre()); ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-primary" type="submit">Buscar</button>
            <!-- Enlace para quitar la búsqueda y el filtro, solo aparece si hay alguno -->
            <?php if ($buscar != "" || $categoria != 0) { ?>
                <a href="<?php echo Ruta::h('libro'); ?>">Ver todos</a>
            <?php } ?>
        </form>

        <!-- Aviso cuando la búsqueda o el filtro no encuentran ningún libro -->
        <?php if (count($libros) == 0) { ?>
            <p class="sin-resultados">
                No se encontraron libros<?php if ($buscar != "") { ?> para "<?php echo htmlspecialchars($buscar); ?>"<?php } ?><?php if ($categoria != 0) { ?> en la categoría "<?php echo htmlspecialchars($categoriaNombre); ?>"<?php } ?>.
            </p>
        <?php } ?>

        <!-- ================= LIBROS DESTACADOS =================
             Sin JavaScript es una fila de tarjetas que se desplaza de lado.
             Con JavaScript, main.js la convierte en un carrusel 3D: agrega
             los botones, los puntos y el cambio automático cada 3 segundos. -->
        <?php if (count($destacados) > 0) { ?>
        <section class="destacados" aria-labelledby="titulo-destacados" data-reveal>
            <div class="destacados-cabecera">
                <h3 id="titulo-destacados">Libros destacados</h3>
                <!-- Aquí main.js coloca los botones: anterior, pausa y siguiente -->
                <div class="carrusel-controles" hidden></div>
            </div>
            <div class="carrusel" data-carrusel role="group" aria-roledescription="carrusel" aria-label="Libros destacados. Usa las flechas izquierda y derecha para cambiar de libro." tabindex="0">
                <div class="carrusel-pista">
                    <?php foreach ($destacados as $fila) { ?>
                    <article class="destacado" data-slide>
                        <?php echo portada_html($fila->getTitulo(), $fila->getPortada()); ?>
                        <span class="categoria"><?php echo htmlspecialchars($fila->getCategoria()); ?></span>
                        <h4><?php echo htmlspecialchars($fila->getTitulo()); ?></h4>
                        <p><?php echo htmlspecialchars($fila->getAutor()); ?></p>
                        <!-- Formato y precio: el físico si lo tiene; si no, el digital -->
                        <p class="formato">
                            <?php if ($fila->esFisico()) { ?>
                                <span class="badge badge-fisico">Físico</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila->getPrecio()); ?></span>
                            <?php } else { ?>
                                <span class="badge badge-digital">Digital</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila->getPrecioDigital()); ?></span>
                            <?php } ?>
                        </p>
                        <a class="btn btn-primary btn-sm" href="<?php echo Ruta::h('libro', 'ver', ['id' => $fila->getId()]); ?>">Ver libro</a>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php } ?>

        <!-- Cuadrícula del catálogo. "data-reveal-hijos": las tarjetas
             aparecen de a una cuando entran en la pantalla. -->
        <div class="catalogo" data-reveal-hijos>
            <!-- Recorre los libros uno por uno y crea una tarjeta para cada uno -->
            <?php foreach ($libros as $fila) { ?>
            <!-- Los textos de la base se muestran con htmlspecialchars para que
                 se vean como texto y el navegador no los interprete como HTML. -->
            <div class="libro">
                <!-- Portada: la imagen o, si no tiene (o no carga), un reemplazo propio -->
                <?php echo portada_html($fila->getTitulo(), $fila->getPortada()); ?>
                <div class="libro-cuerpo">
                    <span class="categoria"><?php echo htmlspecialchars($fila->getCategoria()); ?></span>
                    <h3><?php echo htmlspecialchars($fila->getTitulo()); ?></h3>
                    <!-- El año solo se muestra si el libro tiene año -->
                    <p class="autor">
                        <?php echo htmlspecialchars($fila->getAutor()); ?><?php if ($fila->getAnio()) { ?> · <span class="anio"><?php echo $fila->getAnio(); ?></span><?php } ?>
                    </p>
                    <!-- Valoración media con estrellas, o aviso si no tiene reseñas -->
                    <p class="valoracion-tarjeta">
                        <?php if ($fila->getTotalResenas() > 0) { ?>
                            <span class="estrellas">★</span> <?php echo $fila->getMedia(); ?> (<?php echo $fila->getTotalResenas(); ?>)
                        <?php } else { ?>
                            Sin reseñas
                        <?php } ?>
                    </p>
                    <div class="libro-formatos">
                        <!-- Formato físico: precio y disponibilidad (copias en verde o "Agotado" en rojo).
                             El stock y el "Agotado" son solo del formato físico. -->
                        <?php if ($fila->esFisico()) { ?>
                            <p class="formato">
                                <span class="badge badge-fisico">Físico</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila->getPrecio()); ?></span>
                                <?php if ($fila->getStock() > 0) { ?>
                                    <span class="disponible"><?php echo $fila->getStock(); ?> <?php echo ($fila->getStock() == 1) ? "disponible" : "disponibles"; ?></span>
                                <?php } else { ?>
                                    <span class="agotado">Agotado</span>
                                <?php } ?>
                            </p>
                        <?php } ?>
                        <!-- Formato digital: solo el precio (un libro digital no se agota) -->
                        <?php if ($fila->esDigital()) { ?>
                            <p class="formato">
                                <span class="badge badge-digital">Digital</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila->getPrecioDigital()); ?></span>
                            </p>
                        <?php } ?>
                    </div>
                    <!-- Botón que abre la página de detalle de este libro -->
                    <a class="btn btn-primary" href="<?php echo Ruta::h('libro', 'ver', ['id' => $fila->getId()]); ?>">Ver detalle</a>
                </div>
            </div>
            <?php } ?>
        </div>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
