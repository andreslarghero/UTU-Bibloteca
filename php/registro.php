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
    $nombre = texto_de($_POST["nombre"] ?? null);
    $correo = texto_de($_POST["correo"] ?? null);
    $clave = texto_de($_POST["clave"] ?? null, false);

    // VALIDACIÓN EN EL SERVIDOR (las mismas reglas que "Mi perfil" y
    // "Gestionar usuarios", en funciones.php). El "required" del
    // formulario es solo una ayuda del navegador: la comprobación que
    // vale es esta. Si algo falla, se muestra el primer error y no se
    // guarda nada en la base.
    if ($nombre === "" || $correo === "" || trim($clave) === "") {
        $mensaje = "Completa el nombre, el correo y la contraseña.";
    } else {
        $errores = validar_nombre_correo($conexion, $nombre, $correo);
        if (count($errores) > 0) {
            $mensaje = $errores[0];
        }
    }

    if ($mensaje == "") {
        // Cifra la contraseña antes de guardarla.
        $clave_segura = password_hash($clave, PASSWORD_DEFAULT);

        // Guarda el nuevo usuario. Si la base falla, no se muestran
        // detalles internos.
        try {
            $insertar = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, correo, clave) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($insertar, "sss", $nombre, $correo, $clave_segura);
            mysqli_stmt_execute($insertar);

            // Lleva al login con un aviso de que la cuenta se creó.
            header("Location: login.php?registrado=1");
            exit;
        } catch (Throwable $e) {
            $mensaje = "No se pudo crear la cuenta.";
        }
    }
}
?>
<?php
$titulo_pagina = "Registro";
include "cabecera.php";
?>

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

<?php include "pie.php"; ?>
