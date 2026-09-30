<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * ActualizarEmpresaDTO — Encapsula y valida la actualización de una empresa.
 * 
 * Inmutabilidad: El persona_id y el código corporativo son inmutables tras su creación.
 * Solo se permite modificar la identidad operativa compacta (nombre_corto).
 */
class ActualizarEmpresaDTO
{
    private const CAMPOS_PERMITIDOS = [
        'nombre_corto',
        'csrf_token',
        '_csrf_token'
    ];

    public string $nombreCorto;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->nombreCorto = $instancia->nombreCorto;
        }
    }

    public static function desdeArray(array $datos): self
    {
        // Detectar si se intentó modificar atributos inmutables
        if (isset($datos['persona_id']) || isset($datos['codigo'])) {
            throw new ValidacionExcepcion(
                'Atributos inmutables detectados: El persona_id y el código de empresa no pueden modificarse tras su creación.',
                ['inmutables' => ['persona_id y codigo son inmutables.']],
                422
            );
        }

        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la actualización de empresa: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $nombreCorto = trim((string) ($datos['nombre_corto'] ?? ''));
        if ($nombreCorto === '') {
            $errores['nombre_corto'][] = 'El nombre corto de empresa es obligatorio.';
        } elseif (strlen($nombreCorto) < 2 || strlen($nombreCorto) > 64) {
            $errores['nombre_corto'][] = 'El nombre corto debe tener entre 2 y 64 caracteres.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de actualización de empresa inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->nombreCorto = $nombreCorto;

        return $dto;
    }
}
