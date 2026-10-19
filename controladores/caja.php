<?php
// ============================================================
// CONTROLADOR: CAJA DEL MOSTRADOR
// Pantalla para el bibliotecario: se escanea (o se escribe) el
// código de barras de cada libro y se va armando el ticket.
// El ticket es temporal: se guarda en la sesión, no en la base.
// Al pulsar "Finalizar venta" se guarda la venta en las tablas
// ventas y venta_lineas y se descuenta el stock, todo junto en
// una transacción. Solo pueden entrar los bibliotecarios.
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
require_once __DIR__ . "/../modelos/usuario.php";
require_once __DIR__ . "/../modelos/venta.php";
require_once __DIR__ . "/../vistas/ayudas.php";   // formatearPrecio() para escribir el importe en el mensaje

// ------------------------------------------------------------
// STEAM (LECTOR FÍSICO CON ARDUINO)
// steam.php trae las funciones que avisan al Arduino el resultado
// de cada escaneo. Es una salida adicional: si STEAM no está
// disponible no hacen nada y la caja funciona igual.
// ------------------------------------------------------------
require_once __DIR__ . "/steam.php";

// ------------------------------------------------------------
// TICKET TEMPORAL
// $_SESSION["ticket"] es una lista de líneas. Cada línea guarda
// libro_id, titulo, precio y cantidad, y se identifica por el id
// del libro, así el mismo libro nunca aparece en dos líneas.
// ------------------------------------------------------------
if (!isset($_SESSION["ticket"])) {
    $_SESSION["ticket"] = [];
}

// ------------------------------------------------------------
// ACCIONES DE LA CAJA
// Se ejecuta al enviar cualquiera de los formularios de la página.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = texto_de($_POST["accion"] ?? null);

    // Aviso que se mostrará después de la acción y su tipo ("exito" o "error").
    $mensaje = "";
    $tipo = "error";

    // --- Agregar un libro por su código de barras ---
    if ($accion == "agregar") {
        $codigo = trim($_POST["codigo"]);

        if ($codigo == "") {
            $mensaje = "Escanea o escribe un código de barras.";
        } else {
            // Busca el libro por su código de barras. Solo cuentan los libros activos
            // que se venden en formato físico: la caja no vende libros digitales.
            // Se usa una consulta preparada (el "?") para evitar ataques de inyección SQL.
            $libro = libro_activo_fisico_por_codigo($conexion, $codigo);

            if (!$libro) {
                $mensaje = "Libro no encontrado (código " . $codigo . ").";

                // STEAM: avisa "no encontrado", salvo que el código sea de un
                // libro solo digital (el lector físico no trabaja con esos).
                if (!steam_es_solo_digital($conexion, $codigo)) {
                    steam_avisar_no_encontrado();
                }
            } elseif ($libro["stock"] == 0) {
                $mensaje = "Libro agotado: " . $libro["titulo"] . ".";

                // STEAM: avisa que el libro no tiene stock.
                steam_avisar_agotado($libro["titulo"]);
            } else {
                $id = $libro["id"];

                // Cuántas copias de este libro ya hay en el ticket.
                $en_ticket = isset($_SESSION["ticket"][$id]) ? $_SESSION["ticket"][$id]["cantidad"] : 0;

                if ($en_ticket + 1 > $libro["stock"]) {
                    // El ticket no puede tener más copias que el stock real.
                    $mensaje = "No hay más stock disponible de " . $libro["titulo"] . " (stock: " . $libro["stock"] . ").";
                } else {
                    // Si el libro ya estaba, sube la cantidad; si no, crea la línea.
                    // El precio se toma siempre de la base de datos.
                    $_SESSION["ticket"][$id] = [
                        "libro_id" => $id,
                        "titulo" => $libro["titulo"],
                        "precio" => $libro["precio"],
                        "cantidad" => $en_ticket + 1
                    ];
                    $mensaje = "Agregado: " . $libro["titulo"] . ".";
                    $tipo = "exito";

                    // STEAM: muestra el título y el precio real del libro.
                    steam_avisar_encontrado($libro["titulo"], $libro["precio"]);
                }
            }
        }
    }

    // --- Quitar una unidad de una línea ---
    if ($accion == "quitar") {
        $id = (int) $_POST["libro_id"];

        if (isset($_SESSION["ticket"][$id])) {
            $_SESSION["ticket"][$id]["cantidad"] = $_SESSION["ticket"][$id]["cantidad"] - 1;

            // Si la línea se queda sin unidades, se elimina.
            if ($_SESSION["ticket"][$id]["cantidad"] <= 0) {
                unset($_SESSION["ticket"][$id]);
            }
        }
    }

    // --- Eliminar una línea completa ---
    if ($accion == "eliminar") {
        $id = (int) $_POST["libro_id"];
        unset($_SESSION["ticket"][$id]);
    }

    // --- Vaciar todo el ticket ---
    if ($accion == "vaciar") {
        $_SESSION["ticket"] = [];
    }

    // --- Finalizar la venta ---
    // Guarda la venta, sus líneas y descuenta el stock dentro de una
    // transacción: o se guarda todo (COMMIT) o no se guarda nada (ROLLBACK).
    // Es una venta de mostrador: el bibliotecario es el vendedor. El cliente es
    // opcional: si se escribe su correo, la venta queda a su nombre y aparece en
    // su "Mis compras"; si se deja vacío, queda sin cliente.
    if ($accion == "finalizar") {
        // Cliente de la venta (solo un usuario que exista y esté activo).
        $correo_cliente = texto_de($_POST["correo_cliente"] ?? null);
        $cliente = null;
        if ($correo_cliente != "") {
            $cliente = usuario_activo_por_correo($conexion, $correo_cliente);
        }

        if (count($_SESSION["ticket"]) == 0) {
            $mensaje = "El ticket está vacío.";
        } elseif ($correo_cliente != "" && !$cliente) {
            // El ticket se conserva para poder corregir el correo.
            $mensaje = "No hay un usuario activo con ese correo.";
        } else {
            // El modelo (venta_registrar_mostrador) guarda la venta, sus líneas y descuenta
            // el stock dentro de una transacción, volviendo a consultar cada libro en la
            // base (sin confiar en lo guardado en la sesión). Aquí solo se elige el mensaje.
            $resultado = venta_registrar_mostrador($conexion, $_SESSION["ticket"], $_SESSION["id"], $cliente ? $cliente["id"] : null);
            $error = $resultado["error"];
            $venta_id = $resultado["venta_id"];
            $total = $resultado["total"];

            if ($error == "") {
                // Venta guardada: se vacía el ticket y se avisa con el número y el total.
                $_SESSION["ticket"] = [];
                $mensaje = "Venta registrada correctamente. Venta N.º " . $venta_id . ". Total vendido: $ " . formatearPrecio($total) . ".";
                $tipo = "exito";
            } else {
                // El ticket se conserva para poder corregirlo.
                $mensaje = $error;
            }
        }
    }

    // Guarda el aviso en la sesión y recarga la página. Así, si se
    // refresca el navegador, no se repite el último escaneo.
    aviso_guardar("caja", $mensaje, $tipo);
    header("Location: caja.php");
    exit;
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
list($mensaje, $tipo) = aviso_leer("caja");

// ------------------------------------------------------------
// TOTAL DEL TICKET
// Suma el subtotal (precio x cantidad) de todas las líneas.
// ------------------------------------------------------------
$total = 0;
foreach ($_SESSION["ticket"] as $linea) {
    $total = $total + $linea["precio"] * $linea["cantidad"];
}

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/caja.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Caja";
require __DIR__ . "/../vistas/caja.php";
