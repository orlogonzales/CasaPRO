<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Servicios\PoliticaContrasenaServicio;

/**
 * CrearUsuarioDTO — Encapsula y valida los datos de alta de un nuevo usuario en CasaPRO.
 */
class CrearUsuarioDTO
{
    private const CAMPOS_PERMITIDOS = [
        'persona_id',
        'email',
        'nombre_usuario',
        'password',
        'password_temporal',
        'roles',
        '_csrf_token',
        'csrf_token'
    ];

    public int $personaId;
    public string $email;
    public string $nombreUsuario;
    public string $passwordTemporal;
    public string $password;
    /** @var array<int> */
    public array $roles = [];

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->personaId = $instancia->personaId;
            $this->email = $instancia->email;
            $this->nombreUsuario = $instancia->nombreUsuario;
            $this->passwordTemporal = $instancia->passwordTemporal;
            $this->password = $instancia->password;
            $this->roles = $instancia->roles;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el alta de usuario: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        $personaId = (int) ($datos['persona_id'] ?? 0);
        if ($personaId <= 0) {
            $errores['persona_id'][] = 'Debe seleccionar una Persona Natural válida.';
        }

        $email = strtolower(trim((string) ($datos['email'] ?? '')));
        if ($email === '') {
            $errores['email'][] = 'El correo electrónico corporativo es obligatorio.';
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

        $passCandidato = (string) ($datos['password_temporal'] ?? $datos['password'] ?? '');
        if ($passCandidato === '') {
            $errores['password_temporal'][] = 'La contraseña temporal es obligatoria.';
        } else {
            $errs = PoliticaContrasenaServicio::obtenerErrores($passCandidato);
            if (!empty($errs)) {
                $errores['password_temporal'] = $errs;
            }
        }

        $rolesRaw = $datos['roles'] ?? [];
        if (!is_array($rolesRaw) || empty($rolesRaw)) {
            $errores['roles'][] = 'Debe asignar al menos un rol funcional al usuario.';
        } else {
            $roles = [];
            foreach ($rolesRaw as $r) {
                $rolId = (int) $r;
                if ($rolId > 0) {
                    $roles[] = $rolId;
                }
            }
            if (empty($roles)) {
                $errores['roles'][] = 'La lista de roles contiene valores inválidos.';
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de alta de usuario incompletos o inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->personaId = $personaId;
        $dto->email = $email;
        $dto->nombreUsuario = $nombreUsuario;
        $dto->passwordTemporal = $passCandidato;
        $dto->password = $passCandidato;
        $dto->roles = array_values(array_unique($roles));

        return $dto;
    }
}
