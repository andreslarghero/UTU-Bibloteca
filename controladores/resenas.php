<?php
// ============================================================
// CONTROLADOR: GESTIÓN DE RESEÑAS
// Página para el administrador: muestra todas las reseñas de
// todos los libros y permite eliminar las que no sean adecuadas.
// Solo pueden entrar los usuarios con admin = 1.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_bibliotecario.php";
require_once __DIR__ . "/../modelos/resena.php";

// Aquí se guarda el mensaje que confirma el borrado.
$mensaje = "";

// ------------------------------------------------------------
// ELIMINAR UNA RESEÑA
// Se ejecuta al pulsar "Eliminar" en una fila.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $resena_id = (int) $_POST["resena_id"];

    resena_eliminar($conexion, $resena_id);

    $mensaje = "Reseña eliminada correctamente.";
}

// Pide todas las reseñas con el título del libro y el nombre del usuario,
// de la más nueva a la más antigua.
$resenas = resena_listar_todas($conexion);

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/resenas.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Gestión de reseñas";
require __DIR__ . "/../vistas/resenas.php";
