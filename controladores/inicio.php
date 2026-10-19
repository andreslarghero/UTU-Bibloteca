<?php
// ============================================================
// CONTROLADOR: PÁGINA DE INICIO
// Primera página del sitio. La puede ver cualquiera: no hace
// falta haber iniciado sesión.
// Si el usuario YA inició sesión, la conserva: esta página no
// cierra la sesión ni la modifica. Solo "Cerrar sesión" la cierra.
// ============================================================

// Recupera la sesión (si existe) para saber si hay un usuario dentro.
session_start();

// Conecta con la base de datos y trae lo que usa este controlador.
require_once __DIR__ . "/../modelos/conexion.php";
require_once __DIR__ . "/../modelos/libro.php";
require_once __DIR__ . "/ayudas.php";

// ------------------------------------------------------------
// USUARIO CON SESIÓN
// Si hay sesión, se comprueba en la base que el usuario sigue
// existiendo y sigue activo, igual que en el resto de las
// páginas, y se actualizan su nombre y su rol para el menú.
// Solo a un usuario dado de baja se le cierra la sesión.
// ------------------------------------------------------------
if (isset($_SESSION["id"])) {
    actualizar_sesion($conexion);
}

// ------------------------------------------------------------
// LIBROS DEL BLOQUE PRINCIPAL
// ------------------------------------------------------------
// Cuatro libros con portada para la composición de la derecha.
$libros_hero = libro_portadas_inicio($conexion);

// Dato que usa la página para decidir qué botón mostrar.
$con_sesion = isset($_SESSION["id"]);

// Avisa al menú de que esta página está en la carpeta principal.
$nav_desde_raiz = true;

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/inicio.php. Estas variables se usan allí.
// ------------------------------------------------------------
require __DIR__ . "/../vistas/inicio.php";
