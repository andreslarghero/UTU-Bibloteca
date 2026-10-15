<?php
// ============================================================
// CATÁLOGO DE LIBROS
// Muestra los libros de la base de datos en tarjetas, con un
// buscador por título o autor y un filtro por categoría.
// Solo pueden verlo los usuarios que han iniciado sesión.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_usuario.php";

// ------------------------------------------------------------
// CATEGORÍAS
// Se cargan todas para el desplegable del filtro y para comprobar
// que la categoría que llega por la dirección existe.
// ------------------------------------------------------------
$categorias = [];
$lista_categorias = mysqli_query($conexion, "SELECT id, nombre FROM categorias ORDER BY nombre");
while ($fila = mysqli_fetch_assoc($lista_categorias)) {
    $categorias[$fila["id"]] = $fila["nombre"];
}

// ------------------------------------------------------------
// BUSCADOR Y FILTRO POR CATEGORÍA
// Los dos llegan por la dirección: libros.php?buscar=texto&categoria=3
// Se pueden usar por separado o juntos.
// ------------------------------------------------------------

// Texto de búsqueda (vacío = sin búsqueda).
$buscar = texto_de($_GET["buscar"] ?? null);

// Categoría elegida (0 = todas). Solo se acepta un número que sea el id
// de una categoría que existe; cualquier otra cosa se trata como "todas".
$categoria = entero_de($_GET["categoria"] ?? null);
if (!isset($categorias[$categoria])) {
    $categoria = 0;
}

// Una sola consulta sirve para todos los casos:
//  - Trae los libros con el nombre de su categoría (JOIN con categorias),
//    su valoración media y el número de reseñas (LEFT JOIN para incluir
//    libros sin reseñas).
//  - Solo libros activos (activo = 0 es un libro dado de baja).
//  - Búsqueda: los % permiten encontrar el texto en cualquier parte del
//    título o del autor. Si no se buscó nada queda "%%", que coincide con todos.
//  - Categoría: "? = 0" hace que con 0 (todas) no se filtre por categoría.
//  - Orden: por id, para que el catálogo salga siempre igual.
// Es una consulta preparada: los valores van en los "?" y nunca se pegan
// en el texto del SQL, para evitar ataques de inyección SQL.
$texto = "%" . $buscar . "%";
$consulta = mysqli_prepare($conexion,
    "SELECT libros.*, categorias.nombre AS categoria,
            ROUND(AVG(resenias.puntuacion), 1) AS media, COUNT(resenias.id) AS total_resenas
     FROM libros
     JOIN categorias ON categorias.id = libros.categoria_id
     LEFT JOIN resenias ON resenias.libro_id = libros.id
     WHERE libros.activo = 1
       AND (libros.titulo LIKE ? OR libros.autor LIKE ?)
       AND (? = 0 OR libros.categoria_id = ?)
     GROUP BY libros.id
     ORDER BY libros.id");
mysqli_stmt_bind_param($consulta, "ssii", $texto, $texto, $categoria, $categoria);
mysqli_stmt_execute($consulta);
$resultado = mysqli_stmt_get_result($consulta);

// Se pasan todos los libros a una lista, porque se usan dos veces:
// en el carrusel de destacados y en la cuadrícula del catálogo.
$libros = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $libros[] = $fila;
}

// ------------------------------------------------------------
// LIBROS DESTACADOS (para el carrusel)
// No se hace otra consulta: se eligen hasta 10 de la lista que ya
// se trajo, mezclando físicos y digitales y variando las categorías.
// Solo se muestran en el catálogo completo (sin búsqueda ni filtro).
// ------------------------------------------------------------
$destacados = [];
if ($buscar == "" && $categoria == 0 && count($libros) >= 3) {
    // Se separan físicos y digitales y se intercalan: uno y uno.
    $fisicos = [];
    $digitales = [];
    foreach ($libros as $libro) {
        if ($libro["formato_digital"] == 1 && $libro["formato_fisico"] == 0) {
            $digitales[] = $libro;
        } else {
            $fisicos[] = $libro;
        }
    }
    $intercalados = [];
    for ($i = 0; $i < max(count($fisicos), count($digitales)); $i++) {
        if (isset($fisicos[$i])) { $intercalados[] = $fisicos[$i]; }
        if (isset($digitales[$i])) { $intercalados[] = $digitales[$i]; }
    }

    // Primera pasada: un libro por categoría, para que haya variedad.
    $categorias_usadas = [];
    foreach ($intercalados as $libro) {
        if (count($destacados) < 10 && !isset($categorias_usadas[$libro["categoria_id"]])) {
            $destacados[$libro["id"]] = $libro;
            $categorias_usadas[$libro["categoria_id"]] = true;
        }
    }
    // Segunda pasada: se completa hasta 10 con los que siguen.
    foreach ($intercalados as $libro) {
        if (count($destacados) < 10 && !isset($destacados[$libro["id"]])) {
            $destacados[$libro["id"]] = $libro;
        }
    }
}

// Función que dibuja la portada (imagen o reemplazo propio).
include "portada.php";
?>
<?php
$titulo_pagina = "Catálogo";
include "cabecera.php";
?>

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
        <form class="buscador" name="form_busqueda_catalogo" method="GET" action="libros.php">
            <label class="solo-lectores" for="buscar">Buscar por título o autor</label>
            <input type="text" id="buscar" name="buscar" placeholder="Buscar por título o autor..." value="<?php echo htmlspecialchars($buscar); ?>">
            <!-- Desplegable con las categorías de la base; queda marcada la elegida -->
            <label class="solo-lectores" for="categoria">Categoría</label>
            <select id="categoria" name="categoria">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $id_categoria => $nombre_categoria) { ?>
                    <option value="<?php echo $id_categoria; ?>" <?php if ($categoria == $id_categoria) echo "selected"; ?>><?php echo htmlspecialchars($nombre_categoria); ?></option>
                <?php } ?>
            </select>
            <button class="btn btn-primary" type="submit">Buscar</button>
            <!-- Enlace para quitar la búsqueda y el filtro, solo aparece si hay alguno -->
            <?php if ($buscar != "" || $categoria != 0) { ?>
                <a href="libros.php">Ver todos</a>
            <?php } ?>
        </form>

        <!-- Aviso cuando la búsqueda o el filtro no encuentran ningún libro -->
        <?php if (count($libros) == 0) { ?>
            <p class="sin-resultados">
                No se encontraron libros<?php if ($buscar != "") { ?> para "<?php echo htmlspecialchars($buscar); ?>"<?php } ?><?php if ($categoria != 0) { ?> en la categoría "<?php echo htmlspecialchars($categorias[$categoria]); ?>"<?php } ?>.
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
                        <?php echo portada_html($fila["titulo"], $fila["portada"]); ?>
                        <span class="categoria"><?php echo htmlspecialchars($fila["categoria"]); ?></span>
                        <h4><?php echo htmlspecialchars($fila["titulo"]); ?></h4>
                        <p><?php echo htmlspecialchars($fila["autor"]); ?></p>
                        <!-- Formato y precio: el físico si lo tiene; si no, el digital -->
                        <p class="formato">
                            <?php if ($fila["formato_fisico"] == 1) { ?>
                                <span class="badge badge-fisico">Físico</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila["precio"]); ?></span>
                            <?php } else { ?>
                                <span class="badge badge-digital">Digital</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila["precio_digital"]); ?></span>
                            <?php } ?>
                        </p>
                        <a class="btn btn-primary btn-sm" href="libro.php?id=<?php echo $fila["id"]; ?>">Ver libro</a>
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
                <?php echo portada_html($fila["titulo"], $fila["portada"]); ?>
                <div class="libro-cuerpo">
                    <span class="categoria"><?php echo htmlspecialchars($fila["categoria"]); ?></span>
                    <h3><?php echo htmlspecialchars($fila["titulo"]); ?></h3>
                    <!-- El año solo se muestra si el libro tiene año -->
                    <p class="autor">
                        <?php echo htmlspecialchars($fila["autor"]); ?><?php if ($fila["anio"]) { ?> · <span class="anio"><?php echo $fila["anio"]; ?></span><?php } ?>
                    </p>
                    <!-- Valoración media con estrellas, o aviso si no tiene reseñas -->
                    <p class="valoracion-tarjeta">
                        <?php if ($fila["total_resenas"] > 0) { ?>
                            <span class="estrellas">★</span> <?php echo $fila["media"]; ?> (<?php echo $fila["total_resenas"]; ?>)
                        <?php } else { ?>
                            Sin reseñas
                        <?php } ?>
                    </p>
                    <div class="libro-formatos">
                        <!-- Formato físico: precio y disponibilidad (copias en verde o "Agotado" en rojo).
                             El stock y el "Agotado" son solo del formato físico. -->
                        <?php if ($fila["formato_fisico"] == 1) { ?>
                            <p class="formato">
                                <span class="badge badge-fisico">Físico</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila["precio"]); ?></span>
                                <?php if ($fila["stock"] > 0) { ?>
                                    <span class="disponible"><?php echo $fila["stock"]; ?> <?php echo ($fila["stock"] == 1) ? "disponible" : "disponibles"; ?></span>
                                <?php } else { ?>
                                    <span class="agotado">Agotado</span>
                                <?php } ?>
                            </p>
                        <?php } ?>
                        <!-- Formato digital: solo el precio (un libro digital no se agota) -->
                        <?php if ($fila["formato_digital"] == 1) { ?>
                            <p class="formato">
                                <span class="badge badge-digital">Digital</span>
                                <span class="precio">$ <?php echo formatearPrecio($fila["precio_digital"]); ?></span>
                            </p>
                        <?php } ?>
                    </div>
                    <!-- Botón que abre la página de detalle de este libro -->
                    <a class="btn btn-primary" href="libro.php?id=<?php echo $fila["id"]; ?>">Ver detalle</a>
                </div>
            </div>
            <?php } ?>
        </div>
    </main>

<?php include "pie.php"; ?>
