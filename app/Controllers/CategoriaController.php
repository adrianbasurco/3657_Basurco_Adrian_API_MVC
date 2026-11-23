<?php

namespace App\Controllers;

use App\Models\Categoria;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;

class CategoriaController
{
    private Categoria $categoria;

    private const REGLAS = [
        'nombre'      => 'requerido|texto|max:60',
        'descripcion' => 'texto|max:255',
    ];

    public function __construct(PDO $db)
    {
        $this->categoria = new Categoria($db);
    }

    // GET /api/categorias
    public function index(): void
    {
        Response::success(
            $this->categoria->obtenerTodos(),
            'Categorías obtenidas correctamente'
        );
    }

    // GET /api/categorias/{id}
    public function show(int $id): void
    {
        $categoria = $this->buscarOFallar($id);

        Response::success($categoria, 'Categoría encontrada');
    }

    // POST /api/categorias
    public function store(): void
    {
        $datos = Request::body();

        Validator::validar($datos, self::REGLAS);

        if ($this->categoria->existeNombre($datos['nombre'])) {
            Response::error('Ya existe una categoría con el nombre "' . $datos['nombre'] . '"', 409);
        }

        $id = $this->categoria->crear($datos);

        Response::success(
            $this->categoria->obtenerPorId($id),
            'Categoría creada correctamente',
            201
        );
    }

    // PUT /api/categorias/{id}
    public function update(int $id): void
    {
        $this->buscarOFallar($id);

        $datos = Request::body();

        Validator::validar($datos, self::REGLAS);

        if ($this->categoria->existeNombre($datos['nombre'], $id)) {
            Response::error('Ya existe otra categoría con el nombre "' . $datos['nombre'] . '"', 409);
        }

        $this->categoria->actualizar($id, $datos);

        Response::success(
            $this->categoria->obtenerPorId($id),
            'Categoría actualizada correctamente'
        );
    }

    // DELETE /api/categorias/{id}  (borrado lógico)
    public function destroy(int $id): void
    {
        $this->buscarOFallar($id);

        $productos = $this->categoria->contarProductosActivos($id);

        if ($productos > 0) {
            Response::error(
                "No se puede eliminar la categoría porque tiene {$productos} producto(s) activo(s)",
                409
            );
        }

        $this->categoria->eliminar($id);

        Response::success(['id_categoria' => $id], 'Categoría eliminada correctamente');
    }

    private function buscarOFallar(int $id): array
    {
        $categoria = $this->categoria->obtenerPorId($id);

        if (!$categoria) {
            Response::error("Categoría con id {$id} no encontrada", 404);
        }

        return $categoria;
    }
}
