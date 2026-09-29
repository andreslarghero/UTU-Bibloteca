<?php
// ============================================================
// PÁGINA DE INICIO DE SESIÓN
// Muestra el formulario de login y, si el correo y la contraseña
// son correctos, abre la sesión y lleva al catálogo.
// ============================================================

// Inicia (o recupera) la sesión, que recuerda al usuario entre páginas.
session_start();

// Conecta con la base de datos.
include "conexion.php";

// Aquí se guarda el mensaje de error que se muestra en el formulario.
$mensaje = "";

// ------------------------------------------------------------
// PROCESAR EL FORMULARIO
// Este bloque solo se ejecuta cuando se pulsa "Entrar".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge el correo y la contraseña escritos.
    $correo = trim($_POST["correo"]);
    $clave = $_POST["clave"];

    // Busca al usuario por su correo (con consulta preparada por seguridad).
    $consulta = mysqli_prepare($conexion, "SELECT id, nombre, clave, admin FROM usuarios WHERE correo = ?");
    mysqli_stmt_bind_param($consulta, "s", $correo);
    mysqli_stmt_execute($consulta);
    $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

    // Comprueba que el usuario exista y que la contraseña coincida con la cifrada.
    if ($usuario && password_verify($clave, $usuario["clave"])) {
        // Guarda los datos del usuario en la sesión y lo lleva al catálogo.
        $_SESSION["id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["admin"] = $usuario["admin"];   // 1 si es administrador
        header("Location: libros.php");
        exit;
    } else {
        // Datos incorrectos: se muestra un aviso.
        $mensaje = "Correo o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=5">
</head>
<body>
    <!-- Encabezado con el título y el menú de navegación -->
    <header>
        <h1>Biblioteca Digital</h1>
        <nav>
            <a href="../index.html">Inicio</a>
            <a href="registro.php">Registrarse</a>
        </nav>
    </header>

    <main>
        <!-- Formulario de login: envía los datos a esta misma página -->
        <form class="formulario" method="POST">
            <h2>Iniciar sesión</h2>

            <!-- Aviso verde que aparece al venir desde el registro -->
            <?php if (isset($_GET["registrado"])) { ?>
                <p class="exito">Cuenta creada. Ya puedes iniciar sesión.</p>
            <?php } ?>

            <!-- Aviso rojo si el correo o la contraseña no son correctos -->
            <?php if ($mensaje) { ?>
                <p class="error"><?php echo $mensaje; ?></p>
            <?php } ?>

            <!-- Campos que tiene que rellenar el usuario -->
            <label>Correo (Gmail)</label>
            <input type="email" name="correo" required>
            <label>Contraseña</label>
            <input type="password" name="clave" required>

            <button type="submit">Entrar</button>
            <p>¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
        </form>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
