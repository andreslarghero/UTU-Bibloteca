<?php
// ============================================================
// MODELO: USUARIOS
// Todas las consultas de la tabla "usuarios". Las funciones solo
// leen y guardan datos: las reglas (permisos, validaciones y
// mensajes) las decide el controlador.
// La contraseña cifrada solo sale de aquí en usuario_por_correo()
// (para el login) y usuario_clave() (para confirmarla en el perfil).
// ============================================================

// Un usuario por su id, sin la contraseña. Devuelve null si no existe.
function usuario_por_id($conexion, $id) {
    return buscar_fila($conexion,
        "SELECT id, nombre, correo, rol, activo FROM usuarios WHERE id = ?",
        "i", $id);
}

// Un usuario por su correo, CON la contraseña cifrada (solo para el login).
function usuario_por_correo($conexion, $correo) {
    return buscar_fila($conexion,
        "SELECT id, nombre, clave, rol, activo FROM usuarios WHERE correo = ?",
        "s", $correo);
}

// Solo el id de un usuario ACTIVO con ese correo (o null).
function usuario_activo_por_correo($conexion, $correo) {
    return buscar_fila($conexion, "SELECT id FROM usuarios WHERE correo = ? AND activo = 1", "s", $correo);
}

// La contraseña cifrada de un usuario (solo para comprobar la actual).
function usuario_clave($conexion, $id) {
    $fila = buscar_fila($conexion, "SELECT clave FROM usuarios WHERE id = ?", "i", $id);
    return $fila ? $fila["clave"] : null;
}

// ¿Ya hay OTRO usuario con ese correo? $excluir_id es el usuario que se
// edita (0 si es una cuenta nueva): así puede conservar su propio correo.
function usuario_correo_en_uso($conexion, $correo, $excluir_id = 0) {
    return (bool) buscar_fila($conexion, "SELECT id FROM usuarios WHERE correo = ? AND id <> ?", "si", $correo, $excluir_id);
}

// Todos los usuarios, activos e inactivos, sin la contraseña.
function usuario_listar($conexion) {
    return buscar_filas($conexion, "SELECT id, nombre, correo, rol, activo FROM usuarios ORDER BY id");
}

// Cuántos bibliotecarios activos hay. Se usa para no dejar nunca el
// sistema sin ninguno.
function usuario_contar_bibliotecarios_activos($conexion) {
    $fila = buscar_fila($conexion, "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'bibliotecario' AND activo = 1");
    return $fila["total"];
}

// Crea un usuario activo. $clave_segura ya viene cifrada con password_hash.
function usuario_crear($conexion, $nombre, $correo, $clave_segura, $rol = "cliente") {
    return ejecutar($conexion,
        "INSERT INTO usuarios (nombre, correo, clave, rol, activo) VALUES (?, ?, ?, ?, 1)",
        "ssss", $nombre, $correo, $clave_segura, $rol);
}

// Cambia los datos de acceso de la cuenta propia. Nunca modifica el rol
// ni el estado. Con $clave_segura en null, la contraseña no se toca.
function usuario_actualizar_perfil($conexion, $id, $nombre, $correo, $clave_segura = null) {
    if ($clave_segura === null) {
        return ejecutar($conexion, "UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?",
            "ssi", $nombre, $correo, $id);
    }
    return ejecutar($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, clave = ? WHERE id = ?",
        "sssi", $nombre, $correo, $clave_segura, $id);
}

// Cambia los datos de cualquier usuario (lo usa el bibliotecario). Con
// $clave_segura en null, la contraseña no se toca.
function usuario_actualizar($conexion, $id, $nombre, $correo, $rol, $clave_segura = null) {
    if ($clave_segura === null) {
        return ejecutar($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, rol = ? WHERE id = ?",
            "sssi", $nombre, $correo, $rol, $id);
    }
    return ejecutar($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, clave = ? WHERE id = ?",
        "ssssi", $nombre, $correo, $rol, $clave_segura, $id);
}

// Baja lógica (activo = 0) o reactivación (activo = 1). Nunca se usa DELETE.
function usuario_cambiar_estado($conexion, $id, $activo) {
    return ejecutar($conexion, "UPDATE usuarios SET activo = ? WHERE id = ?", "ii", $activo, $id);
}
?>
