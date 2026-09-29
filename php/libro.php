<?php
// ============================================================
// DETALLE DE UN LIBRO
// Muestra toda la información de un libro concreto, sus reseñas
// y un formulario para que el usuario deje su valoración.
// Se abre desde el catálogo con la dirección libro.php?id=NÚMERO.
// Solo pueden verla los usuarios que han iniciado sesión.
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
// BUSCAR EL LIBRO
// Toma el id de la dirección y busca ese libro en la base de datos.
// (int) convierte el valor a número para evitar datos no válidos.
// ------------------------------------------------------------
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$consulta = mysqli_prepare($conexion, "SELECT * FROM libros WHERE id = ?");
mysqli_stmt_bind_param($consulta, "i", $id);
mysqli_stmt_execute($consulta);
$libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

// ------------------------------------------------------------
// GUARDAR O ELIMINAR UNA RESEÑA
// Se ejecuta al enviar el formulario de reseña o al pulsar "Eliminar".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && $libro) {
    if ($_POST["accion"] == "guardar") {
        // La puntuación se limita entre 1 y 5 estrellas.
        $puntuacion = min(5, max(1, (int) $_POST["puntuacion"]));
        $comentario = trim($_POST["comentario"]);

        // Si el usuario ya tenía reseña en este libro, se actualiza en vez de duplicarse.
        $guardar = mysqli_prepare($conexion,
            "INSERT INTO resenas (libro_id, usuario_id, puntuacion, comentario) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion), comentario = VALUES(comentario), fecha = NOW()");
        mysqli_stmt_bind_param($guardar, "iiis", $id, $_SESSION["id"], $puntuacion, $comentario);
        mysqli_stmt_execute($guardar);
    }

    if ($_POST["accion"] == "eliminar") {
        $resena_id = (int) $_POST["resena_id"];

        if (!empty($_SESSION["admin"])) {
            // El administrador puede borrar cualquier reseña.
            $borrar = mysqli_prepare($conexion, "DELETE FROM resenas WHERE id = ?");
            mysqli_stmt_bind_param($borrar, "i", $resena_id);
        } else {
            // Un usuario normal solo puede borrar su propia reseña.
            $borrar = mysqli_prepare($conexion, "DELETE FROM resenas WHERE id = ? AND usuario_id = ?");
            mysqli_stmt_bind_param($borrar, "ii", $resena_id, $_SESSION["id"]);
        }
        mysqli_stmt_execute($borrar);
    }

    // Recarga la página para mostrar los cambios (y evitar reenviar el formulario al refrescar).
    header("Location: libro.php?id=" . $id);
    exit;
}

// ------------------------------------------------------------
// DATOS DE LAS RESEÑAS
// ------------------------------------------------------------
if ($libro) {
    // Valoración media y número total de reseñas del libro.
    $consulta = mysqli_prepare($conexion, "SELECT ROUND(AVG(puntuacion), 1) AS media, COUNT(*) AS total FROM resenas WHERE libro_id = ?");
    mysqli_stmt_bind_param($consulta, "i", $id);
    mysqli_stmt_execute($consulta);
    $valoracion = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

    // Lista de reseñas con el nombre de quien las escribió, de la más nueva a la más antigua.
    $consulta = mysqli_prepare($conexion,
        "SELECT resenas.*, usuarios.nombre FROM resenas
         JOIN usuarios ON usuarios.id = resenas.usuario_id
         WHERE resenas.libro_id = ? ORDER BY resenas.fecha DESC");
    mysqli_stmt_bind_param($consulta, "i", $id);
    mysqli_stmt_execute($consulta);
    $resenas = mysqli_stmt_get_result($consulta);

    // Reseña del usuario actual (si ya tiene una), para rellenar el formulario.
    $consulta = mysqli_prepare($conexion, "SELECT puntuacion, comentario FROM resenas WHERE libro_id = ? AND usuario_id = ?");
    mysqli_stmt_bind_param($consulta, "ii", $id, $_SESSION["id"]);
    mysqli_stmt_execute($consulta);
    $mi_resena = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
}

// Convierte un número (1 a 5) en estrellas llenas y vacías, por ejemplo 3 = ★★★☆☆.
function estrellas($numero) {
    $llenas = (int) round($numero);
    return str_repeat("★", $llenas) . str_repeat("☆", 5 - $llenas);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $libro ? htmlspecialchars($libro["titulo"]) : "Libro no encontrado"; ?> - Biblioteca Digital</title>
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
        <!-- Enlace para volver al catálogo -->
        <a class="volver" href="libros.php">&larr; Volver al catálogo</a>

        <?php if ($libro) { ?>
        <!-- Ficha del libro: portada a la izquierda e información a la derecha -->
        <div class="detalle">
            <img src="<?php echo $libro["portada"]; ?>" alt="<?php echo htmlspecialchars($libro["titulo"]); ?>">

            <div class="detalle-info">
                <h2><?php echo htmlspecialchars($libro["titulo"]); ?></h2>

                <!-- Valoración media del libro -->
                <p class="valoracion">
                    <?php if ($valoracion["total"] > 0) { ?>
                        <span class="estrellas"><?php echo estrellas($valoracion["media"]); ?></span>
                        <?php echo $valoracion["media"]; ?> de 5 (<?php echo $valoracion["total"]; ?> reseñas)
                    <?php } else { ?>
                        Sin reseñas todavía
                    <?php } ?>
                </p>

                <!-- Datos principales del libro -->
                <p><strong>Autor:</strong> <?php echo htmlspecialchars($libro["autor"]); ?></p>
                <p><strong>Año de publicación:</strong> <?php echo $libro["anio"]; ?></p>
                <p><strong>Género:</strong> <?php echo htmlspecialchars($libro["genero"]); ?></p>

                <!-- Disponibilidad: copias en verde o "Agotado" en rojo -->
                <p><strong>Disponibilidad:</strong>
                    <?php if ($libro["stock"] > 0) { ?>
                        <span class="disponible"><?php echo $libro["stock"]; ?> copias disponibles</span>
                    <?php } else { ?>
                        <span class="agotado">Agotado</span>
                    <?php } ?>
                </p>

                <!-- Resumen del libro -->
                <h3>Descripción</h3>
                <p><?php echo htmlspecialchars($libro["descripcion"]); ?></p>
            </div>
        </div>

        <!-- ====================== RESEÑAS ====================== -->
        <section class="resenas">
            <h2>Reseñas</h2>

            <!-- Formulario para escribir (o editar) la reseña del usuario -->
            <form class="form-resena" method="POST">
                <h3><?php echo $mi_resena ? "Editar tu reseña" : "Escribe tu reseña"; ?></h3>
                <input type="hidden" name="accion" value="guardar">

                <label>Valoración</label>
                <select name="puntuacion" required>
                    <?php for ($i = 5; $i >= 1; $i--) { ?>
                        <option value="<?php echo $i; ?>" <?php if ($mi_resena && $mi_resena["puntuacion"] == $i) echo "selected"; ?>>
                            <?php echo estrellas($i); ?> (<?php echo $i; ?>)
                        </option>
                    <?php } ?>
                </select>

                <label>Comentario</label>
                <textarea name="comentario" rows="4" placeholder="¿Qué te pareció este libro?" required><?php echo $mi_resena ? htmlspecialchars($mi_resena["comentario"]) : ""; ?></textarea>

                <button type="submit">Publicar reseña</button>
            </form>

            <!-- Lista de reseñas del libro -->
            <?php if (mysqli_num_rows($resenas) == 0) { ?>
                <p class="sin-resultados">Todavía nadie ha reseñado este libro. ¡Sé el primero!</p>
            <?php } ?>

            <?php while ($r = mysqli_fetch_assoc($resenas)) { ?>
            <div class="resena">
                <div class="resena-cabecera">
                    <strong><?php echo htmlspecialchars($r["nombre"]); ?></strong>
                    <span class="estrellas"><?php echo estrellas($r["puntuacion"]); ?></span>
                    <span class="fecha"><?php echo date("d/m/Y", strtotime($r["fecha"])); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($r["comentario"])); ?></p>

                <!-- Botón para eliminar: aparece en la reseña propia o si eres administrador -->
                <?php if ($r["usuario_id"] == $_SESSION["id"] || !empty($_SESSION["admin"])) { ?>
                <form method="POST" onsubmit="return confirm('¿Eliminar esta reseña?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="resena_id" value="<?php echo $r["id"]; ?>">
                    <button class="boton-eliminar" type="submit">Eliminar</button>
                </form>
                <?php } ?>
            </div>
            <?php } ?>
        </section>

        <?php } else { ?>
        <!-- Aviso si el id no corresponde a ningún libro -->
        <p class="sin-resultados">El libro que buscas no existe.</p>
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
