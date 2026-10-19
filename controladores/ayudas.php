<?php
// ============================================================
// CONTROLADOR: AYUDAS COMPARTIDAS
// Funciones que usan varios controladores para leer lo que envía
// el navegador, validar datos y guardar avisos entre páginas.
// No dibujan HTML (eso es de las vistas) ni consultan tablas
// directamente (eso es de los modelos).
// ============================================================

require_once __DIR__ . "/../modelos/usuario.php";

// Texto enviado por el navegador, sin espacios sobrantes. Si el
// valor no existe o no es un texto, devuelve "". Con $recortar en
// false se devuelve tal cual (para contraseñas).
function texto_de($valor, $recortar = true) {
    if (!is_string($valor)) {
        return "";
    }
    return $recortar ? trim($valor) : $valor;
}

// Número entero positivo enviado por el navegador. Solo se acepta
// un valor formado por dígitos; cualquier otra cosa devuelve 0.
function entero_de($valor) {
    return (is_string($valor) && ctype_digit($valor)) ? (int) $valor : 0;
}

// Cambia las letras con tilde y la ñ por su letra simple.
function quitar_tildes($texto) {
    return strtr($texto, [
        "á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n",
        "Á" => "A", "É" => "E", "Í" => "I", "Ó" => "O", "Ú" => "U", "Ü" => "U", "Ñ" => "N"
    ]);
}

// Comprueba en la base que el usuario de la sesión sigue existiendo
// y activo, y actualiza su nombre y su rol en la sesión. Si ya no
// existe o fue dado de baja, cierra la sesión y devuelve false.
function actualizar_sesion($conexion) {
    $usuario = usuario_por_id($conexion, $_SESSION["id"]);

    if (!$usuario || $usuario["activo"] != 1) {
        $_SESSION = [];
        session_destroy();
        return false;
    }

    $_SESSION["nombre"] = $usuario["nombre"];
    $_SESSION["rol"] = $usuario["rol"];
    return true;
}

// Valida el nombre y el correo de una cuenta. $id es el usuario que
// se edita (0 si es una cuenta nueva): así puede conservar su propio
// correo. Devuelve la lista de errores (vacía si todo está bien).
function validar_nombre_correo($conexion, $nombre, $correo, $id = 0) {
    $errores = [];

    if ($nombre == "") {
        $errores[] = "El nombre es obligatorio.";
    } elseif (strlen($nombre) > 100) {
        $errores[] = "El nombre es demasiado largo.";
    }

    if ($correo == "") {
        $errores[] = "El correo es obligatorio.";
    } elseif (strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo no es válido.";
    } elseif (usuario_correo_en_uso($conexion, $correo, $id)) {
        $errores[] = "Ya existe otro usuario con ese correo.";
    }

    return $errores;
}

// Aviso de una página que se muestra una sola vez después de
// redirigir (así, al refrescar el navegador, la acción no se repite).
// $pagina identifica la página; $tipo es "exito" o "error".
function aviso_guardar($pagina, $mensaje, $tipo = "exito") {
    $_SESSION["aviso_" . $pagina] = [$mensaje, $tipo];
}

// Devuelve [mensaje, tipo] del aviso guardado (o ["", "error"]) y lo borra.
function aviso_leer($pagina) {
    $aviso = isset($_SESSION["aviso_" . $pagina]) ? $_SESSION["aviso_" . $pagina] : ["", "error"];
    unset($_SESSION["aviso_" . $pagina]);
    return $aviso;
}
?>
