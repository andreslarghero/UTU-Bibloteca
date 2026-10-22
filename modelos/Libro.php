<?php
require_once __DIR__ . '/Portada.php';

// ============================================================
// MODELO: LIBRO
// Cada objeto es una fila de la tabla "libros" (con algunos datos
// que el repositorio agrega al leerla: valoración media y cantidad
// de reseñas). La categoría es un texto de la propia tabla "libros".
// Un libro puede venderse en formato físico, digital o en los dos.
// También se usa para llevar al formulario lo que el bibliotecario
// escribió, aunque tenga errores: por eso los atributos aceptan
// texto sin validar y validar() revisa las reglas.
// ============================================================
class Libro
{
    private int $id;
    private string $titulo;
    private string $autor;
    private $anio;
    private $portada;
    private $descripcion;
    private string $categoria;      // categoría (texto, hasta 50 letras)
    private $isbn;
    private $precio;
    private $codigoBarras;
    private $stock;
    private bool $formatoFisico;
    private bool $formatoDigital;
    private $precioDigital;
    private $archivoDigital;
    private bool $activo;
    private $media;                 // valoración media (solo en el catálogo)
    private int $totalResenas;      // cantidad de reseñas (solo en el catálogo)

    // $d es un arreglo con los campos del libro (los que falten quedan vacíos).
    public function __construct(array $d = [])
    {
        $this->id = (int) ($d['id'] ?? 0);
        $this->titulo = (string) ($d['titulo'] ?? '');
        $this->autor = (string) ($d['autor'] ?? '');
        $this->anio = $d['anio'] ?? '';
        $this->portada = $d['portada'] ?? '';
        $this->descripcion = $d['descripcion'] ?? '';
        // Sin espacios sobrantes al principio, al final ni repetidos en el medio.
        $this->categoria = trim(preg_replace('/\s+/u', ' ', (string) ($d['categoria'] ?? '')));
        $this->isbn = $d['isbn'] ?? '';
        $this->precio = $d['precio'] ?? '';
        $this->codigoBarras = $d['codigo_barras'] ?? '';
        $this->stock = $d['stock'] ?? '0';
        $this->formatoFisico = !empty($d['formato_fisico']);
        $this->formatoDigital = !empty($d['formato_digital']);
        // Se distingue "sin valor" (null en la base) de "vacío" (formulario).
        $this->precioDigital = array_key_exists('precio_digital', $d) ? $d['precio_digital'] : '';
        $this->archivoDigital = $d['archivo_digital'] ?? null;
        $this->activo = !array_key_exists('activo', $d) || !empty($d['activo']);
        $this->media = $d['media'] ?? null;
        $this->totalResenas = (int) ($d['total_resenas'] ?? 0);
    }

    // Un libro nuevo y vacío, para el formulario de alta.
    public static function nuevo(): Libro
    {
        return new Libro(['formato_fisico' => 1, 'stock' => '0']);
    }

    // ---------- Lectura ----------
    public function getId(): int { return $this->id; }
    public function getTitulo(): string { return $this->titulo; }
    public function getAutor(): string { return $this->autor; }
    public function getAnio() { return $this->anio; }
    public function getPortada() { return $this->portada; }
    public function getDescripcion() { return $this->descripcion; }
    public function getCategoria(): string { return $this->categoria; }
    public function getIsbn() { return $this->isbn; }
    public function getPrecio() { return $this->precio; }
    public function getCodigoBarras() { return $this->codigoBarras; }
    public function getStock() { return $this->stock; }
    public function getPrecioDigital() { return $this->precioDigital; }
    public function getArchivoDigital() { return $this->archivoDigital; }
    public function getMedia() { return $this->media; }
    public function getTotalResenas(): int { return $this->totalResenas; }

    public function esFisico(): bool { return $this->formatoFisico; }
    public function esDigital(): bool { return $this->formatoDigital; }
    public function estaActivo(): bool { return $this->activo; }
    public function tieneArchivo(): bool { return $this->archivoDigital != ''; }

    // El stock es solo del formato físico: sin copias, el libro está agotado.
    public function hayStock(): bool { return $this->stock > 0; }

    // Precio con el que se muestra el libro: el físico si lo tiene; si no, el digital.
    public function precioMostrado()
    {
        return $this->formatoFisico ? $this->precio : $this->precioDigital;
    }

    // ---------- Cambios ----------
    public function setArchivoDigital($archivo): void { $this->archivoDigital = $archivo; }
    public function setPortada($portada): void { $this->portada = $portada; }
    public function setCategoria(string $categoria): void { $this->categoria = $categoria; }

    // Datos listos para guardar en la base: los campos opcionales vacíos
    // quedan como null y lo que no corresponde al formato queda en 0 o null.
    public function aFila(): array
    {
        return [
            'titulo' => $this->titulo,
            'autor' => $this->autor,
            'anio' => ($this->anio == '') ? null : (int) $this->anio,
            'portada' => ($this->portada == '') ? null : $this->portada,
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoria,
            'isbn' => ($this->isbn == '') ? null : $this->isbn,
            // Si el libro no es físico, no usa precio físico ni stock: quedan en 0.
            'precio' => $this->formatoFisico ? (float) $this->precio : 0,
            'codigo_barras' => ($this->codigoBarras == '') ? null : $this->codigoBarras,
            'stock' => $this->formatoFisico ? (int) $this->stock : 0,
            'formato_fisico' => $this->formatoFisico ? 1 : 0,
            'formato_digital' => $this->formatoDigital ? 1 : 0,
            // Si el libro no es digital, no tiene precio digital ni archivo.
            'precio_digital' => $this->formatoDigital ? (float) $this->precioDigital : null,
            'archivo_digital' => $this->formatoDigital ? $this->archivoDigital : null,
        ];
    }

    // ---------- Validación ----------
    // Revisa las reglas del libro y devuelve la lista de errores (vacía si todo
    // está bien). Usa el repositorio solo para comprobar que el ISBN y el código
    // de barras no estén en otro libro.
    // Si la portada es un archivo local válido, deja su ruta ya normalizada.
    public function validar(RepositorioLibros $libros): array
    {
        $errores = [];

        // Todo libro tiene que venderse al menos en un formato.
        if (!$this->formatoFisico && !$this->formatoDigital) {
            $errores[] = 'Marca al menos un formato: físico, digital o los dos.';
        }

        if ($this->titulo == '') {
            $errores[] = 'El título es obligatorio.';
        } elseif (strlen($this->titulo) > 150) {
            $errores[] = 'El título es demasiado largo.';
        }

        if ($this->autor == '') {
            $errores[] = 'El autor es obligatorio.';
        } elseif (strlen($this->autor) > 100) {
            $errores[] = 'El autor es demasiado largo.';
        }

        // El año puede quedar vacío; si se escribe, tiene que ser razonable.
        if ($this->anio != '') {
            if (!ctype_digit($this->anio) || $this->anio < 1000 || $this->anio > date('Y')) {
                $errores[] = 'El año debe ser un número entre 1000 y ' . date('Y') . '.';
            }
        }

        // La portada puede quedar vacía. Si se escribe: una dirección web completa
        // o un archivo local que SOLO puede estar en la carpeta img/portadas.
        if ($this->portada != '') {
            $error = Portada::validar($this->portada);
            if ($error != '') {
                $errores[] = $error;
            } else {
                $this->portada = Portada::normalizar($this->portada);
            }
        }

        // La categoría es obligatoria (hasta 50 letras).
        if ($this->categoria == '') {
            $errores[] = 'La categoría es obligatoria.';
        } elseif (mb_strlen($this->categoria) > 50) {
            $errores[] = 'La categoría es demasiado larga (máximo 50 letras).';
        }

        // El precio y el stock son del formato físico: solo se exigen si el libro es físico.
        if ($this->formatoFisico) {
            if (!is_numeric($this->precio) || $this->precio < 0 || $this->precio > 999999.99) {
                $errores[] = 'El precio físico debe ser un número igual o mayor que 0.';
            }

            // ctype_digit solo acepta dígitos: rechaza negativos, decimales y texto.
            if (!ctype_digit((string) $this->stock)) {
                $errores[] = 'El stock debe ser un número entero igual o mayor que 0.';
            }
        }

        // El ISBN puede quedar vacío, pero si tiene valor no puede repetirse en otro libro.
        if (strlen($this->isbn) > 20) {
            $errores[] = 'El ISBN es demasiado largo.';
        } elseif ($this->isbn != '') {
            if ($libros->isbnEnUso($this->isbn, $this->id)) {
                $errores[] = 'Ya existe otro libro con ese ISBN.';
            }
        }

        // El código de barras es obligatorio si el libro es físico (es el que lee la
        // caja). Si tiene valor, no puede repetirse en otro libro.
        if ($this->codigoBarras == '') {
            if ($this->formatoFisico) {
                $errores[] = 'El código de barras es obligatorio para el formato físico.';
            }
        } elseif (strlen($this->codigoBarras) > 20) {
            $errores[] = 'El código de barras es demasiado largo.';
        } elseif ($libros->codigoEnUso($this->codigoBarras, $this->id)) {
            $errores[] = 'Ya existe otro libro con ese código de barras.';
        }

        return $errores;
    }

    // Si el libro es digital, el precio digital tiene que ser un número válido.
    public function validarPrecioDigital(): array
    {
        if ($this->formatoDigital) {
            if (!is_numeric($this->precioDigital) || $this->precioDigital < 0 || $this->precioDigital > 999999.99) {
                return ['El precio digital debe ser un número igual o mayor que 0.'];
            }
        }
        return [];
    }

    // Cómo se ve el libro cuando se lo usa como texto.
    public function __toString(): string
    {
        return htmlspecialchars($this->titulo) . ' - ' . htmlspecialchars($this->autor);
    }
}
