<?php
// ============================================================
// CATÁLOGO DE LIBROS
// Muestra todos los libros de la base de datos en tarjetas.
// Solo pueden verlo los usuarios que han iniciado sesión.
// ============================================================

// Inicia (o recupera) la sesión del usuario.
session_start();

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// Si no hay un usuario conectado, lo manda al login.
// ------------------------------------------------------------
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

// Conecta con la base de datos.
include "conexion.php";

// ------------------------------------------------------------
// BUSCADOR
// Si se escribió algo en el buscador, trae solo los libros cuyo
// título o autor contengan ese texto. Si no, trae todos.
// ------------------------------------------------------------
$buscar = isset($_GET["buscar"]) ? trim($_GET["buscar"]) : "";

// Consulta base: trae los libros junto con su valoración media y
// el número de reseñas (LEFT JOIN para incluir libros sin reseñas).
$sql = "SELECT libros.*, ROUND(AVG(resenas.puntuacion), 1) AS media, COUNT(resenas.id) AS total_resenas
        FROM libros LEFT JOIN resenas ON resenas.libro_id = libros.id";

if ($buscar != "") {
    // Los % permiten encontrar el texto en cualquier parte del título o autor.
    // Se usa una consulta preparada (el "?") para evitar ataques de inyección SQL.
    $texto = "%" . $buscar . "%";
    $consulta = mysqli_prepare($conexion, $sql . " WHERE libros.titulo LIKE ? OR libros.autor LIKE ? GROUP BY libros.id");
    mysqli_stmt_bind_param($consulta, "ss", $texto, $texto);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
} else {
    // Sin búsqueda: pide todos los libros.
    $resultado = mysqli_query($conexion, $sql . " GROUP BY libros.id");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=5">
</head>
<body>
    <!-- Encabezado: menú, saludo con el nombre del usuario y botón para salir -->
    <header>
        <h1>Biblioteca Digital</h1>
        <nav>
            <a href="../index.html">Inicio</a>
            <a href="libros.php">Catálogo</a>
            <!-- Enlaces de administración, solo para administradores -->
            <?php if (!empty($_SESSION["admin"])) { ?>
                <a href="stock.php">Gestionar stock</a>
                <a href="resenas.php">Gestionar reseñas</a>
            <?php } ?>
            <span class="usuario">Hola, <?php echo htmlspecialchars($_SESSION["nombre"]); ?></span>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </header>

    <main>
        <h2>Catálogo de libros</h2>

        <!-- Buscador: envía el texto a esta misma página por la dirección (?buscar=...) -->
        <form class="buscador" method="GET">
            <input type="text" name="buscar" placeholder="Buscar por título o autor..." value="<?php echo htmlspecialchars($buscar); ?>">
            <button type="submit">&#128269; Buscar</button>
            <!-- Botón para quitar la búsqueda, solo aparece si hay una -->
            <?php if ($buscar != "") { ?>
                <a href="libros.php">Ver todos</a>
            <?php } ?>
        </form>

        <!-- Aviso cuando la búsqueda no encuentra ningún libro -->
        <?php if (mysqli_num_rows($resultado) == 0) { ?>
            <p class="sin-resultados">No se encontraron libros para "<?php echo htmlspecialchars($buscar); ?>".</p>
        <?php } ?>

        <!-- Cuadrícula del catálogo -->
        <div class="catalogo">
            <!-- Recorre los libros uno por uno y crea una tarjeta para cada uno -->
            <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>
            <div class="libro">
                <img src="<?php echo $fila["portada"]; ?>" alt="<?php echo $fila["titulo"]; ?>">
                <h3><?php echo $fila["titulo"]; ?></h3>
                <p><?php echo $fila["autor"]; ?></p>
                <span class="anio"><?php echo $fila["anio"]; ?></span>
                <!-- Valoración media con estrellas, o aviso si no tiene reseñas -->
                <p class="valoracion-tarjeta">
                    <?php if ($fila["total_resenas"] > 0) { ?>
                        <span class="estrellas">★</span> <?php echo $fila["media"]; ?> (<?php echo $fila["total_resenas"]; ?>)
                    <?php } else { ?>
                        Sin reseñas
                    <?php } ?>
                </p>
                <!-- Disponibilidad: copias en verde o "Agotado" en rojo -->
                <?php if ($fila["stock"] > 0) { ?>
                    <p class="disponible"><?php echo $fila["stock"]; ?> disponibles</p>
                <?php } else { ?>
                    <p class="agotado">Agotado</p>
                <?php } ?>
                <!-- Botón que abre la página de detalle de este libro -->
                <a class="boton-detalle" href="libro.php?id=<?php echo $fila["id"]; ?>">Ver detalle</a>
            </div>
            <?php } ?>
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
