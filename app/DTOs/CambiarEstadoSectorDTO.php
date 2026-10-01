<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Sector;

/**
 * CambiarEstadoSectorDTO — Valida los datos para conmutar el estado de un Sector.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class CambiarEstadoSectorDTO
{
    private const CAMPOS_PERMITIDOS = [
        'estado',
        'motivo',
        'csrf_token',
        '_csrf_token'
    ];

    public string $estado;
    public string $motivo;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->estado = $instancia->estado;
            $this->motivo = $instancia->motivo;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $errores = [];

        // 1. Blindaje Anti-Polución
        $camposDesconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($camposDesconocidos)) {
            throw new ValidacionExcepcion(
                'Se detectaron campos no permitidos en el formulario: ' . implode(', ', $camposDesconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $dto = new self();

        // 2. estado
        $estado = strtoupper(trim((string) ($datos['estado'] ?? '')));
        if ($estado === '') {
            $errores['estado'] = 'El nuevo estado del sector es obligatorio.';
        } elseif (!in_array($estado, Sector::ESTADOS_PERMITIDOS, true)) {
            $errores['estado'] = "El estado '{$estado}' no es válido para un sector.";
        } else {
            $dto->estado = $estado;
        }

        // 3. motivo
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $errores['motivo'] = 'Debe indicar el motivo del cambio de estado.';
        } elseif (strlen($motivo) < 3 || strlen($motivo) > 255) {
            $errores['motivo'] = 'El motivo debe tener entre 3 y 255 caracteres.';
        } else {
            $dto->motivo = $motivo;
        }

        if (!empty($errores)) {
            $primerError = is_array(reset($errores)) ? reset($errores)[0] : reset($errores);
            throw new ValidacionExcepcion((string) $primerError, $errores, 422);
        }

        return $dto;
    }
}
