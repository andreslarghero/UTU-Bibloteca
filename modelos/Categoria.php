<?php
// ============================================================
// MODELO: CATEGORÍA
// Cada objeto es una fila de la tabla "categorias".
// ============================================================
class Categoria
{
    private int $id;
    private string $nombre;

    public function __construct(int $id, string $nombre)
    {
        $this->id = $id;
        $this->nombre = $nombre;
    }

    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }

    public function __toString(): string
    {
        return htmlspecialchars($this->nombre);
    }
}
