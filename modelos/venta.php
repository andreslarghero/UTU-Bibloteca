<?php
// ============================================================
// MODELO: VENTAS Y COMPRAS
// Tablas "ventas" y "venta_lineas". Aquí están las dos operaciones
// que registran una venta (caja y compra digital), cada una dentro
// de una transacción, y las consultas del historial.
//
// IMPORTANTE: las consultas "del cliente" (mis compras y descargas)
// siempre exigen el id del cliente. Las consultas "de todas" son solo
// para el bibliotecario. No se unifican a propósito, para que una
// compra ajena nunca pueda verse por error.
// ============================================================

require_once __DIR__ . "/libro.php";

// ---------- Registrar una venta ----------

// Venta de mostrador (caja). $ticket es la lista de líneas guardada en
// la sesión (libro_id, titulo, precio, cantidad); $vendedor es el id del
// bibliotecario y $id_cliente el del cliente (o null si no hay cliente).
// Guarda la venta, sus líneas y descuenta el stock dentro de una
// transacción: o se guarda todo (COMMIT) o no se guarda nada (ROLLBACK).
// Devuelve ["error" => texto o "", "venta_id" => número, "total" => importe].
function venta_registrar_mostrador($conexion, $ticket, $vendedor, $id_cliente) {
    // Se ordena el ticket por id de libro para que todas las ventas
    // bloqueen los libros en el mismo orden.
    ksort($ticket);

    $error = "";       // Si queda vacío, todo salió bien
    $total = 0;        // Total recalculado con los precios de la base
    $lineas = [];      // Líneas definitivas de la venta
    $venta_id = 0;

    try {
        mysqli_begin_transaction($conexion);

        // 1. Vuelve a consultar cada libro del ticket en la base, sin
        //    confiar en lo guardado en la sesión (con bloqueo de la fila).
        foreach ($ticket as $linea) {
            $libro_id = $linea["libro_id"];
            $cantidad = $linea["cantidad"];

            $libro = libro_bloquear_fisico($conexion, $libro_id);

            if (!$libro || $libro["activo"] != 1 || $libro["formato_fisico"] != 1) {
                // El libro se borró, se dio de baja o dejó de venderse
                // en formato físico después de escanearlo.
                $error = "Uno de los libros ya no está disponible: " . $linea["titulo"] . ".";
                break;
            }

            if ($libro["stock"] < $cantidad) {
                $error = "Stock insuficiente para " . $libro["titulo"] . " (quedan " . $libro["stock"] . ").";
                break;
            }

            // El precio definitivo es el que tiene el libro en la base en este momento.
            $lineas[] = [
                "libro_id" => $libro_id,
                "titulo" => $libro["titulo"],
                "cantidad" => $cantidad,
                "precio" => $libro["precio"]
            ];
            $total = $total + $libro["precio"] * $cantidad;
        }

        // 2. Guarda la venta (la fecha la pone sola la base).
        if ($error == "") {
            $total = round($total, 2);

            if (ejecutar($conexion, "INSERT INTO ventas (id_cliente, id_vendedor, total) VALUES (?, ?, ?)",
                    "iid", $id_cliente, $vendedor, $total)) {
                // Número de la venta recién creada.
                $venta_id = mysqli_insert_id($conexion);
            } else {
                $error = "No se pudo registrar la venta.";
            }
        }

        // 3. Guarda una línea por cada libro y descuenta su stock.
        if ($error == "") {
            foreach ($lineas as $l) {
                // Todo lo que se vende en la caja es formato físico.
                if (!ejecutar($conexion,
                        "INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, ?, ?, 'fisico')",
                        "iiid", $venta_id, $l["libro_id"], $l["cantidad"], $l["precio"])) {
                    $error = "No se pudo registrar la venta.";
                    break;
                }

                $filas = libro_descontar_stock($conexion, $l["libro_id"], $l["cantidad"]);

                if ($filas === false) {
                    $error = "No se pudo registrar la venta.";
                    break;
                }

                // Si no se modificó ninguna fila es que el stock ya no alcanzaba.
                if ($filas != 1) {
                    $error = "Stock insuficiente para " . $l["titulo"] . ".";
                    break;
                }
            }
        }

        // 4. Si todo salió bien se confirma; si algo falló se deshace todo.
        if ($error == "") {
            if (!mysqli_commit($conexion)) {
                $error = "No se pudo registrar la venta.";
            }
        } else {
            mysqli_rollback($conexion);
        }
    } catch (Throwable $e) {
        // Cualquier error inesperado de la base: se deshace todo y se
        // devuelve un aviso general, sin detalles internos de MySQL.
        mysqli_rollback($conexion);
        $error = "No se pudo registrar la venta.";
    }

    return ["error" => $error, "venta_id" => $venta_id, "total" => $total];
}

// Compra de un libro digital por parte de un cliente (web).
// Todo va dentro de una transacción: o se guardan la venta y su línea
// (COMMIT) o no se guarda nada (ROLLBACK).
// Devuelve ["estado" => ..., "venta_id" => número, "precio" => importe]:
//   "ok"            la compra se registró
//   "ya_comprado"   el cliente ya tenía este libro digital (no se cobra de nuevo)
//   "no_disponible" el libro no existe, está de baja o no se vende en digital
//   "error"         no se pudo registrar
function venta_registrar_digital($conexion, $id_cliente, $libro_id) {
    $estado = "error";
    $venta_id = 0;
    $precio = 0;

    try {
        mysqli_begin_transaction($conexion);

        // 1. Vuelve a leer el libro de la base con bloqueo: si el mismo
        //    usuario envía la compra dos veces a la vez, la segunda espera a
        //    la primera y entonces ya encuentra el libro comprado.
        $libro = libro_bloquear_digital($conexion, $libro_id);

        // 2. El libro tiene que existir, estar activo, venderse en formato
        //    digital y tener precio digital y archivo.
        if (!$libro || $libro["activo"] != 1 || $libro["formato_digital"] != 1
            || $libro["precio_digital"] === null || $libro["archivo_digital"] === null) {
            $estado = "no_disponible";
        } elseif (venta_cliente_compro_digital($conexion, $id_cliente, $libro_id)) {
            // 3. Si ya lo compró, no se crea otra venta ni se cobra de nuevo.
            $estado = "ya_comprado";
        } else {
            // 4. Guarda la venta: el cliente es el usuario de la sesión, no hay
            //    vendedor (es una compra por la web) y el total es el precio
            //    digital que tiene el libro en la base en este momento.
            $precio = $libro["precio_digital"];

            if (ejecutar($conexion, "INSERT INTO ventas (id_cliente, id_vendedor, total) VALUES (?, NULL, ?)",
                    "id", $id_cliente, $precio)) {
                $venta_id = mysqli_insert_id($conexion);

                // 5. Guarda la línea: una copia, en formato digital. El stock no
                //    se toca: el stock es solo del formato físico.
                if (ejecutar($conexion,
                        "INSERT INTO venta_lineas (venta_id, libro_id, cantidad, precio, formato) VALUES (?, ?, 1, ?, 'digital')",
                        "iid", $venta_id, $libro_id, $precio)) {
                    $estado = "ok";
                }
            }
        }

        // 6. Si se guardó todo (o ya estaba comprado) se confirma; si algo
        //    falló se deshace todo, para que nunca quede una venta sin su línea.
        if ($estado == "ok" || $estado == "ya_comprado") {
            if (!mysqli_commit($conexion)) {
                $estado = "error";
            }
        } else {
            mysqli_rollback($conexion);
        }
    } catch (Throwable $e) {
        // Cualquier error inesperado de la base: se deshace todo.
        mysqli_rollback($conexion);
        $estado = "error";
    }

    return ["estado" => $estado, "venta_id" => $venta_id, "precio" => $precio];
}

// ---------- Compras de un cliente ----------

// ¿Este cliente ya compró este libro en formato digital?
function venta_cliente_compro_digital($conexion, $id_cliente, $libro_id) {
    $fila = buscar_fila($conexion,
        "SELECT COUNT(*) AS total FROM venta_lineas
         JOIN ventas ON ventas.id = venta_lineas.venta_id
         WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'",
        "ii", $id_cliente, $libro_id);
    return $fila["total"] > 0;
}

// El libro que este cliente compró en digital y puede descargar (o null).
// Exige a la vez: una venta de este cliente, con una línea de este libro en
// formato digital, y que el libro tenga un archivo digital guardado. Una venta
// de mostrador (sin cliente) o una compra física no dan derecho a la descarga.
function venta_libro_descargable($conexion, $id_cliente, $libro_id) {
    return buscar_fila($conexion,
        "SELECT libros.titulo, libros.archivo_digital
         FROM venta_lineas
         JOIN ventas ON ventas.id = venta_lineas.venta_id
         JOIN libros ON libros.id = venta_lineas.libro_id
         WHERE ventas.id_cliente = ? AND venta_lineas.libro_id = ? AND venta_lineas.formato = 'digital'
           AND libros.archivo_digital IS NOT NULL
         LIMIT 1",
        "ii", $id_cliente, $libro_id);
}

// Las ventas de ESTE cliente, de la más nueva a la más antigua, con el
// nombre del vendedor (LEFT JOIN por si la venta no tiene).
function venta_listar_cliente($conexion, $id_cliente) {
    return buscar_filas($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         WHERE ventas.id_cliente = ?
         ORDER BY ventas.fecha DESC, ventas.id DESC",
        "i", $id_cliente);
}

// Una venta, pero solo si pertenece a este cliente (o null). Así, aunque
// alguien escriba a mano el número de una venta ajena, no la ve.
function venta_cliente_por_id($conexion, $venta_id, $id_cliente) {
    return buscar_fila($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         WHERE ventas.id = ? AND ventas.id_cliente = ?",
        "ii", $venta_id, $id_cliente);
}

// Libros de una compra, con el precio al que se vendieron (el de
// venta_lineas, no el precio actual del libro). También aquí se exige
// que la venta sea de este cliente.
function venta_lineas_cliente($conexion, $venta_id, $id_cliente) {
    return buscar_filas($conexion,
        "SELECT libros.titulo, venta_lineas.libro_id, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato,
                libros.archivo_digital IS NOT NULL AS tiene_archivo,
                UPPER(SUBSTRING_INDEX(libros.archivo_digital, '.', -1)) AS tipo_archivo
         FROM venta_lineas
         JOIN ventas ON ventas.id = venta_lineas.venta_id
         JOIN libros ON libros.id = venta_lineas.libro_id
         WHERE venta_lineas.venta_id = ? AND ventas.id_cliente = ?
         ORDER BY venta_lineas.id",
        "ii", $venta_id, $id_cliente);
}

// ---------- Todas las operaciones (solo el bibliotecario) ----------

// Todas las ventas, de la más nueva a la más antigua, con el nombre del
// cliente y del vendedor. LEFT JOIN incluye las ventas sin cliente (de mostrador).
function venta_listar_todas($conexion) {
    return buscar_filas($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total,
                clientes.nombre AS cliente, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         ORDER BY ventas.fecha DESC, ventas.id DESC");
}

// Una venta cualquiera (o null).
function venta_por_id($conexion, $venta_id) {
    return buscar_fila($conexion,
        "SELECT ventas.id, ventas.fecha, ventas.total,
                clientes.nombre AS cliente, vendedores.nombre AS vendedor
         FROM ventas
         LEFT JOIN usuarios AS clientes ON clientes.id = ventas.id_cliente
         LEFT JOIN usuarios AS vendedores ON vendedores.id = ventas.id_vendedor
         WHERE ventas.id = ?",
        "i", $venta_id);
}

// Libros vendidos en una venta, con el precio al que se vendieron.
function venta_lineas($conexion, $venta_id) {
    return buscar_filas($conexion,
        "SELECT libros.titulo, venta_lineas.cantidad, venta_lineas.precio, venta_lineas.formato
         FROM venta_lineas
         JOIN libros ON libros.id = venta_lineas.libro_id
         WHERE venta_lineas.venta_id = ?
         ORDER BY venta_lineas.id",
        "i", $venta_id);
}
?>
