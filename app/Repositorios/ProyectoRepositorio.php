<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\Proyecto;
use PDO;

/**
 * ProyectoRepositorio — Persistencia PDO nativa para Proyectos inmobiliarios.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 * Reglas:
 * - Ámbito territorial estricto por empresa_id.
 * - Sentencias preparadas nativas, cero concatenación.
 * - Inexistencia deliberada de sentencias DELETE físicas.
 */
class ProyectoRepositorio
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

    public function insertar(Proyecto $proyecto, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `proyectos` (
                    `empresa_id`, `codigo`, `nombre`, `descripcion`, `tipo_proyecto`,
                    `moneda`, `distrito_id`, `direccion_referencia`, `tipo_tolerancia`,
                    `valor_tolerancia`, `latitud`, `longitud`, `zoom_mapa`, `estado`, `creado_en`
                ) VALUES (
                    :empresa_id, :codigo, :nombre, :descripcion, :tipo_proyecto,
                    :moneda, :distrito_id, :direccion_referencia, :tipo_tolerancia,
                    :valor_tolerancia, :latitud, :longitud, :zoom_mapa, :estado, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $proyecto->obtenerEmpresaId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $proyecto->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $proyecto->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $proyecto->obtenerDescripcion(), $proyecto->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_proyecto', $proyecto->obtenerTipoProyecto(), PDO::PARAM_STR);
        $stmt->bindValue(':moneda', $proyecto->obtenerMoneda(), PDO::PARAM_STR);
        $stmt->bindValue(':distrito_id', $proyecto->obtenerDistritoId(), PDO::PARAM_INT);
        $stmt->bindValue(':direccion_referencia', $proyecto->obtenerDireccionReferencia(), $proyecto->obtenerDireccionReferencia() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_tolerancia', $proyecto->obtenerTipoTolerancia(), PDO::PARAM_STR);
        $stmt->bindValue(':valor_tolerancia', $proyecto->obtenerValorTolerancia());
        $stmt->bindValue(':latitud', $proyecto->obtenerLatitud());
        $stmt->bindValue(':longitud', $proyecto->obtenerLongitud());
        $stmt->bindValue(':zoom_mapa', $proyecto->obtenerZoomMapa(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $proyecto->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $id = (int) $conn->lastInsertId();
        $proyecto->asignarId($id);
        return $id;
    }

    public function actualizar(Proyecto $proyecto, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        // Regla: empresa_id, codigo y moneda son inmutables tras su creación.
        $sql = "UPDATE `proyectos`
                SET `nombre` = :nombre,
                    `descripcion` = :descripcion,
                    `tipo_proyecto` = :tipo_proyecto,
                    `distrito_id` = :distrito_id,
                    `direccion_referencia` = :direccion_referencia,
                    `tipo_tolerancia` = :tipo_tolerancia,
                    `valor_tolerancia` = :valor_tolerancia,
                    `latitud` = :latitud,
                    `longitud` = :longitud,
                    `zoom_mapa` = :zoom_mapa,
                    `actualizado_en` = NOW()
                WHERE `id` = :id AND `empresa_id` = :empresa_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $proyecto->obtenerNombre(), PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $proyecto->obtenerDescripcion(), $proyecto->obtenerDescripcion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_proyecto', $proyecto->obtenerTipoProyecto(), PDO::PARAM_STR);
        $stmt->bindValue(':distrito_id', $proyecto->obtenerDistritoId(), PDO::PARAM_INT);
        $stmt->bindValue(':direccion_referencia', $proyecto->obtenerDireccionReferencia(), $proyecto->obtenerDireccionReferencia() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_tolerancia', $proyecto->obtenerTipoTolerancia(), PDO::PARAM_STR);
        $stmt->bindValue(':valor_tolerancia', $proyecto->obtenerValorTolerancia());
        $stmt->bindValue(':latitud', $proyecto->obtenerLatitud());
        $stmt->bindValue(':longitud', $proyecto->obtenerLongitud());
        $stmt->bindValue(':zoom_mapa', $proyecto->obtenerZoomMapa(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $proyecto->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $proyecto->obtenerEmpresaId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function cambiarEstado(int $id, string $estado, int $empresaId, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `proyectos`
                SET `estado` = :estado,
                    `actualizado_en` = NOW()
                WHERE `id` = :id AND `empresa_id` = :empresa_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function buscarPorId(int $id, ?int $empresaId = null, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT p.*,
                       e.codigo AS empresa_codigo,
                       e.nombre_corto AS empresa_nombre,
                       d.nombre AS distrito_nombre,
                       d.codigo_ubigeo,
                       prov.id AS provincia_id,
                       prov.nombre AS provincia_nombre,
                       dep.id AS departamento_id,
                       dep.nombre AS departamento_nombre
                FROM `proyectos` p
                INNER JOIN `empresas` e ON e.id = p.empresa_id
                INNER JOIN `distritos` d ON d.id = p.distrito_id
                INNER JOIN `provincias` prov ON prov.id = d.provincia_id
                INNER JOIN `departamentos` dep ON dep.id = prov.departamento_id
                WHERE p.id = :id";

        if ($empresaId !== null) {
            $sql .= " AND p.empresa_id = :empresa_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if ($empresaId !== null) {
            $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function buscarPorCodigo(string $codigo, int $empresaId, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `proyectos` WHERE `codigo` = :codigo AND `empresa_id` = :empresa_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function existeCodigoEnEmpresa(string $codigo, int $empresaId, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `proyectos` WHERE `codigo` = :codigo AND `empresa_id` = :empresa_id";
        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarDataTables(int $empresaId, array $criterios, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);

        $draw = (int) ($criterios['draw'] ?? 1);
        $start = max(0, (int) ($criterios['start'] ?? 0));
        $length = max(1, min(100, (int) ($criterios['length'] ?? 10)));
        $search = trim((string) ($criterios['search'] ?? ''));
        $estado = trim((string) ($criterios['estado'] ?? ''));
        $tipoProyecto = trim((string) ($criterios['tipo_proyecto'] ?? ''));

        // Whitelist estricta de ordenamiento
        $columnasOrden = [
            'codigo'        => 'p.codigo',
            'nombre'        => 'p.nombre',
            'tipo_proyecto' => 'p.tipo_proyecto',
            'distrito'      => 'd.nombre',
            'estado'        => 'p.estado',
            'creado_en'     => 'p.creado_en',
            'id'            => 'p.id',
            0               => 'p.codigo',
            1               => 'p.nombre',
            2               => 'p.tipo_proyecto',
            3               => 'd.nombre',
            7               => 'p.estado'
        ];

        $orderParam = $criterios['order_by'] ?? '';
        if (is_numeric($orderParam) && isset($columnasOrden[(int) $orderParam])) {
            $orderCol = $columnasOrden[(int) $orderParam];
        } elseif (isset($columnasOrden[$orderParam])) {
            $orderCol = $columnasOrden[$orderParam];
        } else {
            $orderCol = 'p.id';
        }
        $orderDir = strtoupper((string) ($criterios['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        // 1. Total registros de la empresa (sin filtros de búsqueda)
        $sqlTotal = "SELECT COUNT(*) FROM `proyectos` WHERE `empresa_id` = :empresa_id";
        $stmtTotal = $conn->prepare($sqlTotal);
        $stmtTotal->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmtTotal->execute();
        $recordsTotal = (int) $stmtTotal->fetchColumn();

        // 2. Filtros dinámicos
        $where = ["p.empresa_id = :empresa_id"];
        $params = [':empresa_id' => $empresaId];

        if ($estado !== '') {
            $where[] = "p.estado = :estado";
            $params[':estado'] = $estado;
        }

        if ($tipoProyecto !== '') {
            $where[] = "p.tipo_proyecto = :tipo_proyecto";
            $params[':tipo_proyecto'] = $tipoProyecto;
        }

        if ($search !== '') {
            $where[] = "(p.codigo LIKE :b1 OR p.nombre LIKE :b2 OR p.descripcion LIKE :b3 OR d.nombre LIKE :b4)";
            $like = '%' . $search . '%';
            $params[':b1'] = $like;
            $params[':b2'] = $like;
            $params[':b3'] = $like;
            $params[':b4'] = $like;
        }

        $whereClause = implode(' AND ', $where);

        // 3. Conteo de registros filtrados
        $sqlFiltered = "SELECT COUNT(*)
                        FROM `proyectos` p
                        INNER JOIN `distritos` d ON d.id = p.distrito_id
                        WHERE {$whereClause}";
        $stmtFiltered = $conn->prepare($sqlFiltered);
        foreach ($params as $k => $v) {
            $stmtFiltered->bindValue($k, $v);
        }
        $stmtFiltered->execute();
        $recordsFiltered = (int) $stmtFiltered->fetchColumn();

        // 4. Datos con métricas consolidadas de predios matrices
        $sqlData = "SELECT p.*,
                           d.nombre AS distrito_nombre,
                           dep.nombre AS departamento_nombre,
                           COUNT(pm.id) AS total_predios,
                           COALESCE(SUM(pm.area_registral_m2), 0) AS area_registral_total_m2,
                           COALESCE(SUM(pm.area_topografica_m2), 0) AS area_topografica_total_m2,
                           SUM(CASE WHEN pm.area_topografica_m2 IS NULL AND pm.estado = 'ACTIVO' THEN 1 ELSE 0 END) AS predios_pendientes_levantamiento
                    FROM `proyectos` p
                    INNER JOIN `distritos` d ON d.id = p.distrito_id
                    INNER JOIN `provincias` prov ON prov.id = d.provincia_id
                    INNER JOIN `departamentos` dep ON dep.id = prov.departamento_id
                    LEFT JOIN `proyecto_predios_matriz` pm ON pm.proyecto_id = p.id AND pm.estado = 'ACTIVO'
                    WHERE {$whereClause}
                    GROUP BY p.id, d.nombre, dep.nombre
                    ORDER BY {$orderCol} {$orderDir}
                    LIMIT :start, :length";

        $stmtData = $conn->prepare($sqlData);
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':start', $start, PDO::PARAM_INT);
        $stmtData->bindValue(':length', $length, PDO::PARAM_INT);
        $stmtData->execute();
        $datos = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $datos
        ];
    }

    public function listarTodosActivos(int $empresaId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT p.id, p.codigo, p.nombre, p.tipo_proyecto, p.moneda, p.estado
                FROM `proyectos` p
                WHERE p.empresa_id = :empresa_id AND p.estado != 'INACTIVO'
                ORDER BY p.codigo ASC, p.nombre ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
