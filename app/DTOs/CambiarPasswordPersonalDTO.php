<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Servicios\PoliticaContrasenaServicio;

/**
 * CambiarPasswordPersonalDTO — Encapsula y valida el cambio de contraseña propio del usuario autenticado.
 */
class CambiarPasswordPersonalDTO
{
    private const CAMPOS_PERMITIDOS = [
        'password_actual',
        'nuevo_password',
        'password_nuevo',
        'confirmar_password',
        'password_confirmacion',
        '_csrf_token',
        'csrf_token'
    ];

    public string $passwordActual;
    public string $nuevoPassword;
    public string $confirmarPassword;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->passwordActual = $instancia->passwordActual;
            $this->nuevoPassword = $instancia->nuevoPassword;
            $this->confirmarPassword = $instancia->confirmarPassword;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el cambio de contraseña: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $passwordActual = (string) ($datos['password_actual'] ?? '');
        if ($passwordActual === '') {
            $errores['password_actual'][] = 'La contraseña actual es obligatoria.';
        }

        $nuevoPassword = (string) ($datos['nuevo_password'] ?? $datos['password_nuevo'] ?? '');
        if ($nuevoPassword === '') {
            $errores['nuevo_password'][] = 'La nueva contraseña es obligatoria.';
        } else {
            $errs = PoliticaContrasenaServicio::obtenerErrores($nuevoPassword);
            if (!empty($errs)) {
                $errores['nuevo_password'] = $errs;
            }
        }

        $confirmarPassword = (string) ($datos['confirmar_password'] ?? $datos['password_confirmacion'] ?? '');
        if ($confirmarPassword === '') {
            $errores['confirmar_password'][] = 'Debe confirmar la nueva contraseña.';
        } elseif ($nuevoPassword !== $confirmarPassword) {
            $errores['confirmar_password'][] = 'La confirmación no coincide con la nueva contraseña.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de cambio de contraseña inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->passwordActual = $passwordActual;
        $dto->nuevoPassword = $nuevoPassword;
        $dto->confirmarPassword = $confirmarPassword;

        return $dto;
    }
}
