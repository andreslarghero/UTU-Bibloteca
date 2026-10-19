<?php
// ============================================================
// CONTROLADOR: REGISTRO
// Muestra el formulario para crear una cuenta y, al enviarlo,
// guarda el nuevo usuario en la tabla "usuarios".
// ============================================================

// Recupera la sesión (si existe), solo para que el menú sepa si ya hay
// un usuario dentro. Esta página no la modifica.
session_start();

// Conecta con la base de datos y trae lo que usa este controlador.
require_once __DIR__ . "/../modelos/conexion.php";
require_once __DIR__ . "/../modelos/usuario.php";
require_once __DIR__ . "/ayudas.php";

// Aquí se guarda el mensaje de error que se muestra en el formulario.
$mensaje = "";

// ------------------------------------------------------------
// PROCESAR EL FORMULARIO
// Este bloque solo se ejecuta cuando se pulsa "Registrarse".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge los datos escritos en el formulario. Si algún campo no
    // llega (o no es un texto), se toma como vacío.
    $nombre = texto_de($_POST["nombre"] ?? null);
    $correo = texto_de($_POST["correo"] ?? null);
    $clave = texto_de($_POST["clave"] ?? null, false);

    // VALIDACIÓN EN EL SERVIDOR (las mismas reglas que "Mi perfil" y
    // "Gestionar usuarios", en ayudas.php). El "required" del
    // formulario es solo una ayuda del navegador: la comprobación que
    // vale es esta. Si algo falla, se muestra el primer error y no se
    // guarda nada en la base.
    if ($nombre === "" || $correo === "" || trim($clave) === "") {
        $mensaje = "Completa el nombre, el correo y la contraseña.";
    } else {
        $errores = validar_nombre_correo($conexion, $nombre, $correo);
        if (count($errores) > 0) {
            $mensaje = $errores[0];
        }
    }

    if ($mensaje == "") {
        // Cifra la contraseña antes de guardarla.
        $clave_segura = password_hash($clave, PASSWORD_DEFAULT);

        // Guarda el nuevo usuario. Si la base falla, no se muestran
        // detalles internos.
        try {
            usuario_crear($conexion, $nombre, $correo, $clave_segura);

            // Lleva al login con un aviso de que la cuenta se creó.
            header("Location: login.php?registrado=1");
            exit;
        } catch (Throwable $e) {
            $mensaje = "No se pudo crear la cuenta.";
        }
    }
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/registro.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Registro";
require __DIR__ . "/../vistas/registro.php";
