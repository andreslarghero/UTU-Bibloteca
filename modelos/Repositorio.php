<?php
// ============================================================
// MODELO: INTERFAZ REPOSITORIO
// Un contrato: todo repositorio se compromete a poder devolver
// todos sus objetos y buscar uno por su id. Cada repositorio
// agrega, además, las consultas propias de su tabla.
// ============================================================
interface Repositorio
{
    // Todos los objetos de la tabla.
    public function todos(): array;

    // Un objeto por su id, o null si no existe.
    public function buscar(int $id);
}
