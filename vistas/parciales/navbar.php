<?php
// ============================================================
// VISTA PARCIAL: ENCABEZADO Y MENÚ DE NAVEGACIÓN
// Se incluye en todas las páginas (desde cabecera.php) para que el
// menú sea siempre el mismo. Los enlaces los da el propio usuario
// (polimorfismo: un Cliente y un Bibliotecario devuelven menús
// distintos con enlacesMenu()):
//   - sin sesión:     Inicio, Iniciar sesión, Registrarse
//   - cliente:        Inicio, Catálogo, Mi perfil, Mis compras, Cerrar sesión
//   - bibliotecario:  lo anterior y, además, los enlaces de gestión
//
// OJO: el menú es solo navegación, NO es seguridad. Que un enlace
// no se muestre no impide entrar escribiendo la dirección: de eso
// se encargan los controladores (requerirSesion y requerirBibliotecario)
// al principio de cada acción.
//
// Variables que recibe: $usuarioMenu (el usuario con sesión, o null) y
// $menuActivo (el identificador del enlace que se resalta).
// ============================================================
$nav_enlaces = $usuarioMenu ? $usuarioMenu->enlacesMenu() : Usuario::enlacesInvitado();
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
    <script src="js/main.js?v=<?php echo JS_VERSION; ?>" defer></script>

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
                <a href="index.php" <?php if ($menuActivo == "inicio") echo 'class="activo"'; ?>>Inicio</a>
                <?php foreach ($nav_enlaces as $nav_enlace) { ?>
                    <a href="<?php echo Ruta::h($nav_enlace["controlador"], $nav_enlace["accion"]); ?>" <?php if ($menuActivo == $nav_enlace["id"]) echo 'class="activo"'; ?>><?php echo $nav_enlace["texto"]; ?></a>
                <?php } ?>
                <!-- "Cerrar sesión" es el único enlace que cierra la sesión -->
                <?php if ($usuarioMenu) { ?>
                    <a class="salir" href="<?php echo Ruta::h("auth", "salir"); ?>">Cerrar sesión</a>
                <?php } ?>
            </nav>
            <?php if ($usuarioMenu) { ?>
                <span class="usuario">Hola, <?php echo htmlspecialchars($usuarioMenu->getNombre()); ?></span>
            <?php } ?>
        </div>
    </header>
