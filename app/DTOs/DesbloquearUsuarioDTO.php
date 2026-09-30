<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * DesbloquearUsuarioDTO — Encapsula y valida la solicitud de levantamiento de bloqueo defensivo.
 */
class DesbloquearUsuarioDTO
{
    private const CAMPOS_PERMITIDOS = [
        'motivo',
        '_csrf_token',
        'csrf_token'
    ];

    public string $motivo;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->motivo = $instancia->motivo;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el desbloqueo de usuario: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $errores['motivo'][] = 'El motivo del desbloqueo administrativo es obligatorio.';
        } elseif (mb_strlen($motivo) < 5) {
            $errores['motivo'][] = 'El motivo del desbloqueo debe contener al menos 5 caracteres explicativos.';
        } elseif (mb_strlen($motivo) > 500) {
            $errores['motivo'][] = 'El motivo del desbloqueo no puede exceder 500 caracteres.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de desbloqueo incompletos o inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->motivo = $motivo;

        return $dto;
    }
}
