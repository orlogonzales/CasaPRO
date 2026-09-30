<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Servicios\PoliticaContrasenaServicio;

/**
 * ResetearPasswordDTO — Encapsula y valida el reseteo administrativo de contraseña de un usuario.
 */
class ResetearPasswordDTO
{
    private const CAMPOS_PERMITIDOS = [
        'password_temporal',
        'motivo',
        '_csrf_token',
        'csrf_token'
    ];

    public string $passwordTemporal;
    public string $motivo;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->passwordTemporal = $instancia->passwordTemporal;
            $this->motivo = $instancia->motivo;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el reseteo de contraseña: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $passwordTemporal = (string) ($datos['password_temporal'] ?? '');
        if ($passwordTemporal === '') {
            $errores['password_temporal'][] = 'La nueva contraseña temporal es obligatoria.';
        } else {
            $errs = PoliticaContrasenaServicio::obtenerErrores($passwordTemporal);
            if (!empty($errs)) {
                $errores['password_temporal'] = $errs;
            }
        }

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $errores['motivo'][] = 'El motivo administrativo del reseteo es obligatorio.';
        } elseif (mb_strlen($motivo) < 5) {
            $errores['motivo'][] = 'El motivo administrativo debe contener al menos 5 caracteres explicativos.';
        } elseif (mb_strlen($motivo) > 500) {
            $errores['motivo'][] = 'El motivo administrativo no puede exceder 500 caracteres.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de reseteo de contraseña incompletos o inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->passwordTemporal = $passwordTemporal;
        $dto->motivo = $motivo;

        return $dto;
    }
}
