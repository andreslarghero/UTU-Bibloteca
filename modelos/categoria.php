<?php
// ============================================================
// MODELO: CATEGORÍAS
// ============================================================

// Todas las categorías, ordenadas por nombre, como un arreglo
// id => nombre. Sirve para el filtro del catálogo, el formulario de
// libros y para comprobar que una categoría elegida existe.
function categoria_listar($conexion) {
    $categorias = [];
    foreach (buscar_filas($conexion, "SELECT id, nombre FROM categorias ORDER BY nombre") as $fila) {
        $categorias[$fila["id"]] = $fila["nombre"];
    }
    return $categorias;
}
?>
