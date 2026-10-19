<?php
// ============================================================
// VISTA PARCIAL: CABECERA COMÚN DE LAS PÁGINAS
// Abre el documento HTML, el <head> y el <body>, e incluye el menú.
// El controlador (a través de render) define antes:
//   $tituloPagina  texto del título de la pestaña
//   $claseBody     clase del <body> (puede estar vacía)
// El pie correspondiente está en pie.php.
// ============================================================
// Las ayudas de presentación (precios, portadas, versiones) se cargan aquí.
require_once __DIR__ . "/../ayudas.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina); ?> - Biblioteca Digital</title>
    <link rel="stylesheet" href="css/estilos.css?v=<?php echo CSS_VERSION; ?>">
</head>
<body<?php if (!empty($claseBody)) { echo ' class="' . htmlspecialchars($claseBody) . '"'; } ?>>
<?php include __DIR__ . "/navbar.php"; ?>
