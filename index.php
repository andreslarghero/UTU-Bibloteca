<?php
// ============================================================
// ENRUTADOR: ÚNICO PUNTO DE ENTRADA DEL SITIO
// Todas las páginas se piden aquí, indicando el controlador y la acción:
//
//   index.php?controlador=libro&accion=ver&id=3
//
// Este archivo lee la dirección, crea el controlador que corresponde y
// llama a su acción. Si no se indica nada, muestra la página de inicio.
// Cada controlador está en controladores/, los datos en modelos/ y el
// HTML en vistas/.
// ============================================================

// Recupera (o inicia) la sesión, que recuerda al usuario entre páginas.
session_start();

// Los controladores que existen: nombre en la dirección => clase.
$controladores = [
    'inicio' => 'InicioController',
    'auth' => 'AuthController',
    'libro' => 'LibroController',
    'perfil' => 'PerfilController',
    'compra' => 'CompraController',
    'caja' => 'CajaController',
    'stock' => 'StockController',
    'resena' => 'ResenaController',
    'libroadmin' => 'LibroAdminController',
    'usuarioadmin' => 'UsuarioAdminController',
    'operacion' => 'OperacionController',
];

$nombre = $_GET['controlador'] ?? 'inicio';
$accion = $_GET['accion'] ?? 'index';

// Solo se aceptan nombres de acción formados por letras (así no se puede
// llamar a métodos especiales como __construct).
if (!is_string($nombre) || !is_string($accion) || !isset($controladores[$nombre]) || !preg_match('/^[a-z][A-Za-z]*$/', $accion)) {
    http_response_code(404);
    exit('Página no encontrada.');
}

$clase = $controladores[$nombre];

require_once __DIR__ . '/controladores/' . $clase . '.php';
$controlador = new $clase();

// La acción tiene que ser un método público del controlador.
if (!is_callable([$controlador, $accion])) {
    http_response_code(404);
    exit('Página no encontrada.');
}

$controlador->$accion();
