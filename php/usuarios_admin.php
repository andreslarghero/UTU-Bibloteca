<?php
// ============================================================
// GESTIÓN DE USUARIOS (ABM)
// Página para el bibliotecario: permite dar de alta usuarios,
// editarlos, darlos de baja y reactivarlos.
// La baja es LÓGICA: el usuario no se borra, solo se marca con
// activo = 0. Así conserva sus ventas y sus reseñas, pero ya no
// puede iniciar sesión.
// Solo pueden entrar los bibliotecarios.
// ============================================================

// ------------------------------------------------------------
// PROTECCIÓN DE LA PÁGINA
// verificar_bibliotecario.php inicia la sesión, conecta con la
// base de datos y comprueba en la base, en cada petición, que el
// usuario sigue activo y sigue siendo bibliotecario. Si no, lo
// saca de aquí antes de ejecutar nada de esta página.
// ------------------------------------------------------------
include "verificar_bibliotecario.php";

// Los únicos roles permitidos.
$roles = ["cliente", "bibliotecario"];

// Devuelve cuántos bibliotecarios activos hay en este momento.
// Se usa para no dejar nunca el sistema sin ninguno.
function bibliotecarios_activos($conexion) {
    $resultado = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'bibliotecario' AND activo = 1");
    $fila = mysqli_fetch_assoc($resultado);
    return $fila["total"];
}

// Errores de validación que se muestran encima del formulario.
$errores = [];

// Datos que se muestran en el formulario. Con id = 0 es un usuario nuevo.
// La contraseña nunca se carga en el formulario.
$usuario = ["id" => 0, "nombre" => "", "correo" => "", "rol" => "cliente"];

// ------------------------------------------------------------
// ACCIONES
// Se ejecuta al enviar cualquiera de los formularios de la página.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accion = isset($_POST["accion"]) ? $_POST["accion"] : "";

    // --- Dar de baja o reactivar ---
    // Es una baja lógica: nunca se usa DELETE, solo se cambia "activo".
    if ($accion == "baja" || $accion == "reactivar") {
        $id = (int) $_POST["id"];
        $aviso = "";
        $tipo = "error";

        // Busca al usuario para conocer su rol y su estado actual.
        $consulta = mysqli_prepare($conexion, "SELECT id, rol, activo FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($consulta, "i", $id);
        mysqli_stmt_execute($consulta);
        $encontrado = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

        if (!$encontrado) {
            $aviso = "El usuario no existe.";
        } elseif ($accion == "baja" && $id == $_SESSION["id"]) {
            // Regla 1: nadie puede darse de baja a sí mismo.
            $aviso = "No puedes darte de baja a ti mismo.";
        } elseif ($accion == "baja" && $encontrado["rol"] == "bibliotecario" && $encontrado["activo"] == 1 && bibliotecarios_activos($conexion) <= 1) {
            // Regla 2: no se puede dar de baja al último bibliotecario activo.
            $aviso = "Debe existir al menos un bibliotecario activo.";
        } else {
            $activo = ($accion == "reactivar") ? 1 : 0;

            $consulta = mysqli_prepare($conexion, "UPDATE usuarios SET activo = ? WHERE id = ?");
            mysqli_stmt_bind_param($consulta, "ii", $activo, $id);
            mysqli_stmt_execute($consulta);

            $aviso = ($activo == 1) ? "Usuario reactivado." : "Usuario dado de baja.";
            $tipo = "exito";
        }

        $_SESSION["usuarios_mensaje"] = $aviso;
        $_SESSION["usuarios_tipo"] = $tipo;
        header("Location: usuarios_admin.php");
        exit;
    }

    // --- Guardar un usuario (alta si id = 0, edición si id > 0) ---
    if ($accion == "guardar") {
        // Recoge lo escrito en el formulario. trim() quita los espacios sobrantes.
        $usuario["id"] = (int) $_POST["id"];
        $usuario["nombre"] = trim($_POST["nombre"]);
        $usuario["correo"] = trim($_POST["correo"]);
        $usuario["rol"] = isset($_POST["rol"]) ? $_POST["rol"] : "";
        $clave = $_POST["clave"];

        // Datos actuales del usuario, si es una edición.
        $actual = null;

        // ---------- Validaciones ----------
        if ($usuario["id"] > 0) {
            $consulta = mysqli_prepare($conexion, "SELECT id, rol, activo FROM usuarios WHERE id = ?");
            mysqli_stmt_bind_param($consulta, "i", $usuario["id"]);
            mysqli_stmt_execute($consulta);
            $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

            if (!$actual) {
                $errores[] = "El usuario que quieres editar no existe.";
            }
        }

        if ($usuario["nombre"] == "") {
            $errores[] = "El nombre es obligatorio.";
        } elseif (strlen($usuario["nombre"]) > 100) {
            $errores[] = "El nombre es demasiado largo.";
        }

        if ($usuario["correo"] == "") {
            $errores[] = "El correo es obligatorio.";
        } elseif (strlen($usuario["correo"]) > 150 || !filter_var($usuario["correo"], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El correo no es válido.";
        } else {
            // El correo no puede repetirse en otro usuario.
            $consulta = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE correo = ? AND id <> ?");
            mysqli_stmt_bind_param($consulta, "si", $usuario["correo"], $usuario["id"]);
            mysqli_stmt_execute($consulta);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($consulta))) {
                $errores[] = "Ya existe otro usuario con ese correo.";
            }
        }

        // La contraseña es obligatoria al crear. Al editar puede quedar
        // vacía, y entonces se conserva la que ya tenía.
        if ($usuario["id"] == 0 && $clave == "") {
            $errores[] = "La contraseña es obligatoria.";
        }

        if (!in_array($usuario["rol"], $roles)) {
            $errores[] = "Elige un rol válido.";
        }

        // Regla 2: no se puede pasar a cliente al último bibliotecario activo.
        if ($actual && $actual["rol"] == "bibliotecario" && $actual["activo"] == 1 && $usuario["rol"] == "cliente" && bibliotecarios_activos($conexion) <= 1) {
            $errores[] = "Debe existir al menos un bibliotecario activo.";
        }

        // ---------- Guardado ----------
        if (count($errores) == 0) {
            $guardado = false;

            try {
                if ($usuario["id"] == 0) {
                    // Alta: la contraseña se guarda cifrada y el usuario queda activo.
                    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                    $consulta = mysqli_prepare($conexion, "INSERT INTO usuarios (nombre, correo, clave, rol, activo) VALUES (?, ?, ?, ?, 1)");
                    mysqli_stmt_bind_param($consulta, "ssss", $usuario["nombre"], $usuario["correo"], $clave_segura, $usuario["rol"]);
                    $guardado = mysqli_stmt_execute($consulta);
                    $aviso = "Usuario agregado: " . $usuario["nombre"] . ".";
                } elseif ($clave == "") {
                    // Edición sin contraseña nueva: la contraseña actual no se toca.
                    $consulta = mysqli_prepare($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, rol = ? WHERE id = ?");
                    mysqli_stmt_bind_param($consulta, "sssi", $usuario["nombre"], $usuario["correo"], $usuario["rol"], $usuario["id"]);
                    $guardado = mysqli_stmt_execute($consulta);
                    $aviso = "Usuario actualizado: " . $usuario["nombre"] . ".";
                } else {
                    // Edición con contraseña nueva: se guarda cifrada.
                    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                    $consulta = mysqli_prepare($conexion, "UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, clave = ? WHERE id = ?");
                    mysqli_stmt_bind_param($consulta, "ssssi", $usuario["nombre"], $usuario["correo"], $usuario["rol"], $clave_segura, $usuario["id"]);
                    $guardado = mysqli_stmt_execute($consulta);
                    $aviso = "Usuario actualizado: " . $usuario["nombre"] . " (con contraseña nueva).";
                }
            } catch (Throwable $e) {
                // Error inesperado de la base: no se muestran detalles internos.
                $guardado = false;
            }

            if ($guardado) {
                // Si el bibliotecario editó su propia cuenta, se actualiza su sesión.
                if ($usuario["id"] == $_SESSION["id"]) {
                    $_SESSION["nombre"] = $usuario["nombre"];
                    $_SESSION["rol"] = $usuario["rol"];
                    $_SESSION["admin"] = ($usuario["rol"] == "bibliotecario") ? 1 : 0;
                }

                // Guarda el aviso en la sesión y recarga la página, para que
                // al refrescar el navegador no se repita el guardado.
                $_SESSION["usuarios_mensaje"] = $aviso;
                $_SESSION["usuarios_tipo"] = "exito";
                header("Location: usuarios_admin.php");
                exit;
            }

            $errores[] = "No se pudo guardar el usuario.";
        }
    }
}

// ------------------------------------------------------------
// CARGAR UN USUARIO PARA EDITARLO
// Si en la dirección viene ?editar=NÚMERO (y no se acaba de
// enviar el formulario), se rellenan los campos con ese usuario.
// La consulta no trae la contraseña cifrada: nunca sale en la página.
// ------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] != "POST" && isset($_GET["editar"])) {
    $id = (int) $_GET["editar"];

    $consulta = mysqli_prepare($conexion, "SELECT id, nombre, correo, rol FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($consulta, "i", $id);
    mysqli_stmt_execute($consulta);
    $encontrado = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

    if ($encontrado) {
        $usuario = $encontrado;
    } else {
        $errores[] = "El usuario que quieres editar no existe.";
    }
}

// ------------------------------------------------------------
// AVISO DE LA ÚLTIMA ACCIÓN
// Se lee de la sesión y se borra, para que se muestre una sola vez.
// ------------------------------------------------------------
$mensaje = isset($_SESSION["usuarios_mensaje"]) ? $_SESSION["usuarios_mensaje"] : "";
$tipo = isset($_SESSION["usuarios_tipo"]) ? $_SESSION["usuarios_tipo"] : "exito";
unset($_SESSION["usuarios_mensaje"], $_SESSION["usuarios_tipo"]);

// ------------------------------------------------------------
// LISTADO
// Todos los usuarios, activos e inactivos. No se pide la contraseña.
// ------------------------------------------------------------
$lista = mysqli_query($conexion, "SELECT id, nombre, correo, rol, activo FROM usuarios ORDER BY id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios - Biblioteca Digital</title>
    <link rel="stylesheet" href="../css/estilos.css?v=28">
</head>
<body>
<?php include "navbar.php"; ?>

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
        <form class="form-libro" name="form_usuario" method="POST" action="usuarios_admin.php" id="formulario">
            <h3><?php echo ($usuario["id"] > 0) ? "Editar usuario" : "Agregar usuario"; ?></h3>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?php echo (int) $usuario["id"]; ?>">

            <div>
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($usuario["nombre"]); ?>" required>
            </div>
            <div>
                <label for="correo">Correo</label>
                <input type="email" id="correo" name="correo" maxlength="150" value="<?php echo htmlspecialchars($usuario["correo"]); ?>" required>
            </div>
            <div>
                <!-- La contraseña nunca se muestra. Al editar, si se deja vacía, no cambia. -->
                <label for="clave"><?php echo ($usuario["id"] > 0) ? "Contraseña nueva (vacía = no cambiar)" : "Contraseña"; ?></label>
                <input type="password" id="clave" name="clave" autocomplete="new-password" <?php if ($usuario["id"] == 0) echo "required"; ?>>
            </div>
            <div>
                <label for="rol">Rol</label>
                <select id="rol" name="rol" required>
                    <?php foreach ($roles as $rol) { ?>
                        <option value="<?php echo $rol; ?>" <?php if ($usuario["rol"] == $rol) echo "selected"; ?>><?php echo ucfirst($rol); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="campo-ancho botones-libro">
                <button class="btn btn-primary" type="submit"><?php echo ($usuario["id"] > 0) ? "Guardar cambios" : "Agregar usuario"; ?></button>
                <!-- Al editar, permite volver al formulario vacío sin guardar -->
                <?php if ($usuario["id"] > 0) { ?>
                    <a class="volver" href="usuarios_admin.php">Cancelar</a>
                <?php } ?>
            </div>
        </form>

        <h3 class="titulo-ticket">Usuarios registrados</h3>

        <!-- Tabla con todos los usuarios, también los dados de baja -->
        <!-- "tabla-scroll": en un móvil la tabla se desplaza de lado dentro de este bloque, sin ensanchar la página -->
        <div class="tabla-scroll">
        <table class="tabla-stock">
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th></th>
            </tr>
            <!-- Una fila por cada usuario -->
            <?php while ($fila = mysqli_fetch_assoc($lista)) { ?>
            <!-- data-fila: main.js lo usa para resaltar la fila recién modificada -->
            <tr data-fila="<?php echo $fila["id"]; ?>" <?php if ($fila["activo"] == 0) echo 'class="fila-inactiva"'; ?>>
                <td><?php echo $fila["id"]; ?></td>
                <td><?php echo htmlspecialchars($fila["nombre"]); ?></td>
                <td><?php echo htmlspecialchars($fila["correo"]); ?></td>
                <td>
                    <!-- Etiqueta con el rol del usuario -->
                    <?php if ($fila["rol"] == "bibliotecario") { ?>
                        <span class="badge badge-bibliotecario">Bibliotecario</span>
                    <?php } else { ?>
                        <span class="badge badge-cliente">Cliente</span>
                    <?php } ?>
                </td>
                <td>
                    <!-- Etiqueta verde si está activo, roja si está dado de baja -->
                    <?php if ($fila["activo"] == 1) { ?>
                        <span class="disponible">Activo</span>
                    <?php } else { ?>
                        <span class="agotado">Inactivo</span>
                    <?php } ?>
                </td>
                <td>
                    <div class="acciones-linea">
                        <a class="btn btn-primary btn-sm" href="usuarios_admin.php?editar=<?php echo $fila["id"]; ?>#formulario">Editar</a>
                        <?php if ($fila["id"] == $_SESSION["id"]) { ?>
                            <!-- La cuenta propia no se puede dar de baja -->
                            <span class="fecha">Tu cuenta</span>
                        <?php } else { ?>
                        <!-- Baja lógica o reactivación, según el estado del usuario -->
                        <!-- data-confirmar (solo al dar de baja): main.js pregunta antes de enviar -->
                        <form name="form_usuario_estado_<?php echo $fila["id"]; ?>" method="POST" action="usuarios_admin.php" <?php if ($fila["activo"] == 1) echo 'data-confirmar="¿Dar de baja este usuario? No podrá iniciar sesión hasta que lo reactives."'; ?>>
                            <input type="hidden" name="id" value="<?php echo $fila["id"]; ?>">
                            <?php if ($fila["activo"] == 1) { ?>
                                <input type="hidden" name="accion" value="baja">
                                <button class="btn btn-danger btn-sm" type="submit">Dar de baja</button>
                            <?php } else { ?>
                                <input type="hidden" name="accion" value="reactivar">
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

    <!-- Pie de página -->
    <footer>
        <p>Biblioteca Digital</p>
    </footer>
</body>
</html>
<?php
// Cierra la conexión con la base de datos al terminar la página.
mysqli_close($conexion);
?>
