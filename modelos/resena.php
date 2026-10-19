<?php
// ============================================================
// MODELO: RESEÑAS
// Todas las consultas de la tabla "resenias".
// ============================================================

// Guarda la reseña de un usuario en un libro. Si ya tenía una, la
// actualiza en vez de duplicarla (hay una sola por usuario y libro).
function resena_guardar($conexion, $libro_id, $usuario_id, $puntuacion, $comentario) {
    return ejecutar($conexion,
        "INSERT INTO resenias (libro_id, usuario_id, puntuacion, comentario) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion), comentario = VALUES(comentario), fecha = NOW()",
        "iiis", $libro_id, $usuario_id, $puntuacion, $comentario);
}

// Elimina una reseña. Con $usuario_id solo la borra si es de ese
// usuario; con null la borra sin importar de quién es (el bibliotecario).
function resena_eliminar($conexion, $id, $usuario_id = null) {
    if ($usuario_id === null) {
        return ejecutar($conexion, "DELETE FROM resenias WHERE id = ?", "i", $id);
    }
    return ejecutar($conexion, "DELETE FROM resenias WHERE id = ? AND usuario_id = ?", "ii", $id, $usuario_id);
}

// Valoración media y número total de reseñas de un libro.
function resena_valoracion($conexion, $libro_id) {
    return buscar_fila($conexion,
        "SELECT ROUND(AVG(puntuacion), 1) AS media, COUNT(*) AS total FROM resenias WHERE libro_id = ?",
        "i", $libro_id);
}

// Las reseñas de un libro con el nombre de quien las escribió, de la
// más nueva a la más antigua.
function resena_listar_libro($conexion, $libro_id) {
    return buscar_filas($conexion,
        "SELECT resenias.*, usuarios.nombre FROM resenias
         JOIN usuarios ON usuarios.id = resenias.usuario_id
         WHERE resenias.libro_id = ? ORDER BY resenias.fecha DESC",
        "i", $libro_id);
}

// La reseña de un usuario en un libro (o null), para rellenar el formulario.
function resena_de_usuario($conexion, $libro_id, $usuario_id) {
    return buscar_fila($conexion,
        "SELECT puntuacion, comentario FROM resenias WHERE libro_id = ? AND usuario_id = ?",
        "ii", $libro_id, $usuario_id);
}

// Todas las reseñas con el título del libro y el nombre del usuario,
// de la más nueva a la más antigua (para "Gestionar reseñas").
function resena_listar_todas($conexion) {
    return buscar_filas($conexion,
        "SELECT resenias.*, libros.titulo, usuarios.nombre FROM resenias
         JOIN libros ON libros.id = resenias.libro_id
         JOIN usuarios ON usuarios.id = resenias.usuario_id
         ORDER BY resenias.fecha DESC");
}
?>
