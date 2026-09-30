<?php

declare(strict_types=1);

namespace App\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando una operación viola una regla del modelo de negocio o integridad semántica.
 */
class ReglaNegocioExcepcion extends RuntimeException
{
    private array $errores;

    public function __construct(string $mensaje, int $codigo = 422, array $errores = [], ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->errores = $errores;
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
