<?php
// ============================================================
// CONTROLADOR: GESTIÓN DE USUARIOS (ABM)
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
require_once __DIR__ . "/verificar_bibliotecario.php";
require_once __DIR__ . "/../modelos/usuario.php";

// Los únicos roles permitidos.
$roles = ["cliente", "bibliotecario"];

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
    $accion = texto_de($_POST["accion"] ?? null);

    // --- Dar de baja o reactivar ---
    // Es una baja lógica: nunca se usa DELETE, solo se cambia "activo".
    if ($accion == "baja" || $accion == "reactivar") {
        $id = (int) $_POST["id"];
        $aviso = "";
        $tipo = "error";

        // Busca al usuario para conocer su rol y su estado actual.
        $encontrado = usuario_por_id($conexion, $id);

        if (!$encontrado) {
            $aviso = "El usuario no existe.";
        } elseif ($accion == "baja" && $id == $_SESSION["id"]) {
            // Regla 1: nadie puede darse de baja a sí mismo.
            $aviso = "No puedes darte de baja a ti mismo.";
        } elseif ($accion == "baja" && $encontrado["rol"] == "bibliotecario" && $encontrado["activo"] == 1 && usuario_contar_bibliotecarios_activos($conexion) <= 1) {
            // Regla 2: no se puede dar de baja al último bibliotecario activo.
            $aviso = "Debe existir al menos un bibliotecario activo.";
        } else {
            $activo = ($accion == "reactivar") ? 1 : 0;

            usuario_cambiar_estado($conexion, $id, $activo);

            $aviso = ($activo == 1) ? "Usuario reactivado." : "Usuario dado de baja.";
            $tipo = "exito";
        }

        aviso_guardar("usuarios", $aviso, $tipo);
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
            $actual = usuario_por_id($conexion, $usuario["id"]);

            if (!$actual) {
                $errores[] = "El usuario que quieres editar no existe.";
            }
        }

        $errores = array_merge($errores, validar_nombre_correo($conexion, $usuario["nombre"], $usuario["correo"], $usuario["id"]));

        // La contraseña es obligatoria al crear. Al editar puede quedar
        // vacía, y entonces se conserva la que ya tenía.
        if ($usuario["id"] == 0 && $clave == "") {
            $errores[] = "La contraseña es obligatoria.";
        }

        if (!in_array($usuario["rol"], $roles)) {
            $errores[] = "Elige un rol válido.";
        }

        // Regla 2: no se puede pasar a cliente al último bibliotecario activo.
        if ($actual && $actual["rol"] == "bibliotecario" && $actual["activo"] == 1 && $usuario["rol"] == "cliente" && usuario_contar_bibliotecarios_activos($conexion) <= 1) {
            $errores[] = "Debe existir al menos un bibliotecario activo.";
        }

        // ---------- Guardado ----------
        if (count($errores) == 0) {
            $guardado = false;

            try {
                if ($usuario["id"] == 0) {
                    // Alta: la contraseña se guarda cifrada y el usuario queda activo.
                    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                    $guardado = usuario_crear($conexion, $usuario["nombre"], $usuario["correo"], $clave_segura, $usuario["rol"]);
                    $aviso = "Usuario agregado: " . $usuario["nombre"] . ".";
                } elseif ($clave == "") {
                    // Edición sin contraseña nueva: la contraseña actual no se toca.
                    $guardado = usuario_actualizar($conexion, $usuario["id"], $usuario["nombre"], $usuario["correo"], $usuario["rol"]);
                    $aviso = "Usuario actualizado: " . $usuario["nombre"] . ".";
                } else {
                    // Edición con contraseña nueva: se guarda cifrada.
                    $clave_segura = password_hash($clave, PASSWORD_DEFAULT);
                    $guardado = usuario_actualizar($conexion, $usuario["id"], $usuario["nombre"], $usuario["correo"], $usuario["rol"], $clave_segura);
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
                }

                // Guarda el aviso en la sesión y recarga la página, para que
                // al refrescar el navegador no se repita el guardado.
                aviso_guardar("usuarios", $aviso);
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

    $encontrado = usuario_por_id($conexion, $id);

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
list($mensaje, $tipo) = aviso_leer("usuarios");

// ------------------------------------------------------------
// LISTADO
// Todos los usuarios, activos e inactivos. No se pide la contraseña.
// ------------------------------------------------------------
$lista = usuario_listar($conexion);

// ------------------------------------------------------------
// VISTA
// El HTML está en vistas/usuarios_admin.php. Estas variables se usan allí.
// ------------------------------------------------------------
$titulo_pagina = "Gestión de usuarios";
require __DIR__ . "/../vistas/usuarios_admin.php";
