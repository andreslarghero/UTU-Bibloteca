<?php
require_once __DIR__ . '/../config/Texto.php';
require_once __DIR__ . '/RepositorioLibros.php';

// ============================================================
// SERVICIO STEAM: AVISO AL LECTOR FÍSICO (ARDUINO)
// La Caja usa esta clase para avisarle al Arduino el resultado de
// cada escaneo. PHP no habla con el Arduino: solo escribe una línea
// en un archivo de texto. El programa puente_arduino.py lee ese
// archivo y le manda el comando al Arduino por USB.
//
//   Caja PHP  ->  comando_steam.txt  ->  puente_arduino.py  ->  Arduino
//
// STEAM es una mejora adicional: si la carpeta no existe, no hay
// permisos o el puente está apagado, estos métodos no hacen nada y
// la Caja sigue funcionando igual.
// ============================================================
class Steam
{
    // STEAM_ACTIVO en false apaga el aviso sin tocar la Caja.
    private const ACTIVO = true;

    // Ruta del archivo de comando:
    // - Por defecto es la carpeta steam/puente del propio proyecto,
    //   calculada desde la ubicación de este archivo. Así funciona en
    //   cualquier PC sin depender de una letra de unidad.
    // - Si existe config/steam_config_local.php (no se sube a GitHub),
    //   ese archivo puede definir STEAM_ARCHIVO_COMANDO con otra ruta.
    //   En la PC del equipo apunta a E:/PROYECTO/steam/puente/.
    private static function archivoComando(): string
    {
        $local = dirname(__DIR__) . '/config/steam_config_local.php';
        if (is_file($local)) {
            include_once $local;
        }
        if (defined('STEAM_ARCHIVO_COMANDO')) {
            return STEAM_ARCHIVO_COMANDO;
        }
        return dirname(__DIR__) . '/steam/puente/comando_steam.txt';
    }

    // El LCD del Arduino solo muestra 16 letras por fila y no sabe dibujar
    // tildes ni la ñ. Prepara una COPIA del título para enviarla; el título
    // guardado en la base no cambia.
    // Ejemplo: "Cien años de soledad" -> "Cien anos de sol"
    public static function textoLcd(string $texto): string
    {
        // 1. Cambia las letras con tilde y la ñ por su letra simple.
        $texto = Texto::sinTildes($texto);

        // 2. La barra "|" separa las partes del comando: no puede ir dentro del título.
        $texto = str_replace('|', ' ', $texto);

        // 3. Quita cualquier otro carácter que el LCD no pueda mostrar
        //    (solo quedan letras, números y signos comunes).
        $texto = preg_replace('/[^\x20-\x7E]/', '', $texto);

        // 4. Deja un solo espacio entre palabras y corta en 16 letras.
        $texto = trim(preg_replace('/ +/', ' ', $texto));
        return trim(substr($texto, 0, 16));
    }

    // Formato sencillo, sin "$" (lo agrega el Arduino).
    // Ejemplos: 590.00 -> "590"     590.50 -> "590,50"
    public static function precio($precio): string
    {
        $precio = round((float) $precio, 2);

        if ($precio == floor($precio)) {
            return number_format($precio, 0, '', '');
        }
        return number_format($precio, 2, ',', '');
    }

    // Guarda en el archivo UNA línea con el último evento:
    //
    //   identificador + espacio + comando
    //   ejemplo:  6718a3c5e4b0f1.12345678 OK|1984|590
    //
    // El identificador es distinto en cada escaneo. Gracias a él el puente
    // sabe si la línea es un evento nuevo, aunque se escanee dos veces seguidas
    // el mismo libro. Devuelve true si se pudo escribir y false si no. Nunca
    // muestra errores: el "@" y el try/catch evitan que un fallo llegue a la Caja.
    private static function enviarComando(string $comando): bool
    {
        try {
            if (!self::ACTIVO) {
                return false;
            }

            $archivo = self::archivoComando();

            // Si la carpeta del puente no existe, STEAM no está disponible.
            if (!is_dir(dirname($archivo))) {
                return false;
            }

            // El comando tiene que ser una sola línea.
            $comando = trim(str_replace(["\r", "\n"], ' ', $comando));
            if ($comando == '') {
                return false;
            }

            $evento = uniqid('', true);
            $escrito = @file_put_contents($archivo, $evento . ' ' . $comando . "\n");

            return $escrito !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ---------- Los tres avisos que entiende el Arduino ----------

    // Libro físico encontrado y con stock: OK|titulo|precio
    public static function avisarEncontrado(string $titulo, $precio): bool
    {
        return self::enviarComando('OK|' . self::textoLcd($titulo) . '|' . self::precio($precio));
    }

    // El código no corresponde a ningún libro: NO_ENCONTRADO
    public static function avisarNoEncontrado(): bool
    {
        return self::enviarComando('NO_ENCONTRADO');
    }

    // Libro físico con stock 0 en la base: AGOTADO|titulo
    public static function avisarAgotado(string $titulo): bool
    {
        return self::enviarComando('AGOTADO|' . self::textoLcd($titulo));
    }

    // ¿El código es de un libro SOLO digital? El lector físico trabaja con libros
    // físicos: si el código pertenece a un libro que solo se vende en digital, la
    // Caja no envía ningún aviso al Arduino. Ante cualquier fallo devuelve false
    // (se trata como código desconocido).
    public static function esSoloDigital(string $codigo): bool
    {
        try {
            $libro = (new RepositorioLibros())->porCodigo($codigo);
            return $libro && !$libro->esFisico();
        } catch (Throwable $e) {
            return false;
        }
    }
}
