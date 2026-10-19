<?php
require_once __DIR__ . '/Usuario.php';

// ============================================================
// MODELO: CLIENTE
// Un cliente es un usuario que compra libros. No agrega nada
// al menú: usa el de la clase base.
// ============================================================
class Cliente extends Usuario
{
    public const ROL = 'cliente';

    public function rol(): string
    {
        return self::ROL;
    }
}
