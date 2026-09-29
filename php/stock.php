<?php
// ============================================================
// GESTIÓN DE STOCK
// Página para el administrador: muestra todos los libros con
// sus copias disponibles y permite cambiar la cantidad.
// Solo pueden entrar los usuarios con admin = 1.
// ============================================================

// Inicia (o recupera) la sesión del usuario.
session_start();

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// Si no hay sesión, manda al login. Si el usuario no es
// administrador, lo devuelve al catálogo.
// ------------------------------------------------------------
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION["admin"])) {
    header("Location: libros.php");
    exit;
}

// Conecta con la base de datos.
include "conexion.php";

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

    $actualizar = mysqli_prepare($conexion, "UPDATE libros SET stock = ? WHERE id = ?");
    mysqli_stmt_bind_param($actualizar, "ii", $stock, $id);
    mysqli_stmt_execute($actualizar);

    $mensaje = "Stock actualizado correctamente.";
}

// Pide todos los libros para mostrarlos en la tabla.
$resultado = mysqli_query($conexion, "SELECT id, titulo, autor, portada, stock FROM libros");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de stock - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=5">
</head>
<body>
    <!-- Encabezado: menú, saludo con el nombre del usuario y botón para salir -->
    <header>
        <h1>Biblioteca Digital</h1>
        <nav>
            <a href="../index.html">Inicio</a>
            <a href="libros.php">Catálogo</a>
            <a href="stock.php">Gestionar stock</a>
            <a href="resenas.php">Gestionar reseñas</a>
            <span class="usuario">Hola, <?php echo htmlspecialchars($_SESSION["nombre"]); ?></span>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main>
        <h2>Gestión de stock</h2>

        <!-- Aviso verde después de guardar un cambio -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <!-- Tabla con todos los libros y su stock -->
        <table class="tabla-stock">
            <tr>
                <th>Portada</th>
                <th>Libro</th>
                <th>Estado</th>
                <th>Copias disponibles</th>
            </tr>
            <!-- Una fila por cada libro -->
            <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
                <td><img src="<?php echo $fila["portada"]; ?>" alt="<?php echo htmlspecialchars($fila["titulo"]); ?>"></td>
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
                    <form class="form-stock" method="POST">
                        <input type="hidden" name="id" value="<?php echo $fila["id"]; ?>">
                        <input type="number" name="stock" min="0" value="<?php echo $fila["stock"]; ?>">
                        <button type="submit">Guardar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
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
