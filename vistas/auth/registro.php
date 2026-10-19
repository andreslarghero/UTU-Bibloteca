<?php
// ============================================================
// VISTA: registro (AuthController::registro y ::registrar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <!-- Página en dos mitades, igual que la de inicio de sesión -->
    <main class="acceso">
<?php include __DIR__ . "/../parciales/acceso_visual.php"; ?>

        <section class="acceso-panel">
            <!-- Formulario de registro: envía los datos a esta misma página -->
            <form class="formulario" name="form_registro" method="POST" action="<?php echo Ruta::h('auth', 'registrar'); ?>">
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
                <p>¿Ya tienes cuenta? <a href="<?php echo Ruta::h('auth'); ?>">Inicia sesión</a></p>
            </form>
        </section>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
