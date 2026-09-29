<?php
// ============================================================
// GESTIÓN DE RESEÑAS
// Página para el administrador: muestra todas las reseñas de
// todos los libros y permite eliminar las que no sean adecuadas.
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

// Aquí se guarda el mensaje que confirma el borrado.
$mensaje = "";

// ------------------------------------------------------------
// ELIMINAR UNA RESEÑA
// Se ejecuta al pulsar "Eliminar" en una fila.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $resena_id = (int) $_POST["resena_id"];

    $borrar = mysqli_prepare($conexion, "DELETE FROM resenas WHERE id = ?");
    mysqli_stmt_bind_param($borrar, "i", $resena_id);
    mysqli_stmt_execute($borrar);

    $mensaje = "Reseña eliminada correctamente.";
}

// Pide todas las reseñas con el título del libro y el nombre del usuario,
// de la más nueva a la más antigua.
$resultado = mysqli_query($conexion,
    "SELECT resenas.*, libros.titulo, usuarios.nombre FROM resenas
     JOIN libros ON libros.id = resenas.libro_id
     JOIN usuarios ON usuarios.id = resenas.usuario_id
     ORDER BY resenas.fecha DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de reseñas - Biblioteca Digital</title>
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
        <h2>Gestión de reseñas</h2>

        <!-- Aviso verde después de eliminar una reseña -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <?php if (mysqli_num_rows($resultado) == 0) { ?>
            <!-- Aviso si todavía no hay reseñas -->
            <p class="sin-resultados">Todavía no hay reseñas.</p>
        <?php } else { ?>
        <!-- Tabla con todas las reseñas -->
        <table class="tabla-stock">
            <tr>
                <th>Libro</th>
                <th>Usuario</th>
                <th>Valoración</th>
                <th>Comentario</th>
                <th>Fecha</th>
                <th></th>
            </tr>
            <!-- Una fila por cada reseña -->
            <?php while ($r = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
                <td><a href="libro.php?id=<?php echo $r["libro_id"]; ?>"><?php echo htmlspecialchars($r["titulo"]); ?></a></td>
                <td><?php echo htmlspecialchars($r["nombre"]); ?></td>
                <td class="estrellas"><?php echo str_repeat("★", $r["puntuacion"]) . str_repeat("☆", 5 - $r["puntuacion"]); ?></td>
                <td><?php echo nl2br(htmlspecialchars($r["comentario"])); ?></td>
                <td><?php echo date("d/m/Y", strtotime($r["fecha"])); ?></td>
                <td>
                    <!-- Botón para eliminar la reseña (pide confirmación) -->
                    <form method="POST" onsubmit="return confirm('¿Eliminar esta reseña?');">
                        <input type="hidden" name="resena_id" value="<?php echo $r["id"]; ?>">
                        <button class="boton-eliminar" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        <?php } ?>
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
