<?php

namespace App\Controllers;

use App\Models\Producto;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class ProductoController
{
    private Producto $producto;

    private const REGLAS = [
        'id_categoria' => 'requerido|entero|mayor:0',
        'nombre'       => 'requerido|texto|max:100',
        'descripcion'  => 'texto|max:255',
        'presentacion' => 'requerido|texto|max:50',
        'precio'       => 'requerido|numerico|mayor:0',
        'stock'        => 'entero|min:0',
    ];

    public function __construct(PDO $db)
    {
        $this->producto = new Producto($db);
    }

    // GET /api/productos   (opcional: ?categoria=1)
    public function index(): void
    {
        $idCategoria = null;

        if (isset($_GET['categoria'])) {
            $idCategoria = filter_var($_GET['categoria'], FILTER_VALIDATE_INT);

            if ($idCategoria === false || $idCategoria <= 0) {
                Response::error('El parámetro categoria debe ser un número entero mayor que 0', 400);
            }
        }

        Response::success(
            $this->producto->obtenerTodos($idCategoria),
            'Productos obtenidos correctamente'
        );
    }

    // GET /api/productos/{id}
    public function show(int $id): void
    {
        Response::success($this->buscarOFallar($id), 'Producto encontrado');
    }

    // POST /api/productos
    public function store(): void
    {
        $datos = $this->validar(Request::body());

        $id = $this->producto->crear($datos);

        Response::success(
            $this->producto->obtenerPorId($id),
            'Producto creado correctamente',
            201
        );
    }

    // PUT /api/productos/{id}
    public function update(int $id): void
    {
        $this->buscarOFallar($id);

        $datos = $this->validar(Request::body());

        $this->producto->actualizar($id, $datos);

        Response::success(
            $this->producto->obtenerPorId($id),
            'Producto actualizado correctamente'
        );
    }

    // DELETE /api/productos/{id}  (borrado lógico)
    public function destroy(int $id): void
    {
        $this->buscarOFallar($id);

        $pedidos = $this->producto->contarPedidosAbiertos($id);

        if ($pedidos > 0) {
            Response::error(
                "No se puede eliminar el producto porque está en {$pedidos} pedido(s) en curso",
                409
            );
        }

        $this->producto->eliminar($id);

        Response::success(['id_producto' => $id], 'Producto eliminado correctamente');
    }

    private function validar(array $datos): array
    {
        Validator::validar($datos, self::REGLAS);

        // Clave foránea: la categoría debe existir y estar activa
        if (!$this->producto->categoriaActivaExiste((int) $datos['id_categoria'])) {
            Response::error(
                'Datos inválidos',
                400,
                ['id_categoria' => "La categoría {$datos['id_categoria']} no existe o está inactiva"]
            );
        }

        return $datos;
    }

    private function buscarOFallar(int $id): array
    {
        $producto = $this->producto->obtenerPorId($id);

        if (!$producto) {
            Response::error("Producto con id {$id} no encontrado", 404);
        }

        return $producto;
    }
}
