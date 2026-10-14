<?php
// ============================================================
// ENCABEZADO Y MENÚ DE NAVEGACIÓN
// Este archivo se incluye en todas las páginas para que el menú
// sea siempre el mismo. Mira la sesión y muestra:
//   - sin sesión:     Inicio, Iniciar sesión, Registrarse
//   - cliente:        Inicio, Catálogo, Mi perfil, Mis compras, Cerrar sesión
//   - bibliotecario:  lo anterior y, además, los enlaces de gestión
//
// OJO: el menú es solo navegación, NO es seguridad. Que un enlace
// no se muestre no impide entrar escribiendo la dirección: de eso
// se encargan verificar_usuario.php y verificar_bibliotecario.php
// al principio de cada página.
// ============================================================

// La página de inicio está en la carpeta principal y las demás en
// la carpeta "php", así que la dirección de los enlaces cambia según
// desde dónde se incluya el menú. La página de inicio lo avisa
// creando la variable $nav_desde_raiz antes de incluir este archivo.
$nav_carpeta = isset($nav_desde_raiz) ? "php/" : "";
$nav_inicio = isset($nav_desde_raiz) ? "index.php" : "../index.php";

// ¿Hay un usuario con sesión iniciada? ¿Es bibliotecario?
$nav_sesion = isset($_SESSION["id"]);
$nav_bibliotecario = $nav_sesion && isset($_SESSION["rol"]) && $_SESSION["rol"] == "bibliotecario";

// Página que se está viendo, para resaltar su enlace en el menú.
// La ficha y la compra de un libro pertenecen al catálogo, y la
// descarga pertenece a "Mis compras".
$nav_actual = basename($_SERVER["SCRIPT_NAME"]);
if ($nav_actual == "libro.php" || $nav_actual == "comprar_digital.php") {
    $nav_actual = "libros.php";
}
if ($nav_actual == "descargar.php") {
    $nav_actual = "mis_compras.php";
}

// Lista de enlaces del menú: archivo de destino y texto que se muestra.
if ($nav_sesion) {
    $nav_enlaces = [
        "libros.php" => "Catálogo",
        "perfil.php" => "Mi perfil",
        "mis_compras.php" => "Mis compras"
    ];

    // Enlaces de gestión, solo para bibliotecarios.
    if ($nav_bibliotecario) {
        $nav_enlaces["caja.php"] = "Caja";
        $nav_enlaces["operaciones.php"] = "Operaciones";
        $nav_enlaces["stock.php"] = "Gestionar stock";
        $nav_enlaces["libros_admin.php"] = "Gestionar libros";
        $nav_enlaces["usuarios_admin.php"] = "Gestionar usuarios";
        $nav_enlaces["resenas.php"] = "Gestionar reseñas";
    }
} else {
    $nav_enlaces = [
        "login.php" => "Iniciar sesión",
        "registro.php" => "Registrarse"
    ];
}
?>
    <!-- Aviso para la hoja de estilos de que hay JavaScript: agrega la clase
         "js" a la página. Con esa clase se activan las animaciones. Si el
         archivo main.js no llegara a cargar, a los 3 segundos se quita la
         clase para que nada quede oculto: la web funciona igual sin JavaScript. -->
    <script>
        document.documentElement.classList.add("js");
        setTimeout(function () {
            if (!window.BibliotecaLista) { document.documentElement.classList.remove("js"); }
        }, 3000);
    </script>
    <!-- JavaScript de todo el sitio (un solo archivo). "defer" hace que se
         ejecute cuando la página ya terminó de leerse. -->
    <script src="<?php echo isset($nav_desde_raiz) ? "js/main.js" : "../js/main.js"; ?>?v=7" defer></script>

    <!-- Cabecera del sitio (igual en todas las páginas): una sola barra con
         el menú a la izquierda y el saludo a la derecha. No lleva logo ni
         nombre: empieza directamente con los enlaces. -->
    <header>
        <div class="cabecera">
            <!-- Botón del menú para pantallas chicas. Viene oculto: lo muestra
                 main.js. Sin JavaScript el menú se ve siempre completo. -->
            <button class="menu-boton" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Abrir el menú" hidden>
                <span></span><span></span><span></span>
            </button>
            <nav id="menu-principal" aria-label="Menú principal">
                <!-- "Inicio" lleva a la página de inicio y conserva la sesión -->
                <a href="<?php echo $nav_inicio; ?>" <?php if ($nav_actual == "index.php") echo 'class="activo"'; ?>>Inicio</a>
                <?php foreach ($nav_enlaces as $nav_archivo => $nav_texto) { ?>
                    <a href="<?php echo $nav_carpeta . $nav_archivo; ?>" <?php if ($nav_actual == $nav_archivo) echo 'class="activo"'; ?>><?php echo $nav_texto; ?></a>
                <?php } ?>
                <!-- "Cerrar sesión" es el único enlace que cierra la sesión -->
                <?php if ($nav_sesion) { ?>
                    <a class="salir" href="<?php echo $nav_carpeta; ?>logout.php">Cerrar sesión</a>
                <?php } ?>
            </nav>
            <?php if ($nav_sesion) { ?>
                <span class="usuario">Hola, <?php echo htmlspecialchars($_SESSION["nombre"]); ?></span>
            <?php } ?>
        </div>
    </header>
