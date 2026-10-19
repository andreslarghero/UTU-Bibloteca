<?php
// ============================================================
// MODELO: ARCHIVOS DE PORTADA
// Las portadas son archivos del propio proyecto, guardados en la
// carpeta "img/portadas". En la base, el campo "portada" guarda la
// ruta relativa (por ejemplo: img/portadas/1984.webp), así el sitio
// funciona igual en cualquier carpeta donde se copie.
//
// Este archivo solo valida y ubica el archivo. El HTML de la portada
// se arma en vistas/ayudas.php (portada_html).
// ============================================================

// Una portada local solo puede ser un archivo de imagen que esté
// directamente dentro de la carpeta img/portadas.
// Devuelve la ruta ya normalizada (img/portadas/archivo.ext) si es
// válida y el archivo existe; si no, devuelve "".
// La usan "Gestionar libros" (antes de guardar) y las vistas que
// muestran portadas (antes de dibujar la imagen): así, aunque en la
// base hubiera un valor incorrecto, nunca se usa.
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
?>
