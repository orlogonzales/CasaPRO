<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Proyecto;

/**
 * CambiarEstadoProyectoDTO — Valida la conmutación de estado del ciclo de vida de un Proyecto.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class CambiarEstadoProyectoDTO
{
    private const CAMPOS_PERMITIDOS = [
        'estado',
        'motivo',
        'csrf_token',
        '_csrf_token'
    ];

    public string $estado;
    public ?string $motivo = null;

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
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en cambio de estado: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados detectados.']],
                422
            );
        }

        $estado = strtoupper(trim((string) ($datos['estado'] ?? '')));
        $permitidos = [
            Proyecto::ESTADO_PLANIFICACION,
            Proyecto::ESTADO_EN_VENTA,
            Proyecto::ESTADO_CONSOLIDADO,
            Proyecto::ESTADO_CERRADO,
            Proyecto::ESTADO_INACTIVO
        ];

        if (!in_array($estado, $permitidos, true)) {
            throw new ValidacionExcepcion(
                'Estado no permitido para Proyecto.',
                ['estado' => ['El estado debe ser: PLANIFICACION, EN_VENTA, CONSOLIDADO, CERRADO o INACTIVO.']],
                422
            );
        }

        $dto = new self();
        $dto->estado = $estado;
        $dto->motivo = isset($datos['motivo']) && trim((string) $datos['motivo']) !== '' ? trim((string) $datos['motivo']) : null;

        return $dto;
    }
}
