<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Usuario;

/**
 * CambiarEstadoUsuarioDTO — Encapsula y valida la transición de estado administrativo de un usuario.
 */
class CambiarEstadoUsuarioDTO
{
    private const CAMPOS_PERMITIDOS = [
        'estado',
        'nuevo_estado',
        'motivo',
        '_csrf_token',
        'csrf_token'
    ];

    public string $estado;
    public string $nuevoEstado;
    public string $motivo;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->estado = $instancia->estado;
            $this->nuevoEstado = $instancia->nuevoEstado;
            $this->motivo = $instancia->motivo;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el cambio de estado de usuario: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $estado = strtoupper(trim((string) ($datos['nuevo_estado'] ?? $datos['estado'] ?? '')));
        if ($estado === '') {
            $errores['nuevo_estado'][] = 'El estado administrativo es obligatorio.';
        } elseif (!in_array($estado, [Usuario::ESTADO_ACTIVO, Usuario::ESTADO_INACTIVO], true)) {
            $errores['nuevo_estado'][] = "El estado administrativo debe ser 'ACTIVO' o 'INACTIVO'.";
        }

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $errores['motivo'][] = 'El motivo administrativo del cambio de estado es obligatorio.';
        } elseif (mb_strlen($motivo) < 5) {
            $errores['motivo'][] = 'El motivo administrativo debe contener al menos 5 caracteres explicativos.';
        } elseif (mb_strlen($motivo) > 500) {
            $errores['motivo'][] = 'El motivo administrativo no puede exceder 500 caracteres.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de cambio de estado incompletos o inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->estado = $estado;
        $dto->nuevoEstado = $estado;
        $dto->motivo = $motivo;

        return $dto;
    }
}
