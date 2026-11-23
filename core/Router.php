<?php

namespace Core;

/**
 * Router basado en el proyecto del docente.
 * Registra rutas por método HTTP y ejecuta el controlador correspondiente.
 * Las rutas con {id} solo aceptan números enteros.
 */
class Router
{
    private array $routes = [];

    // =====================================================
    // MÉTODOS HTTP
    // =====================================================

    public function get(string $route, callable $action): void
    {
        $this->add('GET', $route, $action);
    }

    public function post(string $route, callable $action): void
    {
        $this->add('POST', $route, $action);
    }

    public function put(string $route, callable $action): void
    {
        $this->add('PUT', $route, $action);
    }

    public function patch(string $route, callable $action): void
    {
        $this->add('PATCH', $route, $action);
    }

    public function delete(string $route, callable $action): void
    {
        $this->add('DELETE', $route, $action);
    }

    // =====================================================
    // REGISTRAR RUTA
    // =====================================================

    private function add(string $method, string $route, callable $action): void
    {
        // /api/productos/ pasa a /api/productos
        if ($route !== '/') {
            $route = rtrim($route, '/');
        }

        $this->routes[] = [
            'method' => strtoupper($method),
            'route'  => $route,
            'action' => $action
        ];
    }

    // =====================================================
    // PROCESAR PETICIÓN
    // =====================================================

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        // Solo el PATH: /api/productos?x=1 -> /api/productos
        $uri = rawurldecode(parse_url($uri, PHP_URL_PATH) ?? '/');

        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        $rutaExiste = false;

        foreach ($this->routes as $route) {

            // /api/productos/{id} pasa a la expresión #^/api/productos/(\d+)$#
            $pattern = preg_quote($route['route'], '#');
            $pattern = str_replace('\{id\}', '(\d+)', $pattern);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            // La URL existe, pero puede ser con otro método HTTP
            $rutaExiste = true;

            if ($route['method'] !== $method) {
                continue;
            }

            // Quitar la coincidencia completa y convertir los parámetros a entero
            array_shift($matches);
            $params = array_map('intval', $matches);

            // Ejemplo: ProductoController->show(5)
            call_user_func_array($route['action'], $params);

            return;
        }

        if ($rutaExiste) {
            Response::error("Método {$method} no permitido para esta ruta", 405);
        }

        Response::error('Ruta no encontrada', 404);
    }
}
