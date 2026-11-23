<?php

namespace App\Controllers;

use App\Models\Pedido;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class PedidoController
{
    private Pedido $pedido;

    public function __construct(PDO $db)
    {
        $this->pedido = new Pedido($db);
    }

    // GET /api/pedidos   (opcional: ?estado=Pendiente)
    public function index(): void
    {
        $estado = $_GET['estado'] ?? null;

        if ($estado !== null && !in_array($estado, Pedido::ESTADOS, true)) {
            Response::error('El parámetro estado debe ser uno de: ' . implode(', ', Pedido::ESTADOS), 400);
        }

        Response::success(
            $this->pedido->obtenerTodos($estado),
            'Pedidos obtenidos correctamente'
        );
    }

    // GET /api/pedidos/{id}   (incluye sus detalles)
    public function show(int $id): void
    {
        Response::success($this->buscarOFallar($id), 'Pedido encontrado');
    }

    // POST /api/pedidos
    public function store(): void
    {
        $datos = Request::body();

        Validator::validar($datos, [
            'id_cliente'        => 'requerido|entero|mayor:0',
            'direccion_entrega' => 'texto|max:180',
            'observaciones'     => 'texto|max:255',
        ]);

        // Clave foránea: el cliente debe existir
        $cliente = $this->pedido->obtenerCliente((int) $datos['id_cliente']);

        if (!$cliente) {
            Response::error('Datos inválidos', 400, [
                'id_cliente' => "El cliente {$datos['id_cliente']} no existe"
            ]);
        }

        // Si no se envía dirección, se usa la dirección registrada del cliente
        if (empty($datos['direccion_entrega'])) {
            if (empty($cliente['direccion'])) {
                Response::error('Datos inválidos', 400, [
                    'direccion_entrega' => 'El campo direccion_entrega es obligatorio (el cliente no tiene dirección registrada)'
                ]);
            }
            $datos['direccion_entrega'] = $cliente['direccion'];
        }

        $detalles = $this->validarDetalles($datos['detalles'] ?? []);

        $id = $this->pedido->crear($datos, $detalles);

        Response::success(
            $this->pedido->obtenerPorId($id),
            'Pedido creado correctamente',
            201
        );
    }

    // PUT /api/pedidos/{id}
    public function update(int $id): void
    {
        $this->buscarOFallar($id);

        $datos = Request::body();

        Validator::validar($datos, [
            'direccion_entrega' => 'requerido|texto|max:180',
            'estado'            => 'requerido|en:' . implode(',', Pedido::ESTADOS),
            'observaciones'     => 'texto|max:255',
        ]);

        $this->pedido->actualizar($id, $datos);

        Response::success(
            $this->pedido->obtenerPorId($id),
            'Pedido actualizado correctamente'
        );
    }

    // DELETE /api/pedidos/{id}
    public function destroy(int $id): void
    {
        $this->buscarOFallar($id);

        $this->pedido->eliminar($id);

        Response::success(['id_pedido' => $id], 'Pedido eliminado correctamente');
    }

    /**
     * Valida la lista opcional de productos al crear un pedido:
     * "detalles": [ { "id_producto": 1, "cantidad": 2 } ]
     */
    private function validarDetalles(mixed $detalles): array
    {
        if (!is_array($detalles) || (!empty($detalles) && !array_is_list($detalles))) {
            Response::error('Datos inválidos', 400, [
                'detalles' => 'El campo detalles debe ser una lista de productos'
            ]);
        }

        $errores = [];
        $productos = [];

        foreach ($detalles as $i => $item) {

            if (!is_array($item)) {
                $errores["detalles[{$i}]"] = 'Cada detalle debe tener id_producto y cantidad';
                continue;
            }

            foreach (Validator::errores($item, [
                'id_producto' => 'requerido|entero|mayor:0',
                'cantidad'    => 'requerido|entero|mayor:0',
            ]) as $campo => $mensaje) {
                $errores["detalles[{$i}].{$campo}"] = $mensaje;
            }

            if (isset($item['id_producto']) && in_array((int) $item['id_producto'], $productos, true)) {
                $errores["detalles[{$i}].id_producto"] = "El producto {$item['id_producto']} está repetido; suma las cantidades en un solo detalle";
            }

            $productos[] = (int) ($item['id_producto'] ?? 0);
        }

        if (!empty($errores)) {
            Response::error('Datos inválidos', 400, $errores);
        }

        return $detalles;
    }

    private function buscarOFallar(int $id): array
    {
        $pedido = $this->pedido->obtenerPorId($id);

        if (!$pedido) {
            Response::error("Pedido con id {$id} no encontrado", 404);
        }

        return $pedido;
    }
}
