<?php
// ============================================================
// VISTA PARCIAL: MITAD VISUAL DE LOGIN Y REGISTRO
// Bloque decorativo que se muestra a la izquierda del formulario
// en pantallas grandes: el lema sobre el video de fondo. El video
// (el mismo del inicio) ocupa todo el fondo de la página, detrás del
// formulario. Es solo decoración, por eso lleva aria-hidden. El lema no se
// muestra en el móvil.
// Se incluye desde las vistas de login y registro: así las dos páginas
// usan exactamente el mismo bloque.
// ============================================================

?>
        <!-- El video cubre todo el fondo de la página (detrás del formulario) -->
        <video class="hero-video" autoplay muted loop playsinline preload="auto" aria-hidden="true">
            <source src="img/hero.mp4" type="video/mp4">
        </video>
        <div class="hero-capa" aria-hidden="true"></div>

        <section class="acceso-visual" aria-hidden="true">
            <p class="acceso-lema">Tu biblioteca, siempre contigo</p>
            <p class="acceso-sublema">Libros físicos y digitales, tus compras y tus lecturas en un solo lugar.</p>
        </section>
