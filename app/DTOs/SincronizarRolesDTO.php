<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * SincronizarRolesDTO — Encapsula y valida la lista de roles para asociar a un usuario.
 */
class SincronizarRolesDTO
{
    private const CAMPOS_PERMITIDOS = [
        'roles',
        '_csrf_token',
        'csrf_token'
    ];

    /** @var array<int> */
    public array $roles = [];

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->roles = $instancia->roles;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la asignación de roles: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $rolesRaw = $datos['roles'] ?? [];
        if (!is_array($rolesRaw) || empty($rolesRaw)) {
            throw new ValidacionExcepcion('Debe seleccionar al menos un rol funcional.', ['roles' => ['Mínimo un rol requerido.']], 422);
        }

        $roles = [];
        foreach ($rolesRaw as $r) {
            $rolId = (int) $r;
            if ($rolId > 0) {
                $roles[] = $rolId;
            }
        }

        if (empty($roles)) {
            throw new ValidacionExcepcion('La lista de roles contiene identificadores inválidos.', ['roles' => ['Roles inválidos.']], 422);
        }

        $dto = new self();
        $dto->roles = array_values(array_unique($roles));

        return $dto;
    }
}
