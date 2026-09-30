<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * DTO para la transición especializada de estado de Persona (ACTIVO <-> INACTIVO).
 */
class CambiarEstadoPersonaDTO
{
    private const CAMPOS_PERMITIDOS = [
        'estado',
        'motivo'
    ];

    public string $estado;
    public string $motivo;

    public static function desdeArray(array $datos): self
    {
        CrearPersonaDTO::validarCamposPermitidos($datos, self::CAMPOS_PERMITIDOS, 'CambiarEstadoPersona');

        $errores = [];

        if (empty($datos['estado']) || !is_string($datos['estado'])) {
            $errores['estado'][] = 'El campo estado es obligatorio.';
        } else {
            $estadoUpper = strtoupper(trim($datos['estado']));
            if (!in_array($estadoUpper, ['ACTIVO', 'INACTIVO'], true)) {
                $errores['estado'][] = 'El estado solo puede ser ACTIVO o INACTIVO.';
            }
        }

        if (empty($datos['motivo']) || !is_string($datos['motivo']) || trim($datos['motivo']) === '') {
            $errores['motivo'][] = 'El motivo del cambio de estado es obligatorio para fines de auditoría.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación en cambio de estado.', $errores, 422);
        }

        $dto = new self();
        $dto->estado = strtoupper(trim((string) $datos['estado']));
        $dto->motivo = trim((string) $datos['motivo']);

        return $dto;
    }
}
