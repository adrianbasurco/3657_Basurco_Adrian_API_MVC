<?php

namespace Core;

class Request
{
    /**
     * Devuelve el cuerpo JSON de la petición como arreglo.
     * Si el JSON está mal escrito responde 400.
     */
    public static function body(): array
    {
        $input = file_get_contents('php://input');

        if ($input === false || trim($input) === '') {
            return [];
        }

        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Response::error(
                'El cuerpo de la petición no es un JSON válido',
                400
            );
        }

        // Quitar espacios al inicio y final de los textos
        array_walk($data, function (&$valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
            }
        });

        return $data;
    }
}
