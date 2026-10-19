<?php
// ============================================================
// CONFIG: DIRECCIONES DEL SITIO
// Todas las páginas se piden al enrutador (index.php) indicando el
// controlador y la acción:
//   index.php?controlador=libro&accion=ver&id=3
// Esta clase arma esas direcciones en un solo lugar.
// ============================================================
class Ruta
{
    // Dirección sin escapar (para redirigir con header("Location: ...")).
    public static function a(string $controlador, string $accion = 'index', array $parametros = []): string
    {
        $datos = ['controlador' => $controlador];
        if ($accion !== 'index') {
            $datos['accion'] = $accion;
        }
        return 'index.php?' . http_build_query($datos + $parametros);
    }

    // Dirección lista para escribir dentro de un atributo HTML (href, action).
    public static function h(string $controlador, string $accion = 'index', array $parametros = []): string
    {
        return htmlspecialchars(self::a($controlador, $accion, $parametros));
    }
}
