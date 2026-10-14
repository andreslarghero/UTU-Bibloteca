<?php
// ============================================================
// GESTIÓN DE STOCK
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
include "verificar_bibliotecario.php";

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
    $actualizar = mysqli_prepare($conexion, "UPDATE libros SET stock = ? WHERE id = ? AND formato_fisico = 1");
    mysqli_stmt_bind_param($actualizar, "ii", $stock, $id);
    mysqli_stmt_execute($actualizar);

    $mensaje = "Stock actualizado correctamente.";
}

// Pide los libros que se venden en formato físico para mostrarlos en la tabla.
// Los libros solo digitales no tienen stock, así que no aparecen aquí.
$resultado = mysqli_query($conexion, "SELECT id, titulo, autor, portada, stock FROM libros WHERE formato_fisico = 1");

// Función que dibuja la portada (imagen o reemplazo propio).
include "portada.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de stock - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

    <main>
        <h2>Gestión de stock</h2>
        <p class="descripcion-pagina">Copias disponibles de los libros físicos. Los libros digitales no usan stock.</p>

        <!-- Aviso verde después de guardar un cambio -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <!-- Tabla con todos los libros y su stock -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla-stock">
            <tr>
                <th>Portada</th>
                <th>Libro</th>
                <th>Estado</th>
                <th>Copias disponibles</th>
            </tr>
            <!-- Una fila por cada libro -->
            <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién guardada -->
            <tr data-fila="<?php echo $fila["id"]; ?>">
                <td>
                    <!-- Miniatura de la portada (o su reemplazo si no tiene imagen) -->
                    <?php echo portada_html($fila["titulo"], $fila["portada"]); ?>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($fila["titulo"]); ?></strong><br>
                    <?php echo htmlspecialchars($fila["autor"]); ?>
                </td>
                <td>
                    <!-- Etiqueta verde si hay copias, roja si está agotado -->
                    <?php if ($fila["stock"] > 0) { ?>
                        <span class="disponible">Disponible</span>
                    <?php } else { ?>
                        <span class="agotado">Agotado</span>
                    <?php } ?>
                </td>
                <td>
                    <!-- Formulario para cambiar el stock de este libro -->
                    <!-- La etiqueta del campo no se ve en pantalla (clase "solo-lectores"),
                         pero la leen los lectores de pantalla. El id lleva el número
                         del libro para que no se repita entre filas. -->
                    <form class="form-stock" name="form_stock_<?php echo $fila["id"]; ?>" method="POST" action="stock.php">
                        <input type="hidden" name="id" value="<?php echo $fila["id"]; ?>">
                        <label class="solo-lectores" for="stock_<?php echo $fila["id"]; ?>">Copias disponibles de <?php echo htmlspecialchars($fila["titulo"]); ?></label>
                        <input type="number" id="stock_<?php echo $fila["id"]; ?>" name="stock" min="0" value="<?php echo $fila["stock"]; ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
<?php
// Cierra la conexión con la base de datos al terminar la página.
mysqli_close($conexion);
?>
