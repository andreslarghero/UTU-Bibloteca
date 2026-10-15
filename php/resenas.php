<?php
// ============================================================
// GESTIÓN DE RESEÑAS
// Página para el administrador: muestra todas las reseñas de
// todos los libros y permite eliminar las que no sean adecuadas.
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

// Aquí se guarda el mensaje que confirma el borrado.
$mensaje = "";

// ------------------------------------------------------------
// ELIMINAR UNA RESEÑA
// Se ejecuta al pulsar "Eliminar" en una fila.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $resena_id = (int) $_POST["resena_id"];

    $borrar = mysqli_prepare($conexion, "DELETE FROM resenias WHERE id = ?");
    mysqli_stmt_bind_param($borrar, "i", $resena_id);
    mysqli_stmt_execute($borrar);

    $mensaje = "Reseña eliminada correctamente.";
}

// Pide todas las reseñas con el título del libro y el nombre del usuario,
// de la más nueva a la más antigua.
$resultado = mysqli_query($conexion,
    "SELECT resenias.*, libros.titulo, usuarios.nombre FROM resenias
     JOIN libros ON libros.id = resenias.libro_id
     JOIN usuarios ON usuarios.id = resenias.usuario_id
     ORDER BY resenias.fecha DESC");
?>
<?php
$titulo_pagina = "Gestión de reseñas";
include "cabecera.php";
?>

    <main>
        <h2>Gestión de reseñas</h2>
        <p class="descripcion-pagina">Todas las reseñas publicadas. Se puede eliminar cualquiera.</p>

        <!-- Aviso verde después de eliminar una reseña -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo $mensaje; ?></p>
        <?php } ?>

        <?php if (mysqli_num_rows($resultado) == 0) { ?>
            <!-- Aviso si todavía no hay reseñas -->
            <p class="sin-resultados">Todavía no hay reseñas.</p>
        <?php } else { ?>
        <!-- Tabla con todas las reseñas -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
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
                    <form name="form_eliminar_resenia_<?php echo $r["id"]; ?>" method="POST" action="resenas.php" data-confirmar="¿Eliminar esta reseña? No se puede deshacer.">
                        <input type="hidden" name="resena_id" value="<?php echo $r["id"]; ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
        <?php } ?>
    </main>

<?php include "pie.php"; ?>
