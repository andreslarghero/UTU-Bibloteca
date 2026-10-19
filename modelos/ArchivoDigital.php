<?php
// ============================================================
// MODELO: ARCHIVOS DIGITALES (PDF y EPUB)
// Los archivos se guardan en la carpeta "digitales" del proyecto,
// que está protegida para que no se pueda abrir desde el navegador.
// En la base solo se guarda el nombre del archivo. Esta clase valida
// los archivos que sube el bibliotecario, los guarda con un nombre
// seguro y ubica de forma segura los que se descargan.
// ============================================================
class ArchivoDigital
{
    public const TAMANO_MAXIMO = 20 * 1024 * 1024;   // 20 MB

    // La carpeta protegida donde se guardan los archivos.
    public static function carpeta(): string
    {
        return dirname(__DIR__) . '/digitales/';
    }

    // Revisa el archivo que llegó en $_FILES["archivo"]. Devuelve
    // ["error" => texto o "", "extension" => "pdf" o "epub", "archivo" => datos o null].
    // "archivo" queda con los datos solo si se envió un archivo válido; si no se
    // envió ninguno (error 4), no hay error ni archivo.
    public static function validarSubida($archivo): array
    {
        $vacio = ['error' => '', 'extension' => '', 'archivo' => null];

        // ¿Se envió un archivo? (error 4 = UPLOAD_ERR_NO_FILE = no se eligió ninguno)
        if (!$archivo || is_array($archivo['error']) || $archivo['error'] == UPLOAD_ERR_NO_FILE) {
            return $vacio;
        }

        // La extensión se toma del nombre original, pero ese nombre no se usa para nada más.
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $resultado = ['error' => '', 'extension' => $extension, 'archivo' => null];

        if ($archivo['error'] == UPLOAD_ERR_INI_SIZE || $archivo['error'] == UPLOAD_ERR_FORM_SIZE || $archivo['size'] > self::TAMANO_MAXIMO) {
            $resultado['error'] = 'El archivo digital es demasiado grande (máximo 20 MB).';
        } elseif ($archivo['error'] != UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            $resultado['error'] = 'No se pudo subir el archivo digital.';
        } elseif ($extension != 'pdf' && $extension != 'epub') {
            $resultado['error'] = 'El archivo digital debe ser PDF o EPUB.';
        } else {
            // No alcanza con la extensión: se mira el comienzo del archivo para
            // comprobar que por dentro es realmente un PDF o un EPUB.
            //  - Un PDF siempre empieza con "%PDF-".
            //  - Un EPUB es un archivo comprimido que empieza con "PK" y lleva
            //    escrito su tipo "application/epub+zip" al principio.
            $inicio = (string) file_get_contents($archivo['tmp_name'], false, null, 0, 200);

            if ($extension == 'pdf') {
                $valido = (substr($inicio, 0, 5) == '%PDF-');
            } else {
                $valido = (substr($inicio, 0, 4) == "PK\x03\x04" && strpos($inicio, 'application/epub+zip') !== false);
            }

            if ($valido) {
                $resultado['archivo'] = $archivo;
            } else {
                $resultado['error'] = 'El archivo no es un PDF o EPUB válido.';
            }
        }

        return $resultado;
    }

    // Guarda el archivo subido en la carpeta protegida. No se conserva el nombre
    // original: se inventa uno seguro y único ("libro_" + 32 letras y números al
    // azar + la extensión validada). Devuelve el nombre nuevo o "" si falló.
    public static function guardar(array $subida, string $extension): string
    {
        $nombre = 'libro_' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (move_uploaded_file($subida['tmp_name'], self::carpeta() . $nombre)) {
            return $nombre;
        }
        return '';
    }

    // Quita un archivo recién subido que no quedó asociado a ningún libro.
    public static function quitar(string $nombre): void
    {
        unlink(self::carpeta() . $nombre);
    }

    // La ruta real de un archivo para descargarlo, o false si no existe o no es
    // un PDF/EPUB dentro de la carpeta. La ubicación se arma con el nombre guardado
    // en la base, nunca con algo escrito por el usuario: basename() se queda solo
    // con el nombre y se comprueba que el archivo está dentro de "digitales".
    public static function ruta(string $nombreGuardado)
    {
        $nombre = basename($nombreGuardado);
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $carpeta = realpath(dirname(__DIR__) . '/digitales');
        $ruta = ($carpeta === false) ? false : realpath($carpeta . DIRECTORY_SEPARATOR . $nombre);

        if ($ruta === false || !is_file($ruta) || strpos($ruta, $carpeta . DIRECTORY_SEPARATOR) !== 0
            || ($extension != 'pdf' && $extension != 'epub')) {
            return false;
        }
        return $ruta;
    }
}
