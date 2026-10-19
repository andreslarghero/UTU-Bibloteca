<?php
// ============================================================
// VISTA: login
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/login.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <!-- Página en dos mitades: a la izquierda el bloque visual y a la
         derecha el formulario. En el móvil solo se ve el formulario. -->
    <main class="acceso">
<?php include __DIR__ . "/parciales/acceso_visual.php"; ?>

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

<?php include __DIR__ . "/parciales/pie.php"; ?>
