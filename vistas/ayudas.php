<?php
// ============================================================
// VISTA: AYUDAS DE PRESENTACIÓN
// Funciones que solo sirven para MOSTRAR datos en las vistas:
// formato de precios, estrellas y portadas. No consultan la base
// ni toman decisiones del negocio.
// ============================================================

// Versiones de la hoja de estilos y del JavaScript. Al cambiar un
// archivo se sube el número aquí (un solo lugar) y los navegadores
// descargan la versión nueva.
define("CSS_VERSION", 30);
define("JS_VERSION", 8);

// La validación del archivo de portada es del modelo.
require_once __DIR__ . "/../modelos/portada.php";

// Precio con el formato del sitio: 1.234,50
function formatearPrecio($precio) {
    return number_format($precio, 2, ",", ".");
}

// Convierte un número (1 a 5) en estrellas llenas y vacías, por ejemplo 3 = ★★★☆☆.
function estrellas($numero) {
    $llenas = (int) round($numero);
    return str_repeat("★", $llenas) . str_repeat("☆", 5 - $llenas);
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
    $en_carpeta_php = realpath(dirname($_SERVER["SCRIPT_FILENAME"])) === realpath(__DIR__ . "/../php");
    return ($en_carpeta_php ? "../" : "") . $local;
}

// Dibuja una portada: la imagen o, si el archivo no está, un reemplazo
// propio (nunca una imagen rota).
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
?>
