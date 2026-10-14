<?php
// ============================================================
// CONTROL DE ACCESO DE LAS PÁGINAS DEL BIBLIOTECARIO
// Este archivo se incluye al principio de todas las páginas de
// administración (caja, operaciones, stock, reseñas, libros y
// usuarios). Hace dos comprobaciones, las dos contra la base de
// datos y en cada petición:
//   1. Que el usuario sigue existiendo y sigue activo.
//   2. Que sigue siendo bibliotecario.
// ============================================================

// 1. Control general: inicia la sesión, conecta con la base y
//    comprueba que el usuario existe y está activo. Si no, cierra
//    su sesión y lo manda al login. También deja en la sesión el
//    rol que el usuario tiene ahora en la base.
include "verificar_usuario.php";

// 2. Si ya no es bibliotecario, pierde el acceso y vuelve al catálogo.
//    El "exit" impide que se ejecute cualquier acción de la página.
if ($_SESSION["rol"] != "bibliotecario") {
    header("Location: libros.php");
    exit;
}
?>
