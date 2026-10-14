<?php
// ============================================================
// PÁGINA DE REGISTRO
// Muestra el formulario para crear una cuenta y, al enviarlo,
// guarda el nuevo usuario en la tabla "usuarios".
// ============================================================

// Recupera la sesión (si existe), solo para que el menú sepa si ya hay
// un usuario dentro. Esta página no la modifica.
session_start();

// Conecta con la base de datos.
include "conexion.php";

// Aquí se guarda el mensaje de error que se muestra en el formulario.
$mensaje = "";

// ------------------------------------------------------------
// PROCESAR EL FORMULARIO
// Este bloque solo se ejecuta cuando se pulsa "Registrarse".
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge los datos escritos en el formulario. Si algún campo no
    // llega (o no es un texto), se toma como vacío.
    // trim() quita los espacios sobrantes al principio y al final.
    $nombre = (isset($_POST["nombre"]) && is_string($_POST["nombre"])) ? trim($_POST["nombre"]) : "";
    $correo = (isset($_POST["correo"]) && is_string($_POST["correo"])) ? trim($_POST["correo"]) : "";
    $clave = (isset($_POST["clave"]) && is_string($_POST["clave"])) ? $_POST["clave"] : "";

    // VALIDACIÓN EN EL SERVIDOR: ningún dato puede quedar vacío.
    // El "required" del formulario es solo una ayuda del navegador
    // (se puede saltear enviando los datos de otra forma), así que
    // la comprobación que vale es esta. Si falta algo, se muestra
    // el error y no se consulta ni se guarda nada en la base.
    if ($nombre === "" || $correo === "" || trim($clave) === "") {
        $mensaje = "Completa el nombre, el correo y la contraseña.";
    }

    // Comprueba si ya existe un usuario con ese correo.
    // Se usa una consulta preparada (el "?") para evitar ataques de inyección SQL.
    if ($mensaje == "") {
        $consulta = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE correo = ?");
        mysqli_stmt_bind_param($consulta, "s", $correo);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_store_result($consulta);

        if (mysqli_stmt_num_rows($consulta) > 0) {
            // El correo ya está en uso: se avisa y no se crea la cuenta.
            $mensaje = "Ese correo ya está registrado.";
        }
    }

    if ($mensaje == "") {
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
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

    <!-- Página en dos mitades, igual que la de inicio de sesión -->
    <main class="acceso">
<?php include "acceso_visual.php"; ?>

        <section class="acceso-panel">
            <!-- Formulario de registro: envía los datos a esta misma página -->
            <form class="formulario" name="form_registro" method="POST" action="registro.php">
                <p class="acceso-marca">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6.5C10.5 5 8 4.5 4 4.5v13c4 0 6.5.5 8 2 1.5-1.5 4-2 8-2v-13c-4 0-6.5.5-8 2z"/><path d="M12 6.5v13"/></svg>
                    Biblioteca Digital
                </p>
                <h2>Crear cuenta</h2>

                <!-- Muestra el mensaje de error solo si hay uno -->
                <?php if ($mensaje) { ?>
                    <p class="error" role="alert"><?php echo $mensaje; ?></p>
                <?php } ?>

                <!-- Campos que tiene que rellenar el usuario -->
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" autocomplete="name" required>
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" autocomplete="email" required>
                <label for="clave">Contraseña</label>
                <!-- "campo-clave": main.js le agrega el botón Mostrar / Ocultar -->
                <div class="campo-clave">
                    <input type="password" id="clave" name="clave" autocomplete="new-password" required>
                </div>
                <!-- Indicador de fortaleza: lo muestra main.js. Es solo una ayuda
                     visual; no impide registrarse. -->
                <div class="fortaleza" data-fortaleza="clave" data-nivel="0" hidden>
                    <div class="fortaleza-barra"><span></span></div>
                    <span class="fortaleza-texto" aria-live="polite"></span>
                </div>

                <button class="btn btn-primary btn-lg" type="submit" data-enviando="Creando cuenta...">Registrarse</button>
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
            </form>
        </section>
    </main>

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
