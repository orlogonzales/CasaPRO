<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Modelos\Proyecto;
use App\Modelos\ProyectoPredioMatriz;
use App\DTOs\CrearProyectoDTO;
use App\DTOs\ActualizarProyectoDTO;
use App\DTOs\CambiarEstadoProyectoDTO;
use App\DTOs\CrearPredioMatrizDTO;
use App\DTOs\ActualizarPredioMatrizDTO;
use App\DTOs\CambiarEstadoPredioMatrizDTO;
use App\Repositorios\ProyectoRepositorio;
use App\Repositorios\ProyectoPredioMatrizRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use PDO;
use Throwable;

/**
 * ProyectoServicio — Servicio de dominio para gestión de Proyectos y Predios Matrices.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 * Reglas de negocio:
 * 1. Pertenencia territorial estricta a Empresa activa.
 * 2. Unicidad de código corporativo de proyecto por empresa (409).
 * 3. Unicidad de partida registral dentro del proyecto si no es nula (409).
 * 4. Tolerancia determinista de áreas (ABSOLUTA_M2 o PORCENTUAL).
 * 5. Cero DELETE físico: ciclo de vida gobernado por estados.
 * 6. Auditoría forense atómica con Actor USER real.
 */
class ProyectoServicio
{
    private ProveedorConexion $proveedorConexion;
    private ProyectoRepositorio $proyectoRepositorio;
    private ProyectoPredioMatrizRepositorio $predioRepositorio;
    private EmpresaRepositorio $empresaRepositorio;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?ProyectoRepositorio $proyectoRepositorio = null,
        ?ProyectoPredioMatrizRepositorio $predioRepositorio = null,
        ?EmpresaRepositorio $empresaRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->proyectoRepositorio = $proyectoRepositorio ?? new ProyectoRepositorio($this->proveedorConexion);
        $this->predioRepositorio = $predioRepositorio ?? new ProyectoPredioMatrizRepositorio($this->proveedorConexion);
        $this->empresaRepositorio = $empresaRepositorio ?? new EmpresaRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
    }

    /**
     * Da de alta un nuevo Proyecto Inmobiliario vinculado a la empresa activa.
     */
    public function crear(
        CrearProyectoDTO $dto,
        int $empresaId,
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
            // 1. Validar que la empresa exista y esté activa
            $empresa = $this->empresaRepositorio->buscarPorId($empresaId, $conexion);
            if (!$empresa) {
                throw new RecursoNoEncontradoExcepcion('La empresa seleccionada no existe.', 404);
            }
            if (($empresa['estado'] ?? '') !== 'ACTIVO') {
                throw new ReglaNegocioExcepcion('No se pueden crear proyectos en una empresa inactiva.', 409);
            }

            // 2. Validar unicidad de código dentro de la empresa
            if ($this->proyectoRepositorio->existeCodigoEnEmpresa($dto->codigo, $empresaId, null, $conexion)) {
                throw new ReglaNegocioExcepcion(
                    "Ya existe un proyecto con el código '{$dto->codigo}' en la empresa activa.",
                    409
                );
            }

            // 3. Instanciar e insertar entidad Proyecto
            $proyecto = new Proyecto(
                $empresaId,
                $dto->codigo,
                $dto->nombre,
                $dto->distritoId,
                $dto->tipoProyecto,
                $dto->moneda,
                $dto->tipoTolerancia,
                $dto->valorTolerancia,
                $dto->estado,
                $dto->descripcion,
                $dto->direccionReferencia,
                $dto->latitud,
                $dto->longitud,
                $dto->zoomMapa
            );

            $id = $this->proyectoRepositorio->insertar($proyecto, $conexion);

            // 4. Registro de auditoría forense append-only
            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyectos',
                'registro_id'      => $id,
                'accion'           => 'CREAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => $proyecto->toArray(),
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Alta de nuevo proyecto inmobiliario: {$proyecto->obtenerNombre()} ({$proyecto->obtenerCodigo()})",
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza la información de un Proyecto existente garantizando inmutabilidad de código y moneda.
     */
    public function actualizar(
        int $id,
        ActualizarProyectoDTO $dto,
        int $empresaId,
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
            $actual = $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conexion);
            if (!$actual) {
                throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe en la empresa activa.', 404);
            }

            $proyecto = Proyecto::desdeArray($actual);
            $proyecto->establecerNombre($dto->nombre)
                     ->establecerDescripcion($dto->descripcion)
                     ->establecerTipoProyecto($dto->tipoProyecto)
                     ->establecerDistritoId($dto->distritoId)
                     ->establecerDireccionReferencia($dto->direccionReferencia)
                     ->establecerPoliticaTolerancia($dto->tipoTolerancia, $dto->valorTolerancia)
                     ->establecerCoordenadas($dto->latitud, $dto->longitud, $dto->zoomMapa);

            $this->proyectoRepositorio->actualizar($proyecto, $conexion);

            // Auditoría forense
            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyectos',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $actual,
                'datos_nuevos'     => $proyecto->toArray(),
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Actualización de proyecto: {$proyecto->obtenerNombre()}",
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Consulta el detalle de un Proyecto validando su pertenencia a la empresa activa (Anti-IDOR).
     */
    public function obtenerPorId(int $id, int $empresaId, ?PDO $conexion = null): array
    {
        $conn = $conexion ?? $this->proveedorConexion->obtenerConexion();
        $proyecto = $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conn);
        if (!$proyecto) {
            throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe en la empresa activa.', 404);
        }
        return $proyecto;
    }

    /**
     * Cambia el estado del ciclo de vida de un Proyecto.
     */
    public function cambiarEstado(
        int $id,
        CambiarEstadoProyectoDTO $dto,
        int $empresaId,
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
            $actual = $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conexion);
            if (!$actual) {
                throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe en la empresa activa.', 404);
            }

            $this->proyectoRepositorio->cambiarEstado($id, $dto->estado, $empresaId, $conexion);

            // Auditoría
            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyectos',
                'registro_id'      => $id,
                'accion'           => 'CAMBIAR_ESTADO',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $actual['estado']],
                'datos_nuevos'     => ['estado' => $dto->estado],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Cambio de estado del proyecto a {$dto->estado}",
                    'motivo'             => $dto->motivo,
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->proyectoRepositorio->buscarPorId($id, $empresaId, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Da de alta un nuevo Predio Matriz dentro de un Proyecto.
     */
    public function crearPredioMatriz(
        CrearPredioMatrizDTO $dto,
        int $empresaId,
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
            // 1. Validar que el proyecto pertenezca a la empresa activa (Anti-IDOR)
            $proyecto = $this->proyectoRepositorio->buscarPorId($dto->proyectoId, $empresaId, $conexion);
            if (!$proyecto) {
                throw new RecursoNoEncontradoExcepcion('El proyecto de destino no existe en la empresa activa.', 404);
            }

            // 2. Si se suministra partida registral, verificar unicidad en el proyecto
            if ($dto->partidaRegistral !== null) {
                if ($this->predioRepositorio->existePartidaEnProyecto($dto->proyectoId, $dto->partidaRegistral, null, $conexion)) {
                    throw new ReglaNegocioExcepcion(
                        "Ya existe un predio matriz con la partida registral '{$dto->partidaRegistral}' en este proyecto.",
                        409
                    );
                }
            }

            // 3. Instanciar e insertar
            $predio = new ProyectoPredioMatriz(
                $dto->proyectoId,
                $dto->denominacion,
                $dto->areaRegistralM2,
                $dto->distritoId,
                $dto->partidaRegistral,
                $dto->tomoFicha,
                $dto->areaTopograficaM2,
                $dto->antecedenteDominial,
                $dto->poligonoGeojson,
                $dto->procedenciaTopografica,
                $dto->estado
            );

            $id = $this->predioRepositorio->insertar($predio, $conexion);

            // 4. Auditoría
            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyecto_predios_matriz',
                'registro_id'      => $id,
                'accion'           => 'CREAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => $predio->toArray(),
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Incorporación de predio matriz: {$predio->obtenerDenominacion()} al proyecto {$proyecto['codigo']}",
                    'proyecto_id'        => $dto->proyectoId,
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->predioRepositorio->buscarPorId($id, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza un Predio Matriz existente validando ascendencia territorial (Anti-IDOR).
     */
    public function actualizarPredioMatriz(
        int $id,
        ActualizarPredioMatrizDTO $dto,
        int $empresaId,
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
            $actual = $this->predioRepositorio->buscarPorId($id, $conexion);
            if (!$actual) {
                throw new RecursoNoEncontradoExcepcion('El predio matriz solicitado no existe.', 404);
            }

            // Anti-IDOR: Verificar que el proyecto del predio pertenezca a la empresa activa
            if ((int) $actual['empresa_id'] !== $empresaId) {
                throw new ReglaNegocioExcepcion('Discordancia territorial: El predio matriz no pertenece a la empresa activa.', 403);
            }

            // Unicidad de partida
            if ($dto->partidaRegistral !== null) {
                if ($this->predioRepositorio->existePartidaEnProyecto((int) $actual['proyecto_id'], $dto->partidaRegistral, $id, $conexion)) {
                    throw new ReglaNegocioExcepcion(
                        "Ya existe otro predio matriz con la partida '{$dto->partidaRegistral}' en este proyecto.",
                        409
                    );
                }
            }

            $predio = ProyectoPredioMatriz::desdeArray($actual);
            $predio->establecerDenominacion($dto->denominacion)
                   ->establecerPartidaRegistral($dto->partidaRegistral)
                   ->establecerTomoFicha($dto->tomoFicha)
                   ->establecerAreaRegistralM2($dto->areaRegistralM2)
                   ->establecerAreaTopograficaM2($dto->areaTopograficaM2)
                   ->establecerDistritoId($dto->distritoId)
                   ->establecerAntecedenteDominial($dto->antecedenteDominial)
                   ->establecerPoligonoGeojson($dto->poligonoGeojson)
                   ->establecerProcedenciaTopografica($dto->procedenciaTopografica);

            $this->predioRepositorio->actualizar($predio, $conexion);

            // Auditoría
            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyecto_predios_matriz',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $actual,
                'datos_nuevos'     => $predio->toArray(),
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Actualización de predio matriz: {$predio->obtenerDenominacion()}",
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->predioRepositorio->buscarPorId($id, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Alterna el estado de un Predio Matriz.
     */
    public function cambiarEstadoPredioMatriz(
        int $id,
        CambiarEstadoPredioMatrizDTO $dto,
        int $empresaId,
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
            $actual = $this->predioRepositorio->buscarPorId($id, $conexion);
            if (!$actual) {
                throw new RecursoNoEncontradoExcepcion('El predio matriz solicitado no existe.', 404);
            }

            if ((int) $actual['empresa_id'] !== $empresaId) {
                throw new ReglaNegocioExcepcion('Discordancia territorial: El predio matriz no pertenece a la empresa activa.', 403);
            }

            $this->predioRepositorio->cambiarEstado($id, $dto->estado, $conexion);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'MOD_CATASTRO',
                'entidad'          => 'proyecto_predios_matriz',
                'registro_id'      => $id,
                'accion'           => 'CAMBIAR_ESTADO',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $actual['estado']],
                'datos_nuevos'     => ['estado' => $dto->estado],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'accion_descripcion' => "Cambio de estado del predio matriz a {$dto->estado}",
                    'motivo'             => $dto->motivo,
                    'empresa_id'         => $empresaId
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return $this->predioRepositorio->buscarPorId($id, $conexion);
        } catch (Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Evalúa la conciliación de áreas (Nivel 1 Registral vs Nivel 2 Topográfico) bajo la política determinista.
     */
    public function evaluarConciliacionAreas(int $proyectoId, int $empresaId, ?PDO $conexion = null): array
    {
        $conn = $conexion ?? $this->proveedorConexion->obtenerConexion();

        $proyecto = $this->proyectoRepositorio->buscarPorId($proyectoId, $empresaId, $conn);
        if (!$proyecto) {
            throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe en la empresa activa.', 404);
        }

        $balance = $this->predioRepositorio->calcularBalanceAreas($proyectoId, $conn);

        $tipoTolerancia = $proyecto['tipo_tolerancia'];
        $valorTolerancia = (float) $proyecto['valor_tolerancia'];

        $discrepanciaAbs = $balance['discrepancia_absoluta_m2'];
        $discrepanciaPct = $balance['discrepancia_porcentaje'];

        $conciliado = false;
        if ($balance['total_predios'] === 0) {
            $estadoSemaforo = 'SIN_PREDIOS';
            $mensaje = 'El proyecto aún no registra predios matrices.';
        } elseif ($balance['predios_pendientes_topografia'] > 0) {
            $estadoSemaforo = 'PENDIENTE_TOPOGRAFIA';
            $mensaje = "Existen {$balance['predios_pendientes_topografia']} predio(s) pendiente(s) de levantamiento topográfico.";
        } else {
            // Aplicar la regla determinista única (GATE 3A-04)
            if ($tipoTolerancia === Proyecto::TOLERANCIA_ABSOLUTA_M2) {
                $conciliado = $discrepanciaAbs <= $valorTolerancia;
            } else {
                $conciliado = $discrepanciaPct <= $valorTolerancia;
            }

            if ($conciliado) {
                $estadoSemaforo = 'CONCILIADO';
                $mensaje = 'Áreas registral y topográfica conciliadas dentro de la tolerancia admitida.';
            } else {
                $estadoSemaforo = 'DISCREPANCIA_FUERA_TOLERANCIA';
                $mensaje = $tipoTolerancia === Proyecto::TOLERANCIA_ABSOLUTA_M2
                    ? sprintf('Discrepancia de %.4f m² excede la tolerancia permitida de %.4f m².', $discrepanciaAbs, $valorTolerancia)
                    : sprintf('Discrepancia de %.2f%% excede la tolerancia permitida de %.2f%%.', $discrepanciaPct, $valorTolerancia);
            }
        }

        return [
            'proyecto_id'            => $proyectoId,
            'proyecto_codigo'        => $proyecto['codigo'],
            'proyecto_nombre'        => $proyecto['nombre'],
            'tipo_tolerancia'        => $tipoTolerancia,
            'valor_tolerancia'       => $valorTolerancia,
            'politica_aplicada'      => [
                'tipo'  => $tipoTolerancia,
                'valor' => $valorTolerancia
            ],
            'balance_predios'        => $balance,
            'conciliado'             => $conciliado,
            'dentro_tolerancia'      => $conciliado,
            'semaforo'               => $estadoSemaforo,
            'mensaje'                => $mensaje
        ];
    }
}
