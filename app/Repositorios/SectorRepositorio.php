<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\Sector;
use App\Modelos\SectorPrecioHistorico;
use PDO;

/**
 * SectorRepositorio — Persistencia PDO para Sectores Urbanísticos e Histórico de Precios.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class SectorRepositorio
{
    private ProveedorConexion $proveedorConexion;

    public function __construct(?ProveedorConexion $proveedorConexion = null)
    {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
    }

    private function obtenerConexion(?PDO $conexion = null): PDO
    {
        return $conexion ?? $this->proveedorConexion->obtenerConexion();
    }

    public function insertar(Sector $sector, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `sectores` (
                    `proyecto_id`, `codigo`, `nombre`, `descripcion`,
                    `area_bruta_m2`, `area_util_m2`, `area_cesion_m2`, `area_comun_m2`,
                    `orden`, `estado`, `creado_en`
                ) VALUES (
                    :proyecto_id, :codigo, :nombre, :descripcion,
                    :area_bruta_m2, :area_util_m2, :area_cesion_m2, :area_comun_m2,
                    :orden, :estado, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $sector->obtenerProyectoId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $sector->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $sector->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $sector->obtenerDescripcion(), $sector->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':area_bruta_m2', $sector->obtenerAreaBrutaM2());
        $stmt->bindValue(':area_util_m2', $sector->obtenerAreaUtilM2());
        $stmt->bindValue(':area_cesion_m2', $sector->obtenerAreaCesionM2());
        $stmt->bindValue(':area_comun_m2', $sector->obtenerAreaComunM2());
        $stmt->bindValue(':orden', $sector->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $sector->obtenerEstado(), PDO::PARAM_STR);

        $stmt->execute();
        return (int) $conn->lastInsertId();
    }

    public function actualizar(int $id, Sector $sector, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `sectores` SET
                    `nombre` = :nombre,
                    `descripcion` = :descripcion,
                    `area_bruta_m2` = :area_bruta_m2,
                    `area_util_m2` = :area_util_m2,
                    `area_cesion_m2` = :area_cesion_m2,
                    `area_comun_m2` = :area_comun_m2,
                    `orden` = :orden,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $sector->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $sector->obtenerDescripcion(), $sector->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':area_bruta_m2', $sector->obtenerAreaBrutaM2());
        $stmt->bindValue(':area_util_m2', $sector->obtenerAreaUtilM2());
        $stmt->bindValue(':area_cesion_m2', $sector->obtenerAreaCesionM2());
        $stmt->bindValue(':area_comun_m2', $sector->obtenerAreaComunM2());
        $stmt->bindValue(':orden', $sector->obtenerOrden(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT s.*,
                       p.codigo AS proyecto_codigo,
                       p.nombre AS proyecto_nombre,
                       p.empresa_id AS empresa_id,
                       p.moneda AS proyecto_moneda,
                       sph.id AS precio_vigente_id,
                       sph.precio_m2_base AS precio_m2_actual,
                       sph.moneda AS precio_moneda,
                       sph.fecha_inicio AS precio_fecha_inicio,
                       sph.motivo AS precio_motivo
                FROM `sectores` s
                INNER JOIN `proyectos` p ON s.proyecto_id = p.id
                LEFT JOIN `sector_precios_historico` sph ON sph.sector_id = s.id AND sph.fecha_fin IS NULL
                WHERE s.id = :id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function buscarPorProyectoYCodigo(int $proyectoId, string $codigo, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT s.*
                FROM `sectores` s
                WHERE s.proyecto_id = :proyecto_id AND s.codigo = :codigo
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Lista los sectores de un proyecto con su precio vigente actual ordenados deterministamente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarPorProyecto(int $proyectoId, ?string $estado = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT s.*,
                       sph.id AS precio_vigente_id,
                       sph.precio_m2_base AS precio_m2_actual,
                       sph.moneda AS precio_moneda,
                       sph.fecha_inicio AS precio_fecha_inicio,
                       sph.motivo AS precio_motivo
                FROM `sectores` s
                LEFT JOIN `sector_precios_historico` sph ON sph.sector_id = s.id AND sph.fecha_fin IS NULL
                WHERE s.proyecto_id = :proyecto_id";

        if ($estado !== null && $estado !== '') {
            $sql .= " AND s.estado = :estado";
        }

        $sql .= " ORDER BY s.orden ASC, s.codigo ASC, s.id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        if ($estado !== null && $estado !== '') {
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cambiarEstado(int $id, string $estado, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `sectores` SET `estado` = :estado, `actualizado_en` = NOW() WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Calcula la suma de áreas de los sectores de un proyecto (excluyendo opcionalmente un sector en edición).
     *
     * @return array{
     *     total_sectores: int,
     *     suma_area_bruta_m2: float,
     *     suma_area_util_m2: float,
     *     suma_area_cesion_m2: float,
     *     suma_area_comun_m2: float
     * }
     */
    public function calcularSumaAreasPorProyecto(int $proyectoId, ?int $excluirSectorId = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) AS total_sectores,
                       COALESCE(SUM(area_bruta_m2), 0) AS suma_area_bruta_m2,
                       COALESCE(SUM(area_util_m2), 0) AS suma_area_util_m2,
                       COALESCE(SUM(area_cesion_m2), 0) AS suma_area_cesion_m2,
                       COALESCE(SUM(area_comun_m2), 0) AS suma_area_comun_m2
                FROM `sectores`
                WHERE `proyecto_id` = :proyecto_id
                  AND `estado` != :estado_inactivo";

        if ($excluirSectorId !== null && $excluirSectorId > 0) {
            $sql .= " AND `id` != :excluir_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        $stmt->bindValue(':estado_inactivo', Sector::ESTADO_INACTIVO, PDO::PARAM_STR);
        if ($excluirSectorId !== null && $excluirSectorId > 0) {
            $stmt->bindValue(':excluir_id', $excluirSectorId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_sectores'      => (int) ($resultado['total_sectores'] ?? 0),
            'suma_area_bruta_m2'  => (float) ($resultado['suma_area_bruta_m2'] ?? 0.0),
            'suma_area_util_m2'   => (float) ($resultado['suma_area_util_m2'] ?? 0.0),
            'suma_area_cesion_m2' => (float) ($resultado['suma_area_cesion_m2'] ?? 0.0),
            'suma_area_comun_m2'  => (float) ($resultado['suma_area_comun_m2'] ?? 0.0),
        ];
    }

    // =========================================================================
    // MÉTODOS DE HISTÓRICO DE PRECIOS POR SECTOR
    // =========================================================================

    public function insertarPrecioHistorico(SectorPrecioHistorico $precio, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `sector_precios_historico` (
                    `sector_id`, `precio_m2_base`, `moneda`, `fecha_inicio`,
                    `fecha_fin`, `motivo`, `creado_por`, `creado_en`
                ) VALUES (
                    :sector_id, :precio_m2_base, :moneda, :fecha_inicio,
                    :fecha_fin, :motivo, :creado_por, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':sector_id', $precio->obtenerSectorId(), PDO::PARAM_INT);
        $stmt->bindValue(':precio_m2_base', $precio->obtenerPrecioM2Base());
        $stmt->bindValue(':moneda', $precio->obtenerMoneda(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_inicio', $precio->obtenerFechaInicio(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $precio->obtenerFechaFin(), $precio->obtenerFechaFin() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':motivo', $precio->obtenerMotivo(), PDO::PARAM_STR);
        $stmt->bindValue(':creado_por', $precio->obtenerCreadoPor(), PDO::PARAM_INT);

        $stmt->execute();
        return (int) $conn->lastInsertId();
    }

    /**
     * Cierra el precio vigente actual de un sector asignando su fecha de fin.
     */
    public function cerrarPrecioVigente(int $sectorId, string $fechaFin, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `sector_precios_historico`
                SET `fecha_fin` = :fecha_fin
                WHERE `sector_id` = :sector_id
                  AND `fecha_fin` IS NULL";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':sector_id', $sectorId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_fin', $fechaFin, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function obtenerPrecioVigente(int $sectorId, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT sph.*, a.nombre AS creado_por_nombre
                FROM `sector_precios_historico` sph
                LEFT JOIN `actores` a ON sph.creado_por = a.id
                WHERE sph.sector_id = :sector_id
                  AND sph.fecha_fin IS NULL
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':sector_id', $sectorId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Lista el historial cronológico completo de precios de un sector.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarHistorialPrecios(int $sectorId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT sph.*,
                       a.nombre AS creado_por_nombre
                FROM `sector_precios_historico` sph
                LEFT JOIN `actores` a ON sph.creado_por = a.id
                WHERE sph.sector_id = :sector_id
                ORDER BY sph.fecha_inicio DESC, sph.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':sector_id', $sectorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
