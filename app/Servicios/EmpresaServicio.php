<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\DTOs\CrearEmpresaDTO;
use App\DTOs\CrearPersonaDTO;
use App\DTOs\ActualizarEmpresaDTO;
use App\DTOs\CambiarEstadoEmpresaDTO;
use App\Modelos\Empresa;
use App\Modelos\Persona;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use PDO;
use Throwable;

/**
 * EmpresaServicio — Servicio de dominio para gestión de entidades corporativas (Empresas).
 * 
 * Reglas de negocio:
 * 1. Persona como Identidad Raíz: Toda Empresa es una extensión operativa de una Persona Jurídica (1 : 0..1).
 * 2. Unicidad del Propietario Transaccional: Control atómico de altas vinculadas u orquestadas.
 * 3. Si falla la creación de Empresa o Auditoría, la Persona Jurídica creada es revertida (Rollback total).
 * 4. Semántica de errores: 422 (Persona Natural o Jurídica Inactiva), 409 (Duplicidad 1:1 o Código repetido).
 * 5. Inmutabilidad: persona_id y codigo no pueden modificarse tras su creación.
 * 6. Inactivación != Borrado: Las empresas inactivas conservan su historial y no se eliminan físicamente.
 * 7. Prohibición de DELETE: No se expone método público de borrado físico.
 */
class EmpresaServicio
{
    private ProveedorConexion $proveedorConexion;
    private EmpresaRepositorio $empresaRepositorio;
    private PersonaServicio $personaServicio;
    private PersonaRepositorio $personaRepositorio;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?EmpresaRepositorio $empresaRepositorio = null,
        ?PersonaServicio $personaServicio = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->empresaRepositorio = $empresaRepositorio ?? new EmpresaRepositorio($this->proveedorConexion);
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
        $this->personaServicio = $personaServicio ?? new PersonaServicio($this->proveedorConexion, $this->personaRepositorio, $this->auditoriaServicio);
    }

    /**
     * Da de alta una nueva entidad corporativa (Empresa) de forma atómica.
     * Soporta modalidad vinculada (persona_id existente) u orquestada (creando Persona Jurídica).
     * 
     * @param CrearEmpresaDTO $dto Datos validados de la empresa
     * @param int $operadorId ID de usuario autenticado que ejecuta la acción
     * @param ContextoPeticion $contexto Contexto de auditoría y trazabilidad
     * @param PDO|null $conexionExterna Conexión PDO opcional para transacciones jerárquicas
     * @return array Detalle completo de la empresa creada
     * @throws ReglaNegocioExcepcion Ante violaciones de reglas de dominio (422 o 409)
     */
    public function crear(
        CrearEmpresaDTO $dto,
        int $operadorId,
        ContextoPeticion $contexto,
        ?PDO $conexionExterna = null
    ): array {
        $conexion = $conexionExterna ?? $this->proveedorConexion->obtenerConexion();
        $transaccionPropia = !$conexion->inTransaction();

        if ($transaccionPropia) {
            $conexion->beginTransaction();
        }

        try {
            $personaId = null;

            // 1. Resolver persona_id según modalidad
            if ($dto->esModalidadOrquestada()) {
                // Modalidad Orquestada: Construir DTO oficial de Persona y validar tipo JURIDICA
                $dtoPersona = CrearPersonaDTO::desdeArray($dto->datosPersona);
                if ($dtoPersona->tipoPersona !== Persona::TIPO_JURIDICA) {
                    throw new ReglaNegocioExcepcion(
                        'Incompatibilidad de dominio: Solo se pueden constituir empresas a partir de Personas Jurídicas.',
                        422
                    );
                }

                // Delegar al servicio oficial de Persona pasando la misma conexión activa
                $resultadoPersona = $this->personaServicio->crear($dtoPersona, $contexto, $conexion);
                $personaId = (int) $resultadoPersona['id'];
            } else {
                // Modalidad Vinculada: Validar existencia, tipo y estado de la persona en el padrón
                $personaId = (int) $dto->personaId;
                $persona = $this->personaRepositorio->buscarPorId($personaId, $conexion);

                if ($persona === null) {
                    throw new ReglaNegocioExcepcion(
                        "La persona con ID {$personaId} no existe en el sistema.",
                        422
                    );
                }

                // Regla: Persona Natural intentando ser empresa -> HTTP 422
                if ($persona['tipo_persona'] !== Persona::TIPO_JURIDICA) {
                    throw new ReglaNegocioExcepcion(
                        'Incompatibilidad de dominio: No se puede constituir una empresa a partir de una Persona Natural.',
                        422
                    );
                }

                // Regla: Persona Jurídica INACTIVA -> HTTP 422
                if ($persona['estado'] !== Persona::ESTADO_ACTIVO) {
                    throw new ReglaNegocioExcepcion(
                        'Incompatibilidad de estado: La Persona Jurídica está INACTIVA. Debe regularizarse en el padrón antes de crear la empresa.',
                        422
                    );
                }
            }

            // 2. Validar Unicidad 1:1 Persona Jurídica -> Empresa (HTTP 409 Conflict)
            if ($this->empresaRepositorio->existePorPersonaId($personaId, null, $conexion)) {
                throw new ReglaNegocioExcepcion(
                    "Conflicto de unicidad: La Persona Jurídica (ID {$personaId}) ya se encuentra vinculada a otra empresa registrada.",
                    409
                );
            }

            // 3. Validar Unicidad de Código Canónico Corporativo (HTTP 409 Conflict)
            if ($this->empresaRepositorio->existeCodigo($dto->codigo, null, $conexion)) {
                throw new ReglaNegocioExcepcion(
                    "Conflicto de unicidad: El código corporativo '{$dto->codigo}' ya se encuentra en uso por otra empresa.",
                    409
                );
            }

            // 4. Construir y persistir la entidad Empresa
            $empresa = new Empresa(
                $personaId,
                $dto->codigo,
                $dto->nombreCorto,
                Empresa::ESTADO_ACTIVO
            );
            $empresaId = $this->empresaRepositorio->insertar($empresa, $conexion);

            // 5. Registrar Auditoría Forense Atómica con Actor USER
            $this->auditoriaServicio->registrar([
                'modulo'           => 'empresas',
                'entidad'          => 'empresas',
                'registro_id'      => $empresaId,
                'accion'           => 'CREAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => $empresa->aArray(),
                'metadatos'        => [
                    'operador_id'   => $operadorId,
                    'modalidad'     => $dto->esModalidadOrquestada() ? 'ORQUESTADA' : 'VINCULADA',
                    'persona_id'    => $personaId,
                    'codigo'        => $dto->codigo,
                    'nombre_corto'  => $dto->nombreCorto,
                ],
                'contexto'         => $contexto
            ], $conexion);

            // 6. Commit de la transacción si fue abierta por este servicio
            if ($transaccionPropia) {
                $conexion->commit();
            }

            $resultado = $this->empresaRepositorio->buscarPorId($empresaId, $conexion);
            return $resultado ?? $empresa->aArray();
        } catch (Throwable $t) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Actualiza la configuración operativa de una empresa (nombre_corto).
     * Regla: persona_id y codigo son estrictamente inmutables.
     */
    public function actualizar(
        int $id,
        ActualizarEmpresaDTO $dto,
        int $operadorId,
        ContextoPeticion $contexto,
        ?PDO $conexionExterna = null
    ): array {
        $conexion = $conexionExterna ?? $this->proveedorConexion->obtenerConexion();
        $transaccionPropia = !$conexion->inTransaction();

        if ($transaccionPropia) {
            $conexion->beginTransaction();
        }

        try {
            $existente = $this->empresaRepositorio->buscarPorId($id, $conexion);
            if ($existente === null) {
                throw new RecursoNoEncontradoExcepcion("La empresa solicitada (ID {$id}) no existe.");
            }

            $empresa = Empresa::desdeArray($existente);
            $datosAnteriores = $empresa->aArray();

            $empresa->establecerNombreCorto($dto->nombreCorto);
            $this->empresaRepositorio->actualizar($empresa, $conexion);

            // Auditoría
            $this->auditoriaServicio->registrar([
                'modulo'           => 'empresas',
                'entidad'          => 'empresas',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos'     => $empresa->aArray(),
                'metadatos'        => [
                    'operador_id'  => $operadorId,
                    'cambio'       => 'nombre_corto',
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->empresaRepositorio->buscarPorId($id, $conexion) ?? $empresa->aArray();
        } catch (Throwable $t) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Alterna el estado operativo de la empresa (ACTIVO <-> INACTIVO).
     * Una empresa inactiva permanece consultable históricamente, pero no es elegible
     * para nuevas operaciones ni contextos territoriales activos.
     */
    public function cambiarEstado(
        int $id,
        CambiarEstadoEmpresaDTO $dto,
        int $operadorId,
        ContextoPeticion $contexto,
        ?PDO $conexionExterna = null
    ): array {
        $conexion = $conexionExterna ?? $this->proveedorConexion->obtenerConexion();
        $transaccionPropia = !$conexion->inTransaction();

        if ($transaccionPropia) {
            $conexion->beginTransaction();
        }

        try {
            $existente = $this->empresaRepositorio->buscarPorId($id, $conexion);
            if ($existente === null) {
                throw new RecursoNoEncontradoExcepcion("La empresa solicitada (ID {$id}) no existe.");
            }

            $estadoAnterior = $existente['estado'];
            $nuevoEstado = $dto->estado;

            if ($estadoAnterior === $nuevoEstado) {
                return $existente;
            }

            $this->empresaRepositorio->cambiarEstado($id, $nuevoEstado, $conexion);

            // Auditoría
            $this->auditoriaServicio->registrar([
                'modulo'           => 'empresas',
                'entidad'          => 'empresas',
                'registro_id'      => $id,
                'accion'           => 'CAMBIAR_ESTADO',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $estadoAnterior],
                'datos_nuevos'     => ['estado' => $nuevoEstado],
                'metadatos'        => [
                    'operador_id'     => $operadorId,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo'    => $nuevoEstado
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->empresaRepositorio->buscarPorId($id, $conexion) ?? array_merge($existente, ['estado' => $nuevoEstado]);
        } catch (Throwable $t) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->empresaRepositorio->buscarPorId($id);
    }

    public function obtenerPorCodigo(string $codigo): ?array
    {
        return $this->empresaRepositorio->buscarPorCodigo($codigo);
    }

    public function obtenerPorPersonaId(int $personaId): ?array
    {
        return $this->empresaRepositorio->buscarPorPersonaId($personaId);
    }

    public function listarTodas(?string $filtroEstado = null): array
    {
        return $this->empresaRepositorio->listarTodas($filtroEstado);
    }
}
