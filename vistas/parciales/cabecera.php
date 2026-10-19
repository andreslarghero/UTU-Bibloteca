<?php
// ============================================================
// VISTA PARCIAL: CABECERA COMÚN DE LAS PÁGINAS
// Abre el documento HTML, el <head> y el <body>, e incluye el menú.
// El controlador define antes:
//   $titulo_pagina  texto del título de la pestaña (obligatorio)
//   $clase_body     clase del <body> (opcional)
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
    <title><?php echo htmlspecialchars($titulo_pagina); ?> - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo CSS_VERSION; ?>">
</head>
<body<?php if (!empty($clase_body)) { echo ' class="' . htmlspecialchars($clase_body) . '"'; } ?>>
<?php include __DIR__ . "/navbar.php"; ?>
