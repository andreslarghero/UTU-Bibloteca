<?php
// ============================================================
// CONTROLADOR: CATÁLOGO DE LIBROS
// Muestra los libros de la base de datos en tarjetas, con un
// buscador por título o autor y un filtro por categoría.
// Solo pueden verlo los usuarios que han iniciado sesión.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_usuario.php";
require_once __DIR__ . "/../modelos/categoria.php";
require_once __DIR__ . "/../modelos/libro.php";

// ------------------------------------------------------------
// CATEGORÍAS
// Se cargan todas para el desplegable del filtro y para comprobar
// que la categoría que llega por la dirección existe.
// ------------------------------------------------------------
$categorias = categoria_listar($conexion);

// ------------------------------------------------------------
// BUSCADOR Y FILTRO POR CATEGORÍA
// Los dos llegan por la dirección: libros.php?buscar=texto&categoria=3
// Se pueden usar por separado o juntos.
// ------------------------------------------------------------

// Texto de búsqueda (vacío = sin búsqueda).
$buscar = texto_de($_GET["buscar"] ?? null);

// Categoría elegida (0 = todas). Solo se acepta un número que sea el id
// de una categoría que existe; cualquier otra cosa se trata como "todas".
$categoria = entero_de($_GET["categoria"] ?? null);
if (!isset($categorias[$categoria])) {
    $categoria = 0;
}

// Una sola consulta sirve para todos los casos:
//  - Trae los libros con el nombre de su categoría (JOIN con categorias),
//    su valoración media y el número de reseñas (LEFT JOIN para incluir
//    libros sin reseñas).
//  - Solo libros activos (activo = 0 es un libro dado de baja).
// El modelo (libro_buscar_catalogo) hace una sola consulta preparada que sirve
// para todos los casos: sin búsqueda, con búsqueda, con categoría o con ambas.
// Los libros se usan dos veces: en el carrusel de destacados y en la cuadrícula.
$libros = libro_buscar_catalogo($conexion, $buscar, $categoria);

// ------------------------------------------------------------
// LIBROS DESTACADOS (para el carrusel)
// No se hace otra consulta: se eligen hasta 10 de la lista que ya
// se trajo, mezclando físicos y digitales y variando las categorías.
// Solo se muestran en el catálogo completo (sin búsqueda ni filtro).
// ------------------------------------------------------------
$destacados = [];
if ($buscar == "" && $categoria == 0 && count($libros) >= 3) {
    // Se separan físicos y digitales y se intercalan: uno y uno.
    $fisicos = [];
    $digitales = [];
    foreach ($libros as $libro) {
        if ($libro["formato_digital"] == 1 && $libro["formato_fisico"] == 0) {
            $digitales[] = $libro;
        } else {
            $fisicos[] = $libro;
        }
    }
    $intercalados = [];
    for ($i = 0; $i < max(count($fisicos), count($digitales)); $i++) {
        if (isset($fisicos[$i])) { $intercalados[] = $fisicos[$i]; }
        if (isset($digitales[$i])) { $intercalados[] = $digitales[$i]; }
    }

    // Primera pasada: un libro por categoría, para que haya variedad.
    $categorias_usadas = [];
    foreach ($intercalados as $libro) {
        if (count($destacados) < 10 && !isset($categorias_usadas[$libro["categoria_id"]])) {
            $destacados[$libro["id"]] = $libro;
            $categorias_usadas[$libro["categoria_id"]] = true;
        }
    }
    // Segunda pasada: se completa hasta 10 con los que siguen.
    foreach ($intercalados as $libro) {
        if (count($destacados) < 10 && !isset($destacados[$libro["id"]])) {
            $destacados[$libro["id"]] = $libro;
        }
    }
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/libros.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Catálogo";
require __DIR__ . "/../vistas/libros.php";
