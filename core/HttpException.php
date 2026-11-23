<?php

namespace Core;

use Exception;

/**
 * Excepción para reglas de negocio (por ejemplo: stock insuficiente).
 * Se lanza desde los modelos dentro de una transacción para poder
 * hacer ROLLBACK, y el manejador global la responde con su código HTTP.
 */
class HttpException extends Exception
{
    public function __construct(string $message, private int $status = 400)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
