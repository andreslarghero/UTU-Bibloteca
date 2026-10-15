<?php
// ============================================================
// MI PERFIL
// Página para cualquier usuario con sesión iniciada: muestra sus
// datos y le permite cambiar su nombre, su correo y su contraseña.
// Para cambiar el correo o la contraseña tiene que confirmar su
// contraseña actual; para cambiar solo el nombre, no.
// El rol y el estado solo se muestran: aquí no se pueden cambiar.
// Cada usuario solo puede editar SU propia cuenta.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_usuario.php inicia la sesión, conecta con la base de
// datos y comprueba en la base, en cada petición, que el usuario
// sigue existiendo y sigue activo. Si no, cierra su sesión y lo
// manda al login antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_usuario.php";

// La cuenta que se muestra y se edita es SIEMPRE la del usuario de la
// sesión. El id nunca se toma del formulario ni de la dirección, así
// nadie puede editar la cuenta de otra persona.
$id = $_SESSION["id"];

// Errores de validación que se muestran encima del formulario.
$errores = [];

// ------------------------------------------------------------
// DATOS ACTUALES DEL USUARIO
// No se pide la contraseña cifrada: nunca sale en la página.
// ------------------------------------------------------------
$perfil = buscar_fila($conexion, "SELECT nombre, correo, rol, activo FROM usuarios WHERE id = ?", "i", $id);

// Valores que se muestran en el formulario.
$nombre = $perfil["nombre"];
$correo = $perfil["correo"];

// ------------------------------------------------------------
// GUARDAR LOS CAMBIOS
// Se ejecuta al enviar el formulario. Solo se usan el nombre, el
// correo y la contraseña: cualquier otro dato que llegue en el
// formulario (id, rol, activo...) se ignora.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoge lo escrito. trim() quita los espacios sobrantes.
    $nombre = texto_de($_POST["nombre"] ?? null);
    $correo = texto_de($_POST["correo"] ?? null);
    $clave = texto_de($_POST["clave"] ?? null, false);
    $clave_actual = texto_de($_POST["clave_actual"] ?? null, false);

    // Qué quiere cambiar el usuario. Para cambiar solo el nombre no hace
    // falta la contraseña actual; para el correo o la contraseña, sí.
    $cambia_correo = ($correo != $perfil["correo"]);
    $cambia_clave = ($clave != "");

    // ---------- Validaciones ----------
    $errores = validar_nombre_correo($conexion, $nombre, $correo, $id);

    // ---------- Contraseña actual ----------
    // Si se cambia el correo o la contraseña (o las dos cosas), hay que
    // confirmar la contraseña actual. Se comprueba una sola vez.
    if ($cambia_correo || $cambia_clave) {
        // Se trae la contraseña cifrada de la cuenta de la sesión. Solo se usa
        // aquí para compararla; nunca se envía a la página.
        $fila = buscar_fila($conexion, "SELECT clave FROM usuarios WHERE id = ?", "i", $id);

        // password_verify compara lo escrito con la contraseña cifrada.
        // El mismo aviso sirve si se dejó vacía o si está mal.
        if (!$fila || !password_verify($clave_actual, $fila["clave"])) {
            $errores[] = "Contraseña actual incorrecta.";
        }
    }

    // ---------- Guardado ----------
    if (count($errores) == 0) {
        $guardado = false;

        try {
            if ($clave == "") {
                // Sin contraseña nueva: la contraseña actual no se toca.
                // Nunca se modifican el rol ni el estado.
                $consulta = mysqli_prepare($conexion, "UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?");
                mysqli_stmt_bind_param($consulta, "ssi", $nombre, $correo, $id);
                $aviso = "Perfil actualizado.";
            } else {
                // Con contraseña nueva: se guarda cifrada con password_hash.
                $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                $consulta = mysqli_prepare($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, clave = ? WHERE id = ?");
                mysqli_stmt_bind_param($consulta, "sssi", $nombre, $correo, $clave_segura, $id);
                $aviso = "Perfil actualizado (con contraseña nueva).";
            }
            $guardado = mysqli_stmt_execute($consulta);
        } catch (Throwable $e) {
            // Error inesperado de la base: no se muestran detalles internos.
            $guardado = false;
        }

        if ($guardado) {
            // Actualiza el nombre de la sesión para que el saludo cambie enseguida.
            $_SESSION["nombre"] = $nombre;

            // Guarda el aviso en la sesión y recarga la página, para que
            // al refrescar el navegador no se repita el guardado.
            aviso_guardar("perfil", $aviso);
            header("Location: perfil.php");
            exit;
        }

        $errores[] = "No se pudo guardar el perfil.";
    }
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
list($mensaje) = aviso_leer("perfil");
?>
<?php
$titulo_pagina = "Mi perfil";
include "cabecera.php";
?>

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

<?php include "pie.php"; ?>
