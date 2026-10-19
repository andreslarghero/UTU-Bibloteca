<?php
// ============================================================
// MODELO: RESULTADO DE REGISTRAR UNA VENTA
// Cuenta cómo terminó el registro: el estado, un mensaje (si hubo
// un problema), el número de la venta y el total.
// ============================================================
class ResultadoVenta
{
    public const OK = 'ok';
    public const YA_COMPRADO = 'ya_comprado';       // compra digital repetida: no se cobra de nuevo
    public const NO_DISPONIBLE = 'no_disponible';   // el libro no existe, está de baja o no se vende así
    public const ERROR = 'error';

    private string $estado;
    private string $mensaje;
    private int $ventaId;
    private float $total;

    public function __construct(string $estado, string $mensaje = '', int $ventaId = 0, float $total = 0)
    {
        $this->estado = $estado;
        $this->mensaje = $mensaje;
        $this->ventaId = $ventaId;
        $this->total = $total;
    }

    public static function error(string $mensaje): ResultadoVenta
    {
        return new ResultadoVenta(self::ERROR, $mensaje);
    }

    public function getEstado(): string { return $this->estado; }
    public function getMensaje(): string { return $this->mensaje; }
    public function getVentaId(): int { return $this->ventaId; }
    public function getTotal(): float { return $this->total; }
    public function esOk(): bool { return $this->estado === self::OK; }
}
