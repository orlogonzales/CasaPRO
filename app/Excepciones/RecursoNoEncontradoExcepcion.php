<?php

declare(strict_types=1);

namespace App\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando una entidad solicitada por identificador no existe en la base de datos (HTTP 404).
 */
class RecursoNoEncontradoExcepcion extends RuntimeException
{
    public function __construct(string $mensaje = 'El recurso solicitado no fue encontrado.', int $codigo = 404, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
    }
}
