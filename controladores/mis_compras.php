<?php
// ============================================================
// CONTROLADOR: MIS COMPRAS
// Página para cualquier usuario con sesión iniciada: muestra el
// historial de SUS compras y el detalle de cada una. Los libros
// comprados en formato digital tienen un enlace "Descargar".
// Es solo de consulta: no modifica nada de la base.
// Cada usuario ve únicamente las ventas en las que él es el
// cliente. Una venta de mostrador aparece solo si el bibliotecario
// indicó el correo del cliente al finalizarla; si no, queda sin cliente.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_usuario.php";
require_once __DIR__ . "/../modelos/venta.php";

// El cliente cuyas compras se muestran es SIEMPRE el usuario de la
// sesión. El id nunca se toma de la dirección ni de un formulario.
$id_cliente = $_SESSION["id"];

// ------------------------------------------------------------
// LISTA DE COMPRAS
// Solo las ventas de este cliente, de la más nueva a la más antigua,
// con el nombre del vendedor (LEFT JOIN por si la venta no tiene).
// ------------------------------------------------------------
$compras = venta_listar_cliente($conexion, $id_cliente);

// ------------------------------------------------------------
// DETALLE DE UNA COMPRA
// Si en la dirección viene ?venta=NÚMERO, se busca esa venta, pero
// solo si pertenece a este cliente: la consulta pide a la vez el
// número de venta Y el id del cliente de la sesión. Así, aunque
// alguien escriba a mano el número de una venta ajena, no la ve.
// Solo se acepta un valor formado por dígitos.
// ------------------------------------------------------------
$venta_id = entero_de($_GET["venta"] ?? null);

// Indica si en la dirección se pidió alguna venta (aunque el valor no sea válido).
$pidio_detalle = isset($_GET["venta"]);
$venta = null;
$lineas = [];

if ($venta_id > 0) {
    // Datos generales de la compra elegida (solo si es de este cliente).
    $venta = venta_cliente_por_id($conexion, $venta_id, $id_cliente);

    if ($venta) {
        // Libros de esa compra, con el precio al que se vendieron (el de
        // venta_lineas, no el precio actual del libro). También aquí se
        // exige que la venta sea de este cliente.
        $lineas = venta_lineas_cliente($conexion, $venta_id, $id_cliente);
    }
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/mis_compras.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Mis compras";
require __DIR__ . "/../vistas/mis_compras.php";
