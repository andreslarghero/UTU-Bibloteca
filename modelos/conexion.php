<?php
// ============================================================
// MODELO: CONEXIÓN A LA BASE DE DATOS
// Todos los controladores incluyen este archivo (con require_once)
// para poder leer y guardar datos en MySQL. Además trae dos
// funciones de apoyo que usan los demás modelos para consultar.
// ============================================================

// Datos de acceso a MySQL (los valores por defecto de XAMPP).
// Si tu MySQL usa otro usuario o contraseña, cámbialos aquí.
$servidor = "localhost";
$usuario = "root";
$clave = "";
$base_datos = "biblioteca";

// Abre la conexión con la base de datos "biblioteca".
$conexion = mysqli_connect($servidor, $usuario, $clave, $base_datos);

// Si la conexión falla, detiene la página y muestra el motivo.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Usa UTF-8 para que las tildes y la ñ se vean bien.
mysqli_set_charset($conexion, "utf8");

// Ejecuta una consulta preparada y devuelve el resultado completo.
// $sql lleva "?" donde van los valores; $tipos son sus tipos ("i" entero,
// "s" texto, "d" decimal), en el mismo orden que $valores. Los valores
// nunca se pegan en el SQL (así no hay inyección SQL).
function consultar($conexion, $sql, $tipos = "", ...$valores) {
    $consulta = mysqli_prepare($conexion, $sql);
    if ($tipos != "") {
        mysqli_stmt_bind_param($consulta, $tipos, ...$valores);
    }
    mysqli_stmt_execute($consulta);
    return $consulta;
}

// Devuelve UNA fila como arreglo (o null si no hay).
function buscar_fila($conexion, $sql, $tipos = "", ...$valores) {
    $consulta = consultar($conexion, $sql, $tipos, ...$valores);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
}

// Devuelve TODAS las filas como una lista de arreglos.
function buscar_filas($conexion, $sql, $tipos = "", ...$valores) {
    $consulta = consultar($conexion, $sql, $tipos, ...$valores);
    return mysqli_fetch_all(mysqli_stmt_get_result($consulta), MYSQLI_ASSOC);
}

// Ejecuta una instrucción que modifica datos (INSERT, UPDATE, DELETE).
// Devuelve true si se ejecutó bien.
function ejecutar($conexion, $sql, $tipos = "", ...$valores) {
    $consulta = mysqli_prepare($conexion, $sql);
    if ($tipos != "") {
        mysqli_stmt_bind_param($consulta, $tipos, ...$valores);
    }
    return mysqli_stmt_execute($consulta);
}
?>
