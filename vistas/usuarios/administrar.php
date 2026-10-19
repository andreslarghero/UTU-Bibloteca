<?php
// ============================================================
// VISTA: gestión de usuarios (UsuarioAdminController::index, ::editar y ::guardar)
// Solo dibuja HTML con los datos que le entrega el controlador:
// no consulta la base de datos ni decide permisos.
// ============================================================
?>
<?php include __DIR__ . "/../parciales/cabecera.php"; ?>

    <main>
        <h2>Gestión de usuarios</h2>
        <p class="descripcion-pagina">Alta, edición y baja de las cuentas de clientes y bibliotecarios.</p>

        <!-- Aviso de la última acción: verde si salió bien, rojo si se rechazó -->
        <?php if ($mensaje) { ?>
            <p class="<?php echo ($tipo == "exito") ? "exito" : "error"; ?>"><?php echo htmlspecialchars($mensaje); ?></p>
        <?php } ?>

        <!-- Errores de validación del formulario -->
        <?php foreach ($errores as $error) { ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <!-- Formulario de alta y edición (usa el mismo estilo que el de libros).
             El campo oculto "id" indica si es un usuario nuevo (0) o uno que ya existe. -->
        <form class="form-libro" name="form_usuario" method="POST" action="<?php echo Ruta::h('usuarioadmin', 'guardar'); ?>" id="formulario">
            <h3><?php echo ($usuario->getId() > 0) ? "Editar usuario" : "Agregar usuario"; ?></h3>
            <input type="hidden" name="id" value="<?php echo $usuario->getId(); ?>">

            <div>
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($usuario->getNombre()); ?>" required>
            </div>
            <div>
                <label for="correo">Correo</label>
                <input type="email" id="correo" name="correo" maxlength="150" value="<?php echo htmlspecialchars($usuario->getCorreo()); ?>" required>
            </div>
            <div>
                <!-- La contraseña nunca se muestra. Al editar, si se deja vacía, no cambia. -->
                <label for="clave"><?php echo ($usuario->getId() > 0) ? "Contraseña nueva (vacía = no cambiar)" : "Contraseña"; ?></label>
                <input type="password" id="clave" name="clave" autocomplete="new-password" <?php if ($usuario->getId() == 0) echo "required"; ?>>
            </div>
            <div>
                <label for="rol">Rol</label>
                <select id="rol" name="rol" required>
                    <?php foreach ($roles as $rol) { ?>
                        <option value="<?php echo $rol; ?>" <?php if ($usuario->rol() == $rol) echo "selected"; ?>><?php echo ucfirst($rol); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="campo-ancho botones-libro">
                <button class="btn btn-primary" type="submit"><?php echo ($usuario->getId() > 0) ? "Guardar cambios" : "Agregar usuario"; ?></button>
                <!-- Al editar, permite volver al formulario vacío sin guardar -->
                <?php if ($usuario->getId() > 0) { ?>
                    <a class="volver" href="<?php echo Ruta::h('usuarioadmin'); ?>">Cancelar</a>
                <?php } ?>
            </div>
        </form>

        <h3 class="titulo-ticket">Usuarios registrados</h3>

        <!-- Tabla con todos los usuarios, también los dados de baja -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla">
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th></th>
            </tr>
            <!-- Una fila por cada usuario -->
            <?php foreach ($lista as $fila) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién modificada -->
            <tr data-fila="<?php echo $fila->getId(); ?>" <?php if (!$fila->estaActivo()) echo 'class="fila-inactiva"'; ?>>
                <td><?php echo $fila->getId(); ?></td>
                <td><?php echo htmlspecialchars($fila->getNombre()); ?></td>
                <td><?php echo htmlspecialchars($fila->getCorreo()); ?></td>
                <td>
                    <!-- Etiqueta con el rol del usuario -->
                    <?php if ($fila->esBibliotecario()) { ?>
                        <span class="badge badge-bibliotecario">Bibliotecario</span>
                    <?php } else { ?>
                        <span class="badge badge-cliente">Cliente</span>
                    <?php } ?>
                </td>
                <td>
                    <!-- Etiqueta verde si está activo, roja si está dado de baja -->
                    <?php if ($fila->estaActivo()) { ?>
                        <span class="disponible">Activo</span>
                    <?php } else { ?>
                        <span class="agotado">Inactivo</span>
                    <?php } ?>
                </td>
                <td>
                    <div class="acciones-linea">
                        <a class="btn btn-primary btn-sm" href="<?php echo Ruta::h('usuarioadmin', 'editar', ['id' => $fila->getId()]); ?>#formulario">Editar</a>
                        <?php if ($fila->getId() == $usuarioMenu->getId()) { ?>
                            <!-- La cuenta propia no se puede dar de baja -->
                            <span class="fecha">Tu cuenta</span>
                        <?php } else { ?>
                        <!-- Baja lógica o reactivación, según el estado del usuario -->
                        <!-- data-confirmar (solo al dar de baja): main.js pregunta antes de enviar -->
                        <form name="form_usuario_estado_<?php echo $fila->getId(); ?>" method="POST" action="<?php echo Ruta::h('usuarioadmin', $fila->estaActivo() ? 'baja' : 'reactivar'); ?>" <?php if ($fila->estaActivo()) echo 'data-confirmar="¿Dar de baja este usuario? No podrá iniciar sesión hasta que lo reactives."'; ?>>
                            <input type="hidden" name="id" value="<?php echo $fila->getId(); ?>">
                            <?php if ($fila->estaActivo()) { ?>
                                <button class="btn btn-danger btn-sm" type="submit">Dar de baja</button>
                            <?php } else { ?>
                                <button class="btn btn-success btn-sm" type="submit">Reactivar</button>
                            <?php } ?>
                        </form>
                        <?php } ?>
                    </div>
                </td>
            </tr>
            <?php } ?>
        </table>
        </div>
    </main>

<?php include __DIR__ . "/../parciales/pie.php"; ?>
