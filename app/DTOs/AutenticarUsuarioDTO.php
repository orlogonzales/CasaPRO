<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * AutenticarUsuarioDTO — Validación y encapsulamiento de credenciales entrantes.
 * Aplica whitelist estricta y formato seguro.
 */
class AutenticarUsuarioDTO
{
    private const CAMPOS_PERMITIDOS = [
        'identificador',
        'password',
        'recordarme',
        'csrf_token',
        '_csrf_token'
    ];

    public string $identificador;
    public string $password;
    public bool $recordarme;

    public static function desdeArray(array $datos): self
    {
        // Validar que no existan campos no permitidos (whitelist)
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la petición de autenticación: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $identificador = trim((string) ($datos['identificador'] ?? ''));
        if ($identificador === '') {
            $errores['identificador'][] = 'El usuario o correo electrónico es obligatorio.';
        } elseif (strlen($identificador) > 191) {
            $errores['identificador'][] = 'El identificador no puede superar los 191 caracteres.';
        }

        $password = (string) ($datos['password'] ?? '');
        if ($password === '') {
            $errores['password'][] = 'La contraseña es obligatoria.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de acceso incompletos o inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->identificador = $identificador;
        $dto->password = $password;
        $dto->recordarme = filter_var($datos['recordarme'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $dto;
    }
}
