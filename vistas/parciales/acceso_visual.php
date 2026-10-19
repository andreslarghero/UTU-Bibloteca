<?php
// ============================================================
// VISTA PARCIAL: MITAD VISUAL DE LOGIN Y REGISTRO
// Bloque decorativo que se muestra a la izquierda del formulario
// en pantallas grandes: fondo oscuro, una composición de tres
// libros superpuestos y el lema. Los libros quedan quietos (no
// hay animaciones continuas). Es solo decoración, por eso lleva
// aria-hidden. En el móvil no se muestra.
// Se incluye desde login.php y registro.php: así las dos páginas
// usan exactamente el mismo bloque.
// ============================================================

// portada_html() dibuja una portada (la imagen o, si el archivo no
// estuviera, el reemplazo propio: nunca una imagen rota).
require_once __DIR__ . "/../ayudas.php";

// Las tres portadas de la composición, de atrás hacia adelante.
// Son archivos del proyecto, de la carpeta img/portadas.
$acceso_libros = [
    ["Cien años de soledad", "img/portadas/cien-anos-de-soledad.webp"],
    ["Fahrenheit 451", "img/portadas/fahrenheit-451.webp"],
    ["1984", "img/portadas/1984.webp"]
];
?>
        <section class="acceso-visual" aria-hidden="true">
            <!-- Tres libros superpuestos, como una pila -->
            <div class="acceso-libros">
                <?php foreach ($acceso_libros as $acceso_libro) { ?>
                    <div class="acceso-libro"><?php echo portada_html($acceso_libro[0], $acceso_libro[1], true); ?></div>
                <?php } ?>
            </div>

            <p class="acceso-lema">Tu biblioteca, siempre contigo</p>
            <p class="acceso-sublema">Libros físicos y digitales, tus compras y tus lecturas en un solo lugar.</p>
        </section>
