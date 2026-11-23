<?php

namespace Core;

/**
 * Validador sencillo de datos de entrada.
 *
 * Uso:
 *   Validator::validar($datos, [
 *       'nombre' => 'requerido|texto|max:60',
 *       'precio' => 'requerido|numerico|mayor:0',
 *   ]);
 *
 * Si hay errores responde 400 con la lista de errores por campo.
 *
 * Reglas disponibles:
 *   requerido   -> el campo debe existir y no estar vacío
 *   texto       -> debe ser texto
 *   entero      -> número entero
 *   numerico    -> número (entero o decimal)
 *   mayor:N     -> número mayor que N
 *   min:N       -> número mayor o igual que N
 *   max:N       -> texto con N caracteres como máximo
 *   email       -> correo electrónico válido
 *   telefono    -> solo dígitos, espacios, + o -, de 6 a 20 caracteres
 *   en:a,b,c    -> el valor debe ser uno de la lista
 */
class Validator
{
    public static function validar(array $datos, array $reglas): void
    {
        $errores = self::errores($datos, $reglas);

        if (!empty($errores)) {
            Response::error('Datos inválidos', 400, $errores);
        }
    }

    public static function errores(array $datos, array $reglas): array
    {
        $errores = [];

        foreach ($reglas as $campo => $reglasCampo) {

            $lista = explode('|', $reglasCampo);
            $valor = $datos[$campo] ?? null;
            $vacio = $valor === null || $valor === '';

            if ($vacio) {
                if (in_array('requerido', $lista, true)) {
                    $errores[$campo] = "El campo {$campo} es obligatorio";
                }
                // Si no es obligatorio y viene vacío, no se valida lo demás
                continue;
            }

            foreach ($lista as $regla) {

                [$nombre, $parametro] = array_pad(explode(':', $regla, 2), 2, null);

                $error = match ($nombre) {
                    'texto' => !is_string($valor)
                        ? "El campo {$campo} debe ser texto" : null,

                    'entero' => filter_var($valor, FILTER_VALIDATE_INT) === false || is_bool($valor)
                        ? "El campo {$campo} debe ser un número entero" : null,

                    'numerico' => !is_numeric($valor)
                        ? "El campo {$campo} debe ser un número" : null,

                    'mayor' => is_numeric($valor) && (float) $valor <= (float) $parametro
                        ? "El campo {$campo} debe ser mayor que {$parametro}" : null,

                    'min' => is_numeric($valor) && (float) $valor < (float) $parametro
                        ? "El campo {$campo} debe ser mayor o igual que {$parametro}" : null,

                    'max' => is_string($valor) && mb_strlen($valor) > (int) $parametro
                        ? "El campo {$campo} admite como máximo {$parametro} caracteres" : null,

                    'email' => filter_var($valor, FILTER_VALIDATE_EMAIL) === false
                        ? "El campo {$campo} debe ser un correo válido" : null,

                    'telefono' => !is_string($valor) && !is_int($valor)
                        || !preg_match('/^[0-9 +\-]{6,20}$/', (string) $valor)
                        ? "El campo {$campo} debe ser un teléfono válido (6 a 20 dígitos)" : null,

                    'en' => !in_array((string) $valor, explode(',', (string) $parametro), true)
                        ? "El campo {$campo} debe ser uno de: " . str_replace(',', ', ', (string) $parametro) : null,

                    default => null,
                };

                if ($error !== null) {
                    $errores[$campo] = $error;
                    break; // Un error por campo es suficiente
                }
            }
        }

        return $errores;
    }
}
