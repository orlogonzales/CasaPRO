<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Modelos\Rol;
use App\Modelos\UsuarioEmpresaRol;
use App\DTOs\AsignarRolEmpresaDTO;
use App\DTOs\RevocarRolEmpresaDTO;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\AsignacionTerritorialRepositorio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use PDO;
use Throwable;

/**
 * AutorizacionServicio — Motor soberano de autorización RBAC y validación de ámbitos territoriales (Scopes).
 * Opera bajo la política Deny by Default y memoria de ejecución desacoplada de la sesión.
 */
class AutorizacionServicio
{
    private ProveedorConexion $proveedorConexion;
    private UsuarioRepositorio $usuarioRepositorio;
    private RolRepositorio $rolRepositorio;
    private EmpresaRepositorio $empresaRepositorio;
    private AsignacionTerritorialRepositorio $asignacionTerritorialRepositorio;
    private AuditoriaServicio $auditoriaServicio;

    /**
     * Cache de privilegios en memoria de ejecución (exclusivo para el ciclo del request).
     * @var array<int, array<string>>
     */
    private array $cachePrivilegios = [];

    /**
     * Cache de roles globales en memoria de ejecución.
     * @var array<int, array<string>>
     */
    private array $cacheRoles = [];

    /**
     * Cache de roles por empresa en memoria de ejecución: [usuarioId => [empresaId => [codigos]]]
     * @var array<int, array<int, array<string>>>
     */
    private array $cacheRolesEmpresa = [];

    /**
     * Cache de privilegios por empresa en memoria de ejecución: [usuarioId => [empresaId => [codigos]]]
     * @var array<int, array<int, array<string>>>
     */
    private array $cachePrivilegiosEmpresa = [];

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?UsuarioRepositorio $usuarioRepositorio = null,
        ?RolRepositorio $rolRepositorio = null,
        ?EmpresaRepositorio $empresaRepositorio = null,
        ?AsignacionTerritorialRepositorio $asignacionTerritorialRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->usuarioRepositorio = $usuarioRepositorio ?? new UsuarioRepositorio($this->proveedorConexion);
        $this->rolRepositorio = $rolRepositorio ?? new RolRepositorio($this->proveedorConexion);
        $this->empresaRepositorio = $empresaRepositorio ?? new EmpresaRepositorio($this->proveedorConexion);
        $this->asignacionTerritorialRepositorio = $asignacionTerritorialRepositorio ?? new AsignacionTerritorialRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
    }

    /**
     * Evalúa si un usuario posee un privilegio funcional granular dentro de un ámbito territorial.
     *
     * Regla SUPERADMIN: Si el usuario ostenta el rol SUPERADMIN (ámbito GLOBAL), resuelve true
     * inmediatamente (Bypass RBAC universal). No obstante, el SUPERADMIN continúa sujeto a
     * autenticación, estado activo, CSRF, integridad y contexto operativo válido.
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

        $privilegioLimpio = strtolower(trim($privilegioCodigo));
        $scopeLimpio = strtoupper(trim($scope));

        $rolesGlobales = $this->obtenerRolesUsuario($usuarioId);
        $esSuperadmin = in_array(Rol::ROL_SUPERADMIN, $rolesGlobales, true);

        // 1. Scope GLOBAL (Recursos transversales del sistema)
        if ($scopeLimpio === 'GLOBAL') {
            if ($esSuperadmin) {
                return true;
            }
            $privilegiosGlobales = $this->obtenerPrivilegiosUsuario($usuarioId);
            return in_array($privilegioLimpio, $privilegiosGlobales, true);
        }

        // 2. Scope EMPRESA (Recursos territoriales acotados a una empresa)
        if ($scopeLimpio === 'EMPRESA') {
            if ($alcanceId === null || $alcanceId <= 0) {
                return false;
            }

            // Regla soberana: La empresa debe existir y estar en estado ACTIVO (incluso para SUPERADMIN)
            $empresa = $this->empresaRepositorio->buscarPorId($alcanceId);
            if ($empresa === null || ($empresa['estado'] ?? '') !== 'ACTIVO') {
                return false;
            }

            // SUPERADMIN ostenta bypass funcional sobre cualquier empresa legítimamente ACTIVA
            if ($esSuperadmin) {
                return true;
            }

            // Validar si el usuario ostenta el privilegio en esa empresa específica
            $privilegiosEmpresa = $this->obtenerPrivilegiosUsuarioEnEmpresa($usuarioId, $alcanceId);
            return in_array($privilegioLimpio, $privilegiosEmpresa, true);
        }

        // Cualquier otro scope no soportado resuelve false (Deny by Default)
        return false;
    }

    /**
     * Obtiene los códigos de roles globales activos asignados al usuario.
     * @return array<string>
     */
    public function obtenerRolesUsuario(int $usuarioId): array
    {
        if (!isset($this->cacheRoles[$usuarioId])) {
            $this->cacheRoles[$usuarioId] = $this->usuarioRepositorio->obtenerCodigosRoles($usuarioId);
        }
        return $this->cacheRoles[$usuarioId];
    }

    /**
     * Obtiene los códigos de privilegios activos de roles globales asignados al usuario.
     * @return array<string>
     */
    public function obtenerPrivilegiosUsuario(int $usuarioId): array
    {
        if (!isset($this->cachePrivilegios[$usuarioId])) {
            $this->cachePrivilegios[$usuarioId] = $this->rolRepositorio->obtenerCodigosPrivilegiosPorUsuario($usuarioId);
        }
        return $this->cachePrivilegios[$usuarioId];
    }

    /**
     * Obtiene los códigos de roles activos asignados al usuario en una empresa específica.
     * @return array<string>
     */
    public function obtenerRolesUsuarioEnEmpresa(int $usuarioId, int $empresaId): array
    {
        if ($usuarioId <= 0 || $empresaId <= 0) {
            return [];
        }

        if (isset($this->cacheRolesEmpresa[$usuarioId][$empresaId])) {
            return $this->cacheRolesEmpresa[$usuarioId][$empresaId];
        }

        // Si es SUPERADMIN, ostenta el rol virtualmente en toda empresa activa
        $rolesGlobales = $this->obtenerRolesUsuario($usuarioId);
        if (in_array(Rol::ROL_SUPERADMIN, $rolesGlobales, true)) {
            $this->cacheRolesEmpresa[$usuarioId][$empresaId] = [Rol::ROL_SUPERADMIN];
            return $this->cacheRolesEmpresa[$usuarioId][$empresaId];
        }

        $roles = $this->asignacionTerritorialRepositorio->obtenerCodigosRolesUsuarioEnEmpresa($usuarioId, $empresaId);
        $this->cacheRolesEmpresa[$usuarioId][$empresaId] = $roles;
        return $roles;
    }

    /**
     * Obtiene los códigos de privilegios activos asignados al usuario en una empresa específica.
     * @return array<string>
     */
    public function obtenerPrivilegiosUsuarioEnEmpresa(int $usuarioId, int $empresaId): array
    {
        if ($usuarioId <= 0 || $empresaId <= 0) {
            return [];
        }

        if (isset($this->cachePrivilegiosEmpresa[$usuarioId][$empresaId])) {
            return $this->cachePrivilegiosEmpresa[$usuarioId][$empresaId];
        }

        // Si es SUPERADMIN, tiene todos los privilegios activos del catálogo
        $rolesGlobales = $this->obtenerRolesUsuario($usuarioId);
        if (in_array(Rol::ROL_SUPERADMIN, $rolesGlobales, true)) {
            $conn = $this->proveedorConexion->obtenerConexion();
            $todos = $conn->query("SELECT LOWER(codigo) FROM `privilegios`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $this->cachePrivilegiosEmpresa[$usuarioId][$empresaId] = $todos;
            return $todos;
        }

        $privilegios = $this->asignacionTerritorialRepositorio->obtenerCodigosPrivilegiosUsuarioEnEmpresa($usuarioId, $empresaId);
        $this->cachePrivilegiosEmpresa[$usuarioId][$empresaId] = $privilegios;
        return $privilegios;
    }

    /**
     * Obtiene el listado de empresas activas disponibles para el usuario.
     * - SUPERADMIN: Todas las empresas activas del sistema.
     * - Usuario regular: Subconjunto estricto de empresas activas donde tiene al menos un rol activo.
     */
    public function obtenerEmpresasDisponiblesParaUsuario(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        $rolesGlobales = $this->obtenerRolesUsuario($usuarioId);
        if (in_array(Rol::ROL_SUPERADMIN, $rolesGlobales, true)) {
            return $this->empresaRepositorio->listarTodas('ACTIVO');
        }

        return $this->asignacionTerritorialRepositorio->obtenerEmpresasDisponiblesParaUsuario($usuarioId);
    }

    /**
     * Verifica si un usuario cuenta con autorización territorial para acceder a una empresa.
     */
    public function puedeAccederEmpresa(int $usuarioId, int $empresaId): bool
    {
        if ($usuarioId <= 0 || $empresaId <= 0) {
            return false;
        }

        // La empresa debe existir y estar en estado ACTIVO
        $empresa = $this->empresaRepositorio->buscarPorId($empresaId);
        if ($empresa === null || ($empresa['estado'] ?? '') !== 'ACTIVO') {
            return false;
        }

        // SUPERADMIN tiene acceso territorial universal a toda empresa ACTIVA
        $rolesGlobales = $this->obtenerRolesUsuario($usuarioId);
        if (in_array(Rol::ROL_SUPERADMIN, $rolesGlobales, true)) {
            return true;
        }

        return $this->asignacionTerritorialRepositorio->usuarioTieneAccesoEmpresa($usuarioId, $empresaId);
    }

    /**
     * Asigna formalmente un rol a un usuario en una empresa activa.
     * Transaccional con incremento de version_autorizacion y registro en auditoría forense.
     */
    public function asignarRolEmpresa(AsignarRolEmpresaDTO $dto, int $actorId, ?ContextoPeticion $contexto = null): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        // 1. Validar existencia y vigencia del usuario objetivo
        $usuario = $this->usuarioRepositorio->buscarPorId($dto->usuarioId, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }
        if (!$usuario->estaActivo()) {
            throw new ReglaNegocioExcepcion('No se pueden asignar roles a un usuario inactivo.', 422);
        }

        // 2. Validar existencia y estado de la empresa (409 Conflict si está inactiva)
        $empresa = $this->empresaRepositorio->buscarPorId($dto->empresaId, $conn);
        if ($empresa === null) {
            throw new ReglaNegocioExcepcion('La empresa especificada no existe.', 404);
        }
        if (($empresa['estado'] ?? '') !== 'ACTIVO') {
            throw new ReglaNegocioExcepcion('La empresa especificada se encuentra inactiva y no admite nuevas asignaciones.', 409);
        }

        // 3. Validar existencia del rol
        $rolesValidos = $this->rolRepositorio->validarIdsRolesActivos([$dto->rolId], $conn);
        if (empty($rolesValidos)) {
            throw new ReglaNegocioExcepcion('El rol especificado no existe o se encuentra inactivo.', 422);
        }
        $rol = $rolesValidos[0];

        // 4. Regla: El rol SUPERADMIN es exclusivo de ámbito GLOBAL y no debe asignarse por empresa
        if (($rol['codigo'] ?? '') === Rol::ROL_SUPERADMIN) {
            throw new ReglaNegocioExcepcion('El rol SUPERADMIN es de ámbito GLOBAL y no puede asignarse a nivel de empresa.', 422);
        }

        $conn->beginTransaction();

        try {
            $existente = $this->asignacionTerritorialRepositorio->buscarPorTupla($dto->usuarioId, $dto->empresaId, $dto->rolId, $conn);
            $accionAuditoria = 'ASIGNAR_ROL_EMPRESA';
            $datosAnteriores = null;
            $asignacionId = 0;

            if ($existente !== null) {
                if ($existente->estaActivo()) {
                    throw new ReglaNegocioExcepcion('El usuario ya cuenta con este rol activo en la empresa especificada.', 409);
                }
                // Reactivación de asignación inactiva previa
                $datosAnteriores = $existente->aArray();
                $existente->reactivar($actorId);
                $this->asignacionTerritorialRepositorio->actualizar($existente, $conn);
                $asignacionId = (int) $existente->obtenerId();
                $accionAuditoria = 'REACTIVAR_ROL_EMPRESA';
            } else {
                // Nueva asignación
                $nueva = new UsuarioEmpresaRol(
                    $dto->usuarioId,
                    $dto->empresaId,
                    $dto->rolId,
                    $actorId,
                    UsuarioEmpresaRol::ESTADO_ACTIVO
                );
                $asignacionId = $this->asignacionTerritorialRepositorio->insertar($nueva, $conn);
            }

            // Incrementar version_autorizacion del usuario individual
            $nuevaVersion = $this->usuarioRepositorio->incrementarVersionAutorizacion($dto->usuarioId, $conn);

            // Registrar traza forense inmutable
            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuario_empresa_roles',
                'registro_id'      => $asignacionId,
                'accion'           => $accionAuditoria,
                'resultado'        => 'EXITO',
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos'     => [
                    'id'           => $asignacionId,
                    'usuario_id'   => $dto->usuarioId,
                    'empresa_id'   => $dto->empresaId,
                    'rol_id'       => $dto->rolId,
                    'estado'       => UsuarioEmpresaRol::ESTADO_ACTIVO,
                    'asignado_por' => $actorId,
                ],
                'metadatos'        => ['motivo' => $dto->motivo ?? 'Asignación de rol territorial'],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();
            $this->invalidarCacheUsuario($dto->usuarioId);

            return [
                'id'                   => $asignacionId,
                'usuario_id'           => $dto->usuarioId,
                'empresa_id'           => $dto->empresaId,
                'rol_id'               => $dto->rolId,
                'estado'               => UsuarioEmpresaRol::ESTADO_ACTIVO,
                'version_autorizacion' => $nuevaVersion
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Revoca un rol territorial específico a un usuario en una empresa.
     * Aplica baja lógica (estado = INACTIVO), incrementa version_autorizacion y audita.
     */
    public function revocarRolEmpresa(RevocarRolEmpresaDTO $dto, int $actorId, ?ContextoPeticion $contexto = null): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $asignacion = $this->asignacionTerritorialRepositorio->buscarPorTupla($dto->usuarioId, $dto->empresaId, $dto->rolId, $conn);
        if ($asignacion === null) {
            throw new ReglaNegocioExcepcion('La asignación de rol territorial especificada no existe.', 404);
        }

        if (!$asignacion->estaActivo()) {
            throw new ReglaNegocioExcepcion('La asignación de rol ya se encuentra inactiva.', 409);
        }

        $conn->beginTransaction();

        try {
            $datosAnteriores = $asignacion->aArray();
            $asignacion->revocar($actorId);
            $this->asignacionTerritorialRepositorio->actualizar($asignacion, $conn);

            // Incrementar version_autorizacion del usuario individual
            $nuevaVersion = $this->usuarioRepositorio->incrementarVersionAutorizacion($dto->usuarioId, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuario_empresa_roles',
                'registro_id'      => $asignacion->obtenerId(),
                'accion'           => 'REVOCAR_ROL_EMPRESA',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos'     => $asignacion->aArray(),
                'metadatos'        => ['motivo' => $dto->motivo],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();
            $this->invalidarCacheUsuario($dto->usuarioId);

            return [
                'id'                   => $asignacion->obtenerId(),
                'usuario_id'           => $dto->usuarioId,
                'empresa_id'           => $dto->empresaId,
                'rol_id'               => $dto->rolId,
                'estado'               => UsuarioEmpresaRol::ESTADO_INACTIVO,
                'version_autorizacion' => $nuevaVersion
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Revoca el acceso total de un usuario a una empresa desactivando todos sus roles activos en ella.
     */
    public function revocarAccesoEmpresa(int $usuarioId, int $empresaId, string $motivo, int $actorId, ?ContextoPeticion $contexto = null): array
    {
        if ($usuarioId <= 0 || $empresaId <= 0) {
            throw new ValidacionExcepcion('Identificadores de usuario y empresa inválidos.', [
                'usuario_id' => ['ID de usuario requerido.'],
                'empresa_id' => ['ID de empresa requerida.']
            ]);
        }

        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '' || strlen($motivoLimpio) < 5) {
            throw new ValidacionExcepcion('El motivo de revocación de acceso es obligatorio.', [
                'motivo' => ['Debe indicar un motivo descriptivo (mínimo 5 caracteres).']
            ]);
        }

        $conn = $this->proveedorConexion->obtenerConexion();
        $activas = $this->asignacionTerritorialRepositorio->listarPorUsuario($usuarioId, 'ACTIVO', $conn);

        $afectadas = array_filter($activas, fn($a) => (int) $a['empresa_id'] === $empresaId);
        if (empty($afectadas)) {
            throw new ReglaNegocioExcepcion('El usuario no posee roles activos en la empresa especificada.', 404);
        }

        $conn->beginTransaction();

        try {
            foreach ($afectadas as $fila) {
                $obj = UsuarioEmpresaRol::desdeArray($fila);
                $obj->revocar($actorId);
                $this->asignacionTerritorialRepositorio->actualizar($obj, $conn);
            }

            // Incrementar version_autorizacion del usuario individual
            $nuevaVersion = $this->usuarioRepositorio->incrementarVersionAutorizacion($usuarioId, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuario_empresa_roles',
                'registro_id'      => $empresaId,
                'accion'           => 'REVOCAR_EMPRESA',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['roles_inactivados' => array_column($afectadas, 'rol_id')],
                'datos_nuevos'     => ['estado' => 'INACTIVO'],
                'metadatos'        => ['motivo' => $motivoLimpio],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();
            $this->invalidarCacheUsuario($usuarioId);

            return [
                'usuario_id'           => $usuarioId,
                'empresa_id'           => $empresaId,
                'roles_revocados'      => count($afectadas),
                'version_autorizacion' => $nuevaVersion
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Invalida los cachés en memoria de privilegios y roles para un usuario o para todo el ciclo.
     */
    public function invalidarCacheUsuario(?int $usuarioId = null): void
    {
        if ($usuarioId === null) {
            $this->cachePrivilegios = [];
            $this->cacheRoles = [];
            $this->cacheRolesEmpresa = [];
            $this->cachePrivilegiosEmpresa = [];
        } else {
            unset(
                $this->cachePrivilegios[$usuarioId],
                $this->cacheRoles[$usuarioId],
                $this->cacheRolesEmpresa[$usuarioId],
                $this->cachePrivilegiosEmpresa[$usuarioId]
            );
        }
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
