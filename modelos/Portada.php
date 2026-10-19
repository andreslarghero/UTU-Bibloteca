<?php
// ============================================================
// MODELO: ARCHIVOS DE PORTADA
// Las portadas son archivos del propio proyecto, guardados en la
// carpeta "img/portadas". En la base, el campo "portada" guarda la
// ruta relativa (por ejemplo: img/portadas/1984.webp), así el sitio
// funciona igual en cualquier carpeta donde se copie. También se
// acepta una dirección web completa (http:// o https://).
// Esta clase solo valida y ubica el archivo; el HTML de la portada
// se arma en vistas/ayudas.php.
// ============================================================
class Portada
{
    private const PREFIJO = 'img/portadas/';

    // Una portada local solo puede ser un archivo de imagen que esté
    // directamente dentro de la carpeta img/portadas.
    // Devuelve la ruta ya normalizada (img/portadas/archivo.ext) si es
    // válida y el archivo existe; si no, devuelve "".
    // Así, aunque en la base hubiera un valor incorrecto, nunca se usa.
    public static function local($portada): string
    {
        // 1. Normalizar los separadores: las barras de Windows pasan a "/".
        $ruta = str_replace('\\', '/', trim((string) $portada));

        // 2. Rechazar lo que intenta salir de la carpeta: "..", una ruta
        //    absoluta ("/..." o "C:...") y caracteres de control.
        if ($ruta === '' || strpos($ruta, '..') !== false || $ruta[0] === '/'
            || preg_match('/^[A-Za-z]:/', $ruta) || preg_match('/[\x00-\x1F]/', $ruta)) {
            return '';
        }

        // 3. Tiene que empezar exactamente por "img/portadas/". Cualquier
        //    otra carpeta (digitales/, sql/, js/, css/...) se rechaza.
        if (strpos($ruta, self::PREFIJO) !== 0) {
            return '';
        }

        // 4. Después del prefijo solo puede venir el nombre del archivo
        //    (sin más carpetas), con letras, números y guiones, y con una
        //    de las extensiones de imagen permitidas.
        $nombre = substr($ruta, strlen(self::PREFIJO));
        if (!preg_match('/^[A-Za-z0-9_-]+\.(webp|jpg|jpeg|png)$/', $nombre)) {
            return '';
        }

        // 5. El archivo tiene que existir de verdad y estar dentro de la
        //    carpeta img/portadas. realpath() resuelve la ubicación real,
        //    y se comprueba que su carpeta sea exactamente esa.
        $carpeta = realpath(dirname(__DIR__) . '/img/portadas');
        $archivo = ($carpeta === false) ? false : realpath($carpeta . DIRECTORY_SEPARATOR . $nombre);
        if ($archivo === false || !is_file($archivo) || dirname($archivo) !== $carpeta) {
            return '';
        }

        return self::PREFIJO . $nombre;
    }

    // Revisa el valor que escribió el bibliotecario. Devuelve el mensaje de
    // error, o "" si es válido. El mensaje es siempre el mismo y no muestra
    // rutas internas.
    public static function validar(string $portada): string
    {
        if (preg_match('/^https?:\/\//i', $portada)) {
            if (strlen($portada) > 255 || !filter_var($portada, FILTER_VALIDATE_URL)) {
                return 'La dirección web de la portada no es válida.';
            }
            return '';
        }

        if (self::local($portada) === '') {
            return 'La portada debe ser una imagen (.webp, .jpg, .jpeg o .png) que exista en la carpeta img/portadas, por ejemplo img/portadas/mi-libro.webp, o una dirección web que empiece por http:// o https://.';
        }
        return '';
    }

    // La forma en que se guarda: una dirección web queda igual y un archivo
    // local queda con su ruta normalizada.
    public static function normalizar(string $portada): string
    {
        if (preg_match('/^https?:\/\//i', $portada)) {
            return $portada;
        }
        return self::local($portada);
    }

    // Lo que hay que poner en el "src" de la imagen, o "" si el libro no tiene
    // una portada que se pueda mostrar.
    public static function src($portada): string
    {
        $portada = trim((string) $portada);

        // Dirección web completa (un libro cargado con una imagen de otro sitio).
        if (preg_match('#^https?://#i', $portada)) {
            return $portada;
        }

        // Portada local: tiene que pasar toda la validación. Si no, se usa el reemplazo.
        return self::local($portada);
    }
}
