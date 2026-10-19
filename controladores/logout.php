<?php
// ============================================================
// CONTROLADOR: CERRAR SESIÓN
// Borra la sesión del usuario y lo devuelve al login.
// ============================================================

// Recupera la sesión actual para poder cerrarla.
session_start();

// Elimina todos los datos de la sesión (el usuario deja de estar conectado).
session_destroy();

// Redirige a la página de inicio de sesión.
header("Location: login.php");
exit;
?>
