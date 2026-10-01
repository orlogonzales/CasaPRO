<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\ProveedorConexion;
use App\Core\Respuesta;
use App\DTOs\AsignarRolEmpresaDTO;
use App\DTOs\RevocarRolEmpresaDTO;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Rol;
use App\Modelos\UsuarioEmpresaRol;
use App\Repositorios\AsignacionTerritorialRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\UsuarioRepositorio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\AutorizacionServicio;
use Throwable;

/**
 * AsignacionTerritorialControlador — Controlador REST para la gestión visual
 * y administrativa de asignaciones territoriales (Usuario ↔ Empresa ↔ Rol).
 *
 * Principios vinculantes:
 * - 0 DDL: Reutiliza la tabla 28 `usuario_empresa_roles`.
 * - Backend Fail-Closed: Rechazo estricto a roles no permitidos (ej. SUPERADMIN).
 * - Reactivación transparente de tuplas históricas inactivas.
 * - Respuestas JSON estructuradas y manejo seguro de transacciones.
 */
class AsignacionTerritorialControlador extends BaseControlador
{
    private AsignacionTerritorialRepositorio $asignacionRepo;
    private AutorizacionServicio $autorizacionServicio;
    private EmpresaRepositorio $empresaRepo;
    private RolRepositorio $rolRepo;
    private UsuarioRepositorio $usuarioRepo;

    public function __construct(
        ?AsignacionTerritorialRepositorio $asignacionRepo = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?EmpresaRepositorio $empresaRepo = null,
        ?RolRepositorio $rolRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null
    ) {
        $proveedor = new ProveedorConexion();
        $this->asignacionRepo = $asignacionRepo ?? new AsignacionTerritorialRepositorio($proveedor);
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepositorio($proveedor);
        $this->rolRepo = $rolRepo ?? new RolRepositorio($proveedor);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($proveedor);

        $auditoria = new AuditoriaServicio($proveedor);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio(
            $proveedor,
            $this->usuarioRepo,
            $this->rolRepo,
            $this->empresaRepo,
            $this->asignacionRepo,
            $auditoria
        );
    }

    /**
     * Lista las asignaciones territoriales asociadas a un usuario específico.
     * GET /api/usuarios/{id}/asignaciones
     */
    public function listarPorUsuario(Peticion $peticion, Respuesta $respuesta, array $args = []): void
    {
        $usuarioId = (int) ($args['id'] ?? 0);
        if ($usuarioId <= 0) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 400,
                'mensaje' => 'Identificador de usuario inválido.'
            ], 400);
            return;
        }

        $usuario = $this->usuarioRepo->buscarPorId($usuarioId);
        if ($usuario === null) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => 'El usuario especificado no existe.'
            ], 404);
            return;
        }

        $asignaciones = $this->asignacionRepo->listarPorUsuario($usuarioId);

        $respuesta->json([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => [
                'usuario_id'    => $usuarioId,
                'asignaciones'  => $asignaciones,
                'total'         => count($asignaciones)
            ]
        ], 200);
    }

    /**
     * Formaliza la asignación (o reactivación) de un rol a un usuario en una empresa.
     * POST /api/usuarios/{id}/asignaciones
     */
    public function asignar(Peticion $peticion, Respuesta $respuesta, array $args = []): void
    {
        $usuarioId = (int) ($args['id'] ?? 0);
        if ($usuarioId <= 0) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 400,
                'mensaje' => 'Identificador de usuario inválido.'
            ], 400);
            return;
        }

        $cuerpo = $peticion->obtenerCuerpo();
        $empresaId = (int) ($cuerpo['empresa_id'] ?? 0);
        $rolId = (int) ($cuerpo['rol_id'] ?? 0);
        $motivo = isset($cuerpo['motivo']) ? (string) $cuerpo['motivo'] : 'Asignación administrativa de rol territorial';

        // 1. Validar DTO con allowlist estricta
        try {
            $dto = new AsignarRolEmpresaDTO($usuarioId, $empresaId, $rolId, $motivo);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
            return;
        }

        // 2. Extraer Actor y Contexto del operador en sesión
        $auth = GestorSesion::obtener('auth');
        $actorId = (int) ($auth['actor_id'] ?? 1);
        $contexto = ContextoPeticion::crearDesdeEntorno($peticion);

        // 3. Ejecutar a través del servicio soberano de autorización
        try {
            $resultado = $this->autorizacionServicio->asignarRolEmpresa($dto, $actorId, $contexto);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Rol territorial asignado exitosamente.',
                'datos'   => $resultado
            ], 201);
        } catch (ReglaNegocioExcepcion $e) {
            $codigoHttp = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 409;
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => $codigoHttp,
                'mensaje' => $e->getMessage()
            ], $codigoHttp);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al procesar la asignación territorial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Conmuta el estado de una asignación territorial (Revocar o Reactivar).
     * PATCH /api/asignaciones/{id}/estado
     */
    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, array $args = []): void
    {
        $asignacionId = (int) ($args['id'] ?? 0);
        if ($asignacionId <= 0) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 400,
                'mensaje' => 'Identificador de asignación inválido.'
            ], 400);
            return;
        }

        $cuerpo = $peticion->obtenerCuerpo();
        $nuevoEstado = strtoupper(trim((string) ($cuerpo['estado'] ?? '')));
        $motivo = trim((string) ($cuerpo['motivo'] ?? ''));

        if (!in_array($nuevoEstado, [UsuarioEmpresaRol::ESTADO_ACTIVO, UsuarioEmpresaRol::ESTADO_INACTIVO], true)) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => 'El estado especificado es inválido (solo ACTIVO o INACTIVO).'
            ], 422);
            return;
        }

        $asignacion = $this->asignacionRepo->buscarPorId($asignacionId);
        if ($asignacion === null) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => 'La asignación territorial no existe.'
            ], 404);
            return;
        }

        $auth = GestorSesion::obtener('auth');
        $actorId = (int) ($auth['actor_id'] ?? 1);
        $contexto = ContextoPeticion::crearDesdeEntorno($peticion);

        try {
            if ($nuevoEstado === UsuarioEmpresaRol::ESTADO_INACTIVO) {
                // Revocación lógica
                if (!$asignacion->estaActivo()) {
                    $respuesta->json([
                        'estado'  => 'error',
                        'codigo'  => 409,
                        'mensaje' => 'La asignación ya se encuentra inactiva.'
                    ], 409);
                    return;
                }

                $motivoRevocacion = $motivo !== '' ? $motivo : 'Revocación administrativa de rol territorial';
                $dtoRevocar = new RevocarRolEmpresaDTO(
                    $asignacion->obtenerUsuarioId(),
                    $asignacion->obtenerEmpresaId(),
                    $asignacion->obtenerRolId(),
                    $motivoRevocacion
                );

                $resultado = $this->autorizacionServicio->revocarRolEmpresa($dtoRevocar, $actorId, $contexto);
                $mensaje = 'Rol territorial revocado exitosamente.';
            } else {
                // Reactivación
                if ($asignacion->estaActivo()) {
                    $respuesta->json([
                        'estado'  => 'error',
                        'codigo'  => 409,
                        'mensaje' => 'La asignación ya se encuentra activa.'
                    ], 409);
                    return;
                }

                $motivoAsignacion = $motivo !== '' ? $motivo : 'Reactivación de asignación territorial';
                $dtoAsignar = new AsignarRolEmpresaDTO(
                    $asignacion->obtenerUsuarioId(),
                    $asignacion->obtenerEmpresaId(),
                    $asignacion->obtenerRolId(),
                    $motivoAsignacion
                );

                $resultado = $this->autorizacionServicio->asignarRolEmpresa($dtoAsignar, $actorId, $contexto);
                $mensaje = 'Rol territorial reactivado exitosamente.';
            }

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => $mensaje,
                'datos'   => $resultado
            ], 200);

        } catch (ReglaNegocioExcepcion $e) {
            $codigoHttp = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 409;
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => $codigoHttp,
                'mensaje' => $e->getMessage()
            ], $codigoHttp);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al conmutar estado de la asignación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista los colaboradores asignados a una empresa (Vista espejo de solo consulta).
     * GET /api/empresas/{id}/colaboradores
     */
    public function listarPorEmpresa(Peticion $peticion, Respuesta $respuesta, array $args = []): void
    {
        $empresaId = (int) ($args['id'] ?? 0);
        if ($empresaId <= 0) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 400,
                'mensaje' => 'Identificador de empresa inválido.'
            ], 400);
            return;
        }

        $empresa = $this->empresaRepo->buscarPorId($empresaId);
        if ($empresa === null) {
            $respuesta->json([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => 'La empresa especificada no existe.'
            ], 404);
            return;
        }

        $colaboradores = $this->asignacionRepo->listarPorEmpresa($empresaId);

        $respuesta->json([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => [
                'empresa_id'    => $empresaId,
                'colaboradores' => $colaboradores,
                'total'         => count($colaboradores)
            ]
        ], 200);
    }

    /**
     * Retorna el catálogo de empresas activas disponibles para el selector modal.
     * GET /api/asignaciones/empresas-disponibles
     */
    public function empresasDisponibles(Peticion $peticion, Respuesta $respuesta): void
    {
        $empresas = $this->empresaRepo->listarTodas('ACTIVO');

        $resultado = array_map(static function (array $e): array {
            return [
                'id'           => (int) $e['id'],
                'codigo'       => (string) $e['codigo'],
                'nombre_corto' => (string) $e['nombre_corto'],
                'estado'       => (string) $e['estado']
            ];
        }, $empresas);

        $respuesta->json([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => $resultado
        ], 200);
    }

    /**
     * Retorna el catálogo de roles disponibles para asignación territorial,
     * excluyendo estrictamente SUPERADMIN por regla soberana.
     * GET /api/asignaciones/roles-disponibles
     */
    public function rolesDisponibles(Peticion $peticion, Respuesta $respuesta): void
    {
        $roles = $this->rolRepo->listarTodosActivos();

        // Filtrar obligatoriamente SUPERADMIN
        $rolesTerritoriales = array_values(array_filter($roles, static function (array $r): bool {
            return ($r['codigo'] ?? '') !== Rol::ROL_SUPERADMIN;
        }));

        $resultado = array_map(static function (array $r): array {
            return [
                'id'          => (int) $r['id'],
                'codigo'      => (string) $r['codigo'],
                'nombre'      => (string) $r['nombre'],
                'descripcion' => (string) ($r['descripcion'] ?? '')
            ];
        }, $rolesTerritoriales);

        $respuesta->json([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => $resultado
        ], 200);
    }
}
