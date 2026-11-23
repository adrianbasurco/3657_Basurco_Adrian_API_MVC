<?php

// ==========================================================
// RUTAS DE LA API - MARAH
// Un módulo por cada tabla de marah_db:
// categorias, productos, clientes, pedidos, detalle_pedido, contactos
// ==========================================================

use App\Controllers\CategoriaController;
use App\Controllers\ProductoController;
use App\Controllers\ClienteController;
use App\Controllers\PedidoController;
use App\Controllers\DetallePedidoController;
use App\Controllers\ContactoController;
use Core\Response;

// INSTANCIAR CONTROLLERS

$categoriaController = new CategoriaController($db);
$productoController  = new ProductoController($db);
$clienteController   = new ClienteController($db);
$pedidoController    = new PedidoController($db);
$detalleController   = new DetallePedidoController($db);
$contactoController  = new ContactoController($db);

// INICIO: comprobar que la API está funcionando

$router->get('/api', function () {
    Response::success([
        'api'     => 'Marah - API REST MVC',
        'modulos' => [
            '/api/categorias',
            '/api/productos',
            '/api/clientes',
            '/api/pedidos',
            '/api/detalles',
            '/api/contactos',
        ],
    ], 'API funcionando correctamente');
});

// 1. CATEGORÍAS


$router->get('/api/categorias',         [$categoriaController, 'index']);   // Listar
$router->get('/api/categorias/{id}',    [$categoriaController, 'show']);    // Obtener por ID
$router->post('/api/categorias',        [$categoriaController, 'store']);   // Crear
$router->put('/api/categorias/{id}',    [$categoriaController, 'update']);  // Actualizar
$router->delete('/api/categorias/{id}', [$categoriaController, 'destroy']); // Eliminar (lógico)

// 2. PRODUCTOS

$router->get('/api/productos',          [$productoController, 'index']);
$router->get('/api/productos/{id}',     [$productoController, 'show']);
$router->post('/api/productos',         [$productoController, 'store']);
$router->put('/api/productos/{id}',     [$productoController, 'update']);
$router->delete('/api/productos/{id}',  [$productoController, 'destroy']);

// 3. CLIENTES

$router->get('/api/clientes',           [$clienteController, 'index']);
$router->get('/api/clientes/{id}',      [$clienteController, 'show']);
$router->post('/api/clientes',          [$clienteController, 'store']);
$router->put('/api/clientes/{id}',      [$clienteController, 'update']);
$router->delete('/api/clientes/{id}',   [$clienteController, 'destroy']);

// 4. PEDIDOS

$router->get('/api/pedidos',            [$pedidoController, 'index']);
$router->get('/api/pedidos/{id}',       [$pedidoController, 'show']);
$router->post('/api/pedidos',           [$pedidoController, 'store']);
$router->put('/api/pedidos/{id}',       [$pedidoController, 'update']);
$router->delete('/api/pedidos/{id}',    [$pedidoController, 'destroy']);

// Detalles de un pedido específico
$router->get('/api/pedidos/{id}/detalles', [$detalleController, 'porPedido']);

// 5. DETALLE DE PEDIDO

$router->get('/api/detalles',           [$detalleController, 'index']);
$router->get('/api/detalles/{id}',      [$detalleController, 'show']);
$router->post('/api/detalles',          [$detalleController, 'store']);
$router->put('/api/detalles/{id}',      [$detalleController, 'update']);
$router->delete('/api/detalles/{id}',   [$detalleController, 'destroy']);

// 6. CONTACTOS

$router->get('/api/contactos',          [$contactoController, 'index']);
$router->get('/api/contactos/{id}',     [$contactoController, 'show']);
$router->post('/api/contactos',         [$contactoController, 'store']);
$router->put('/api/contactos/{id}',     [$contactoController, 'update']);
$router->delete('/api/contactos/{id}',  [$contactoController, 'destroy']);
