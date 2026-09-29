<?php
// ============================================================
// PÁGINA DE REGISTRO
// Muestra el formulario para crear una cuenta y, al enviarlo,
// guarda el nuevo usuario en la tabla "usuarios".
// ============================================================

// Conecta con la base de datos.
include "conexion.php";

// Aquí se guarda el mensaje de error que se muestra en el formulario.
$mensaje = "";

// ------------------------------------------------------------
// PROCESAR EL FORMULARIO
// Este bloque solo se ejecuta cuando se pulsa "Registrarse".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge los datos escritos en el formulario.
    // trim() quita los espacios sobrantes al principio y al final.
    $nombre = trim($_POST["nombre"]);
    $correo = trim($_POST["correo"]);
    $clave = $_POST["clave"];

    // Comprueba si ya existe un usuario con ese correo.
    // Se usa una consulta preparada (el "?") para evitar ataques de inyección SQL.
    $consulta = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE correo = ?");
    mysqli_stmt_bind_param($consulta, "s", $correo);
    mysqli_stmt_execute($consulta);
    mysqli_stmt_store_result($consulta);

    if (mysqli_stmt_num_rows($consulta) > 0) {
        // El correo ya está en uso: se avisa y no se crea la cuenta.
        $mensaje = "Ese correo ya está registrado.";
    } else {
        // Cifra la contraseña antes de guardarla.
        $clave_segura = password_hash($clave, PASSWORD_DEFAULT);

        // Guarda el nuevo usuario en la base de datos.
        $insertar = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, correo, clave) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($insertar, "sss", $nombre, $correo, $clave_segura);
        mysqli_stmt_execute($insertar);

        // Lleva al login con un aviso de que la cuenta se creó.
        header("Location: login.php?registrado=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=5">
</head>
<body>
    <!-- Encabezado con el título y el menú de navegación -->
    <header>
        <h1>Biblioteca Digital</h1>
        <nav>
            <a href="../index.html">Inicio</a>
            <a href="login.php">Iniciar sesión</a>
        </nav>
    </header>

    <main>
        <!-- Formulario de registro: envía los datos a esta misma página -->
        <form class="formulario" method="POST">
            <h2>Crear cuenta</h2>

            <!-- Muestra el mensaje de error solo si hay uno -->
            <?php if ($mensaje) { ?>
                <p class="error"><?php echo $mensaje; ?></p>
            <?php } ?>

            <!-- Campos que tiene que rellenar el usuario -->
            <label>Nombre</label>
            <input type="text" name="nombre" required>
            <label>Correo (Gmail)</label>
            <input type="email" name="correo" required>
            <label>Contraseña</label>
            <input type="password" name="clave" required>

            <button type="submit">Registrarse</button>
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
        </form>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
