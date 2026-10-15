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
    $usuario = buscar_fila($conexion,
        "SELECT id, nombre, clave, rol, activo FROM usuarios WHERE correo = ?",
        "s", $correo);

    // Comprueba que el usuario exista, que esté activo y que la contraseña
    // coincida con la cifrada. A un usuario dado de baja (activo = 0) se le
    // muestra el mismo aviso que a una contraseña incorrecta, sin dar pistas.
    if ($usuario && $usuario["activo"] == 1 && password_verify($clave, $usuario["clave"])) {
        // Guarda los datos del usuario en la sesión y lo lleva al catálogo.
        $_SESSION["id"] = $usuario["id"];
        $_SESSION["nombre"] = $usuario["nombre"];
        $_SESSION["rol"] = $usuario["rol"];       // "cliente" o "bibliotecario"

        header("Location: libros.php");
        exit;
    } else {
        // Datos incorrectos: se muestra un aviso.
        $mensaje = "Correo o contraseña incorrectos.";
    }
}
?>
<?php
$titulo_pagina = "Iniciar sesión";
include "cabecera.php";
?>

    <!-- Página en dos mitades: a la izquierda el bloque visual y a la
         derecha el formulario. En el móvil solo se ve el formulario. -->
    <main class="acceso">
<?php include "acceso_visual.php"; ?>

        <section class="acceso-panel">
            <!-- Formulario de login: envía los datos a esta misma página -->
            <form class="formulario" name="form_login" method="POST" action="login.php">
                <p class="acceso-marca">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 6.5C10.5 5 8 4.5 4 4.5v13c4 0 6.5.5 8 2 1.5-1.5 4-2 8-2v-13c-4 0-6.5.5-8 2z"/><path d="M12 6.5v13"/></svg>
                    Biblioteca Digital
                </p>
                <h2>Iniciar sesión</h2>

                <!-- Aviso verde que aparece al venir desde el registro -->
                <?php if (isset($_GET["registrado"])) { ?>
                    <p class="exito">Cuenta creada. Ya puedes iniciar sesión.</p>
                <?php } ?>

                <!-- Aviso rojo si el correo o la contraseña no son correctos -->
                <?php if ($mensaje) { ?>
                    <p class="error" role="alert"><?php echo $mensaje; ?></p>
                <?php } ?>

                <!-- Campos que tiene que rellenar el usuario -->
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" autocomplete="email" required>
                <label for="clave">Contraseña</label>
                <!-- "campo-clave": main.js le agrega el botón Mostrar / Ocultar -->
                <div class="campo-clave">
                    <input type="password" id="clave" name="clave" autocomplete="current-password" required>
                </div>

                <!-- data-enviando: texto que muestra el botón mientras se envía -->
                <button class="btn btn-primary btn-lg" type="submit" data-enviando="Ingresando...">Entrar</button>
                <p>¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
            </form>
        </section>
    </main>

<?php include "pie.php"; ?>
