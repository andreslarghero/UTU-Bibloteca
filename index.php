<?php
// ============================================================
// PÁGINA DE INICIO
// Primera página del sitio. La puede ver cualquiera: no hace
// falta haber iniciado sesión.
// Si el usuario YA inició sesión, la conserva: esta página no
// cierra la sesión ni la modifica. Solo "Cerrar sesión" la cierra.
// ============================================================

// Recupera la sesión (si existe) para saber si hay un usuario dentro.
session_start();

// Conecta con la base de datos y carga la función que dibuja las portadas.
include "php/conexion.php";
include "php/portada.php";

// ------------------------------------------------------------
// USUARIO CON SESIÓN
// Si hay sesión, se comprueba en la base que el usuario sigue
// existiendo y sigue activo, igual que en el resto de las
// páginas, y se actualizan su nombre y su rol para el menú.
// Solo a un usuario dado de baja se le cierra la sesión.
// ------------------------------------------------------------
if (isset($_SESSION["id"])) {
    $consulta = mysqli_prepare($conexion, "SELECT nombre, rol, activo FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($consulta, "i", $_SESSION["id"]);
    mysqli_stmt_execute($consulta);
    $usuario_sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

    if (!$usuario_sesion || $usuario_sesion["activo"] != 1) {
        // El usuario ya no existe o fue dado de baja.
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION["nombre"] = $usuario_sesion["nombre"];
        $_SESSION["rol"] = $usuario_sesion["rol"];
        $_SESSION["admin"] = ($usuario_sesion["rol"] == "bibliotecario") ? 1 : 0;
    }
}

// ------------------------------------------------------------
// LIBROS DEL BLOQUE PRINCIPAL
// ------------------------------------------------------------
// Cuatro libros con portada para la composición de la derecha.
$libros_hero = mysqli_query($conexion,
    "SELECT titulo, portada FROM libros WHERE activo = 1 AND portada IS NOT NULL ORDER BY id LIMIT 4");

// Dato que usa la página para decidir qué botón mostrar.
$con_sesion = isset($_SESSION["id"]);

// Avisa al menú de que esta página está en la carpeta principal.
$nav_desde_raiz = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Configuración básica: tildes, adaptación a móvil, título y estilos -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca Digital</title>
    <link rel="stylesheet" href="css/estilos.css?v=28">
</head>
<body>
<?php include "php/navbar.php"; ?>

    <!-- La página de inicio tiene dos zonas: arriba, sobre el fondo claro,
         el bloque principal; abajo, una zona oscura a todo el ancho con
         "Explora tu biblioteca" y "Cómo funciona". -->
    <main class="inicio">
        <div class="inicio-arriba">
        <!-- Bloque principal: texto a la izquierda y libros en 3D a la derecha -->
        <section class="hero">
            <div class="hero-texto">
                <p class="hero-etiqueta">Biblioteca Digital</p>
                <h2>Tu biblioteca, en un solo lugar</h2>
                <p>Consulta libros físicos y digitales, administra tus compras y accede a tus lecturas desde una plataforma simple.</p>
                <div class="hero-botones">
                    <a class="btn btn-primary btn-lg" href="php/libros.php">Explorar catálogo</a>
                    <!-- Con sesión se ofrece ir a las compras; sin sesión, crear una cuenta -->
                    <?php if ($con_sesion) { ?>
                        <a class="btn btn-secondary btn-lg" href="php/mis_compras.php">Ver mis compras</a>
                    <?php } else { ?>
                        <a class="btn btn-secondary btn-lg" href="php/registro.php">Crear cuenta</a>
                    <?php } ?>
                </div>
            </div>

            <!-- Composición decorativa de libros (flotan muy despacio).
                 aria-hidden: los lectores de pantalla la saltean. -->
            <div class="hero-libros" aria-hidden="true">
                <?php while ($fila = mysqli_fetch_assoc($libros_hero)) { ?>
                    <div class="hero-libro"><?php echo portada_html($fila["titulo"], $fila["portada"], true); ?></div>
                <?php } ?>
            </div>
        </section>

        </div>

        <!-- Zona oscura: ocupa todo el ancho y su contenido queda centrado -->
        <div class="zona-oscura">
        <div class="zona-interior">
        <!-- Lo que ofrece el sitio -->
        <section class="seccion">
            <h2 data-reveal>Explora tu biblioteca</h2>
            <p class="descripcion-pagina" data-reveal>Todo lo que puedes hacer desde tu cuenta.</p>
            <div class="bloques" data-reveal-hijos>
                <div class="bloque">
                    <span class="icono"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/></svg></span>
                    <h3>Libros físicos</h3>
                    <p>Consulta precio y disponibilidad, y cómpralos en el mostrador.</p>
                </div>
                <div class="bloque">
                    <span class="icono"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="2"/><path d="M10 18.5h4"/></svg></span>
                    <h3>Biblioteca digital</h3>
                    <p>Compra libros digitales y descárgalos cuando quieras.</p>
                </div>
                <div class="bloque">
                    <span class="icono"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg></span>
                    <h3>Reseñas</h3>
                    <p>Valora los libros y lee la opinión de otros lectores.</p>
                </div>
                <div class="bloque">
                    <span class="icono"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                    <h3>Historial de compras</h3>
                    <p>Revisa tus compras y vuelve a descargar tus libros.</p>
                </div>
            </div>
        </section>

        <!-- Pasos para usar el sitio -->
        <section class="seccion">
            <h2 data-reveal>Cómo funciona</h2>
            <p class="descripcion-pagina" data-reveal>Cuatro pasos, sin complicaciones.</p>
            <ol class="pasos" data-reveal-hijos>
                <li class="paso">
                    <span class="paso-numero">1</span>
                    <h3>Explora</h3>
                    <p>Recorre el catálogo, busca por título o autor y filtra por categoría.</p>
                </li>
                <li class="paso">
                    <span class="paso-numero">2</span>
                    <h3>Elige</h3>
                    <p>Abre la ficha del libro y mira sus formatos, precios y reseñas.</p>
                </li>
                <li class="paso">
                    <span class="paso-numero">3</span>
                    <h3>Compra</h3>
                    <p>El formato digital se compra desde la web; el físico, en el mostrador.</p>
                </li>
                <li class="paso">
                    <span class="paso-numero">4</span>
                    <h3>Accede</h3>
                    <p>Descarga tus libros digitales desde "Mis compras" cuando quieras.</p>
                </li>
            </ol>
        </section>
        </div>
        </div>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
<?php
// Cierra la conexión con la base de datos al terminar la página.
mysqli_close($conexion);
?>
