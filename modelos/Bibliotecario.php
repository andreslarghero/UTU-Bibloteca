<?php
require_once __DIR__ . '/Usuario.php';

// ============================================================
// MODELO: BIBLIOTECARIO
// Un bibliotecario es un usuario que además gestiona la biblioteca.
// Sobrescribe enlacesMenu() para agregar los enlaces de gestión,
// reutilizando el menú del padre con parent::.
// ============================================================
class Bibliotecario extends Usuario
{
    public const ROL = 'bibliotecario';

    public function rol(): string
    {
        return self::ROL;
    }

    public function esBibliotecario(): bool
    {
        return true;
    }

    public function enlacesMenu(): array
    {
        return array_merge(parent::enlacesMenu(), [
            ['id' => 'caja', 'controlador' => 'caja', 'accion' => 'index', 'texto' => 'Caja'],
            ['id' => 'operacion', 'controlador' => 'operacion', 'accion' => 'index', 'texto' => 'Operaciones'],
            ['id' => 'stock', 'controlador' => 'stock', 'accion' => 'index', 'texto' => 'Gestionar stock'],
            ['id' => 'libroadmin', 'controlador' => 'libroadmin', 'accion' => 'index', 'texto' => 'Gestionar libros'],
            ['id' => 'usuarioadmin', 'controlador' => 'usuarioadmin', 'accion' => 'index', 'texto' => 'Gestionar usuarios'],
            ['id' => 'resena', 'controlador' => 'resena', 'accion' => 'index', 'texto' => 'Gestionar reseñas'],
        ]);
    }
}
