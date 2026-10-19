<?php
// ============================================================
// VISTA: perfil
// Solo dibuja HTML con los datos que prepara el controlador
// controladores/perfil.php. No consulta la base de datos.
// ============================================================
?>
<?php include __DIR__ . "/parciales/cabecera.php"; ?>

    <main>
        <h2>Mi perfil</h2>
        <p class="descripcion-pagina">Tus datos de acceso. Para cambiar el correo o la contraseña se pide la contraseña actual.</p>

        <!-- Aviso verde después de guardar -->
        <?php if ($mensaje) { ?>
            <p class="exito"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <!-- Errores de validación del formulario -->
        <?php foreach ($errores as $error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <!-- Formulario del perfil (usa el mismo estilo que los de gestión).
             No lleva ningún campo "id": la cuenta que se edita es la de la sesión. -->
        <form class="form-libro" name="form_perfil" method="POST" action="perfil.php">
            <h3>Mis datos</h3>

            <div>
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($nombre); ?>" required>
            </div>
            <div>
                <label for="correo">Correo</label>
                <input type="email" id="correo" name="correo" maxlength="150" value="<?php echo htmlspecialchars($correo); ?>" required>
            </div>
            <div>
                <!-- La contraseña actual nunca se muestra. Si se deja vacía, no cambia. -->
                <label for="clave">Contraseña nueva</label>
                <input type="password" id="clave" name="clave" autocomplete="new-password" placeholder="Vacía = no cambiar">
            </div>
            <div>
                <!-- Solo hace falta para cambiar el correo o la contraseña.
                     Siempre aparece vacía: nunca se rellena con ningún valor. -->
                <label for="clave_actual">Contraseña actual</label>
                <input type="password" id="clave_actual" name="clave_actual" autocomplete="current-password" placeholder="Para cambiar correo o contraseña">
            </div>
            <div class="campo-ancho">
                <!-- El rol y el estado solo se muestran: no son campos del formulario -->
                <p><strong>Rol:</strong>
                    <?php if ($perfil["rol"] == "bibliotecario") { ?>
                        <span class="badge badge-bibliotecario">Bibliotecario</span>
                    <?php } else { ?>
                        <span class="badge badge-cliente">Cliente</span>
                    <?php } ?>
                </p>
                <p><strong>Estado:</strong>
                    <?php if ($perfil["activo"] == 1) { ?>
                        <span class="disponible">Activo</span>
                    <?php } else { ?>
                        <span class="agotado">Inactivo</span>
                    <?php } ?>
                </p>
            </div>
            <div class="campo-ancho botones-libro">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="volver" href="libros.php">Volver al catálogo</a>
            </div>
        </form>
    </main>

<?php include __DIR__ . "/parciales/pie.php"; ?>
