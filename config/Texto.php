<?php
// ============================================================
// CONFIG: UTILIDADES DE TEXTO
// Funciones estáticas pequeñas que usan varias clases.
// ============================================================
class Texto
{
    // Precio con el formato del sitio: 1.234,50
    public static function precio($precio): string
    {
        return number_format((float) $precio, 2, ',', '.');
    }

    // Cambia las letras con tilde y la ñ por su letra simple.
    // Ejemplo: "Cien años" -> "Cien anos"
    public static function sinTildes(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }
}
