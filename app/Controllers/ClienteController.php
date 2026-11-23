<?php

namespace App\Controllers;

use App\Models\Cliente;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class ClienteController
{
    private Cliente $cliente;

    private const REGLAS = [
        'nombres'   => 'requerido|texto|max:80',
        'apellidos' => 'requerido|texto|max:80',
        'telefono'  => 'requerido|telefono',
        'correo'    => 'email|max:120',
        'direccion' => 'texto|max:180',
    ];

    public function __construct(PDO $db)
    {
        $this->cliente = new Cliente($db);
    }

    // GET /api/clientes
    public function index(): void
    {
        Response::success(
            $this->cliente->obtenerTodos(),
            'Clientes obtenidos correctamente'
        );
    }

    // GET /api/clientes/{id}
    public function show(int $id): void
    {
        Response::success($this->buscarOFallar($id), 'Cliente encontrado');
    }

    // POST /api/clientes
    public function store(): void
    {
        $datos = Request::body();

        Validator::validar($datos, self::REGLAS);
        $this->validarUnicos($datos);

        $id = $this->cliente->crear($datos);

        Response::success(
            $this->cliente->obtenerPorId($id),
            'Cliente creado correctamente',
            201
        );
    }

    // PUT /api/clientes/{id}
    public function update(int $id): void
    {
        $this->buscarOFallar($id);

        $datos = Request::body();

        Validator::validar($datos, self::REGLAS);
        $this->validarUnicos($datos, $id);

        $this->cliente->actualizar($id, $datos);

        Response::success(
            $this->cliente->obtenerPorId($id),
            'Cliente actualizado correctamente'
        );
    }

    // DELETE /api/clientes/{id}
    public function destroy(int $id): void
    {
        $cliente = $this->buscarOFallar($id);

        // Clave foránea pedidos.id_cliente con ON DELETE RESTRICT
        if ((int) $cliente['total_pedidos'] > 0) {
            Response::error(
                "No se puede eliminar el cliente porque tiene {$cliente['total_pedidos']} pedido(s) registrado(s)",
                409
            );
        }

        $this->cliente->eliminar($id);

        Response::success(['id_cliente' => $id], 'Cliente eliminado correctamente');
    }

    /**
     * telefono y correo son UNIQUE: se valida antes de guardar
     * para devolver un mensaje claro (409) en vez de un error SQL.
     */
    private function validarUnicos(array $datos, ?int $excluirId = null): void
    {
        $errores = [];

        if ($this->cliente->existeValor('telefono', (string) $datos['telefono'], $excluirId)) {
            $errores['telefono'] = "El teléfono {$datos['telefono']} ya está registrado";
        }

        if (!empty($datos['correo']) && $this->cliente->existeValor('correo', $datos['correo'], $excluirId)) {
            $errores['correo'] = "El correo {$datos['correo']} ya está registrado";
        }

        if (!empty($errores)) {
            Response::error('Ya existe un cliente con esos datos', 409, $errores);
        }
    }

    private function buscarOFallar(int $id): array
    {
        $cliente = $this->cliente->obtenerPorId($id);

        if (!$cliente) {
            Response::error("Cliente con id {$id} no encontrado", 404);
        }

        return $cliente;
    }
}
