<?php
// ============================================================
// CONTROLADOR: CONSULTA DE OPERACIONES
// Página para el bibliotecario: lista las ventas registradas y
// permite ver el detalle de cada una (qué libros se vendieron).
// Es SOLO de consulta: no modifica ni borra nada de la base.
// Solo pueden entrar los bibliotecarios.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_bibliotecario.php";
require_once __DIR__ . "/../modelos/venta.php";

// ------------------------------------------------------------
// LISTA DE VENTAS
// Trae todas las ventas, de la más nueva a la más antigua, con
// el nombre del cliente y del vendedor. La tabla usuarios se usa
// dos veces (una para cada uno), por eso lleva dos nombres.
// LEFT JOIN permite incluir las ventas sin cliente (de mostrador).
// ------------------------------------------------------------
$ventas = venta_listar_todas($conexion);

// ------------------------------------------------------------
// DETALLE DE UNA VENTA
// Si en la dirección viene ?venta=NÚMERO, se buscan esa venta y
// sus líneas. (int) convierte el valor a número para evitar datos
// no válidos, y se usan consultas preparadas (el "?").
// ------------------------------------------------------------
$venta_id = isset($_GET["venta"]) ? (int) $_GET["venta"] : 0;
$venta = null;
$lineas = [];

if ($venta_id > 0) {
    // Datos generales de la venta elegida.
    $venta = venta_por_id($conexion, $venta_id);

    if ($venta) {
        // Libros vendidos en esa venta, con el precio al que se vendieron.
        $lineas = venta_lineas($conexion, $venta_id);
    }
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/operaciones.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Operaciones";
require __DIR__ . "/../vistas/operaciones.php";
