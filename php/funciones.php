<?php
// ============================================================
// FUNCIONES COMPARTIDAS
// Ayudas que usan varias páginas. Este archivo se incluye desde
// conexion.php, así que está disponible en todas las páginas que
// se conectan a la base de datos.
// ============================================================

// Versiones de la hoja de estilos y del JavaScript. Al cambiar un
// archivo se sube el número aquí (un solo lugar) y los navegadores
// descargan la versión nueva.
define("CSS_VERSION", 29);
define("JS_VERSION", 8);

// Precio con el formato del sitio: 1.234,50
function formatearPrecio($precio) {
    return number_format($precio, 2, ",", ".");
}

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

// Ejecuta una consulta preparada que devuelve UNA fila y la devuelve
// como arreglo (o null si no hay). $sql lleva "?" donde van los valores;
// $tipos son sus tipos ("i" entero, "s" texto, "d" decimal), en el
// mismo orden que $valores. Los valores nunca se pegan en el SQL.
function buscar_fila($conexion, $sql, $tipos, ...$valores) {
    $consulta = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($consulta, $tipos, ...$valores);
    mysqli_stmt_execute($consulta);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
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
    $usuario = buscar_fila($conexion, "SELECT nombre, rol, activo FROM usuarios WHERE id = ?", "i", $_SESSION["id"]);

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
    } else {
        if (buscar_fila($conexion, "SELECT id FROM usuarios WHERE correo = ? AND id <> ?", "si", $correo, $id)) {
            $errores[] = "Ya existe otro usuario con ese correo.";
        }
    }

    return $errores;
}

// ¿Este cliente ya compró este libro en formato digital?
function cliente_compro_digital($conexion, $id_cliente, $libro_id) {
    $fila = buscar_fila($conexion,
        "SELECT COUNT(*) AS total FROM venta_lineas
         JOIN ventas ON ventas.id = venta_lineas.venta_id
         WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'",
        "ii", $id_cliente, $libro_id);
    return $fila["total"] > 0;
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
