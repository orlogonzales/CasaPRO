<?php

declare(strict_types=1);

namespace App\DTOs\Contexto;

use App\Excepciones\ValidacionExcepcion;

/**
 * CambiarContextoEmpresaDTO — Validación estricta para el endpoint de conmutación territorial.
 *
 * Microfase: 2D
 */
class CambiarContextoEmpresaDTO
{
    public function __construct(
        public readonly int $empresaId
    ) {}

    public static function desdePeticion(array $datos): self
    {
        // 1. Allowlist estricta: existencia obligatoria del campo
        if (!array_key_exists('empresa_id', $datos)) {
            throw new ValidacionExcepcion(
                'El parámetro empresa_id es obligatorio.',
                ['empresa_id' => 'Debe especificar el identificador de la empresa.']
            );
        }

        $empresaId = $datos['empresa_id'];

        // 2. Validación de tipo entero positivo estricto
        if (!is_int($empresaId) && (!is_string($empresaId) || !ctype_digit(trim($empresaId)))) {
            throw new ValidacionExcepcion(
                'El parámetro empresa_id debe ser un número entero válido.',
                ['empresa_id' => 'Formato de identificador inválido.']
            );
        }

        $idEntero = (int) $empresaId;
        if ($idEntero <= 0) {
            throw new ValidacionExcepcion(
                'El identificador de empresa debe ser mayor que cero.',
                ['empresa_id' => 'Identificador numérico fuera de rango.']
            );
        }

        return new self($idEntero);
    }
}
