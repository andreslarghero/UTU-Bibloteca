<?php
// ============================================================
// MODELO: ARCHIVOS DIGITALES (PDF y EPUB)
// Los archivos se guardan en la carpeta "digitales" del proyecto,
// que no se publica en Apache. En la entrega para localhost
// (preparar_entrega.ps1) se crea config/ruta_digitales.php con la ruta de
// una carpeta FUERA del sitio y los archivos se guardan y leen allí; así
// no se pueden abrir escribiendo una dirección (no hace falta .htaccess).
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
        $configuracion = dirname(__DIR__) . '/config/ruta_digitales.php';
        if (is_file($configuracion)) {
            return rtrim(include $configuracion, '/\\') . '/';
        }
        return dirname(__DIR__) . '/digitales/';
    }

    // ¿Se eligió un archivo en el formulario? $archivo es $_FILES["archivo"]
    // (error 4 = UPLOAD_ERR_NO_FILE = no se eligió ninguno).
    public static function haySubida($archivo): bool
    {
        return $archivo && !is_array($archivo['error']) && $archivo['error'] != UPLOAD_ERR_NO_FILE;
    }

    // Revisa el archivo que llegó. Devuelve el mensaje de error, o "" si es válido.
    public static function validarSubida(array $archivo): string
    {
        // La extensión se toma del nombre original, pero ese nombre no se usa para nada más.
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if ($archivo['error'] == UPLOAD_ERR_INI_SIZE || $archivo['error'] == UPLOAD_ERR_FORM_SIZE || $archivo['size'] > self::TAMANO_MAXIMO) {
            return 'El archivo digital es demasiado grande (máximo 20 MB).';
        }
        if ($archivo['error'] != UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            return 'No se pudo subir el archivo digital.';
        }
        if ($extension != 'pdf' && $extension != 'epub') {
            return 'El archivo digital debe ser PDF o EPUB.';
        }

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

        return $valido ? '' : 'El archivo no es un PDF o EPUB válido.';
    }

    // Guarda el archivo subido (ya validado) en la carpeta protegida. No se conserva
    // el nombre original: se inventa uno seguro y único ("libro_" + 32 letras y
    // números al azar + la extensión). Devuelve el nombre nuevo o "" si falló.
    public static function guardar(array $subida): string
    {
        $extension = strtolower(pathinfo($subida['name'], PATHINFO_EXTENSION));
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
        $carpeta = realpath(self::carpeta());
        $ruta = ($carpeta === false) ? false : realpath($carpeta . DIRECTORY_SEPARATOR . $nombre);

        if ($ruta === false || !is_file($ruta) || strpos($ruta, $carpeta . DIRECTORY_SEPARATOR) !== 0
            || ($extension != 'pdf' && $extension != 'epub')) {
            return false;
        }
        return $ruta;
    }
}
