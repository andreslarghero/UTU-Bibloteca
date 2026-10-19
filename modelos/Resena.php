<?php
// ============================================================
// MODELO: RESEÑA
// Cada objeto es una fila de la tabla "resenias": la opinión (de 1
// a 5 estrellas y un comentario) de un usuario sobre un libro.
// ============================================================
class Resena
{
    private int $id;
    private int $libroId;
    private int $usuarioId;
    private int $puntuacion;
    private string $comentario;
    private string $fecha;
    private string $usuarioNombre;   // quién la escribió (al leer la lista)
    private string $libroTitulo;     // de qué libro es (al leer todas las reseñas)

    // $d es un arreglo con los campos de la reseña (los que falten quedan vacíos).
    public function __construct(array $d)
    {
        $this->id = (int) ($d['id'] ?? 0);
        $this->libroId = (int) ($d['libro_id'] ?? 0);
        $this->usuarioId = (int) ($d['usuario_id'] ?? 0);
        $this->puntuacion = (int) ($d['puntuacion'] ?? 1);
        $this->comentario = (string) ($d['comentario'] ?? '');
        $this->fecha = (string) ($d['fecha'] ?? '');
        $this->usuarioNombre = (string) ($d['nombre'] ?? '');
        $this->libroTitulo = (string) ($d['titulo'] ?? '');
    }

    // Una reseña nueva a partir de lo que llegó del formulario. La puntuación
    // se limita entre 1 y 5 estrellas.
    public static function nueva(int $libroId, int $usuarioId, int $puntuacion, string $comentario): Resena
    {
        return new Resena([
            'libro_id' => $libroId,
            'usuario_id' => $usuarioId,
            'puntuacion' => min(5, max(1, $puntuacion)),
            'comentario' => $comentario,
        ]);
    }

    public function getId(): int { return $this->id; }
    public function getLibroId(): int { return $this->libroId; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getPuntuacion(): int { return $this->puntuacion; }
    public function getComentario(): string { return $this->comentario; }
    public function getFecha(): string { return $this->fecha; }
    public function getUsuarioNombre(): string { return $this->usuarioNombre; }
    public function getLibroTitulo(): string { return $this->libroTitulo; }

    // Las estrellas como texto, por ejemplo 3 = ★★★☆☆.
    public function __toString(): string
    {
        return str_repeat('★', $this->puntuacion) . str_repeat('☆', 5 - $this->puntuacion);
    }
}
