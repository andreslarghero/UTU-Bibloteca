<?php
// ============================================================
// CONTROLADOR: MI PERFIL
// Página para cualquier usuario con sesión iniciada: muestra sus
// datos y le permite cambiar su nombre, su correo y su contraseña.
// Para cambiar el correo o la contraseña tiene que confirmar su
// contraseña actual; para cambiar solo el nombre, no.
// El rol y el estado solo se muestran: aquí no se pueden cambiar.
// Cada usuario solo puede editar SU propia cuenta.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_usuario.php";
require_once __DIR__ . "/../modelos/usuario.php";

// La cuenta que se muestra y se edita es SIEMPRE la del usuario de la
// sesión. El id nunca se toma del formulario ni de la dirección, así
// nadie puede editar la cuenta de otra persona.
$id = $_SESSION["id"];

// Errores de validación que se muestran encima del formulario.
$errores = [];

// ------------------------------------------------------------
// DATOS ACTUALES DEL USUARIO
// No se pide la contraseña cifrada: nunca sale en la página.
// ------------------------------------------------------------
$perfil = usuario_por_id($conexion, $id);

// Valores que se muestran en el formulario.
$nombre = $perfil["nombre"];
$correo = $perfil["correo"];

// ------------------------------------------------------------
// GUARDAR LOS CAMBIOS
// Se ejecuta al enviar el formulario. Solo se usan el nombre, el
// correo y la contraseña: cualquier otro dato que llegue en el
// formulario (id, rol, activo...) se ignora.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge lo escrito. trim() quita los espacios sobrantes.
    $nombre = texto_de($_POST["nombre"] ?? null);
    $correo = texto_de($_POST["correo"] ?? null);
    $clave = texto_de($_POST["clave"] ?? null, false);
    $clave_actual = texto_de($_POST["clave_actual"] ?? null, false);

    // Qué quiere cambiar el usuario. Para cambiar solo el nombre no hace
    // falta la contraseña actual; para el correo o la contraseña, sí.
    $cambia_correo = ($correo != $perfil["correo"]);
    $cambia_clave = ($clave != "");

    // ---------- Validaciones ----------
    $errores = validar_nombre_correo($conexion, $nombre, $correo, $id);

    // ---------- Contraseña actual ----------
    // Si se cambia el correo o la contraseña (o las dos cosas), hay que
    // confirmar la contraseña actual. Se comprueba una sola vez.
    if ($cambia_correo || $cambia_clave) {
        // Se trae la contraseña cifrada de la cuenta de la sesión. Solo se usa
        // aquí para compararla; nunca se envía a la página.
        $clave_guardada = usuario_clave($conexion, $id);

        // password_verify compara lo escrito con la contraseña cifrada.
        // El mismo aviso sirve si se dejó vacía o si está mal.
        if (!$clave_guardada || !password_verify($clave_actual, $clave_guardada)) {
            $errores[] = "Contraseña actual incorrecta.";
        }
    }

    // ---------- Guardado ----------
    if (count($errores) == 0) {
        $guardado = false;

        try {
            if ($clave == "") {
                // Sin contraseña nueva: la contraseña actual no se toca.
                // Nunca se modifican el rol ni el estado.
                $guardado = usuario_actualizar_perfil($conexion, $id, $nombre, $correo);
                $aviso = "Perfil actualizado.";
            } else {
                // Con contraseña nueva: se guarda cifrada con password_hash.
                $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                $guardado = usuario_actualizar_perfil($conexion, $id, $nombre, $correo, $clave_segura);
                $aviso = "Perfil actualizado (con contraseña nueva).";
            }
        } catch (Throwable $e) {
            // Error inesperado de la base: no se muestran detalles internos.
            $guardado = false;
        }

        if ($guardado) {
            // Actualiza el nombre de la sesión para que el saludo cambie enseguida.
            $_SESSION["nombre"] = $nombre;

            // Guarda el aviso en la sesión y recarga la página, para que
            // al refrescar el navegador no se repita el guardado.
            aviso_guardar("perfil", $aviso);
            header("Location: perfil.php");
            exit;
        }

        $errores[] = "No se pudo guardar el perfil.";
    }
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
list($mensaje) = aviso_leer("perfil");

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/perfil.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Mi perfil";
require __DIR__ . "/../vistas/perfil.php";
