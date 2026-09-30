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
                    `version_autorizacion`, `debe_cambiar_password`, `ultimo_login_en`, `ultimo_login_ip`, `creado_en`
                ) VALUES (
                    :persona_id, :actor_id, :email, :nombre_usuario, :password_hash,
                    :estado, :intentos_fallidos, :ultimo_intento_fallido, :bloqueado_hasta,
                    :version_autorizacion, :debe_cambiar_password, :ultimo_login_en, :ultimo_login_ip, NOW()
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
        $stmt->bindValue(':debe_cambiar_password', $usuario->debeCambiarPassword() ? 1 : 0, PDO::PARAM_INT);
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

    public function buscarPorActorId(int $actorId, ?PDO $conexion = null): ?Usuario
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT * FROM `usuarios` WHERE `actor_id` = :actor_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':actor_id', $actorId, PDO::PARAM_INT);
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
        $sql = "SELECT `id`, `estado`, `version_autorizacion`, `debe_cambiar_password`, `actor_id`, `persona_id` 
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

    public function desbloquear(int $id, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `intentos_fallidos` = 0,
                    `ultimo_intento_fallido` = NULL,
                    `bloqueado_hasta` = NULL,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizarPasswordPersonal(int $id, string $nuevoHash, int $nuevaVersion, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `password_hash` = :password_hash,
                    `debe_cambiar_password` = 0,
                    `version_autorizacion` = :version,
                    `intentos_fallidos` = 0,
                    `ultimo_intento_fallido` = NULL,
                    `bloqueado_hasta` = NULL,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':password_hash', $nuevoHash, PDO::PARAM_STR);
        $stmt->bindValue(':version', $nuevaVersion, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function resetearPasswordAdministrativo(int $id, string $hashTemporal, int $nuevaVersion, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `password_hash` = :password_hash,
                    `debe_cambiar_password` = 1,
                    `version_autorizacion` = :version,
                    `intentos_fallidos` = 0,
                    `ultimo_intento_fallido` = NULL,
                    `bloqueado_hasta` = NULL,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':password_hash', $hashTemporal, PDO::PARAM_STR);
        $stmt->bindValue(':version', $nuevaVersion, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cambiarEstado(int $id, string $nuevoEstado, int $nuevaVersion, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `estado` = :estado,
                    `version_autorizacion` = :version,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmt->bindValue(':version', $nuevaVersion, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizarDatosGenerales(int $id, string $email, string $nombreUsuario, ?PDO $conexion = null): void
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET
                    `email` = :email,
                    `nombre_usuario` = :nombre_usuario,
                    `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_usuario', trim($nombreUsuario), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function obtenerPersonasDisponiblesParaUsuario(string $busqueda = '', int $limite = 20, ?PDO $conexion = null): array
    {
        $conn = $this->obtenerConexion($conexion);
        $termino = trim($busqueda);

        $sql = "SELECT p.`id`,
                       CONCAT(pn.`apellido_paterno`, ' ', IFNULL(CONCAT(pn.`apellido_materno`, ' '), ''), pn.`nombres`) AS `nombre_completo`,
                       pd.`numero_documento`,
                       td.`codigo` AS `tipo_documento`
                FROM `personas` p
                LEFT JOIN `persona_natural` pn ON pn.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1
                LEFT JOIN `tipos_documento` td ON td.`id` = pd.`tipo_documento_id`
                WHERE p.`tipo_persona` = 'NATURAL'
                  AND p.`estado` = 'ACTIVO'
                  AND p.`id` NOT IN (SELECT `persona_id` FROM `usuarios`)";

        if ($termino !== '') {
            $sql .= " AND (
                        pn.`nombres` LIKE :b1 OR
                        pn.`apellido_paterno` LIKE :b2 OR
                        pn.`apellido_materno` LIKE :b3 OR
                        pd.`numero_documento` LIKE :b4
                    )";
        }

        $sql .= " ORDER BY pn.`apellido_paterno` ASC, pn.`nombres` ASC LIMIT :limite";

        $stmt = $conn->prepare($sql);
        if ($termino !== '') {
            $like = '%' . $termino . '%';
            $stmt->bindValue(':b1', $like, PDO::PARAM_STR);
            $stmt->bindValue(':b2', $like, PDO::PARAM_STR);
            $stmt->bindValue(':b3', $like, PDO::PARAM_STR);
            $stmt->bindValue(':b4', $like, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerFichaSeguridad(int $id, ?PDO $conexion = null): ?array
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT u.`id`, u.`persona_id`, u.`actor_id`, u.`email`, u.`nombre_usuario`,
                       u.`estado`, u.`intentos_fallidos`, u.`ultimo_intento_fallido`,
                       u.`bloqueado_hasta`, u.`version_autorizacion`, u.`debe_cambiar_password`,
                       u.`ultimo_login_en`, u.`ultimo_login_ip`, u.`creado_en`, u.`actualizado_en`,
                       p.`tipo_persona`,
                       CONCAT(pn.`apellido_paterno`, ' ', IFNULL(CONCAT(pn.`apellido_materno`, ' '), ''), pn.`nombres`) AS `persona_nombre`,
                       pd.`numero_documento`,
                       td.`codigo` AS `documento_tipo`
                FROM `usuarios` u
                INNER JOIN `personas` p ON p.`id` = u.`persona_id`
                LEFT JOIN `persona_natural` pn ON pn.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1
                LEFT JOIN `tipos_documento` td ON td.`id` = pd.`tipo_documento_id`
                WHERE u.`id` = :id LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            return null;
        }

        // Roles asignados
        $sqlRoles = "SELECT r.`id`, r.`codigo`, r.`nombre`, r.`descripcion`, ur.`asignado_en`
                     FROM `roles` r
                     INNER JOIN `usuario_roles` ur ON ur.`rol_id` = r.`id`
                     WHERE ur.`usuario_id` = :id
                     ORDER BY r.`id` ASC";
        $stmtRoles = $conn->prepare($sqlRoles);
        $stmtRoles->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtRoles->execute();
        $usuario['roles'] = $stmtRoles->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Últimos 10 eventos de seguridad
        $sqlEventos = "SELECT `tipo_evento`, `ip`, `user_agent`, `metadatos`, `creado_en`
                       FROM `eventos_seguridad`
                       WHERE `usuario_id` = :id
                       ORDER BY `id` DESC LIMIT 10";
        $stmtEventos = $conn->prepare($sqlEventos);
        $stmtEventos->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtEventos->execute();
        $eventosRaw = $stmtEventos->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $eventos = [];
        foreach ($eventosRaw as $ev) {
            $ev['metadatos'] = !empty($ev['metadatos']) ? json_decode((string) $ev['metadatos'], true) : [];
            $eventos[] = $ev;
        }
        $usuario['eventos_recientes'] = $eventos;

        return $usuario;
    }

    public function contarTotal(?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "SELECT COUNT(*) FROM `usuarios`";
        return (int) $conn->query($sql)->fetchColumn();
    }

    public function contarFiltrados(array $criterios, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $condiciones = [];
        $params = [];

        $this->construirClausulaWhereDataTables($criterios, $condiciones, $params);

        $sql = "SELECT COUNT(DISTINCT u.`id`)
                FROM `usuarios` u
                INNER JOIN `personas` p ON p.`id` = u.`persona_id`
                LEFT JOIN `persona_natural` pn ON pn.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1
                LEFT JOIN `usuario_roles` ur ON ur.`usuario_id` = u.`id`";

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
            'nombre_usuario'   => 'u.`nombre_usuario`',
            'persona_nombre'   => 'persona_nombre',
            'email'            => 'u.`email`',
            'estado'           => 'u.`estado`',
            'ultimo_login_en'  => 'u.`ultimo_login_en`',
            'creado_en'        => 'u.`creado_en`'
        ];

        $columnaOrden = $columnasPermitidas[$criterios['order_by'] ?? ''] ?? 'u.`id`';
        $direccionOrden = strtoupper($criterios['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limite = max(1, (int) ($criterios['length'] ?? 10));
        $offset = max(0, (int) ($criterios['start'] ?? 0));

        $sql = "SELECT u.`id`, u.`nombre_usuario`, u.`email`, u.`estado`, u.`bloqueado_hasta`,
                       u.`intentos_fallidos`, u.`ultimo_login_en`, u.`ultimo_login_ip`,
                       u.`debe_cambiar_password`, u.`creado_en`,
                       u.`persona_id`,
                       MAX(CONCAT(pn.`apellido_paterno`, ' ', IFNULL(CONCAT(pn.`apellido_materno`, ' '), ''), pn.`nombres`)) AS `persona_nombre`,
                       MAX(pd.`numero_documento`) AS `numero_documento`,
                       GROUP_CONCAT(DISTINCT r.`nombre` ORDER BY r.`id` SEPARATOR '||') AS `roles_nombres`,
                       GROUP_CONCAT(DISTINCT r.`codigo` ORDER BY r.`id` SEPARATOR '||') AS `roles_codigos`
                FROM `usuarios` u
                INNER JOIN `personas` p ON p.`id` = u.`persona_id`
                LEFT JOIN `persona_natural` pn ON pn.`persona_id` = p.`id`
                LEFT JOIN `persona_documentos` pd ON pd.`persona_id` = p.`id` AND pd.`es_principal` = 1
                LEFT JOIN `usuario_roles` ur ON ur.`usuario_id` = u.`id`
                LEFT JOIN `roles` r ON r.`id` = ur.`rol_id`";



        if (!empty($condiciones)) {
            $sql .= " WHERE " . implode(' AND ', $condiciones);
        }

        $sql .= " GROUP BY u.`id` ORDER BY {$columnaOrden} {$direccionOrden} LIMIT :limite OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function construirClausulaWhereDataTables(array $criterios, array &$condiciones, array &$params): void
    {
        $estado = trim((string) ($criterios['estado'] ?? ''));
        if ($estado === 'BLOQUEADO') {
            $condiciones[] = "u.`bloqueado_hasta` IS NOT NULL AND u.`bloqueado_hasta` > NOW()";
        } elseif (in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            $condiciones[] = "u.`estado` = :filtro_estado";
            $params[':filtro_estado'] = $estado;
        }

        $rolId = (int) ($criterios['rol_id'] ?? 0);
        if ($rolId > 0) {
            $condiciones[] = "EXISTS (SELECT 1 FROM `usuario_roles` ur_f WHERE ur_f.`usuario_id` = u.`id` AND ur_f.`rol_id` = :filtro_rol_id)";
            $params[':filtro_rol_id'] = $rolId;
        }

        $busqueda = trim((string) ($criterios['search'] ?? ''));
        if ($busqueda !== '') {
            $condiciones[] = "(
                u.`nombre_usuario` LIKE :b1 OR
                u.`email` LIKE :b2 OR
                pn.`nombres` LIKE :b3 OR
                pn.`apellido_paterno` LIKE :b4 OR
                pn.`apellido_materno` LIKE :b5 OR
                pd.`numero_documento` LIKE :b6
            )";
            $like = '%' . $busqueda . '%';
            $params[':b1'] = $like;
            $params[':b2'] = $like;
            $params[':b3'] = $like;
            $params[':b4'] = $like;
            $params[':b5'] = $like;
            $params[':b6'] = $like;
        }
    }

    public function incrementarVersionAutorizacion(int $id, ?PDO $conexion = null): int
    {
        $conn = $this->obtenerConexion($conexion);
        $sql = "UPDATE `usuarios` SET `version_autorizacion` = `version_autorizacion` + 1, `actualizado_en` = NOW() WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt2 = $conn->prepare("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = :id");
        $stmt2->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt2->execute();
        return (int) $stmt2->fetchColumn();
    }
}

