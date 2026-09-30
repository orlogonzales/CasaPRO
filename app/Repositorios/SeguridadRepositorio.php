<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\EventoSeguridad;
use PDO;

/**
 * SeguridadRepositorio — Persistencia para la bitácora técnica de eventos de seguridad.
 */
class SeguridadRepositorio
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

    public function registrarEvento(EventoSeguridad $evento, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "INSERT INTO `eventos_seguridad` (
                    `tipo_evento`, `usuario_id`, `identificador_intento`, `ip`, `user_agent`, `metadatos`, `creado_en`
                ) VALUES (
                    :tipo_evento, :usuario_id, :identificador_intento, :ip, :user_agent, :metadatos, NOW()
                )";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tipo_evento', $evento->obtenerTipoEvento(), PDO::PARAM_STR);
        $stmt->bindValue(':usuario_id', $evento->obtenerUsuarioId(), $evento->obtenerUsuarioId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':identificador_intento', $evento->obtenerIdentificadorIntento(), $evento->obtenerIdentificadorIntento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ip', $evento->obtenerIp(), $evento->obtenerIp() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':user_agent', $evento->obtenerUserAgent(), $evento->obtenerUserAgent() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $metadatos = $evento->obtenerMetadatos();
        $jsonMetadatos = $metadatos !== null ? json_encode($metadatos, JSON_UNESCAPED_UNICODE) : null;
        $stmt->bindValue(':metadatos', $jsonMetadatos, $jsonMetadatos !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->execute();
        $id = (int) $conn->lastInsertId();
        $evento->asignarId($id);
        return $id;
    }

    public function obtenerUltimoEventoPorTipo(string $tipo, ?string $identificador = null, ?PDO $conexion = null): ?EventoSeguridad
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `eventos_seguridad` WHERE `tipo_evento` = :tipo";
        if ($identificador !== null) {
            $sql .= " AND `identificador_intento` = :identificador";
        }
        $sql .= " ORDER BY `id` DESC LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        if ($identificador !== null) {
            $stmt->bindValue(':identificador', $identificador, PDO::PARAM_STR);
        }
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? EventoSeguridad::desdeArray($fila) : null;
    }
}
