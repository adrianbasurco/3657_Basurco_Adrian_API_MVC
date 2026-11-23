<?php

namespace App\Controllers;

use App\Models\DetallePedido;
use App\Models\Pedido;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class DetallePedidoController
{
    private DetallePedido $detalle;
    private Pedido $pedido;

    public function __construct(PDO $db)
    {
        $this->detalle = new DetallePedido($db);
        $this->pedido = new Pedido($db);
    }

    // GET /api/detalles
    public function index(): void
    {
        Response::success(
            $this->detalle->obtenerTodos(),
            'Detalles de pedidos obtenidos correctamente'
        );
    }

    // GET /api/detalles/{id}
    public function show(int $id): void
    {
        Response::success($this->buscarOFallar($id), 'Detalle encontrado');
    }

    // GET /api/pedidos/{id}/detalles
    public function porPedido(int $idPedido): void
    {
        if (!$this->pedido->obtenerPorId($idPedido)) {
            Response::error("Pedido con id {$idPedido} no encontrado", 404);
        }

        Response::success(
            $this->detalle->obtenerPorPedido($idPedido),
            "Detalles del pedido {$idPedido} obtenidos correctamente"
        );
    }

    // POST /api/detalles
    public function store(): void
    {
        $datos = Request::body();

        Validator::validar($datos, [
            'id_pedido'   => 'requerido|entero|mayor:0',
            'id_producto' => 'requerido|entero|mayor:0',
            'cantidad'    => 'requerido|entero|mayor:0',
        ]);

        // El modelo valida pedido, producto, stock y duplicados dentro de la transacción
        $id = $this->detalle->crear(
            (int) $datos['id_pedido'],
            (int) $datos['id_producto'],
            (int) $datos['cantidad']
        );

        Response::success(
            $this->detalle->obtenerPorId($id),
            'Producto agregado al pedido correctamente',
            201
        );
    }

    // PUT /api/detalles/{id}   (solo se cambia la cantidad)
    public function update(int $id): void
    {
        $this->buscarOFallar($id);

        $datos = Request::body();

        Validator::validar($datos, [
            'cantidad' => 'requerido|entero|mayor:0',
        ]);

        $this->detalle->actualizarCantidad($id, (int) $datos['cantidad']);

        Response::success(
            $this->detalle->obtenerPorId($id),
            'Cantidad actualizada correctamente'
        );
    }

    // DELETE /api/detalles/{id}
    public function destroy(int $id): void
    {
        $this->buscarOFallar($id);

        $this->detalle->eliminar($id);

        Response::success(['id_detalle' => $id], 'Producto quitado del pedido correctamente');
    }

    private function buscarOFallar(int $id): array
    {
        $detalle = $this->detalle->obtenerPorId($id);

        if (!$detalle) {
            Response::error("Detalle con id {$id} no encontrado", 404);
        }

        return $detalle;
    }
}
