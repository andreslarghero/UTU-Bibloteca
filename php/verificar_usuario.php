<?php
// ============================================================
// CONTROL DE ACCESO GENERAL
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

// Conecta con la base de datos.
include "conexion.php";

// Busca el estado ACTUAL del usuario de la sesión (consulta preparada).
$consulta = mysqli_prepare($conexion, "SELECT nombre, rol, activo FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($consulta, "i", $_SESSION["id"]);
mysqli_stmt_execute($consulta);
$usuario_sesion = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

// Si el usuario ya no existe o fue dado de baja, se cierra su sesión
// por completo y se lo manda al login. El "exit" impide que se
// ejecute cualquier acción de la página (por ejemplo, guardar una reseña).
if (!$usuario_sesion || $usuario_sesion["activo"] != 1) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

// Se actualizan los datos de la sesión con los de la base, para que
// las páginas vean el nombre y el rol que el usuario tiene ahora y
// no los que tenía al entrar. $_SESSION["admin"] se sigue calculando
// a partir del rol (bibliotecario = 1, cliente = 0).
$_SESSION["nombre"] = $usuario_sesion["nombre"];
$_SESSION["rol"] = $usuario_sesion["rol"];
$_SESSION["admin"] = ($usuario_sesion["rol"] == "bibliotecario") ? 1 : 0;
?>
