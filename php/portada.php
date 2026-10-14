<?php
// ============================================================
// PORTADA DE UN LIBRO
// Funciones que arman el HTML de una portada, para usarla igual
// en todas las páginas (catálogo, carrusel, ficha, stock e inicio).
//
// Las portadas son archivos del propio proyecto, guardados en la
// carpeta "img/portadas". En la base, el campo "portada" guarda la
// ruta relativa (por ejemplo: img/portadas/1984.webp), así el sitio
// funciona igual en cualquier carpeta donde se copie.
//
// Siempre se dibuja primero un "reemplazo" propio: un recuadro sobrio
// con el icono de un libro, las iniciales y el título. Si el libro
// tiene imagen, la imagen se coloca encima y lo tapa. Así, cuando
// un libro no tiene portada (o el archivo no existe), nunca queda
// un cuadro vacío ni una imagen rota.
// ============================================================

// function_exists evita un error si el archivo se incluye dos veces.
if (!function_exists("portada_html")) {

    // --------------------------------------------------------
    // VALIDACIÓN DE UNA PORTADA LOCAL
    // Una portada local solo puede ser un archivo de imagen que
    // esté directamente dentro de la carpeta img/portadas.
    // Devuelve la ruta ya normalizada (img/portadas/archivo.ext)
    // si es válida y el archivo existe; si no, devuelve "".
    // La usan el formulario de "Gestionar libros" (antes de
    // guardar) y las páginas que muestran portadas (antes de
    // dibujar la imagen): así, aunque en la base hubiera un
    // valor incorrecto, nunca se usa.
    // --------------------------------------------------------
    function portada_local($portada) {
        // 1. Normalizar los separadores: las barras de Windows pasan a "/".
        $ruta = str_replace("\\", "/", trim((string) $portada));

        // 2. Rechazar lo que intenta salir de la carpeta: "..", una ruta
        //    absoluta ("/..." o "C:...") y caracteres de control.
        if ($ruta === "" || strpos($ruta, "..") !== false || $ruta[0] === "/"
            || preg_match('/^[A-Za-z]:/', $ruta) || preg_match('/[\x00-\x1F]/', $ruta)) {
            return "";
        }

        // 3. Tiene que empezar exactamente por "img/portadas/". Cualquier
        //    otra carpeta (php/, digitales/, sql/, js/, css/...) se rechaza.
        $prefijo = "img/portadas/";
        if (strpos($ruta, $prefijo) !== 0) {
            return "";
        }

        // 4. Después del prefijo solo puede venir el nombre del archivo
        //    (sin más carpetas), con letras, números y guiones, y con una
        //    de las extensiones de imagen permitidas.
        $nombre = substr($ruta, strlen($prefijo));
        if (!preg_match('/^[A-Za-z0-9_-]+\.(webp|jpg|jpeg|png)$/', $nombre)) {
            return "";
        }

        // 5. El archivo tiene que existir de verdad y estar dentro de la
        //    carpeta img/portadas. realpath() resuelve la ubicación real,
        //    y se comprueba que su carpeta sea exactamente esa.
        $carpeta = realpath(dirname(__DIR__) . "/img/portadas");
        $archivo = ($carpeta === false) ? false : realpath($carpeta . DIRECTORY_SEPARATOR . $nombre);
        if ($archivo === false || !is_file($archivo) || dirname($archivo) !== $carpeta) {
            return "";
        }

        return $prefijo . $nombre;
    }

    // Devuelve la dirección que hay que poner en el "src" de la imagen,
    // o "" si el libro no tiene una portada que se pueda mostrar.
    function portada_src($portada) {
        $portada = trim((string) $portada);

        // Dirección web completa (un libro cargado con una imagen de otro sitio).
        if (preg_match('#^https?://#i', $portada)) {
            return $portada;
        }

        // Portada local: tiene que pasar toda la validación. Si no, se
        // usa el reemplazo.
        $local = portada_local($portada);
        if ($local === "") {
            return "";
        }

        // La ruta guardada es relativa a la carpeta principal del proyecto.
        // Las páginas que están dentro de "php" tienen que subir un nivel.
        $en_carpeta_php = realpath(dirname($_SERVER["SCRIPT_FILENAME"])) === realpath(__DIR__);
        return ($en_carpeta_php ? "../" : "") . $local;
    }

    // $titulo:  título del libro (para las iniciales y el texto alternativo)
    // $portada: valor del campo "portada" de la base, o vacío si no tiene
    // $critica: true para las imágenes importantes que deben cargarse
    //           enseguida; las demás se cargan cuando se acercan a la
    //           pantalla (loading="lazy")
    function portada_html($titulo, $portada, $critica = false) {
        $titulo = trim((string) $titulo);

        // Iniciales: la primera letra de las dos primeras palabras.
        $iniciales = "";
        foreach (preg_split('/\s+/u', $titulo) as $palabra) {
            if ($palabra !== "" && mb_strlen($iniciales) < 2) {
                $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
            }
        }

        $html = '<div class="portada-marco" data-portada>';

        // Reemplazo: icono de libro, iniciales y título.
        $html .= '<div class="portada-vacia" aria-hidden="true">'
               . '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
               . '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/></svg>'
               . '<span class="portada-inicial">' . htmlspecialchars($iniciales) . '</span>'
               . '<span class="portada-titulo">' . htmlspecialchars($titulo) . '</span>'
               . '</div>';

        // Imagen (solo si hay una portada que se pueda mostrar).
        // htmlspecialchars evita que la dirección o el título se
        // interpreten como HTML.
        $src = portada_src($portada);
        if ($src !== "") {
            $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($titulo) . '"'
                   . ($critica ? '' : ' loading="lazy"') . '>';
        }

        $html .= '</div>';
        return $html;
    }
}
?>
