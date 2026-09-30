<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * ActualizarUsuarioDTO — Encapsula y valida la actualización de datos generales de usuario.
 */
class ActualizarUsuarioDTO
{
    private const CAMPOS_PERMITIDOS = [
        'email',
        'nombre_usuario',
        '_csrf_token',
        'csrf_token'
    ];

    public string $email;
    public string $nombreUsuario;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->email = $instancia->email;
            $this->nombreUsuario = $instancia->nombreUsuario;
        }
    }

    public static function desdeArray(array $datos): self

    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la actualización de usuario: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        if ($email === '') {
            $errores['email'][] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores['email'][] = 'El formato del correo electrónico es inválido.';
        } elseif (strlen($email) > 191) {
            $errores['email'][] = 'El correo electrónico no puede exceder 191 caracteres.';
        }

        $nombreUsuario = trim((string) ($datos['nombre_usuario'] ?? ''));
        if ($nombreUsuario === '') {
            $errores['nombre_usuario'][] = 'El nombre de usuario es obligatorio.';
        } elseif (strlen($nombreUsuario) < 3 || strlen($nombreUsuario) > 50) {
            $errores['nombre_usuario'][] = 'El nombre de usuario debe contener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $nombreUsuario)) {
            $errores['nombre_usuario'][] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de usuario inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->email = $email;
        $dto->nombreUsuario = $nombreUsuario;

        return $dto;
    }
}
