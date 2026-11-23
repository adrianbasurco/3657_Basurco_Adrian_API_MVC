<?php

// =====================================================
// PUNTO DE ENTRADA DE LA API - MARAH
// Todas las peticiones /api/... llegan a este archivo.
// =====================================================

// =====================================
// AUTOLOAD
// Si se ejecutó "composer install" se usa el autoload de Composer.
// Si no, se usa un autoload PSR-4 equivalente.
// =====================================

$autoloadComposer = __DIR__ . '/../vendor/autoload.php';

if (is_file($autoloadComposer)) {
    require_once $autoloadComposer;
} else {
    spl_autoload_register(function (string $clase): void {
        $prefijos = [
            'App\\'    => __DIR__ . '/../app/',
            'Config\\' => __DIR__ . '/../config/',
            'Core\\'   => __DIR__ . '/../core/',
        ];

        foreach ($prefijos as $prefijo => $carpeta) {
            if (str_starts_with($clase, $prefijo)) {
                $archivo = $carpeta . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
                if (is_file($archivo)) {
                    require_once $archivo;
                }
                return;
            }
        }
    });
}

use App\Middleware\CorsMiddleware;
use Config\Database;
use Core\Response;
use Core\Router;

// No mostrar errores de PHP en la respuesta (se devuelven como JSON)
ini_set('display_errors', '0');

// =====================================
// MANEJO GLOBAL DE ERRORES
// Cualquier error no controlado responde JSON sin exponer detalles.
// =====================================

set_exception_handler(function (Throwable $e): void {

    // Reglas de negocio lanzadas por los modelos (stock insuficiente, etc.)
    if ($e instanceof Core\HttpException) {
        Response::error($e->getMessage(), $e->getStatus());
    }

    // Guardar el detalle real en el log de PHP (no se muestra al cliente)
    error_log('[API Marah] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    // Errores de integridad de MySQL (SQLSTATE 23000): duplicados o claves foráneas
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        Response::error(
            'La operación no se puede realizar porque el registro está duplicado o relacionado con otros datos',
            409
        );
    }

    // Restricciones CHECK de MySQL (3819)
    if ($e instanceof PDOException && str_contains($e->getMessage(), '3819')) {
        Response::error('Los datos no cumplen las restricciones de la base de datos', 400);
    }

    Response::error('Error interno del servidor', 500);
});

// ==============================
// CORS
// ==============================

CorsMiddleware::handle();

// =====================================
// CONEXIÓN
// =====================================

try {
    $database = new Database();
    $db = $database->connect();
} catch (Throwable $e) {
    error_log('[API Marah] ' . $e->getMessage());
    Response::error('Error al conectar con la base de datos', 500);
}

// =====================================
// ROUTER Y RUTAS
// =====================================

$router = new Router();

require_once __DIR__ . '/../routes/api.php';

// =====================================
// MÉTODO HTTP Y URI
// =====================================

$method = $_SERVER['REQUEST_METHOD'];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Quitar la carpeta base (funciona en XAMPP o con "php -S")
// Ej: /3657_Basurco_Adrian_API_MVC/public/api/productos -> /api/productos
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}

if ($uri === '' || $uri === false) {
    $uri = '/';
}

// =====================================
// EJECUTAR ROUTER
// =====================================

$router->dispatch($method, $uri);
