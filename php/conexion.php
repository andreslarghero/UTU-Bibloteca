<?php
// ============================================================
// CONEXIÓN A LA BASE DE DATOS
// Este archivo se incluye en las demás páginas PHP para que
// puedan leer y guardar datos en MySQL.
// ============================================================

// Datos de acceso a MySQL (los valores por defecto de XAMPP).
// Si tu MySQL usa otro usuario o contraseña, cámbialos aquí.
$servidor = "localhost";
$usuario = "root";
$clave = "";
$base_datos = "biblioteca";

// Abre la conexión con la base de datos "biblioteca".
$conexion = mysqli_connect($servidor, $usuario, $clave, $base_datos);

// Si la conexión falla, detiene la página y muestra el motivo.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Usa UTF-8 para que las tildes y la ñ se vean bien.
mysqli_set_charset($conexion, "utf8");
?>
