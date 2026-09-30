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
}
