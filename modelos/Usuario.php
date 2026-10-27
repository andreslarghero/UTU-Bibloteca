<?php
// ============================================================
// MODELO: USUARIO
// Clase abstracta: no se crea un "Usuario" a secas, siempre es un
// Cliente o un Bibliotecario (relación "es un"). Lo común está
// aquí; lo que cambia según el tipo se sobrescribe en cada hija
// (polimorfismo): rol() y enlacesMenu().
// ============================================================
abstract class Usuario
{
    protected int $id;
    protected string $nombre;
    protected string $correo;
    protected bool $activo;

    public function __construct(int $id, string $nombre, string $correo, bool $activo = true)
    {
        $this->id = $id;
        $this->nombre = $nombre;
        $this->correo = $correo;
        $this->activo = $activo;
    }

    // Crea el objeto del tipo que corresponde a una fila de la tabla "usuarios".
    public static function desdeFila(array $fila): Usuario
    {
        $clase = ($fila['rol'] === Bibliotecario::ROL) ? 'Bibliotecario' : 'Cliente';
        return new $clase((int) $fila['id'], $fila['nombre'], $fila['correo'], (bool) $fila['activo']);
    }

    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getCorreo(): string { return $this->correo; }
    public function estaActivo(): bool { return $this->activo; }

    // "cliente" o "bibliotecario", tal como se guarda en la base.
    abstract public function rol(): string;

    public function esBibliotecario(): bool
    {
        return false;
    }

    // Enlaces del menú: cada uno con su identificador, el controlador al que
    // lleva, la acción y el texto. Las hijas pueden agregar los suyos.
    public function enlacesMenu(): array
    {
        return [
            ['id' => 'libro', 'controlador' => 'libro', 'accion' => 'index', 'texto' => 'Catálogo'],
            ['id' => 'perfil', 'controlador' => 'perfil', 'accion' => 'index', 'texto' => 'Mi perfil'],
            ['id' => 'compra', 'controlador' => 'compra', 'accion' => 'index', 'texto' => 'Mis compras'],
        ];
    }

    // Enlaces del menú para quien no inició sesión.
    public static function enlacesInvitado(): array
    {
        return [
            ['id' => 'login', 'controlador' => 'auth', 'accion' => 'login', 'texto' => 'Iniciar sesión'],
            ['id' => 'registro', 'controlador' => 'auth', 'accion' => 'registro', 'texto' => 'Registrarse'],
        ];
    }

    // ¿El correo tiene un formato válido (y no es demasiado largo)?
    public static function correoValido(string $correo): bool
    {
        return $correo != '' && strlen($correo) <= 150 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }

    // Valida el nombre y el correo (sin tocar la base). Devuelve la lista de
    // errores. Que el correo no esté repetido lo comprueba el repositorio.
    public static function validarNombreCorreo(string $nombre, string $correo): array
    {
        $errores = [];

        if ($nombre == '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($nombre) > 100) {
            $errores[] = 'El nombre es demasiado largo.';
        }

        if ($correo == '') {
            $errores[] = 'El correo es obligatorio.';
        } elseif (!self::correoValido($correo)) {
            $errores[] = 'El correo no es válido.';
        }

        return $errores;
    }

    // Cómo se ve el usuario cuando se lo usa como texto.
    public function __toString(): string
    {
        return $this->nombre;
    }
}
