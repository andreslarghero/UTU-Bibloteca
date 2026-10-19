<?php
// ============================================================
// CONTROLADOR: GESTIÓN DE STOCK
// Página para el administrador: muestra todos los libros con
// sus copias disponibles y permite cambiar la cantidad.
// Solo pueden entrar los usuarios con admin = 1.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
require_once __DIR__ . "/verificar_bibliotecario.php";
require_once __DIR__ . "/../modelos/libro.php";

// Aquí se guarda el mensaje que confirma el cambio.
$mensaje = "";

// ------------------------------------------------------------
// GUARDAR EL NUEVO STOCK
// Se ejecuta al pulsar "Guardar" en la fila de un libro.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // (int) convierte los valores a número; max() evita cantidades negativas.
    $id = (int) $_POST["id"];
    $stock = max(0, (int) $_POST["stock"]);

    // El stock es solo del formato físico: un libro que no es físico no se modifica.
    libro_actualizar_stock($conexion, $id, $stock);

    $mensaje = "Stock actualizado correctamente.";
}

// Pide los libros que se venden en formato físico para mostrarlos en la tabla.
// Los libros solo digitales no tienen stock, así que no aparecen aquí.
$libros = libro_listar_fisicos($conexion);

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/stock.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Gestión de stock";
require __DIR__ . "/../vistas/stock.php";
