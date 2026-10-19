<?php
// ============================================================
// CONTROLADOR: COMPRA DE UN LIBRO DIGITAL
// Página para cualquier usuario con sesión iniciada.
// Primero muestra una pantalla de confirmación y, al confirmar,
// registra la venta a nombre del usuario.
// Es una compra SIMULADA (trabajo académico): no se pide ningún
// medio de pago. Confirmar = registrar la venta.
//
// Lo único que llega del navegador es el id del libro. El
// cliente sale de la sesión, y el precio y el formato salen de
// la base: nada de eso se puede cambiar desde el formulario.
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
require_once __DIR__ . "/../modelos/venta.php";
require_once __DIR__ . "/../vistas/ayudas.php";   // formatearPrecio() para escribir el importe en el mensaje

// El comprador es SIEMPRE el usuario de la sesión.
$id_cliente = $_SESSION["id"];

// ------------------------------------------------------------
// ID DEL LIBRO
// Llega por la dirección (comprar_digital.php?libro=3) al abrir
// la página, o por el formulario al confirmar. Solo se acepta un
// valor formado por dígitos; cualquier otra cosa se trata como 0
// (un libro que no existe).
// ------------------------------------------------------------
$origen = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST : $_GET;
$libro_id = entero_de($origen["libro"] ?? null);

// ------------------------------------------------------------
// CONFIRMAR LA COMPRA
// Se ejecuta al pulsar "Confirmar compra". Todo va dentro de una
// transacción: o se guardan la venta y su línea (COMMIT) o no se
// guarda nada (ROLLBACK).
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // El modelo (venta_registrar_digital) hace todo el trabajo dentro de una
    // transacción y devuelve cómo terminó. Aquí solo se elige el mensaje.
    $resultado = venta_registrar_digital($conexion, $id_cliente, $libro_id);

    if ($resultado["estado"] == "ok") {
        $mensaje = "Compra registrada. Venta N.º " . $resultado["venta_id"] . ". Total: $ " . formatearPrecio($resultado["precio"]) . ".";
        $tipo = "exito";
    } elseif ($resultado["estado"] == "ya_comprado") {
        $mensaje = "Ya tienes este libro digital en Mis compras.";
        $tipo = "exito";
    } elseif ($resultado["estado"] == "no_disponible") {
        $mensaje = "Este libro no está disponible en formato digital.";
        $tipo = "error";
    } else {
        $mensaje = "No se pudo registrar la compra.";
        $tipo = "error";
    }

    // Guarda el aviso en la sesión y recarga la página. Así, si se
    // refresca el navegador, no se vuelve a enviar la compra.
    aviso_guardar("compra", $mensaje, $tipo);
    header("Location: comprar_digital.php?libro=" . $libro_id);
    exit;
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
list($mensaje, $tipo) = aviso_leer("compra");

// ------------------------------------------------------------
// DATOS PARA LA PANTALLA
// Busca el libro (solo si está activo y se vende en digital) y
// comprueba si este usuario ya lo compró en formato digital.
// No se pide el nombre del archivo: esta página no lo necesita.
// ------------------------------------------------------------
$libro = libro_digital_disponible($conexion, $libro_id);

$ya_comprado = false;

if ($libro) {
    $ya_comprado = venta_cliente_compro_digital($conexion, $id_cliente, $libro_id);
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/comprar_digital.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Comprar libro digital";
require __DIR__ . "/../vistas/comprar_digital.php";
