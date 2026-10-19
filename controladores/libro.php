<?php
// ============================================================
// CONTROLADOR: DETALLE DE UN LIBRO
// Muestra toda la información de un libro concreto, sus reseñas
// y un formulario para que el usuario deje su valoración.
// Se abre desde el catálogo con la dirección libro.php?id=NÚMERO.
// Solo pueden verla los usuarios que han iniciado sesión.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_usuario.php";
require_once __DIR__ . "/../modelos/libro.php";
require_once __DIR__ . "/../modelos/resena.php";
require_once __DIR__ . "/../modelos/venta.php";

// ------------------------------------------------------------
// BUSCAR EL LIBRO
// Toma el id de la dirección y busca ese libro en la base de datos.
// (int) convierte el valor a número para evitar datos no válidos.
// El JOIN trae el nombre de la categoría desde la tabla categorias.
// Si el libro está dado de baja (activo = 0) no se encuentra,
// y la página muestra el aviso de que el libro no existe.
// ------------------------------------------------------------
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$libro = libro_activo_por_id($conexion, $id);

// ------------------------------------------------------------
// GUARDAR O ELIMINAR UNA RESEÑA
// Se ejecuta al enviar el formulario de reseña o al pulsar "Eliminar".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && $libro) {
    if ($_POST["accion"] == "guardar") {
        // La puntuación se limita entre 1 y 5 estrellas.
        $puntuacion = min(5, max(1, (int) $_POST["puntuacion"]));
        $comentario = trim($_POST["comentario"]);

        // Si el usuario ya tenía reseña en este libro, se actualiza en vez de duplicarse.
        resena_guardar($conexion, $id, $_SESSION["id"], $puntuacion, $comentario);
    }

    if ($_POST["accion"] == "eliminar") {
        $resena_id = (int) $_POST["resena_id"];

        if ($_SESSION["rol"] == "bibliotecario") {
            // El bibliotecario puede borrar cualquier reseña.
            resena_eliminar($conexion, $resena_id);
        } else {
            // Un usuario normal solo puede borrar su propia reseña.
            resena_eliminar($conexion, $resena_id, $_SESSION["id"]);
        }
    }

    // Recarga la página para mostrar los cambios (y evitar reenviar el formulario al refrescar).
    header("Location: libro.php?id=" . $id);
    exit;
}

// ------------------------------------------------------------
// DATOS DE LAS RESEÑAS
// ------------------------------------------------------------
if ($libro) {
    // Valoración media y número total de reseñas del libro.
    $valoracion = resena_valoracion($conexion, $id);

    // Lista de reseñas con el nombre de quien las escribió, de la más nueva a la más antigua.
    $resenas = resena_listar_libro($conexion, $id);

    // Reseña del usuario actual (si ya tiene una), para rellenar el formulario.
    $mi_resena = resena_de_usuario($conexion, $id, $_SESSION["id"]);
}

// ------------------------------------------------------------
// ¿EL USUARIO YA COMPRÓ ESTE LIBRO EN DIGITAL?
// Se busca una venta de este usuario que tenga una línea de este
// libro en formato digital. Sirve para mostrar "Comprar digital"
// o "Ya comprado" en la ficha.
// ------------------------------------------------------------
$ya_comprado = false;

if ($libro && $libro["formato_digital"] == 1) {
    $ya_comprado = venta_cliente_compro_digital($conexion, $_SESSION["id"], $id);
}

// ------------------------------------------------------------
// TAMBIÉN PODRÍA INTERESARTE
// Hasta 4 libros activos de la misma categoría, sin contar este.
// Es una consulta sencilla de lectura; no cambia nada en la base.
// ------------------------------------------------------------
$relacionados = [];

if ($libro) {
    $relacionados = libro_relacionados($conexion, $libro["categoria_id"], $id);
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/libro.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = $libro ? $libro["titulo"] : "Libro no encontrado";
$clase_body = "pagina-libro";
require __DIR__ . "/../vistas/libro.php";
