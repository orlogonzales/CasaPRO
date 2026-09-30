<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Excepción HTTP para interrupciones de flujo con códigos de estado específicos (400, 403, 404, 500, 503).
 * Capturada de forma centralizada por el Front Controller para emitir la vista correspondiente.
 */
class ExcepcionHttp extends \RuntimeException
{
    private int $codigoEstado;

    public function __construct(int $codigoEstado, string $mensaje = '', ?\Throwable $anterior = null)
    {
        $this->codigoEstado = $codigoEstado;
        parent::__construct($mensaje, $codigoEstado, $anterior);
    }

    public function obtenerCodigoEstado(): int
    {
        return $this->codigoEstado;
    }
}
