<?php

// Todas las APIs responden con la misma estructura:
// { "success": true|false, "message": "...", "data": ... }

namespace Core;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    public static function success(
        mixed $data = null,
        string $message = 'Operación exitosa',
        int $status = 200
    ): never {

        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $status);
    }

    /**
     * @param array $errors Detalle de los campos con error (opcional)
     */
    public static function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): never {

        $respuesta = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $respuesta['errors'] = $errors;
        }

        self::json($respuesta, $status);
    }
}
