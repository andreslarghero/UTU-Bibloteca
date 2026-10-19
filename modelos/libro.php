<?php
// ============================================================
// MODELO: LIBROS
// Todas las consultas de la tabla "libros". Las funciones solo
// leen y guardan datos: las reglas y los mensajes son del
// controlador. Los libros dados de baja (activo = 0) no se borran.
// ============================================================

// ---------- Lectura para el catálogo y la ficha ----------

// Un libro ACTIVO con el nombre de su categoría (o null). Para la ficha.
function libro_activo_por_id($conexion, $id) {
    return buscar_fila($conexion,
        "SELECT libros.*, categorias.nombre AS categoria FROM libros
         JOIN categorias ON categorias.id = libros.categoria_id
         WHERE libros.id = ? AND libros.activo = 1",
        "i", $id);
}

// El catálogo: libros activos con su categoría, su valoración media y el
// número de reseñas (LEFT JOIN para incluir libros sin reseñas).
//  - Búsqueda: los % permiten encontrar el texto en cualquier parte del
//    título o del autor. Con "" queda "%%", que coincide con todos.
//  - Categoría: "? = 0" hace que con 0 (todas) no se filtre por categoría.
function libro_buscar_catalogo($conexion, $buscar, $categoria) {
    $texto = "%" . $buscar . "%";
    return buscar_filas($conexion,
        "SELECT libros.*, categorias.nombre AS categoria,
                ROUND(AVG(resenias.puntuacion), 1) AS media, COUNT(resenias.id) AS total_resenas
         FROM libros
         JOIN categorias ON categorias.id = libros.categoria_id
         LEFT JOIN resenias ON resenias.libro_id = libros.id
         WHERE libros.activo = 1
           AND (libros.titulo LIKE ? OR libros.autor LIKE ?)
           AND (? = 0 OR libros.categoria_id = ?)
         GROUP BY libros.id
         ORDER BY libros.id",
        "ssii", $texto, $texto, $categoria, $categoria);
}

// Hasta 4 libros activos de la misma categoría, sin contar $id.
function libro_relacionados($conexion, $categoria_id, $id) {
    return buscar_filas($conexion,
        "SELECT id, titulo, autor, portada, formato_fisico, precio, precio_digital
         FROM libros
         WHERE categoria_id = ? AND id <> ? AND activo = 1
         ORDER BY id
         LIMIT 4",
        "ii", $categoria_id, $id);
}

// Cuatro libros con portada para la página de inicio.
function libro_portadas_inicio($conexion) {
    return buscar_filas($conexion,
        "SELECT titulo, portada FROM libros WHERE activo = 1 AND portada IS NOT NULL ORDER BY id LIMIT 4");
}

// ---------- Lectura para la caja y las compras ----------

// Un libro activo que se vende en formato físico, por su código de barras.
function libro_activo_fisico_por_codigo($conexion, $codigo) {
    return buscar_fila($conexion,
        "SELECT id, titulo, precio, stock FROM libros WHERE codigo_barras = ? AND activo = 1 AND formato_fisico = 1",
        "s", $codigo);
}

// Solo el campo formato_fisico de un libro, por su código de barras (o null).
function libro_formato_fisico_por_codigo($conexion, $codigo) {
    return buscar_fila($conexion, "SELECT formato_fisico FROM libros WHERE codigo_barras = ?", "s", $codigo);
}

// Un libro disponible para comprar en digital: activo, con formato digital,
// precio digital y archivo (o null). No trae el nombre del archivo.
function libro_digital_disponible($conexion, $id) {
    return buscar_fila($conexion,
        "SELECT id, titulo, autor, precio_digital FROM libros
         WHERE id = ? AND activo = 1 AND formato_digital = 1
           AND precio_digital IS NOT NULL AND archivo_digital IS NOT NULL",
        "i", $id);
}

// ---------- Dentro de una transacción de venta ----------
// "FOR UPDATE" bloquea la fila hasta terminar la transacción, para que
// otra venta no pueda gastar el mismo stock al mismo tiempo.

// Datos actuales de un libro para una venta física.
function libro_bloquear_fisico($conexion, $id) {
    return buscar_fila($conexion,
        "SELECT titulo, precio, stock, activo, formato_fisico FROM libros WHERE id = ? FOR UPDATE",
        "i", $id);
}

// Datos actuales de un libro para una compra digital.
function libro_bloquear_digital($conexion, $id) {
    return buscar_fila($conexion,
        "SELECT titulo, precio_digital, archivo_digital, activo, formato_digital FROM libros WHERE id = ? FOR UPDATE",
        "i", $id);
}

// Descuenta stock. "AND stock >= ?" hace que nunca quede negativo: si no
// alcanza, la fila no se modifica. Devuelve las filas modificadas (1 si
// salió bien, 0 si el stock ya no alcanzaba) o false si la consulta falló.
function libro_descontar_stock($conexion, $id, $cantidad) {
    $consulta = mysqli_prepare($conexion, "UPDATE libros SET stock = stock - ? WHERE id = ? AND stock >= ?");
    mysqli_stmt_bind_param($consulta, "iii", $cantidad, $id, $cantidad);
    if (!mysqli_stmt_execute($consulta)) {
        return false;
    }
    return mysqli_stmt_affected_rows($consulta);
}

// ---------- Gestión (bibliotecario) ----------

// Todos los libros, activos e inactivos, con el nombre de su categoría.
function libro_listar_admin($conexion) {
    return buscar_filas($conexion,
        "SELECT libros.id, libros.titulo, libros.autor, libros.isbn, libros.precio, libros.stock, libros.activo,
                libros.formato_fisico, libros.formato_digital, libros.precio_digital,
                categorias.nombre AS categoria
         FROM libros
         JOIN categorias ON categorias.id = libros.categoria_id
         ORDER BY libros.titulo");
}

// Los libros que se venden en formato físico (los digitales no tienen stock).
function libro_listar_fisicos($conexion) {
    return buscar_filas($conexion,
        "SELECT id, titulo, autor, portada, stock FROM libros WHERE formato_fisico = 1");
}

// Un libro con todos sus campos, para llenar el formulario de edición.
function libro_por_id($conexion, $id) {
    return buscar_fila($conexion, "SELECT * FROM libros WHERE id = ?", "i", $id);
}

// Solo el id y el nombre del archivo digital de un libro (o null).
function libro_archivo_digital($conexion, $id) {
    return buscar_fila($conexion, "SELECT id, archivo_digital FROM libros WHERE id = ?", "i", $id);
}

// ¿Hay OTRO libro con ese ISBN? ($id es el libro que se edita; 0 si es nuevo.)
function libro_isbn_en_uso($conexion, $isbn, $id) {
    return (bool) buscar_fila($conexion, "SELECT id FROM libros WHERE isbn = ? AND id <> ?", "si", $isbn, $id);
}

// ¿Hay OTRO libro con ese código de barras?
function libro_codigo_en_uso($conexion, $codigo, $id) {
    return (bool) buscar_fila($conexion, "SELECT id FROM libros WHERE codigo_barras = ? AND id <> ?", "si", $codigo, $id);
}

// Crea un libro nuevo (queda activo). $d es un arreglo con los campos ya
// validados: titulo, autor, anio, portada, descripcion, categoria_id, isbn,
// precio, codigo_barras, stock, formato_fisico, formato_digital,
// precio_digital y archivo_digital.
function libro_crear($conexion, $d) {
    return ejecutar($conexion,
        "INSERT INTO libros (titulo, autor, anio, portada, descripcion, categoria_id, isbn, precio, codigo_barras, stock,
                             formato_fisico, formato_digital, precio_digital, archivo_digital, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
        "ssissisdsiiids", $d["titulo"], $d["autor"], $d["anio"], $d["portada"],
        $d["descripcion"], $d["categoria_id"], $d["isbn"], $d["precio"], $d["codigo_barras"], $d["stock"],
        $d["formato_fisico"], $d["formato_digital"], $d["precio_digital"], $d["archivo_digital"]);
}

// Cambia los datos de un libro (nunca el id). Mismos campos que libro_crear().
function libro_actualizar($conexion, $id, $d) {
    return ejecutar($conexion,
        "UPDATE libros SET titulo = ?, autor = ?, anio = ?, portada = ?, descripcion = ?, categoria_id = ?,
                isbn = ?, precio = ?, codigo_barras = ?, stock = ?,
                formato_fisico = ?, formato_digital = ?, precio_digital = ?, archivo_digital = ?
         WHERE id = ?",
        "ssissisdsiiidsi", $d["titulo"], $d["autor"], $d["anio"], $d["portada"],
        $d["descripcion"], $d["categoria_id"], $d["isbn"], $d["precio"], $d["codigo_barras"], $d["stock"],
        $d["formato_fisico"], $d["formato_digital"], $d["precio_digital"], $d["archivo_digital"], $id);
}

// Baja lógica (activo = 0) o reactivación (activo = 1). Nunca se usa DELETE.
function libro_cambiar_estado($conexion, $id, $activo) {
    return ejecutar($conexion, "UPDATE libros SET activo = ? WHERE id = ?", "ii", $activo, $id);
}

// Cambia las copias disponibles. Solo vale para libros físicos.
function libro_actualizar_stock($conexion, $id, $stock) {
    return ejecutar($conexion, "UPDATE libros SET stock = ? WHERE id = ? AND formato_fisico = 1", "ii", $stock, $id);
}
?>
