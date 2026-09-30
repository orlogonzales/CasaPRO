<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Empresa;

/**
 * CambiarEstadoEmpresaDTO — Encapsula y valida el cambio de estado de una empresa (ACTIVO / INACTIVO).
 */
class CambiarEstadoEmpresaDTO
{
    private const CAMPOS_PERMITIDOS = [
        'estado',
        'csrf_token',
        '_csrf_token'
    ];

    public string $estado;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->estado = $instancia->estado;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el cambio de estado de empresa: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $estado = strtoupper(trim((string) ($datos['estado'] ?? '')));
        if (!in_array($estado, [Empresa::ESTADO_ACTIVO, Empresa::ESTADO_INACTIVO], true)) {
            $errores['estado'][] = 'El estado debe ser ACTIVO o INACTIVO.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Estado de empresa inválido.', $errores, 422);
        }

        $dto = new self();
        $dto->estado = $estado;

        return $dto;
    }
}
