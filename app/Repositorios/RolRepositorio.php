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
}
