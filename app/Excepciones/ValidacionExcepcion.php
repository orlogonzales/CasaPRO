<?php

declare(strict_types=1);

namespace App\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando los datos de entrada violan las reglas de estructura o tipo del DTO.
 */
class ValidacionExcepcion extends RuntimeException
{
    private array $errores;

    public function __construct(string $mensaje, array $errores = [], int $codigo = 422, ?Throwable $anterior = null)
    {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->errores = $errores;
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }
}
