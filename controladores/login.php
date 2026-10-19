<?php
// ============================================================
// CONTROLADOR: INICIO DE SESIÓN
// Muestra el formulario de login y, si el correo y la contraseña
// son correctos, abre la sesión y lleva al catálogo.
// ============================================================

// Inicia (o recupera) la sesión, que recuerda al usuario entre páginas.
session_start();

// Conecta con la base de datos y trae lo que usa este controlador.
require_once __DIR__ . "/../modelos/conexion.php";
require_once __DIR__ . "/../modelos/usuario.php";
require_once __DIR__ . "/ayudas.php";

// Aquí se guarda el mensaje de error que se muestra en el formulario.
$mensaje = "";

// ------------------------------------------------------------
// PROCESAR EL FORMULARIO
// Este bloque solo se ejecuta cuando se pulsa "Entrar".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge el correo y la contraseña escritos.
    $correo = trim($_POST["correo"]);
    $clave = $_POST["clave"];

    // Busca al usuario por su correo (con consulta preparada por seguridad).
    $usuario = usuario_por_correo($conexion, $correo);

    // Comprueba que el usuario exista, que esté activo y que la contraseña
    // coincida con la cifrada. A un usuario dado de baja (activo = 0) se le
    // muestra el mismo aviso que a una contraseña incorrecta, sin dar pistas.
    if ($usuario && $usuario["activo"] == 1 && password_verify($clave, $usuario["clave"])) {
        // Guarda los datos del usuario en la sesión y lo lleva al catálogo.
        $_SESSION["id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["rol"] = $usuario["rol"];       // "cliente" o "bibliotecario"

        header("Location: libros.php");
        exit;
    } else {
        // Datos incorrectos: se muestra un aviso.
        $mensaje = "Correo o contraseña incorrectos.";
    }
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/login.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Iniciar sesión";
require __DIR__ . "/../vistas/login.php";
