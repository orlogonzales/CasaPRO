<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\Usuario;
use PDO;

/**
 * UsuarioRepositorio — Persistencia PDO nativa para cuentas de usuario.
 * Cero concatenación de parámetros y sentencias preparadas estrictas.
 */
class UsuarioRepositorio
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

    public function insertar(Usuario $usuario, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `usuarios` (
                    `persona_id`, `actor_id`, `email`, `nombre_usuario`, `password_hash`,
                    `estado`, `intentos_fallidos`, `ultimo_intento_fallido`, `bloqueado_hasta`,
                    `version_autorizacion`, `ultimo_login_en`, `ultimo_login_ip`, `creado_en`
                ) VALUES (
                    :persona_id, :actor_id, :email, :nombre_usuario, :password_hash,
                    :estado, :intentos_fallidos, :ultimo_intento_fallido, :bloqueado_hasta,
                    :version_autorizacion, :ultimo_login_en, :ultimo_login_ip, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':persona_id', $usuario->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':actor_id', $usuario->obtenerActorId(), PDO::PARAM_INT);
        $stmt->bindValue(':email', $usuario->obtenerEmail(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_usuario', $usuario->obtenerNombreUsuario(), PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $usuario->obtenerPasswordHash(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $usuario->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':intentos_fallidos', $usuario->obtenerIntentosFallidos(), PDO::PARAM_INT);
        $stmt->bindValue(':ultimo_intento_fallido', $usuario->obtenerUltimoIntentoFallido(), $usuario->obtenerUltimoIntentoFallido() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':bloqueado_hasta', $usuario->obtenerBloqueadoHasta(), $usuario->obtenerBloqueadoHasta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':version_autorizacion', $usuario->obtenerVersionAutorizacion(), PDO::PARAM_INT);
        $stmt->bindValue(':ultimo_login_en', $usuario->obtenerUltimoLoginEn(), $usuario->obtenerUltimoLoginEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ultimo_login_ip', $usuario->obtenerUltimoLoginIp(), $usuario->obtenerUltimoLoginIp() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        $id = (int) $conn->lastInsertId();
        $usuario->asignarId($id);
        return $id;
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?Usuario
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuarios` WHERE `id` = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Usuario::desdeArray($fila) : null;
    }

    public function buscarPorPersonaId(int $personaId, ?PDO $conexion = null): ?Usuario
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuarios` WHERE `persona_id` = :persona_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Usuario::desdeArray($fila) : null;
    }

    public function buscarPorIdentificador(string $identificador, bool $conLock = false, ?PDO $conexion = null): ?Usuario
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuarios` 
                WHERE `email` = :id_email OR `nombre_usuario` = :id_user 
                LIMIT 1" . ($conLock ? " FOR UPDATE" : "");

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_email', strtolower(trim($identificador)), PDO::PARAM_STR);
        $stmt->bindValue(':id_user', trim($identificador), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? Usuario::desdeArray($fila) : null;
    }

    public function actualizarSeguridad(Usuario $usuario, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `password_hash` = :password_hash,
                    `intentos_fallidos` = :intentos_fallidos,
                    `ultimo_intento_fallido` = :ultimo_intento_fallido,
                    `bloqueado_hasta` = :bloqueado_hasta,
                    `ultimo_login_en` = :ultimo_login_en,
                    `ultimo_login_ip` = :ultimo_login_ip,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':password_hash', $usuario->obtenerPasswordHash(), PDO::PARAM_STR);
        $stmt->bindValue(':intentos_fallidos', $usuario->obtenerIntentosFallidos(), PDO::PARAM_INT);
        $stmt->bindValue(':ultimo_intento_fallido', $usuario->obtenerUltimoIntentoFallido(), $usuario->obtenerUltimoIntentoFallido() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':bloqueado_hasta', $usuario->obtenerBloqueadoHasta(), $usuario->obtenerBloqueadoHasta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ultimo_login_en', $usuario->obtenerUltimoLoginEn(), $usuario->obtenerUltimoLoginEn() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ultimo_login_ip', $usuario->obtenerUltimoLoginIp(), $usuario->obtenerUltimoLoginIp() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $usuario->obtenerId(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizarVersionAutorizacion(int $id, int $nuevaVersion, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET `version_autorizacion` = :version WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':version', $nuevaVersion, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function obtenerControlSesion(int $id, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT `id`, `estado`, `version_autorizacion`, `actor_id`, `persona_id` 
                FROM `usuarios` WHERE `id` = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function asignarRol(int $usuarioId, int $rolId, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`, `asignado_en`)
                VALUES (:usuario_id, :rol_id, NOW())
                ON DUPLICATE KEY UPDATE `asignado_en` = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function obtenerCodigosRoles(int $usuarioId, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT r.codigo 
                FROM `roles` r
                INNER JOIN `usuario_roles` ur ON ur.rol_id = r.id
                WHERE ur.usuario_id = :usuario_id AND r.estado = 'ACTIVO'";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function existeEmail(string $email, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `usuarios` WHERE `email` = :email" . ($excluirId !== null ? " AND `id` != :excluir_id" : "");
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function existeNombreUsuario(string $nombreUsuario, ?int $excluirId = null, ?PDO $conexion = null): bool
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `usuarios` WHERE `nombre_usuario` = :nombre_usuario" . ($excluirId !== null ? " AND `id` != :excluir_id" : "");
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre_usuario', trim($nombreUsuario), PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindValue(':excluir_id', $excluirId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
