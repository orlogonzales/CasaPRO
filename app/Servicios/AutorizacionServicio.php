<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Modelos\Rol;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use PDO;

/**
 * AutorizacionServicio — Motor de autorización RBAC y validación de ámbitos (Scopes).
 * Opera bajo la política Deny by Default y memoria de ejecución desacoplada de la sesión.
 */
class AutorizacionServicio
{
    private ProveedorConexion $proveedorConexion;
    private UsuarioRepositorio $usuarioRepositorio;
    private RolRepositorio $rolRepositorio;

    /**
     * Cache de privilegios en memoria de ejecución (exclusivo para el ciclo del request).
     * @var array<int, array<string>>
     */
    private array $cachePrivilegios = [];

    /**
     * Cache de roles en memoria de ejecución.
     * @var array<int, array<string>>
     */
    private array $cacheRoles = [];

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?UsuarioRepositorio $usuarioRepositorio = null,
        ?RolRepositorio $rolRepositorio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->usuarioRepositorio = $usuarioRepositorio ?? new UsuarioRepositorio($this->proveedorConexion);
        $this->rolRepositorio = $rolRepositorio ?? new RolRepositorio($this->proveedorConexion);
    }

    /**
     * Evalúa si un usuario posee un privilegio funcional granular.
     *
     * Regla SUPERADMIN: Si el usuario ostenta el rol SUPERADMIN, resuelve true inmediatamente
     * (Bypass RBAC). No obstante, el SUPERADMIN continúa sujeto a autenticación, estado activo,
     * CSRF, integridad y auditoría.
     */
    public function tienePrivilegio(
        int $usuarioId,
        string $privilegioCodigo,
        string $scope = 'GLOBAL',
        ?int $alcanceId = null
    ): bool {
        if ($usuarioId <= 0) {
            return false;
        }

        $roles = $this->obtenerRolesUsuario($usuarioId);

        // 1. Bypass RBAC para SUPERADMIN
        if (in_array(Rol::ROL_SUPERADMIN, $roles, true)) {
            return true;
        }

        // 2. En Microfase 1G-1, el scope territorial autorizado es exclusivamente GLOBAL
        if (strtoupper($scope) !== 'GLOBAL') {
            return false;
        }

        // 3. Verificación de catálogo de privilegios asignados al usuario
        $privilegios = $this->obtenerPrivilegiosUsuario($usuarioId);
        return in_array(strtolower(trim($privilegioCodigo)), $privilegios, true);
    }

    /**
     * Obtiene los códigos de roles activos asignados al usuario con cache en memoria del request.
     */
    public function obtenerRolesUsuario(int $usuarioId): array
    {
        if (!isset($this->cacheRoles[$usuarioId])) {
            $this->cacheRoles[$usuarioId] = $this->usuarioRepositorio->obtenerCodigosRoles($usuarioId);
        }
        return $this->cacheRoles[$usuarioId];
    }

    /**
     * Obtiene los códigos de privilegios activos asignados al usuario con cache en memoria del request.
     */
    public function obtenerPrivilegiosUsuario(int $usuarioId): array
    {
        if (!isset($this->cachePrivilegios[$usuarioId])) {
            $this->cachePrivilegios[$usuarioId] = $this->rolRepositorio->obtenerCodigosPrivilegiosPorUsuario($usuarioId);
        }
        return $this->cachePrivilegios[$usuarioId];
    }

    /**
     * Valida de manera ultraligera por clave primaria si la sesión del usuario sigue siendo válida.
     * Si el usuario está inactivo o la versión en BD difiere de la sesión (desigualdad estricta), retorna null.
     */
    public function validarControlSesion(int $usuarioId, int $versionSesion): ?array
    {
        $control = $this->usuarioRepositorio->obtenerControlSesion($usuarioId);
        if ($control === null) {
            return null;
        }

        if (($control['estado'] ?? '') !== 'ACTIVO') {
            return null;
        }

        // Regla: Cualquier divergencia en version_autorizacion invalida inmediatamente la sesión
        if ((int) $control['version_autorizacion'] !== $versionSesion) {
            return null;
        }

        return $control;
    }
}
