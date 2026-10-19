<?php
// ============================================================
// CONTROLADOR: DESCARGA DE UN LIBRO DIGITAL
// Entrega el archivo PDF o EPUB de un libro, pero SOLO si el
// usuario de la sesión lo compró en formato digital.
// Se abre desde "Mis compras" con descargar.php?libro=NÚMERO.
//
// Los archivos están en la carpeta "digitales", que no se puede
// abrir desde el navegador. Esta página es la única puerta: lee
// el archivo y lo envía después de comprobar la compra.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_usuario.php";
require_once __DIR__ . "/../modelos/venta.php";

// ------------------------------------------------------------
// ID DEL LIBRO
// Lo único que llega del navegador es el id del libro. Solo se
// acepta un valor formado por dígitos; cualquier otra cosa se
// trata como 0 (un libro que no existe).
// ------------------------------------------------------------
$libro_id = entero_de($_GET["libro"] ?? null);

// ------------------------------------------------------------
// ¿ESTE USUARIO COMPRÓ ESTE LIBRO EN DIGITAL?
// La consulta exige todo a la vez:
//  - una venta cuyo cliente es el usuario de la sesión,
//  - con una línea de este libro,
//  - en formato digital,
//  - y que el libro tenga un archivo digital guardado.
// Una venta de mostrador (sin cliente) o una compra en formato
// físico no dan derecho a la descarga.
// ------------------------------------------------------------
$compra = venta_libro_descargable($conexion, $_SESSION["id"], $libro_id);

// Ya no hace falta la base de datos.
mysqli_close($conexion);
unset($conexion);

// ------------------------------------------------------------
// UBICAR EL ARCHIVO
// La ubicación se arma con el nombre guardado en la base, nunca
// con algo escrito por el usuario. basename() se queda solo con
// el nombre (quita cualquier carpeta), y después se comprueba
// que el archivo existe y que está dentro de la carpeta "digitales".
// ------------------------------------------------------------
$error = "";
$ruta = "";
$extension = "";

if (!$compra) {
    // El mismo aviso sirve si el libro no existe, si no es digital o
    // si es de otra persona: así no se revela cuál es el caso.
    $error = "No puedes descargar este libro.";
} else {
    $nombre = basename($compra["archivo_digital"]);
    $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $carpeta = realpath(__DIR__ . "/../digitales");
    $ruta = ($carpeta === false) ? false : realpath($carpeta . DIRECTORY_SEPARATOR . $nombre);

    if ($ruta === false || !is_file($ruta) || strpos($ruta, $carpeta . DIRECTORY_SEPARATOR) !== 0
        || ($extension != "pdf" && $extension != "epub")) {
        $error = "El archivo de este libro no está disponible en este momento.";
    }
}

// ------------------------------------------------------------
// ENTREGAR EL ARCHIVO
// ------------------------------------------------------------
if ($error == "") {
    // Nombre con el que se guarda en el equipo del usuario: el título del
    // libro. El nombre real del archivo en el servidor nunca se muestra.
    // Se envía dos veces, como indica el estándar:
    //  - "filename": versión simple, sin acentos ni símbolos, que entiende
    //    cualquier navegador;
    //  - "filename*": el título completo con sus acentos (en UTF-8), que es
    //    el que usan los navegadores actuales.
    $titulo = trim(preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]/u', "", $compra["titulo"]));
    $titulo_simple = quitar_tildes($titulo);
    $titulo_simple = trim(preg_replace("/[^A-Za-z0-9 _-]/", "", $titulo_simple));
    if ($titulo == "" || $titulo_simple == "") {
        $titulo = "libro";
        $titulo_simple = "libro";
    }

    // Cabeceras: le dicen al navegador qué tipo de archivo es y que lo
    // descargue ("attachment") en lugar de intentar abrirlo como una página.
    header("Content-Type: " . (($extension == "pdf") ? "application/pdf" : "application/epub+zip"));
    header("Content-Disposition: attachment; filename=\"" . $titulo_simple . "." . $extension . "\"; filename*=UTF-8''" . rawurlencode($titulo . "." . $extension));
    header("Content-Length: " . filesize($ruta));
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store");

    // Envía el contenido del archivo y termina.
    readfile($ruta);
    exit;
}

// ------------------------------------------------------------
// NO SE PUEDE DESCARGAR
// Se muestra una página con el aviso, sin ningún dato interno.
// ------------------------------------------------------------
http_response_code($compra ? 404 : 403);

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/descargar.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Descarga";
require __DIR__ . "/../vistas/descargar.php";
