<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\ProyectoPredioMatriz;
use PDO;

/**
 * ProyectoPredioMatrizRepositorio — Persistencia PDO para Predios Matrices y Terrenos de Origen.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class ProyectoPredioMatrizRepositorio
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

    public function insertar(ProyectoPredioMatriz $predio, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `proyecto_predios_matriz` (
                    `proyecto_id`, `denominacion`, `partida_registral`, `tomo_ficha`,
                    `area_registral_m2`, `area_topografica_m2`, `distrito_id`,
                    `antecedente_dominial`, `poligono_geojson`, `procedencia_topografica`,
                    `estado`, `creado_en`
                ) VALUES (
                    :proyecto_id, :denominacion, :partida_registral, :tomo_ficha,
                    :area_registral_m2, :area_topografica_m2, :distrito_id,
                    :antecedente_dominial, :poligono_geojson, :procedencia_topografica,
                    :estado, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $predio->obtenerProyectoId(), PDO::PARAM_INT);
        $stmt->bindValue(':denominacion', $predio->obtenerDenominacion(), PDO::PARAM_STR);
        $stmt->bindValue(':partida_registral', $predio->obtenerPartidaRegistral(), $predio->obtenerPartidaRegistral() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tomo_ficha', $predio->obtenerTomoFicha(), $predio->obtenerTomoFicha() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':area_registral_m2', $predio->obtenerAreaRegistralM2());
        $stmt->bindValue(':area_topografica_m2', $predio->obtenerAreaTopograficaM2());
        $stmt->bindValue(':distrito_id', $predio->obtenerDistritoId(), PDO::PARAM_INT);
        $stmt->bindValue(':antecedente_dominial', $predio->obtenerAntecedenteDominial(), $predio->obtenerAntecedenteDominial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        
        $geojson = $predio->obtenerPoligonoGeojson();
        $stmt->bindValue(':poligono_geojson', $geojson !== null ? json_encode($geojson, JSON_UNESCAPED_UNICODE) : null, $geojson !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $procedencia = $predio->obtenerProcedenciaTopografica();
        $stmt->bindValue(':procedencia_topografica', $procedencia !== null ? json_encode($procedencia, JSON_UNESCAPED_UNICODE) : null, $procedencia !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->bindValue(':estado', $predio->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $id = (int) $conn->lastInsertId();
        $predio->asignarId($id);
        return $id;
    }

    public function actualizar(ProyectoPredioMatriz $predio, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `proyecto_predios_matriz`
                SET `denominacion` = :denominacion,
                    `partida_registral` = :partida_registral,
                    `tomo_ficha` = :tomo_ficha,
                    `area_registral_m2` = :area_registral_m2,
                    `area_topografica_m2` = :area_topografica_m2,
                    `distrito_id` = :distrito_id,
                    `antecedente_dominial` = :antecedente_dominial,
                    `poligono_geojson` = :poligono_geojson,
                    `procedencia_topografica` = :procedencia_topografica,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':denominacion', $predio->obtenerDenominacion(), PDO::PARAM_STR);
        $stmt->bindValue(':partida_registral', $predio->obtenerPartidaRegistral(), $predio->obtenerPartidaRegistral() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tomo_ficha', $predio->obtenerTomoFicha(), $predio->obtenerTomoFicha() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':area_registral_m2', $predio->obtenerAreaRegistralM2());
        $stmt->bindValue(':area_topografica_m2', $predio->obtenerAreaTopograficaM2());
        $stmt->bindValue(':distrito_id', $predio->obtenerDistritoId(), PDO::PARAM_INT);
        $stmt->bindValue(':antecedente_dominial', $predio->obtenerAntecedenteDominial(), $predio->obtenerAntecedenteDominial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $geojson = $predio->obtenerPoligonoGeojson();
        $stmt->bindValue(':poligono_geojson', $geojson !== null ? json_encode($geojson, JSON_UNESCAPED_UNICODE) : null, $geojson !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $procedencia = $predio->obtenerProcedenciaTopografica();
        $stmt->bindValue(':procedencia_topografica', $procedencia !== null ? json_encode($procedencia, JSON_UNESCAPED_UNICODE) : null, $procedencia !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->bindValue(':id', $predio->obtenerId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function cambiarEstado(int $id, string $estado, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `proyecto_predios_matriz`
                SET `estado` = :estado,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT pm.*,
                       p.empresa_id,
                       p.codigo AS proyecto_codigo,
                       p.nombre AS proyecto_nombre,
                       d.nombre AS distrito_nombre,
                       d.codigo_ubigeo,
                       prov.id AS provincia_id,
                       prov.nombre AS provincia_nombre,
                       dep.id AS departamento_id,
                       dep.nombre AS departamento_nombre
                FROM `proyecto_predios_matriz` pm
                INNER JOIN `proyectos` p ON p.id = pm.proyecto_id
                INNER JOIN `distritos` d ON d.id = pm.distrito_id
                INNER JOIN `provincias` prov ON prov.id = d.provincia_id
                INNER JOIN `departamentos` dep ON dep.id = prov.departamento_id
                WHERE pm.id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function existePartidaEnProyecto(int $proyectoId, string $partida, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `proyecto_predios_matriz`
                WHERE `proyecto_id` = :proyecto_id AND `partida_registral` = :partida";
        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        $stmt->bindValue(':partida', trim($partida), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarPorProyecto(int $proyectoId, ?string $estado = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT pm.*,
                       d.nombre AS distrito_nombre,
                       d.codigo_ubigeo,
                       prov.id AS provincia_id,
                       prov.nombre AS provincia_nombre,
                       dep.id AS departamento_id,
                       dep.nombre AS departamento_nombre
                FROM `proyecto_predios_matriz` pm
                INNER JOIN `distritos` d ON d.id = pm.distrito_id
                INNER JOIN `provincias` prov ON prov.id = d.provincia_id
                INNER JOIN `departamentos` dep ON dep.id = prov.departamento_id
                WHERE pm.proyecto_id = :proyecto_id";

        if ($estado !== null) {
            $sql .= " AND pm.estado = :estado";
        }

        $sql .= " ORDER BY pm.id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calcularBalanceAreas(int $proyectoId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) AS total_predios,
                       COALESCE(SUM(area_registral_m2), 0) AS suma_area_registral_m2,
                       COALESCE(SUM(area_topografica_m2), 0) AS suma_area_topografica_m2,
                       SUM(CASE WHEN area_topografica_m2 IS NOT NULL THEN 1 ELSE 0 END) AS predios_con_topografia,
                       SUM(CASE WHEN area_topografica_m2 IS NULL THEN 1 ELSE 0 END) AS predios_pendientes_topografia
                FROM `proyecto_predios_matriz`
                WHERE `proyecto_id` = :proyecto_id AND `estado` = 'ACTIVO'";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':proyecto_id', $proyectoId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        $registral = (float) ($fila['suma_area_registral_m2'] ?? 0);
        $topografica = (float) ($fila['suma_area_topografica_m2'] ?? 0);
        $discrepanciaAbs = abs($registral - $topografica);
        $discrepanciaPct = $registral > 0 ? ($discrepanciaAbs / $registral) * 100.0 : 0.0;

        return [
            'total_predios'                  => (int) ($fila['total_predios'] ?? 0),
            'suma_area_registral_m2'         => $registral,
            'suma_area_topografica_m2'       => $topografica,
            'predios_con_topografia'         => (int) ($fila['predios_con_topografia'] ?? 0),
            'predios_pendientes_topografia'  => (int) ($fila['predios_pendientes_topografia'] ?? 0),
            'discrepancia_absoluta_m2'       => $discrepanciaAbs,
            'discrepancia_porcentaje'        => $discrepanciaPct
        ];
    }
}
