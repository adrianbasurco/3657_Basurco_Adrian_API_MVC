<?php

namespace App\Controllers;

use App\Models\Contacto;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class ContactoController
{
    private Contacto $contacto;

    public function __construct(PDO $db)
    {
        $this->contacto = new Contacto($db);
    }

    private function reglas(): array
    {
        return [
            'nombre'   => 'requerido|texto|max:100',
            'correo'   => 'requerido|email|max:120',
            'telefono' => 'telefono',
            'asunto'   => 'requerido|texto|max:120',
            'mensaje'  => 'requerido|texto|max:2000',
            'estado'   => 'en:' . implode(',', Contacto::ESTADOS),
        ];
    }

    // GET /api/contactos   (opcional: ?estado=Nuevo)
    public function index(): void
    {
        $estado = $_GET['estado'] ?? null;

        if ($estado !== null && !in_array($estado, Contacto::ESTADOS, true)) {
            Response::error('El parámetro estado debe ser uno de: ' . implode(', ', Contacto::ESTADOS), 400);
        }

        Response::success(
            $this->contacto->obtenerTodos($estado),
            'Mensajes de contacto obtenidos correctamente'
        );
    }

    // GET /api/contactos/{id}
    public function show(int $id): void
    {
        Response::success($this->buscarOFallar($id), 'Mensaje de contacto encontrado');
    }

    // POST /api/contactos
    public function store(): void
    {
        $datos = Request::body();

        Validator::validar($datos, $this->reglas());

        $id = $this->contacto->crear($datos);

        Response::success(
            $this->contacto->obtenerPorId($id),
            'Mensaje de contacto registrado correctamente',
            201
        );
    }

    // PUT /api/contactos/{id}
    public function update(int $id): void
    {
        $actual = $this->buscarOFallar($id);

        $datos = Request::body();

        Validator::validar($datos, $this->reglas());

        // Si no se envía estado, se mantiene el actual
        $datos['estado'] = $datos['estado'] ?? $actual['estado'];

        $this->contacto->actualizar($id, $datos);

        Response::success(
            $this->contacto->obtenerPorId($id),
            'Mensaje de contacto actualizado correctamente'
        );
    }

    // DELETE /api/contactos/{id}
    public function destroy(int $id): void
    {
        $this->buscarOFallar($id);

        $this->contacto->eliminar($id);

        Response::success(['id_contacto' => $id], 'Mensaje de contacto eliminado correctamente');
    }

    private function buscarOFallar(int $id): array
    {
        $contacto = $this->contacto->obtenerPorId($id);

        if (!$contacto) {
            Response::error("Mensaje de contacto con id {$id} no encontrado", 404);
        }

        return $contacto;
    }
}
