<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\DTOs\CrearSectorDTO;
use App\DTOs\ActualizarSectorDTO;
use App\DTOs\CambiarEstadoSectorDTO;
use App\DTOs\AjustarPrecioSectorDTO;
use App\Modelos\Sector;
use App\Modelos\SectorPrecioHistorico;
use App\Repositorios\SectorRepositorio;
use App\Repositorios\ProyectoRepositorio;
use App\Repositorios\ProyectoPredioMatrizRepositorio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use PDO;

/**
 * SectorServicio — Lógica de negocio, balance de áreas y seguridad territorial para Sectores.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class SectorServicio
{
    private ProveedorConexion $proveedorConexion;
    private SectorRepositorio $sectorRepositorio;
    private ProyectoRepositorio $proyectoRepositorio;
    private ProyectoPredioMatrizRepositorio $predioRepositorio;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?SectorRepositorio $sectorRepositorio = null,
        ?ProyectoRepositorio $proyectoRepositorio = null,
        ?ProyectoPredioMatrizRepositorio $predioRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->sectorRepositorio = $sectorRepositorio ?? new SectorRepositorio($this->proveedorConexion);
        $this->proyectoRepositorio = $proyectoRepositorio ?? new ProyectoRepositorio($this->proveedorConexion);
        $this->predioRepositorio = $predioRepositorio ?? new ProyectoPredioMatrizRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
    }

    /**
     * Evalúa el balance integral de áreas y diagnóstico de topografía de un proyecto.
     *
     * Cumple con la regla soberana de no mezclar sumas híbridas:
     * - Calcula por separado área registral total y área topográfica total.
     * - Clasifica la topografía del proyecto como COMPLETA, PARCIAL o PENDIENTE.
     * - Si la topografía es COMPLETA, el balance es DEFINITIVO (usa área topográfica).
     * - Si la topografía es PARCIAL o PENDIENTE, el balance es PROVISIONAL (usa área registral).
     *
     * @return array<string, mixed>
     */
    public function evaluarBalanceAreas(int $proyectoId, ?PDO $conexion = null): array
    {
        $conn = $conexion ?? $this->proveedorConexion->obtenerConexion();

        $proyecto = $this->proyectoRepositorio->buscarPorId($proyectoId, null, $conn);
        if (!$proyecto) {
            throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe.', 404);
        }

        $predios = $this->predioRepositorio->listarPorProyecto($proyectoId, 'ACTIVO', $conn);
        $totalPredios = count($predios);

        $areaRegistralTotalM2 = 0.0;
        $areaTopograficaTotalM2 = 0.0;
        $prediosConTopografia = 0;

        foreach ($predios as $predio) {
            $areaRegistralTotalM2 += (float) ($predio['area_registral_m2'] ?? 0.0);
            if ($predio['area_topografica_m2'] !== null && (float) $predio['area_topografica_m2'] > 0) {
                $areaTopograficaTotalM2 += (float) $predio['area_topografica_m2'];
                $prediosConTopografia++;
            }
        }

        $areaRegistralTotalM2 = round($areaRegistralTotalM2, 4);
        $areaTopograficaTotalM2 = round($areaTopograficaTotalM2, 4);

        // Diagnóstico del levantamiento topográfico
        if ($totalPredios === 0) {
            $estadoTopografia = 'SIN_PREDIOS';
        } elseif ($prediosConTopografia === $totalPredios) {
            $estadoTopografia = 'COMPLETA';
        } elseif ($prediosConTopografia > 0) {
            $estadoTopografia = 'PARCIAL';
        } else {
            $estadoTopografia = 'PENDIENTE';
        }

        // Determinación de superficie de contraste matriz
        if ($estadoTopografia === 'COMPLETA') {
            $areaReferenciaMatrizM2 = $areaTopograficaTotalM2;
            $tipoBalance = 'DEFINITIVO';
            $origenAreaReferencia = 'TOPOGRAFICA';
        } else {
            $areaReferenciaMatrizM2 = $areaRegistralTotalM2;
            $tipoBalance = 'PROVISIONAL';
            $origenAreaReferencia = 'REGISTRAL';
        }

        // Sumatorias sectoriales
        $metricasSectores = $this->sectorRepositorio->calcularSumaAreasPorProyecto($proyectoId, null, $conn);
        $totalSectores = $metricasSectores['total_sectores'];
        $areaSectoresBrutaM2 = round($metricasSectores['suma_area_bruta_m2'], 4);
        $areaSectoresUtilM2 = round($metricasSectores['suma_area_util_m2'], 4);
        $areaSectoresCesionM2 = round($metricasSectores['suma_area_cesion_m2'], 4);
        $areaSectoresComunM2 = round($metricasSectores['suma_area_comun_m2'], 4);

        $areaRemanenteMatrizM2 = round($areaReferenciaMatrizM2 - $areaSectoresBrutaM2, 4);

        // Evaluación de estado de balance
        if ($totalPredios === 0) {
            $estadoBalance = 'SIN_PREDIOS_MATRIZ';
            $mensajeBalance = 'El proyecto no cuenta con predios matrices registrados.';
        } elseif ($totalSectores === 0) {
            $estadoBalance = 'SIN_SECTORES';
            $mensajeBalance = 'El proyecto aún no cuenta con sectores urbanísticos incorporados.';
        } elseif ($areaSectoresBrutaM2 > ($areaReferenciaMatrizM2 + 0.0001)) {
            $estadoBalance = 'EXCEDE_AREA_MATRIZ';
            $mensajeBalance = "La suma de áreas brutas de los sectores ({$areaSectoresBrutaM2} m²) supera el área matriz disponible ({$areaReferenciaMatrizM2} m²).";
        } else {
            // Verificar si algún sector individual es inconsistente
            $sectores = $this->sectorRepositorio->listarPorProyecto($proyectoId, null, $conn);
            $hayInconsistenciaLocal = false;
            foreach ($sectores as $sec) {
                $bruta = (float) $sec['area_bruta_m2'];
                $internas = round((float) $sec['area_util_m2'] + (float) $sec['area_cesion_m2'] + (float) $sec['area_comun_m2'], 4);
                if ($internas > ($bruta + 0.0001)) {
                    $hayInconsistenciaLocal = true;
                    break;
                }
            }

            if ($hayInconsistenciaLocal) {
                $estadoBalance = 'SECTOR_INCONSISTENTE';
                $mensajeBalance = 'Existe al menos un sector donde la suma de áreas útil, cesión y común supera su área bruta.';
            } else {
                $estadoBalance = 'BALANCE_CONCILIADO';
                $mensajeBalance = 'Balance sectorial conciliado dentro de la capacidad física de la matriz.';
            }
        }

        // Porcentajes
        $porcentajeOcupacionMatriz = ($areaReferenciaMatrizM2 > 0)
            ? round(($areaSectoresBrutaM2 / $areaReferenciaMatrizM2) * 100, 2)
            : 0.0;
        $porcentajeUtilSobreBruta = ($areaSectoresBrutaM2 > 0)
            ? round(($areaSectoresUtilM2 / $areaSectoresBrutaM2) * 100, 2)
            : 0.0;
        $porcentajeCesionSobreBruta = ($areaSectoresBrutaM2 > 0)
            ? round(($areaSectoresCesionM2 / $areaSectoresBrutaM2) * 100, 2)
            : 0.0;
        $porcentajeComunSobreBruta = ($areaSectoresBrutaM2 > 0)
            ? round(($areaSectoresComunM2 / $areaSectoresBrutaM2) * 100, 2)
            : 0.0;

        return [
            'proyecto_id'                    => $proyectoId,
            'moneda_proyecto'                => (string) ($proyecto['moneda'] ?? 'PEN'),
            'total_predios'                  => $totalPredios,
            'predios_con_topografia'         => $prediosConTopografia,
            'estado_topografia'              => $estadoTopografia,
            'tipo_balance'                   => $tipoBalance,
            'origen_area_referencia'         => $origenAreaReferencia,
            'area_registral_total_m2'        => $areaRegistralTotalM2,
            'area_topografica_total_m2'      => $areaTopograficaTotalM2,
            'area_referencia_matriz_m2'      => $areaReferenciaMatrizM2,
            'total_sectores'                 => $totalSectores,
            'area_sectores_bruta_m2'         => $areaSectoresBrutaM2,
            'area_sectores_util_m2'          => $areaSectoresUtilM2,
            'area_sectores_cesion_m2'        => $areaSectoresCesionM2,
            'area_sectores_comun_m2'         => $areaSectoresComunM2,
            'area_remanente_matriz_m2'       => $areaRemanenteMatrizM2,
            'porcentaje_ocupacion_matriz'    => $porcentajeOcupacionMatriz,
            'porcentaje_util_sobre_bruta'    => $porcentajeUtilSobreBruta,
            'porcentaje_cesion_sobre_bruta'  => $porcentajeCesionSobreBruta,
            'porcentaje_comun_sobre_bruta'   => $porcentajeComunSobreBruta,
            'estado_balance'                 => $estadoBalance,
            'mensaje_balance'                => $mensajeBalance,
        ];
    }

    /**
     * Da de alta un nuevo Sector e inicializa atómicamente su precio base vigente.
     */
    public function crear(
        CrearSectorDTO $dto,
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
            // 1. Anti-IDOR: Validar que el proyecto pertenezca a la empresa territorial activa
            $proyecto = $this->proyectoRepositorio->buscarPorId($dto->proyectoId, null, $conexion);
            if (!$proyecto || (int) $proyecto['empresa_id'] !== $empresaId) {
                throw new RecursoNoEncontradoExcepcion('El proyecto especificado no existe en el ámbito de la empresa activa.', 404);
            }

            // 2. Unicidad de código dentro del proyecto
            $existente = $this->sectorRepositorio->buscarPorProyectoYCodigo($dto->proyectoId, $dto->codigo, $conexion);
            if ($existente !== null) {
                throw new ReglaNegocioExcepcion("Ya existe un sector con el código '{$dto->codigo}' en este proyecto.", 409);
            }

            // 3. Invariante Macro: Validar que la nueva área bruta no desborde el área disponible del proyecto
            $balanceActual = $this->evaluarBalanceAreas($dto->proyectoId, $conexion);
            $areaReferencia = (float) $balanceActual['area_referencia_matriz_m2'];
            $sumaActual = (float) $balanceActual['area_sectores_bruta_m2'];
            $nuevaSuma = round($sumaActual + $dto->areaBrutaM2, 4);

            if ($areaReferencia > 0 && $nuevaSuma > ($areaReferencia + 0.0001)) {
                throw new ReglaNegocioExcepcion(
                    "La asignación de área bruta ({$dto->areaBrutaM2} m²) provocaría que la suma sectorial ({$nuevaSuma} m²) superaría el área matriz disponible ({$areaReferencia} m²).",
                    422
                );
            }

            // 4. Insertar entidad Sector
            $sectorModelo = new Sector(
                $dto->proyectoId,
                $dto->codigo,
                $dto->nombre,
                $dto->areaBrutaM2,
                $dto->areaUtilM2,
                $dto->areaCesionM2,
                $dto->areaComunM2,
                $dto->orden,
                $dto->estado,
                $dto->descripcion
            );

            $sectorId = $this->sectorRepositorio->insertar($sectorModelo, $conexion);

            // 5. Insertar Precio Base Inicial con fecha_fin = NULL
            $monedaProyecto = (string) $proyecto['moneda'];
            $fechaHoy = date('Y-m-d');
            $precioInicial = new SectorPrecioHistorico(
                $sectorId,
                $dto->precioM2Inicial,
                $monedaProyecto,
                $fechaHoy,
                $dto->motivoPrecioInicial,
                $operadorId,
                null // fecha_fin = null -> precio vigente
            );

            $precioId = $this->sectorRepositorio->insertarPrecioHistorico($precioInicial, $conexion);

            // 6. Auditoría Forense Append-Only
            $this->auditoriaServicio->registrar([
                'modulo'           => 'CATASTRO',
                'entidad'          => 'sectores',
                'accion'           => 'CREAR_SECTOR',
                'registro_id'      => $sectorId,
                'actor_id'         => $operadorId,
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => [
                    'proyecto_id'        => $dto->proyectoId,
                    'codigo'             => $dto->codigo,
                    'nombre'             => $dto->nombre,
                    'area_bruta_m2'      => $dto->areaBrutaM2,
                    'area_util_m2'       => $dto->areaUtilM2,
                    'area_cesion_m2'     => $dto->areaCesionM2,
                    'area_comun_m2'      => $dto->areaComunM2,
                    'precio_m2_inicial'  => $dto->precioM2Inicial,
                    'precio_vigente_id'  => $precioId,
                    'moneda'             => $monedaProyecto,
                    'tipo_balance'       => $balanceActual['tipo_balance'],
                ],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'empresa_id'         => $empresaId,
                    'accion_descripcion' => "Alta de nuevo sector urbanístico: {$dto->nombre} ({$dto->codigo})"
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return [
                'id'                => $sectorId,
                'proyecto_id'       => $dto->proyectoId,
                'codigo'            => $dto->codigo,
                'nombre'            => $dto->nombre,
                'area_bruta_m2'     => $dto->areaBrutaM2,
                'precio_m2_inicial' => $dto->precioM2Inicial,
                'moneda'            => $monedaProyecto,
                'estado'            => $dto->estado,
            ];

        } catch (\Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza la configuración métrica de un sector verificando invariantes.
     */
    public function actualizar(
        int $sectorId,
        ActualizarSectorDTO $dto,
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
            $sectorExistente = $this->sectorRepositorio->buscarPorId($sectorId, $conexion);
            if (!$sectorExistente || (int) $sectorExistente['empresa_id'] !== $empresaId) {
                throw new RecursoNoEncontradoExcepcion('El sector solicitado no existe en el ámbito de la empresa activa.', 404);
            }

            $proyectoId = (int) $sectorExistente['proyecto_id'];

            // Invariante Macro: calcular suma excluyendo el sector actual y sumando la nueva área bruta
            $balance = $this->evaluarBalanceAreas($proyectoId, $conexion);
            $areaReferencia = (float) $balance['area_referencia_matriz_m2'];
            $metricasOtros = $this->sectorRepositorio->calcularSumaAreasPorProyecto($proyectoId, $sectorId, $conexion);
            $nuevaSuma = round($metricasOtros['suma_area_bruta_m2'] + $dto->areaBrutaM2, 4);

            if ($areaReferencia > 0 && $nuevaSuma > ($areaReferencia + 0.0001)) {
                throw new ReglaNegocioExcepcion(
                    "La modificación de área bruta ({$dto->areaBrutaM2} m²) provocaría que la suma sectorial ({$nuevaSuma} m²) exceda el área disponible de la matriz ({$areaReferencia} m²).",
                    422
                );
            }

            $sectorActualizado = new Sector(
                $proyectoId,
                (string) $sectorExistente['codigo'],
                $dto->nombre,
                $dto->areaBrutaM2,
                $dto->areaUtilM2,
                $dto->areaCesionM2,
                $dto->areaComunM2,
                $dto->orden,
                (string) $sectorExistente['estado'],
                $dto->descripcion,
                $sectorId
            );

            $this->sectorRepositorio->actualizar($sectorId, $sectorActualizado, $conexion);

            // Auditoría Forense
            $this->auditoriaServicio->registrar([
                'modulo'           => 'CATASTRO',
                'entidad'          => 'sectores',
                'accion'           => 'ACTUALIZAR_SECTOR',
                'registro_id'      => $sectorId,
                'actor_id'         => $operadorId,
                'resultado'        => 'EXITO',
                'datos_anteriores' => [
                    'nombre'         => $sectorExistente['nombre'],
                    'area_bruta_m2'  => (float) $sectorExistente['area_bruta_m2'],
                    'area_util_m2'   => (float) $sectorExistente['area_util_m2'],
                    'area_cesion_m2' => (float) $sectorExistente['area_cesion_m2'],
                    'area_comun_m2'  => (float) $sectorExistente['area_comun_m2'],
                    'orden'          => (int) $sectorExistente['orden'],
                ],
                'datos_nuevos'     => [
                    'nombre'         => $dto->nombre,
                    'area_bruta_m2'  => $dto->areaBrutaM2,
                    'area_util_m2'   => $dto->areaUtilM2,
                    'area_cesion_m2' => $dto->areaCesionM2,
                    'area_comun_m2'  => $dto->areaComunM2,
                    'orden'          => $dto->orden,
                ],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'empresa_id'         => $empresaId,
                    'accion_descripcion' => "Modificación de sector: {$dto->nombre} ({$sectorExistente['codigo']})"
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return [
                'id'            => $sectorId,
                'proyecto_id'   => $proyectoId,
                'codigo'        => $sectorExistente['codigo'],
                'nombre'        => $dto->nombre,
                'area_bruta_m2' => $dto->areaBrutaM2,
            ];

        } catch (\Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ajusta el precio base del sector versionándolo históricamente sin solapamiento de fechas.
     */
    public function ajustarPrecio(
        int $sectorId,
        AjustarPrecioSectorDTO $dto,
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
            $sector = $this->sectorRepositorio->buscarPorId($sectorId, $conexion);
            if (!$sector || (int) $sector['empresa_id'] !== $empresaId) {
                throw new RecursoNoEncontradoExcepcion('El sector solicitado no existe en el ámbito de la empresa activa.', 404);
            }

            $precioVigente = $this->sectorRepositorio->obtenerPrecioVigente($sectorId, $conexion);

            // Blindaje temporal: validar cronología
            if ($precioVigente !== null) {
                $fechaInicioVigente = (string) $precioVigente['fecha_inicio'];
                if ($dto->fechaInicio < $fechaInicioVigente) {
                    throw new ReglaNegocioExcepcion(
                        "La nueva fecha de vigencia ({$dto->fechaInicio}) no puede ser anterior al inicio del precio vigente actual ({$fechaInicioVigente}).",
                        422
                    );
                }

                // Cierre atómico del precio anterior
                // Si la nueva fecha es igual a la actual, fecha_fin es la misma fecha de hoy para evitar solapamiento
                $fechaCierre = $dto->fechaInicio;
                $this->sectorRepositorio->cerrarPrecioVigente($sectorId, $fechaCierre, $conexion);
            }

            // Inserción del nuevo precio vigente (fecha_fin = NULL)
            $monedaProyecto = (string) $sector['proyecto_moneda'];
            $nuevoPrecio = new SectorPrecioHistorico(
                $sectorId,
                $dto->precioM2Base,
                $monedaProyecto,
                $dto->fechaInicio,
                $dto->motivo,
                $operadorId,
                null
            );

            $nuevoPrecioId = $this->sectorRepositorio->insertarPrecioHistorico($nuevoPrecio, $conexion);

            // Auditoría Forense
            $this->auditoriaServicio->registrar([
                'modulo'           => 'CATASTRO',
                'entidad'          => 'sector_precios_historico',
                'accion'           => 'AJUSTE_PRECIO_SECTOR',
                'registro_id'      => $nuevoPrecioId,
                'actor_id'         => $operadorId,
                'resultado'        => 'EXITO',
                'datos_anteriores' => $precioVigente ? [
                    'precio_anterior_id' => $precioVigente['id'],
                    'precio_m2_base'     => (float) $precioVigente['precio_m2_base'],
                    'fecha_inicio'       => $precioVigente['fecha_inicio'],
                ] : null,
                'datos_nuevos'     => [
                    'sector_id'      => $sectorId,
                    'precio_m2_base' => $dto->precioM2Base,
                    'moneda'         => $monedaProyecto,
                    'fecha_inicio'   => $dto->fechaInicio,
                    'motivo'         => $dto->motivo,
                ],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'empresa_id'         => $empresaId,
                    'accion_descripcion' => "Ajuste de precio base sector #{$sectorId} a {$dto->precioM2Base} {$monedaProyecto}"
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return [
                'id'             => $nuevoPrecioId,
                'sector_id'      => $sectorId,
                'precio_m2_base' => $dto->precioM2Base,
                'moneda'         => $monedaProyecto,
                'fecha_inicio'   => $dto->fechaInicio,
                'motivo'         => $dto->motivo,
                'es_vigente'     => true,
            ];

        } catch (\Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Conmuta el estado de ciclo de vida de un sector.
     */
    public function cambiarEstado(
        int $sectorId,
        CambiarEstadoSectorDTO $dto,
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
            $sector = $this->sectorRepositorio->buscarPorId($sectorId, $conexion);
            if (!$sector || (int) $sector['empresa_id'] !== $empresaId) {
                throw new RecursoNoEncontradoExcepcion('El sector solicitado no existe en el ámbito de la empresa activa.', 404);
            }

            $estadoAnterior = (string) $sector['estado'];
            if ($estadoAnterior === $dto->estado) {
                throw new ReglaNegocioExcepcion("El sector ya se encuentra en estado '{$dto->estado}'.", 409);
            }

            $this->sectorRepositorio->cambiarEstado($sectorId, $dto->estado, $conexion);

            // Auditoría Forense
            $this->auditoriaServicio->registrar([
                'modulo'           => 'CATASTRO',
                'entidad'          => 'sectores',
                'accion'           => 'CAMBIO_ESTADO_SECTOR',
                'registro_id'      => $sectorId,
                'actor_id'         => $operadorId,
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $estadoAnterior],
                'datos_nuevos'     => [
                    'estado' => $dto->estado,
                    'motivo' => $dto->motivo,
                ],
                'metadatos'        => [
                    'operador_id'        => $operadorId,
                    'empresa_id'         => $empresaId,
                    'accion_descripcion' => "Cambio de estado sector #{$sectorId} a {$dto->estado}"
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return [
                'id'              => $sectorId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $dto->estado,
                'motivo'          => $dto->motivo,
            ];

        } catch (\Throwable $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene el detalle de un sector con Anti-IDOR territorial.
     */
    public function obtenerPorId(int $sectorId, int $empresaId): array
    {
        $sector = $this->sectorRepositorio->buscarPorId($sectorId);
        if (!$sector || (int) $sector['empresa_id'] !== $empresaId) {
            throw new RecursoNoEncontradoExcepcion('El sector solicitado no existe en el ámbito de la empresa activa.', 404);
        }

        return $sector;
    }

    /**
     * Lista los sectores de un proyecto con Anti-IDOR territorial.
     */
    public function listarPorProyecto(int $proyectoId, int $empresaId, ?string $estado = null): array
    {
        $proyecto = $this->proyectoRepositorio->buscarPorId($proyectoId);
        if (!$proyecto || (int) $proyecto['empresa_id'] !== $empresaId) {
            throw new RecursoNoEncontradoExcepcion('El proyecto solicitado no existe en el ámbito de la empresa activa.', 404);
        }

        return $this->sectorRepositorio->listarPorProyecto($proyectoId, $estado);
    }

    /**
     * Lista el historial cronológico de precios de un sector con Anti-IDOR territorial.
     */
    public function listarHistorialPrecios(int $sectorId, int $empresaId): array
    {
        $sector = $this->sectorRepositorio->buscarPorId($sectorId);
        if (!$sector || (int) $sector['empresa_id'] !== $empresaId) {
            throw new RecursoNoEncontradoExcepcion('El sector solicitado no existe en el ámbito de la empresa activa.', 404);
        }

        return $this->sectorRepositorio->listarHistorialPrecios($sectorId);
    }
}
