<?php
// ============================================================
// SERVICIO STEAM: AVISO AL LECTOR FÍSICO (ARDUINO)
// La Caja incluye este archivo para avisarle al Arduino el
// resultado de cada escaneo. PHP no habla con el Arduino: solo
// escribe una línea en un archivo de texto. El programa
// puente_arduino.py lee ese archivo y le manda el comando al
// Arduino por USB.
//
//   Caja PHP  ->  comando_steam.txt  ->  puente_arduino.py  ->  Arduino
//
// STEAM es una mejora adicional: si la carpeta no existe, no hay
// permisos o el puente está apagado, estas funciones no hacen
// nada y la Caja sigue funcionando igual.
// ============================================================

// Usa quitar_tildes() de ayudas.php y el modelo de libros. require_once
// evita cargarlos dos veces cuando el controlador ya los trajo.
require_once __DIR__ . "/ayudas.php";
require_once __DIR__ . "/../modelos/libro.php";

// ------------------------------------------------------------
// CONFIGURACIÓN
// STEAM_ACTIVO en false apaga el aviso sin tocar la Caja.
//
// Ruta del archivo de comando:
// - Por defecto es la carpeta steam/puente del propio proyecto,
//   calculada desde la ubicación de este archivo. Así funciona en
//   cualquier PC sin depender de una letra de unidad.
// - Si existe php/steam_config_local.php (no se sube a GitHub),
//   ese archivo puede definir STEAM_ARCHIVO_COMANDO con otra ruta.
//   En la PC del equipo apunta a E:/PROYECTO/steam/puente/.
// ------------------------------------------------------------
define("STEAM_ACTIVO", true);
if (is_file(dirname(__DIR__) . "/php/steam_config_local.php")) {
    include dirname(__DIR__) . "/php/steam_config_local.php";
}
if (!defined("STEAM_ARCHIVO_COMANDO")) {
    define("STEAM_ARCHIVO_COMANDO", dirname(__DIR__) . "/steam/puente/comando_steam.txt");
}

// ------------------------------------------------------------
// TEXTO PARA EL LCD
// El LCD del Arduino solo muestra 16 letras por fila y no sabe
// dibujar tildes ni la ñ. Esta función prepara una COPIA del
// título para enviarla; el título guardado en la base no cambia.
// Ejemplo: "Cien años de soledad" -> "Cien anos de sol"
// ------------------------------------------------------------
function steam_texto_lcd($texto) {
    // 1. Cambia las letras con tilde y la ñ por su letra simple.
    $texto = quitar_tildes($texto);

    // 2. La barra "|" separa las partes del comando: no puede ir dentro del título.
    $texto = str_replace("|", " ", $texto);

    // 3. Quita cualquier otro carácter que el LCD no pueda mostrar
    //    (solo quedan letras, números y signos comunes).
    $texto = preg_replace('/[^\x20-\x7E]/', "", $texto);

    // 4. Deja un solo espacio entre palabras y corta en 16 letras.
    $texto = trim(preg_replace('/ +/', " ", $texto));
    return trim(substr($texto, 0, 16));
}

// ------------------------------------------------------------
// PRECIO PARA EL LCD
// Formato sencillo, sin "$" (lo agrega el Arduino).
// Ejemplos: 590.00 -> "590"     590.50 -> "590,50"
// ------------------------------------------------------------
function steam_precio($precio) {
    $precio = round((float) $precio, 2);

    if ($precio == floor($precio)) {
        return number_format($precio, 0, "", "");
    }
    return number_format($precio, 2, ",", "");
}

// ------------------------------------------------------------
// ESCRIBIR EL COMANDO
// Guarda en el archivo UNA línea con el último evento:
//
//   identificador + espacio + comando
//   ejemplo:  6718a3c5e4b0f1.12345678 OK|1984|590
//
// El identificador es distinto en cada escaneo. Gracias a él el
// puente sabe si la línea es un evento nuevo, aunque se escanee
// dos veces seguidas el mismo libro.
// Devuelve true si se pudo escribir y false si no. Nunca muestra
// errores: el "@" y el try/catch evitan que un fallo llegue a la Caja.
// ------------------------------------------------------------
function steam_enviar_comando($comando) {
    try {
        if (!STEAM_ACTIVO) {
            return false;
        }

        // Si la carpeta del puente no existe, STEAM no está disponible.
        if (!is_dir(dirname(STEAM_ARCHIVO_COMANDO))) {
            return false;
        }

        // El comando tiene que ser una sola línea.
        $comando = trim(str_replace(["\r", "\n"], " ", $comando));
        if ($comando == "") {
            return false;
        }

        $evento = uniqid("", true);
        $escrito = @file_put_contents(STEAM_ARCHIVO_COMANDO, $evento . " " . $comando . "\n");

        return $escrito !== false;
    } catch (Throwable $e) {
        return false;
    }
}

// ------------------------------------------------------------
// LOS TRES AVISOS QUE ENTIENDE EL ARDUINO
// ------------------------------------------------------------

// Libro físico encontrado y con stock: OK|titulo|precio
function steam_avisar_encontrado($titulo, $precio) {
    return steam_enviar_comando("OK|" . steam_texto_lcd($titulo) . "|" . steam_precio($precio));
}

// El código no corresponde a ningún libro: NO_ENCONTRADO
function steam_avisar_no_encontrado() {
    return steam_enviar_comando("NO_ENCONTRADO");
}

// Libro físico con stock 0 en la base: AGOTADO|titulo
function steam_avisar_agotado($titulo) {
    return steam_enviar_comando("AGOTADO|" . steam_texto_lcd($titulo));
}

// ------------------------------------------------------------
// ¿EL CÓDIGO ES DE UN LIBRO SOLO DIGITAL?
// El lector físico trabaja con libros físicos. Si el código
// pertenece a un libro que solo se vende en digital, la Caja no
// envía ningún aviso al Arduino.
// Ante cualquier fallo devuelve false (se trata como código desconocido).
// ------------------------------------------------------------
function steam_es_solo_digital($conexion, $codigo) {
    try {
        $libro = libro_formato_fisico_por_codigo($conexion, $codigo);

        return $libro && $libro["formato_fisico"] == 0;
    } catch (Throwable $e) {
        return false;
    }
}
?>
