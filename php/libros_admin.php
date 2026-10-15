<?php
// ============================================================
// GESTIÓN DE LIBROS (ABM)
// Página para el bibliotecario: permite dar de alta libros,
// editarlos, darlos de baja y reactivarlos.
// La baja es LÓGICA: el libro no se borra, solo se marca con
// activo = 0 y deja de verse en el catálogo y en la caja.
// Cada libro puede venderse en formato físico, en formato
// digital o en los dos. El archivo digital (PDF o EPUB) se sube
// desde esta misma página.
// Solo pueden entrar los bibliotecarios.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_bibliotecario.php";

// ------------------------------------------------------------
// CATEGORÍAS
// Se cargan todas para el desplegable del formulario y para
// comprobar que la categoría elegida existe.
// ------------------------------------------------------------
$categorias = [];
$resultado = mysqli_query($conexion, "SELECT id, nombre FROM categorias ORDER BY nombre");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $categorias[$fila["id"]] = $fila["nombre"];
}

// Errores de validación que se muestran encima del formulario.
$errores = [];

// Datos que se muestran en el formulario. Con id = 0 es un libro nuevo.
$libro = [
    "id" => 0, "titulo" => "", "autor" => "", "anio" => "", "portada" => "", "descripcion" => "",
    "categoria_id" => 0, "isbn" => "", "precio" => "", "codigo_barras" => "", "stock" => "0",
    "formato_fisico" => 1, "formato_digital" => 0, "precio_digital" => ""
];

// Indica si el libro que se está editando ya tiene un archivo digital
// guardado. El nombre del archivo nunca se muestra en la página.
$tiene_archivo = false;

// ------------------------------------------------------------
// ARCHIVOS DIGITALES
// Se guardan en la carpeta "digitales" del proyecto, que está
// protegida para que no se pueda abrir desde el navegador.
// En la base solo se guarda el nombre del archivo.
// ------------------------------------------------------------
$carpeta_digitales = __DIR__ . "/../digitales/";
$tamano_maximo = 20 * 1024 * 1024;   // 20 MB

// ------------------------------------------------------------
// ACCIONES
// Se ejecuta al enviar cualquiera de los formularios de la página.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = texto_de($_POST["accion"] ?? null);

    // Si el archivo enviado supera el límite del servidor, PHP descarta
    // todo el formulario y no llega ningún dato: se avisa del motivo.
    if (count($_POST) == 0) {
        $errores[] = "El archivo es demasiado grande. No se guardó nada.";
    }

    // --- Dar de baja o reactivar ---
    // Es una baja lógica: nunca se usa DELETE, solo se cambia "activo".
    if ($accion == "baja" || $accion == "reactivar") {
        $id = (int) $_POST["id"];
        $activo = ($accion == "reactivar") ? 1 : 0;

        $consulta = mysqli_prepare($conexion, "UPDATE libros SET activo = ? WHERE id = ?");
        mysqli_stmt_bind_param($consulta, "ii", $activo, $id);
        mysqli_stmt_execute($consulta);

        aviso_guardar("libros", ($activo == 1) ? "Libro reactivado." : "Libro dado de baja.");
        header("Location: libros_admin.php");
        exit;
    }

    // --- Guardar un libro (alta si id = 0, edición si id > 0) ---
    if ($accion == "guardar") {
        // Recoge lo escrito en el formulario. trim() quita los espacios sobrantes.
        $libro["id"] = (int) $_POST["id"];
        $libro["titulo"] = trim($_POST["titulo"]);
        $libro["autor"] = trim($_POST["autor"]);
        $libro["anio"] = trim($_POST["anio"]);
        $libro["portada"] = trim($_POST["portada"]);
        $libro["descripcion"] = trim($_POST["descripcion"]);
        $libro["categoria_id"] = (int) $_POST["categoria_id"];
        $libro["isbn"] = trim($_POST["isbn"]);
        // Se acepta el precio con coma o con punto (890,50 o 890.50).
        $libro["precio"] = str_replace(",", ".", trim($_POST["precio"]));
        $libro["codigo_barras"] = trim($_POST["codigo_barras"]);
        $libro["stock"] = trim($_POST["stock"]);
        // Una casilla marcada llega en el formulario; una sin marcar no llega.
        $libro["formato_fisico"] = isset($_POST["formato_fisico"]) ? 1 : 0;
        $libro["formato_digital"] = isset($_POST["formato_digital"]) ? 1 : 0;
        $libro["precio_digital"] = str_replace(",", ".", trim($_POST["precio_digital"]));

        // Nombre del archivo digital que el libro ya tiene guardado (si tiene).
        // Se lee de la base, nunca del formulario.
        $archivo_actual = null;

        // ---------- Validaciones ----------
        // Si es una edición, el libro tiene que existir.
        if ($libro["id"] > 0) {
            $existente = buscar_fila($conexion,
                "SELECT id, archivo_digital FROM libros WHERE id = ?",
                "i", $libro["id"]);
            if ($existente) {
                $archivo_actual = $existente["archivo_digital"];
                $tiene_archivo = ($archivo_actual != "");
            } else {
                $errores[] = "El libro que quieres editar no existe.";
            }
        }

        // Todo libro tiene que venderse al menos en un formato.
        if ($libro["formato_fisico"] == 0 && $libro["formato_digital"] == 0) {
            $errores[] = "Marca al menos un formato: físico, digital o los dos.";
        }

        if ($libro["titulo"] == "") {
            $errores[] = "El título es obligatorio.";
        } elseif (strlen($libro["titulo"]) > 150) {
            $errores[] = "El título es demasiado largo.";
        }

        if ($libro["autor"] == "") {
            $errores[] = "El autor es obligatorio.";
        } elseif (strlen($libro["autor"]) > 100) {
            $errores[] = "El autor es demasiado largo.";
        }

        // El año puede quedar vacío; si se escribe, tiene que ser razonable.
        if ($libro["anio"] != "") {
            if (!ctype_digit($libro["anio"]) || $libro["anio"] < 1000 || $libro["anio"] > date("Y")) {
                $errores[] = "El año debe ser un número entre 1000 y " . date("Y") . ".";
            }
        }

        // La portada puede quedar vacía. Si se escribe, hay dos casos:
        //  - una dirección web completa (http:// o https://), o
        //  - un archivo local, que SOLO puede estar en la carpeta img/portadas.
        // La validación del archivo local está en portada.php (portada_local):
        // normaliza la ruta, rechaza "..", rutas absolutas y otras carpetas,
        // exige una extensión de imagen y comprueba que el archivo exista
        // dentro de img/portadas. Lo que se guarda es la ruta ya normalizada.
        // El mensaje de error es siempre el mismo y no muestra rutas internas.
        if ($libro["portada"] != "") {
            include_once "portada.php";
            if (preg_match("/^https?:\/\//i", $libro["portada"])) {
                if (strlen($libro["portada"]) > 255 || !filter_var($libro["portada"], FILTER_VALIDATE_URL)) {
                    $errores[] = "La dirección web de la portada no es válida.";
                }
            } else {
                $portada_local = portada_local($libro["portada"]);
                if ($portada_local === "") {
                    $errores[] = "La portada debe ser una imagen (.webp, .jpg, .jpeg o .png) que exista en la carpeta img/portadas, por ejemplo img/portadas/mi-libro.webp, o una dirección web que empiece por http:// o https://.";
                } else {
                    $libro["portada"] = $portada_local;
                }
            }
        }

        if (!isset($categorias[$libro["categoria_id"]])) {
            $errores[] = "Elige una categoría válida.";
        }

        // El precio y el stock son del formato físico: solo se exigen si el libro es físico.
        if ($libro["formato_fisico"] == 1) {
            if (!is_numeric($libro["precio"]) || $libro["precio"] < 0 || $libro["precio"] > 999999.99) {
                $errores[] = "El precio físico debe ser un número igual o mayor que 0.";
            }

            // ctype_digit solo acepta dígitos: rechaza negativos, decimales y texto.
            if (!ctype_digit($libro["stock"])) {
                $errores[] = "El stock debe ser un número entero igual o mayor que 0.";
            }
        }

        // El ISBN puede quedar vacío, pero si tiene valor no puede repetirse en otro libro.
        if (strlen($libro["isbn"]) > 20) {
            $errores[] = "El ISBN es demasiado largo.";
        } elseif ($libro["isbn"] != "") {
            if (buscar_fila($conexion,
                "SELECT id FROM libros WHERE isbn = ? AND id <> ?",
                "si", $libro["isbn"], $libro["id"])) {
                $errores[] = "Ya existe otro libro con ese ISBN.";
            }
        }

        // El código de barras es obligatorio si el libro es físico (es el que
        // lee la caja). Si no es físico puede quedar vacío. Si tiene valor,
        // no puede repetirse en otro libro.
        if ($libro["codigo_barras"] == "") {
            if ($libro["formato_fisico"] == 1) {
                $errores[] = "El código de barras es obligatorio para el formato físico.";
            }
        } elseif (strlen($libro["codigo_barras"]) > 20) {
            $errores[] = "El código de barras es demasiado largo.";
        } else {
            if (buscar_fila($conexion,
                "SELECT id FROM libros WHERE codigo_barras = ? AND id <> ?",
                "si", $libro["codigo_barras"], $libro["id"])) {
                $errores[] = "Ya existe otro libro con ese código de barras.";
            }
        }

        // ---------- Formato digital: precio y archivo ----------
        // $subida queda con los datos del archivo nuevo si se envió uno válido.
        $subida = null;
        $extension = "";

        if ($libro["formato_digital"] == 1) {
            if (!is_numeric($libro["precio_digital"]) || $libro["precio_digital"] < 0 || $libro["precio_digital"] > 999999.99) {
                $errores[] = "El precio digital debe ser un número igual o mayor que 0.";
            }

            // ¿Se envió un archivo? (error 4 = UPLOAD_ERR_NO_FILE = no se eligió ninguno)
            $archivo = isset($_FILES["archivo"]) ? $_FILES["archivo"] : null;

            if ($archivo && !is_array($archivo["error"]) && $archivo["error"] != UPLOAD_ERR_NO_FILE) {
                // La extensión se toma del nombre original, pero ese nombre
                // no se usa para nada más.
                $extension = strtolower(pathinfo($archivo["name"], PATHINFO_EXTENSION));

                if ($archivo["error"] == UPLOAD_ERR_INI_SIZE || $archivo["error"] == UPLOAD_ERR_FORM_SIZE || $archivo["size"] > $tamano_maximo) {
                    $errores[] = "El archivo digital es demasiado grande (máximo 20 MB).";
                } elseif ($archivo["error"] != UPLOAD_ERR_OK || !is_uploaded_file($archivo["tmp_name"])) {
                    $errores[] = "No se pudo subir el archivo digital.";
                } elseif ($extension != "pdf" && $extension != "epub") {
                    $errores[] = "El archivo digital debe ser PDF o EPUB.";
                } else {
                    // No alcanza con la extensión: se mira el comienzo del archivo
                    // para comprobar que por dentro es realmente un PDF o un EPUB.
                    //  - Un PDF siempre empieza con "%PDF-".
                    //  - Un EPUB es un archivo comprimido que empieza con "PK" y
                    //    lleva escrito su tipo "application/epub+zip" al principio.
                    $inicio = (string) file_get_contents($archivo["tmp_name"], false, null, 0, 200);

                    if ($extension == "pdf") {
                        $contenido_valido = (substr($inicio, 0, 5) == "%PDF-");
                    } else {
                        $contenido_valido = (substr($inicio, 0, 4) == "PK\x03\x04" && strpos($inicio, "application/epub+zip") !== false);
                    }

                    if ($contenido_valido) {
                        $subida = $archivo;
                    } else {
                        $errores[] = "El archivo no es un PDF o EPUB válido.";
                    }
                }
            } elseif ($archivo_actual == "") {
                // No se envió archivo y el libro tampoco tenía uno guardado.
                $errores[] = "Para el formato digital tienes que subir el archivo (PDF o EPUB).";
            }
        }

        // ---------- Guardado ----------
        if (count($errores) == 0) {
            // Los campos opcionales vacíos se guardan como NULL (sin valor).
            $anio = ($libro["anio"] == "") ? null : (int) $libro["anio"];
            $portada = ($libro["portada"] == "") ? null : $libro["portada"];
            $isbn = ($libro["isbn"] == "") ? null : $libro["isbn"];
            $codigo_barras = ($libro["codigo_barras"] == "") ? null : $libro["codigo_barras"];

            // Si el libro no es físico, no usa precio físico ni stock: quedan en 0.
            $precio = ($libro["formato_fisico"] == 1) ? (float) $libro["precio"] : 0;
            $stock = ($libro["formato_fisico"] == 1) ? (int) $libro["stock"] : 0;

            // Si el libro no es digital, no tiene precio digital ni archivo (NULL).
            // Si es digital y no se subió un archivo nuevo, conserva el que tenía.
            $precio_digital = ($libro["formato_digital"] == 1) ? (float) $libro["precio_digital"] : null;
            $archivo_digital = ($libro["formato_digital"] == 1) ? $archivo_actual : null;

            $guardado = false;
            $archivo_nuevo = "";   // Ruta del archivo recién guardado, si hubo subida

            // Guarda el archivo subido en la carpeta protegida.
            // No se conserva el nombre original: se inventa uno seguro y único
            // ("libro_" + 32 letras y números al azar + la extensión validada).
            if ($subida) {
                $nombre_seguro = "libro_" . bin2hex(random_bytes(16)) . "." . $extension;

                if (move_uploaded_file($subida["tmp_name"], $carpeta_digitales . $nombre_seguro)) {
                    $archivo_digital = $nombre_seguro;
                    $archivo_nuevo = $carpeta_digitales . $nombre_seguro;
                } else {
                    $errores[] = "No se pudo guardar el archivo digital.";
                }
            }
        }

        if (count($errores) == 0) {
            try {
                if ($libro["id"] == 0) {
                    // Alta: el libro nuevo queda activo.
                    $consulta = mysqli_prepare($conexion,
                        "INSERT INTO libros (titulo, autor, anio, portada, descripcion, categoria_id, isbn, precio, codigo_barras, stock,
                                             formato_fisico, formato_digital, precio_digital, archivo_digital, activo)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    mysqli_stmt_bind_param($consulta, "ssissisdsiiids", $libro["titulo"], $libro["autor"], $anio, $portada,
                        $libro["descripcion"], $libro["categoria_id"], $isbn, $precio, $codigo_barras, $stock,
                        $libro["formato_fisico"], $libro["formato_digital"], $precio_digital, $archivo_digital);
                    $guardado = mysqli_stmt_execute($consulta);
                    $mensaje = "Libro agregado: " . $libro["titulo"] . ".";
                } else {
                    // Edición: se cambian los datos, pero nunca el id.
                    $consulta = mysqli_prepare($conexion,
                        "UPDATE libros SET titulo = ?, autor = ?, anio = ?, portada = ?, descripcion = ?, categoria_id = ?,
                                isbn = ?, precio = ?, codigo_barras = ?, stock = ?,
                                formato_fisico = ?, formato_digital = ?, precio_digital = ?, archivo_digital = ?
                         WHERE id = ?");
                    mysqli_stmt_bind_param($consulta, "ssissisdsiiidsi", $libro["titulo"], $libro["autor"], $anio, $portada,
                        $libro["descripcion"], $libro["categoria_id"], $isbn, $precio, $codigo_barras, $stock,
                        $libro["formato_fisico"], $libro["formato_digital"], $precio_digital, $archivo_digital, $libro["id"]);
                    $guardado = mysqli_stmt_execute($consulta);
                    $mensaje = "Libro actualizado: " . $libro["titulo"] . ".";
                }
            } catch (Throwable $e) {
                // Error inesperado de la base: no se muestran detalles internos.
                $guardado = false;
            }

            // Si el libro no se pudo guardar, el archivo recién subido no
            // queda asociado a ningún libro: se quita de la carpeta.
            if (!$guardado && $archivo_nuevo != "") {
                unlink($archivo_nuevo);
            }

            if ($guardado) {
                // Guarda el aviso en la sesión y recarga la página, para que
                // al refrescar el navegador no se repita el guardado.
                aviso_guardar("libros", $mensaje);
                header("Location: libros_admin.php");
                exit;
            }

            $errores[] = "No se pudo guardar el libro.";
        }
    }
}

// ------------------------------------------------------------
// CARGAR UN LIBRO PARA EDITARLO
// Si en la dirección viene ?editar=NÚMERO (y no se acaba de
// enviar el formulario), se rellenan los campos con ese libro.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] != "POST" && isset($_GET["editar"])) {
    $id = (int) $_GET["editar"];

    $encontrado = buscar_fila($conexion, "SELECT * FROM libros WHERE id = ?", "i", $id);

    if ($encontrado) {
        $libro = $encontrado;
        $tiene_archivo = ($encontrado["archivo_digital"] != "");
    } else {
        $errores[] = "El libro que quieres editar no existe.";
    }
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
list($mensaje) = aviso_leer("libros");

// ------------------------------------------------------------
// LISTADO
// Todos los libros, activos e inactivos, con el nombre de su categoría.
// ------------------------------------------------------------
$lista = mysqli_query($conexion,
    "SELECT libros.id, libros.titulo, libros.autor, libros.isbn, libros.precio, libros.stock, libros.activo,
            libros.formato_fisico, libros.formato_digital, libros.precio_digital,
            categorias.nombre AS categoria
     FROM libros
     JOIN categorias ON categorias.id = libros.categoria_id
     ORDER BY libros.titulo");
?>
<?php
$titulo_pagina = "Gestión de libros";
include "cabecera.php";
?>

    <main>
        <h2>Gestión de libros</h2>
        <p class="descripcion-pagina">Alta, edición y baja de los libros del catálogo, en formato físico, digital o ambos.</p>

        <!-- Aviso verde después de guardar, dar de baja o reactivar -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <!-- Errores de validación del formulario -->
        <?php foreach ($errores as $error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <!-- Formulario de alta y edición. El campo oculto "id" indica
             si es un libro nuevo (0) o uno que ya existe.
             enctype="multipart/form-data" es necesario para poder enviar un archivo. -->
        <form class="form-libro" name="form_libro" method="POST" action="libros_admin.php" id="formulario" enctype="multipart/form-data">
            <h3><?php echo ($libro["id"] > 0) ? "Editar libro" : "Agregar libro"; ?></h3>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?php echo (int) $libro["id"]; ?>">

            <div>
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" maxlength="150" value="<?php echo htmlspecialchars($libro["titulo"]); ?>" required>
            </div>
            <div>
                <label for="autor">Autor</label>
                <input type="text" id="autor" name="autor" maxlength="100" value="<?php echo htmlspecialchars($libro["autor"]); ?>" required>
            </div>
            <div>
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Elige una categoría</option>
                    <?php foreach ($categorias as $id_categoria => $nombre_categoria) { ?>
                        <option value="<?php echo $id_categoria; ?>" <?php if ($libro["categoria_id"] == $id_categoria) echo "selected"; ?>>
                            <?php echo htmlspecialchars($nombre_categoria); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label for="anio">Año de publicación</label>
                <input type="number" id="anio" name="anio" min="1000" max="<?php echo date("Y"); ?>" value="<?php echo htmlspecialchars((string) $libro["anio"]); ?>">
            </div>
            <div>
                <label for="isbn">ISBN (opcional)</label>
                <input type="text" id="isbn" name="isbn" maxlength="20" value="<?php echo htmlspecialchars((string) $libro["isbn"]); ?>">
            </div>

            <!-- Formatos en los que se vende el libro: hay que marcar al menos uno -->
            <div class="campo-ancho">
                <!-- Título del grupo de casillas; cada casilla tiene además su propia etiqueta -->
                <label id="formatos_titulo">Formatos en los que se vende</label>
                <div class="casillas" role="group" aria-labelledby="formatos_titulo">
                    <label for="formato_fisico"><input type="checkbox" id="formato_fisico" name="formato_fisico" value="1" <?php if ($libro["formato_fisico"] == 1) echo "checked"; ?>> Formato físico</label>
                    <label for="formato_digital"><input type="checkbox" id="formato_digital" name="formato_digital" value="1" <?php if ($libro["formato_digital"] == 1) echo "checked"; ?>> Formato digital</label>
                </div>
            </div>

            <!-- Datos del formato físico: solo se exigen si está marcado "Formato físico" -->
            <div>
                <label for="codigo_barras">Código de barras (físico)</label>
                <input type="text" id="codigo_barras" name="codigo_barras" maxlength="20" value="<?php echo htmlspecialchars((string) $libro["codigo_barras"]); ?>">
            </div>
            <div>
                <label for="precio">Precio físico</label>
                <input type="number" id="precio" name="precio" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $libro["precio"]); ?>">
            </div>
            <div>
                <label for="stock">Stock (físico)</label>
                <input type="number" id="stock" name="stock" min="0" step="1" value="<?php echo htmlspecialchars((string) $libro["stock"]); ?>">
            </div>

            <!-- Datos del formato digital: solo se exigen si está marcado "Formato digital" -->
            <div>
                <label for="precio_digital">Precio digital</label>
                <input type="number" id="precio_digital" name="precio_digital" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $libro["precio_digital"]); ?>">
            </div>
            <div class="campo-ancho">
                <label for="archivo">Archivo digital (PDF o EPUB, máximo 20 MB)</label>
                <!-- "accept" solo ayuda a elegir el archivo; la comprobación real se hace en el servidor -->
                <input type="file" id="archivo" name="archivo" accept=".pdf,.epub">
                <!-- Nunca se muestra el nombre ni la ubicación del archivo guardado -->
                <?php if ($tiene_archivo) { ?>
                    <p class="ayuda">Este libro ya tiene un archivo digital cargado. Si no eliges otro, se conserva el actual.</p>
                <?php } else { ?>
                    <p class="ayuda">Este libro todavía no tiene un archivo digital cargado.</p>
                <?php } ?>
            </div>

            <div class="campo-ancho">
                <label for="portada">Portada (archivo de la carpeta img/portadas)</label>
                <input type="text" id="portada" name="portada" maxlength="255" placeholder="img/portadas/nombre-del-libro.webp" value="<?php echo htmlspecialchars((string) $libro["portada"]); ?>">
                <p class="ayuda">Si queda vacío, el libro se muestra con la portada de reemplazo.</p>
            </div>
            <div class="campo-ancho">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars((string) $libro["descripcion"]); ?></textarea>
            </div>
            <div class="campo-ancho botones-libro">
                <button class="btn btn-primary" type="submit"><?php echo ($libro["id"] > 0) ? "Guardar cambios" : "Agregar libro"; ?></button>
                <!-- Al editar, permite volver al formulario vacío sin guardar -->
                <?php if ($libro["id"] > 0) { ?>
                    <a class="volver" href="libros_admin.php">Cancelar</a>
                <?php } ?>
            </div>
        </form>

        <h3 class="titulo-ticket">Libros registrados</h3>

        <!-- Tabla con todos los libros, también los dados de baja -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>Título</th>
                <th>Autor</th>
                <th>Categoría</th>
                <th>ISBN</th>
                <th>Físico</th>
                <th>Stock</th>
                <th>Digital</th>
                <th>Estado</th>
                <th></th>
            </tr>
            <!-- Una fila por cada libro -->
            <?php while ($fila = mysqli_fetch_assoc($lista)) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién modificada -->
            <tr data-fila="<?php echo $fila["id"]; ?>" <?php if ($fila["activo"] == 0) echo 'class="fila-inactiva"'; ?>>
                <td><?php echo htmlspecialchars($fila["titulo"]); ?></td>
                <td><?php echo htmlspecialchars($fila["autor"]); ?></td>
                <td><?php echo htmlspecialchars($fila["categoria"]); ?></td>
                <td><?php echo $fila["isbn"] ? htmlspecialchars($fila["isbn"]) : "Sin ISBN"; ?></td>
                <!-- Precio y stock del formato físico, y precio del digital.
                     Un guion indica que el libro no se vende en ese formato. -->
                <td class="sin-corte"><?php echo ($fila["formato_fisico"] == 1) ? "$ " . formatearPrecio($fila["precio"]) : "—"; ?></td>
                <td><?php echo ($fila["formato_fisico"] == 1) ? $fila["stock"] : "—"; ?></td>
                <td class="sin-corte"><?php echo ($fila["formato_digital"] == 1) ? "$ " . formatearPrecio($fila["precio_digital"]) : "—"; ?></td>
                <td>
                    <!-- Etiqueta verde si está activo, roja si está dado de baja -->
                    <?php if ($fila["activo"] == 1) { ?>
                        <span class="disponible">Activo</span>
                    <?php } else { ?>
                        <span class="agotado">Inactivo</span>
                    <?php } ?>
                </td>
                <td>
                    <div class="acciones-linea">
                        <a class="btn btn-primary btn-sm" href="libros_admin.php?editar=<?php echo $fila["id"]; ?>#formulario">Editar</a>
                        <!-- Baja lógica o reactivación, según el estado del libro -->
                        <!-- data-confirmar (solo al dar de baja): main.js pregunta antes de enviar -->
                        <form name="form_libro_estado_<?php echo $fila["id"]; ?>" method="POST" action="libros_admin.php" <?php if ($fila["activo"] == 1) echo 'data-confirmar="¿Dar de baja este libro? Dejará de verse en el catálogo y en la caja."'; ?>>
                            <input type="hidden" name="id" value="<?php echo $fila["id"]; ?>">
                            <?php if ($fila["activo"] == 1) { ?>
                                <input type="hidden" name="accion" value="baja">
                                <button class="btn btn-danger btn-sm" type="submit">Dar de baja</button>
                            <?php } else { ?>
                                <input type="hidden" name="accion" value="reactivar">
                                <button class="btn btn-success btn-sm" type="submit">Reactivar</button>
                            <?php } ?>
                        </form>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </main>

<?php include "pie.php"; ?>
