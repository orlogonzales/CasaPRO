<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\Rol;
use PDO;

/**
 * RolRepositorio — Persistencia y consultas para roles y privilegios RBAC.
 */
class RolRepositorio
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

    public function buscarPorId(int $id, ?PDO $conexion = null): ?Rol
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `roles` WHERE `id` = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Rol::desdeArray($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, ?PDO $conexion = null): ?Rol
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `roles` WHERE `codigo` = :codigo LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigo)), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Rol::desdeArray($fila) : null;
    }

    public function contarSuperadmins(?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(DISTINCT ur.usuario_id) 
                FROM `usuario_roles` ur
                INNER JOIN `roles` r ON r.id = ur.rol_id
                INNER JOIN `usuarios` u ON u.id = ur.usuario_id
                WHERE r.codigo = 'SUPERADMIN' AND u.estado = 'ACTIVO'";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function obtenerCodigosPrivilegiosPorUsuario(int $usuarioId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT DISTINCT p.codigo
                FROM `privilegios` p
                INNER JOIN `rol_privilegios` rp ON rp.privilegio_id = p.id
                INNER JOIN `roles` r ON r.id = rp.rol_id AND r.estado = 'ACTIVO'
                INNER JOIN `usuario_roles` ur ON ur.rol_id = r.id
                WHERE ur.usuario_id = :usuario_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function asociarPrivilegio(int $rolId, int $privilegioId, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`, `creado_en`)
                VALUES (:rol_id, :privilegio_id, NOW())
                ON DUPLICATE KEY UPDATE `creado_en` = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->bindValue(':privilegio_id', $privilegioId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function listarTodosActivos(?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT `id`, `codigo`, `nombre`, `descripcion`, `es_sistema`, `estado`
                FROM `roles`
                WHERE `estado` = 'ACTIVO'
                ORDER BY `id` ASC";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerRolesPorUsuario(int $usuarioId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT r.`id`, r.`codigo`, r.`nombre`, r.`descripcion`, r.`es_sistema`, r.`estado`, ur.`asignado_en`
                FROM `roles` r
                INNER JOIN `usuario_roles` ur ON ur.rol_id = r.id
                WHERE ur.usuario_id = :usuario_id
                ORDER BY r.`id` ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function usuarioTieneRolCodigo(int $usuarioId, string $codigoRol, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*)
                FROM `usuario_roles` ur
                INNER JOIN `roles` r ON r.id = ur.rol_id
                WHERE ur.usuario_id = :usuario_id AND r.codigo = :codigo";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':codigo', strtoupper(trim($codigoRol)), PDO::PARAM_STR);
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function validarIdsRolesActivos(array $rolIds, ?PDO $conexion = null): array
    {
        if (empty($rolIds)) {
            return [];
        }
        $conn = $this->obtenerConexion($conexion);
        $inParams = implode(',', array_fill(0, count($rolIds), '?'));
        $sql = "SELECT `id`, `codigo`, `nombre`, `estado`
                FROM `roles`
                WHERE `id` IN ($inParams)";
        $stmt = $conn->prepare($sql);
        foreach (array_values($rolIds) as $idx => $id) {
            $stmt->bindValue($idx + 1, (int) $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sincronizarRolesUsuario(int $usuarioId, array $nuevosRolIds, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        
        // 1. Obtener roles actuales del usuario
        $sqlActuales = "SELECT `rol_id` FROM `usuario_roles` WHERE `usuario_id` = :usuario_id";
        $stmtActuales = $conn->prepare($sqlActuales);
        $stmtActuales->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmtActuales->execute();
        $actuales = array_map('intval', $stmtActuales->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $nuevos = array_map('intval', array_unique($nuevosRolIds));

        $aEliminar = array_diff($actuales, $nuevos);
        $aInsertar = array_diff($nuevos, $actuales);

        if (!empty($aEliminar)) {
            $inEliminar = implode(',', array_fill(0, count($aEliminar), '?'));
            $sqlDelete = "DELETE FROM `usuario_roles` WHERE `usuario_id` = ? AND `rol_id` IN ($inEliminar)";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->bindValue(1, $usuarioId, PDO::PARAM_INT);
            $pos = 2;
            foreach ($aEliminar as $rolId) {
                $stmtDelete->bindValue($pos++, $rolId, PDO::PARAM_INT);
            }
            $stmtDelete->execute();
        }

        if (!empty($aInsertar)) {
            $sqlInsert = "INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`, `asignado_en`) VALUES (:usuario_id, :rol_id, NOW())";
            $stmtInsert = $conn->prepare($sqlInsert);
            foreach ($aInsertar as $rolId) {
                $stmtInsert->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
                $stmtInsert->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
                $stmtInsert->execute();
            }
        }
    }

    public function contarUsuariosActivosConRolCodigo(string $codigoRol, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(DISTINCT u.id)
                FROM `usuarios` u
                INNER JOIN `usuario_roles` ur ON ur.usuario_id = u.id
                INNER JOIN `roles` r ON r.id = ur.rol_id
                WHERE r.codigo = :codigo AND u.estado = 'ACTIVO' AND r.estado = 'ACTIVO'";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':codigo', strtoupper(trim($codigoRol)), PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}


