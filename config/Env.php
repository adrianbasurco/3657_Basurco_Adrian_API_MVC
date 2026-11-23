<?php

namespace Config;

/**
 * Lee el archivo .env de la raíz del proyecto y carga sus variables.
 *
 * Formato del archivo:
 *   CLAVE=valor
 *   # las líneas con # son comentarios
 *
 * Así los datos sensibles (usuario y contraseña de la BD)
 * no se escriben en el código ni se suben a GitHub.
 */
class Env
{
    private static array $variables = [];
    private static bool $cargado = false;

    public static function cargar(string $ruta): void
    {
        if (self::$cargado) {
            return;
        }

        if (!is_file($ruta)) {
            throw new \RuntimeException(
                'No se encontró el archivo .env. Copia .env.example como .env y completa tus datos.'
            );
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            // Saltar comentarios y líneas sin "="
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);

            $clave = trim($clave);
            $valor = trim($valor);

            // Quitar comillas si el valor está entre comillas
            if (preg_match('/^(["\'])(.*)\1$/', $valor, $m)) {
                $valor = $m[2];
            }

            self::$variables[$clave] = $valor;
        }

        self::$cargado = true;
    }

    public static function get(string $clave, ?string $porDefecto = null): ?string
    {
        return self::$variables[$clave] ?? $porDefecto;
    }
}
