<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\UsuarioEmpresaRol;
use PDO;

/**
 * AsignacionTerritorialRepositorio — Persistencia PDO nativa para asignaciones
 * de usuarios a empresas y roles bajo autorización multidimensional.
 *
 * Reglas inviolables:
 * - Sentencias preparadas nativas y parámetros vinculados. Cero concatenación SQL.
 * - Conexión inyectable para soportar transacciones atómicas externas.
 * - Inexistencia deliberada de sentencias DELETE (baja lógica vía estado INACTIVO).
 */
class AsignacionTerritorialRepositorio
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

    public function buscarPorTupla(int $usuarioId, int $empresaId, int $rolId, ?PDO $conexion = null): ?UsuarioEmpresaRol
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuario_empresa_roles`
                WHERE `usuario_id` = :usuario_id
                  AND `empresa_id` = :empresa_id
                  AND `rol_id` = :rol_id
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? UsuarioEmpresaRol::desdeArray($fila) : null;
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?UsuarioEmpresaRol
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuario_empresa_roles` WHERE `id` = :id LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? UsuarioEmpresaRol::desdeArray($fila) : null;
    }

    public function insertar(UsuarioEmpresaRol $asignacion, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `usuario_empresa_roles` (
                    `usuario_id`, `empresa_id`, `rol_id`, `estado`,
                    `asignado_por`, `asignado_en`, `revocado_por`, `revocado_en`, `actualizado_en`
                ) VALUES (
                    :usuario_id, :empresa_id, :rol_id, :estado,
                    :asignado_por, NOW(), NULL, NULL, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $asignacion->obtenerUsuarioId(), PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $asignacion->obtenerEmpresaId(), PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $asignacion->obtenerRolId(), PDO::PARAM_INT);
        $stmt->bindValue(':estado', $asignacion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':asignado_por', $asignacion->obtenerAsignadoPor(), PDO::PARAM_INT);
        $stmt->execute();

        $id = (int) $conn->lastInsertId();
        $asignacion->asignarId($id);
        return $id;
    }

    public function actualizar(UsuarioEmpresaRol $asignacion, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuario_empresa_roles`
                SET `estado` = :estado,
                    `asignado_por` = :asignado_por,
                    `asignado_en` = :asignado_en,
                    `revocado_por` = :revocado_por,
                    `revocado_en` = :revocado_en,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':estado', $asignacion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':asignado_por', $asignacion->obtenerAsignadoPor(), PDO::PARAM_INT);
        $stmt->bindValue(':asignado_en', $asignacion->obtenerAsignadoEn(), $asignacion->obtenerAsignadoEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':revocado_por', $asignacion->obtenerRevocadoPor(), $asignacion->obtenerRevocadoPor() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':revocado_en', $asignacion->obtenerRevocadoEn(), $asignacion->obtenerRevocadoEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $asignacion->obtenerId(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function obtenerCodigosRolesUsuarioEnEmpresa(int $usuarioId, int $empresaId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT r.`codigo`
                FROM `roles` r
                INNER JOIN `usuario_empresa_roles` uer ON uer.`rol_id` = r.`id`
                INNER JOIN `empresas` e ON e.`id` = uer.`empresa_id`
                WHERE uer.`usuario_id` = :usuario_id
                  AND uer.`empresa_id` = :empresa_id
                  AND uer.`estado` = 'ACTIVO'
                  AND r.`estado` = 'ACTIVO'
                  AND e.`estado` = 'ACTIVO'
                ORDER BY r.`id` ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function obtenerCodigosPrivilegiosUsuarioEnEmpresa(int $usuarioId, int $empresaId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT DISTINCT p.`codigo`
                FROM `privilegios` p
                INNER JOIN `rol_privilegios` rp ON rp.`privilegio_id` = p.`id`
                INNER JOIN `roles` r ON r.`id` = rp.`rol_id`
                INNER JOIN `usuario_empresa_roles` uer ON uer.`rol_id` = r.`id`
                INNER JOIN `empresas` e ON e.`id` = uer.`empresa_id`
                WHERE uer.`usuario_id` = :usuario_id
                  AND uer.`empresa_id` = :empresa_id
                  AND uer.`estado` = 'ACTIVO'
                  AND r.`estado` = 'ACTIVO'
                  AND e.`estado` = 'ACTIVO'
                ORDER BY p.`codigo` ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function obtenerEmpresasDisponiblesParaUsuario(int $usuarioId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT DISTINCT e.`id`, e.`codigo`, e.`nombre_corto`, e.`estado`
                FROM `empresas` e
                INNER JOIN `usuario_empresa_roles` uer ON uer.`empresa_id` = e.`id`
                WHERE uer.`usuario_id` = :usuario_id
                  AND uer.`estado` = 'ACTIVO'
                  AND e.`estado` = 'ACTIVO'
                ORDER BY e.`codigo` ASC, e.`nombre_corto` ASC, e.`id` ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function usuarioTieneAccesoEmpresa(int $usuarioId, int $empresaId, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*)
                FROM `usuario_empresa_roles` uer
                INNER JOIN `empresas` e ON e.`id` = uer.`empresa_id`
                INNER JOIN `roles` r ON r.`id` = uer.`rol_id`
                WHERE uer.`usuario_id` = :usuario_id
                  AND uer.`empresa_id` = :empresa_id
                  AND uer.`estado` = 'ACTIVO'
                  AND e.`estado` = 'ACTIVO'
                  AND r.`estado` = 'ACTIVO'";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function contarAsignacionesActivas(int $usuarioId, int $empresaId, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*)
                FROM `usuario_empresa_roles`
                WHERE `usuario_id` = :usuario_id
                  AND `empresa_id` = :empresa_id
                  AND `estado` = 'ACTIVO'";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function listarPorUsuario(int $usuarioId, ?string $estado = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT uer.*,
                       e.`codigo` AS empresa_codigo,
                       e.`nombre_corto` AS empresa_nombre,
                       e.`estado` AS empresa_estado,
                       r.`codigo` AS rol_codigo,
                       r.`nombre` AS rol_nombre,
                       aa.`nombre` AS asignado_por_nombre,
                       ar.`nombre` AS revocado_por_nombre
                FROM `usuario_empresa_roles` uer
                INNER JOIN `empresas` e ON e.`id` = uer.`empresa_id`
                INNER JOIN `roles` r ON r.`id` = uer.`rol_id`
                INNER JOIN `actores` aa ON aa.`id` = uer.`asignado_por`
                LEFT JOIN `actores` ar ON ar.`id` = uer.`revocado_por`
                WHERE uer.`usuario_id` = :usuario_id";

        if ($estado !== null) {
            $sql .= " AND uer.`estado` = :estado";
        }

        $sql .= " ORDER BY e.`nombre_corto` ASC, r.`id` ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarPorEmpresa(int $empresaId, ?string $estado = null, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT uer.*,
                       u.`nombre_usuario`,
                       u.`email`,
                       u.`estado` AS usuario_estado,
                       r.`codigo` AS rol_codigo,
                       r.`nombre` AS rol_nombre,
                       aa.`nombre` AS asignado_por_nombre,
                       ar.`nombre` AS revocado_por_nombre
                FROM `usuario_empresa_roles` uer
                INNER JOIN `usuarios` u ON u.`id` = uer.`usuario_id`
                INNER JOIN `roles` r ON r.`id` = uer.`rol_id`
                INNER JOIN `actores` aa ON aa.`id` = uer.`asignado_por`
                LEFT JOIN `actores` ar ON ar.`id` = uer.`revocado_por`
                WHERE uer.`empresa_id` = :empresa_id";

        if ($estado !== null) {
            $sql .= " AND uer.`estado` = :estado";
        }

        $sql .= " ORDER BY u.`nombre_usuario` ASC, r.`id` ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        if ($estado !== null) {
            $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
