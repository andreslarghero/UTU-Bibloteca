<?php
// ============================================================
// CONTROLADOR: CONTROL DE ACCESO GENERAL
// Este archivo se incluye al principio de todas las páginas que
// necesitan sesión (catálogo, ficha del libro y, a través de
// verificar_bibliotecario.php, las páginas de administración).
// Inicia la sesión, conecta con la base de datos y comprueba EN
// LA BASE, en cada petición, que el usuario sigue existiendo y
// sigue activo (activo = 1).
// No alcanza con mirar la sesión: sus datos se guardaron al
// iniciar sesión y pueden haber quedado viejos si después dieron
// de baja al usuario o le cambiaron el rol.
// ============================================================

// Inicia (o recupera) la sesión del usuario.
session_start();

// Si no hay sesión, manda al login.
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// Conecta con la base de datos y trae las ayudas compartidas.
require_once __DIR__ . "/../modelos/conexion.php";
require_once __DIR__ . "/ayudas.php";

// Comprueba en la base el estado ACTUAL del usuario (actualizar_sesion,
// en ayudas.php). Si el usuario ya no existe o fue dado de baja, la
// función cierra su sesión por completo y se lo manda al login. El "exit"
// impide que se ejecute cualquier acción de la página (por ejemplo,
// guardar una reseña). Si sigue activo, el nombre y el rol de la sesión
// quedan con los que tiene ahora en la base, no los que tenía al entrar.
if (!actualizar_sesion($conexion)) {
    header("Location: login.php");
    exit;
}
?>
