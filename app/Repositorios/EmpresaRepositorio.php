<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\Empresa;
use PDO;

/**
 * EmpresaRepositorio — Persistencia PDO nativa para entidades corporativas (Empresas).
 * 
 * Reglas inviolables:
 * - Sentencias preparadas y parámetros vinculados nativos. Cero concatenación SQL.
 * - Conexión inyectable para soportar transacciones atómicas externas.
 * - Inexistencia deliberada de sentencias DELETE o métodos de eliminación física.
 */
class EmpresaRepositorio
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

    public function insertar(Empresa $empresa, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `empresas` (
                    `persona_id`, `codigo`, `nombre_corto`, `estado`, `creado_en`
                ) VALUES (
                    :persona_id, :codigo, :nombre_corto, :estado, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':persona_id', $empresa->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':codigo', $empresa->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_corto', $empresa->obtenerNombreCorto(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $empresa->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        $id = (int) $conn->lastInsertId();
        $empresa->asignarId($id);
        return $id;
    }

    public function actualizar(Empresa $empresa, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        // Regla: persona_id y codigo son inmutables. Solo se actualiza nombre_corto.
        $sql = "UPDATE `empresas`
                SET `nombre_corto` = :nombre_corto,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_corto', $empresa->obtenerNombreCorto(), PDO::PARAM_STR);
        $stmt->bindValue(':id', $empresa->obtenerId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function cambiarEstado(int $id, string $estado, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `empresas`
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
        $sql = "SELECT e.*,
                       p.tipo_persona,
                       p.estado AS estado_persona,
                       pj.razon_social,
                       pj.nombre_comercial,
                       pj.fecha_constitucion,
                       pj.objeto_social,
                       pd.numero_documento AS ruc
                FROM `empresas` e
                INNER JOIN `personas` p ON p.id = e.persona_id
                LEFT JOIN `persona_juridica` pj ON pj.persona_id = p.id
                LEFT JOIN `persona_documentos` pd ON pd.persona_id = p.id AND pd.es_principal = 1 AND pd.estado = 'ACTIVO'
                WHERE e.id = :id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function buscarPorCodigo(string $codigo, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT e.*,
                       p.tipo_persona,
                       p.estado AS estado_persona,
                       pj.razon_social,
                       pj.nombre_comercial,
                       pd.numero_documento AS ruc
                FROM `empresas` e
                INNER JOIN `personas` p ON p.id = e.persona_id
                LEFT JOIN `persona_juridica` pj ON pj.persona_id = p.id
                LEFT JOIN `persona_documentos` pd ON pd.persona_id = p.id AND pd.es_principal = 1 AND pd.estado = 'ACTIVO'
                WHERE e.codigo = :codigo
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function buscarPorPersonaId(int $personaId, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT e.*,
                       p.tipo_persona,
                       p.estado AS estado_persona,
                       pj.razon_social,
                       pj.nombre_comercial,
                       pd.numero_documento AS ruc
                FROM `empresas` e
                INNER JOIN `personas` p ON p.id = e.persona_id
                LEFT JOIN `persona_juridica` pj ON pj.persona_id = p.id
                LEFT JOIN `persona_documentos` pd ON pd.persona_id = p.id AND pd.es_principal = 1 AND pd.estado = 'ACTIVO'
                WHERE e.persona_id = :persona_id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function existeCodigo(string $codigo, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `empresas` WHERE `codigo` = :codigo";
        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function existePorPersonaId(int $personaId, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `empresas` WHERE `persona_id` = :persona_id";
        if ($excluirId !== null) {
            $sql .= " AND `id` != :excluir_id";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function listarTodas(?string $filtroEstado = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT e.*,
                       p.tipo_persona,
                       p.estado AS estado_persona,
                       pj.razon_social,
                       pj.nombre_comercial,
                       pd.numero_documento AS ruc
                FROM `empresas` e
                INNER JOIN `personas` p ON p.id = e.persona_id
                LEFT JOIN `persona_juridica` pj ON pj.persona_id = p.id
                LEFT JOIN `persona_documentos` pd ON pd.persona_id = p.id AND pd.es_principal = 1 AND pd.estado = 'ACTIVO'";

        if ($filtroEstado !== null) {
            $sql .= " WHERE e.estado = :estado";
        }

        $sql .= " ORDER BY e.nombre_corto ASC, e.id ASC";

        $stmt = $conn->prepare($sql);
        if ($filtroEstado !== null) {
            $stmt->bindValue(':estado', $filtroEstado, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarTotal(?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `empresas`";
        return (int) $conn->query($sql)->fetchColumn();
    }

    public function contarFiltrados(array $criterios, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $condiciones = [];
        $params = [];

        $this->construirClausulaWhereDataTables($criterios, $condiciones, $params);

        $sql = "SELECT COUNT(DISTINCT e.`id`)
                FROM `empresas` e
                INNER JOIN `personas` p ON p.`id` = e.`persona_id`
                LEFT JOIN `persona_juridica` pj ON pj.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1 AND pd.`estado` = 'ACTIVO'";

        if (!empty($condiciones)) {
            $sql .= " WHERE " . implode(' AND ', $condiciones);
        }

        $stmt = $conn->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function obtenerListadoDataTables(array $criterios, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $condiciones = [];
        $params = [];

        $this->construirClausulaWhereDataTables($criterios, $condiciones, $params);

        $columnasPermitidas = [
            'codigo'       => 'e.`codigo`',
            'nombre_corto' => 'e.`nombre_corto`',
            'razon_social' => 'pj.`razon_social`',
            'ruc'          => 'pd.`numero_documento`',
            'estado'       => 'e.`estado`',
            'creado_en'    => 'e.`creado_en`'
        ];

        $columnaOrden = $columnasPermitidas[$criterios['order_by'] ?? ''] ?? 'e.`id`';
        $direccionOrden = strtoupper($criterios['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limite = max(1, (int) ($criterios['length'] ?? 10));
        $inicio = max(0, (int) ($criterios['start'] ?? 0));

        $sql = "SELECT e.`id`,
                       e.`persona_id`,
                       e.`codigo`,
                       e.`nombre_corto`,
                       e.`estado`,
                       e.`creado_en`,
                       e.`actualizado_en`,
                       pj.`razon_social`,
                       pj.`nombre_comercial`,
                       pd.`numero_documento` AS `ruc`
                FROM `empresas` e
                INNER JOIN `personas` p ON p.`id` = e.`persona_id`
                LEFT JOIN `persona_juridica` pj ON pj.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1 AND pd.`estado` = 'ACTIVO'";

        if (!empty($condiciones)) {
            $sql .= " WHERE " . implode(' AND ', $condiciones);
        }

        $sql .= " ORDER BY {$columnaOrden} {$direccionOrden} LIMIT :limite OFFSET :inicio";

        $stmt = $conn->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function construirClausulaWhereDataTables(array $criterios, array &$condiciones, array &$params): void
    {
        $estado = strtoupper(trim((string) ($criterios['estado'] ?? '')));
        if (in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            $condiciones[] = "e.`estado` = :filtro_estado";
            $params[':filtro_estado'] = $estado;
        }

        $busqueda = trim((string) ($criterios['search'] ?? ''));
        if ($busqueda !== '') {
            $condiciones[] = "(
                e.`codigo` LIKE :b1 OR
                e.`nombre_corto` LIKE :b2 OR
                pj.`razon_social` LIKE :b3 OR
                pj.`nombre_comercial` LIKE :b4 OR
                pd.`numero_documento` LIKE :b5
            )";
            $like = '%' . $busqueda . '%';
            $params[':b1'] = $like;
            $params[':b2'] = $like;
            $params[':b3'] = $like;
            $params[':b4'] = $like;
            $params[':b5'] = $like;
        }
    }

    public function obtenerPersonasJuridicasDisponibles(string $busqueda = '', int $limite = 20, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $termino = trim($busqueda);

        $sql = "SELECT p.`id`,
                       pj.`razon_social`,
                       pj.`nombre_comercial`,
                       pd.`numero_documento` AS `ruc`
                FROM `personas` p
                INNER JOIN `persona_juridica` pj ON pj.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1 AND pd.`estado` = 'ACTIVO'
                WHERE p.`tipo_persona` = 'JURIDICA'
                  AND p.`estado` = 'ACTIVO'
                  AND p.`id` NOT IN (SELECT `persona_id` FROM `empresas`)";

        $params = [];
        if ($termino !== '') {
            $sql .= " AND (pj.`razon_social` LIKE :b1 OR pj.`nombre_comercial` LIKE :b2 OR pd.`numero_documento` LIKE :b3)";
            $like = '%' . $termino . '%';
            $params[':b1'] = $like;
            $params[':b2'] = $like;
            $params[':b3'] = $like;
        }

        $sql .= " ORDER BY pj.`razon_social` ASC LIMIT :limite";

        $stmt = $conn->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, min(100, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
