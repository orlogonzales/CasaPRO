<?php

declare(strict_types=1);

namespace App\Excepciones;

use RuntimeException;
use Throwable;

/**
 * Excepción lanzada cuando los parámetros de consulta o de ruta son sintácticamente incorrectos (HTTP 400).
 */
class PeticionIncorrectaExcepcion extends RuntimeException
{
    private ?array $errores;

    public function __construct(
        string $mensaje = 'La solicitud contiene parámetros inválidos o malformados.',
        int $codigo = 400,
        ?array $errores = null,
        ?Throwable $anterior = null
    ) {
        parent::__construct($mensaje, $codigo, $anterior);
        $this->errores = $errores;
    }

    public function obtenerErrores(): ?array
    {
        return $this->errores;
    }
}
