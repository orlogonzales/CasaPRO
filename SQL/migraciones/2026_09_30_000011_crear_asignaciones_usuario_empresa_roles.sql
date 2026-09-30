-- =============================================================================
-- CasaPRO - Migración 000011: Crear asignaciones usuario_empresa_roles y privilegios
-- Microfase: 2B (Asignaciones Usuario ↔ Empresa y Scopes Territoriales)
-- Fecha: 2026-09-30
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Tabla: usuario_empresa_roles (Asignación granular de roles por empresa)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_empresa_roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a usuarios (cuenta autenticable)',
    `empresa_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a empresas (ámbito territorial corporativo)',
    `rol_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a roles (rol funcional otorgado dentro de la empresa)',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado de la asignación en el ciclo actual',
    `asignado_por` BIGINT UNSIGNED NOT NULL COMMENT 'FK a actores (actor que formalizó la asignación vigente)',
    `asignado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de alta del ciclo de vigencia actual',
    `revocado_por` BIGINT UNSIGNED NULL COMMENT 'FK a actores (actor que formalizó la baja lógica del ciclo)',
    `revocado_en` TIMESTAMP NULL COMMENT 'Fecha de baja lógica del ciclo actual',
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última mutación',
    UNIQUE KEY `uk_usuario_empresa_rol` (`usuario_id`, `empresa_id`, `rol_id`),
    KEY `idx_uer_usuario_estado` (`usuario_id`, `estado`),
    KEY `idx_uer_empresa_estado` (`empresa_id`, `estado`),
    KEY `idx_uer_rol` (`rol_id`),
    KEY `idx_uer_asignado_por` (`asignado_por`),
    KEY `idx_uer_revocado_por` (`revocado_por`),
    CONSTRAINT `fk_uer_usuario`
        FOREIGN KEY (`usuario_id`)
        REFERENCES `usuarios` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_uer_empresa`
        FOREIGN KEY (`empresa_id`)
        REFERENCES `empresas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_uer_rol`
        FOREIGN KEY (`rol_id`)
        REFERENCES `roles` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_uer_actor_asigna`
        FOREIGN KEY (`asignado_por`)
        REFERENCES `actores` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_uer_actor_revoca`
        FOREIGN KEY (`revocado_por`)
        REFERENCES `actores` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación granular de roles por empresa a usuarios bajo autorización multidimensional';

-- -----------------------------------------------------------------------------
-- 2. Catálogo Oficial de Privilegios para el Módulo de Asignaciones Territoriales
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
('asignaciones.ver', 'asignaciones', 'ver', 'Ver Asignaciones Territoriales', 'Permite consultar las asignaciones de empresas y roles de los usuarios'),
('asignaciones.crear', 'asignaciones', 'crear', 'Asignar Roles en Empresas', 'Permite vincular usuarios a empresas con roles específicos'),
('asignaciones.editar', 'asignaciones', 'editar', 'Modificar Asignaciones', 'Permite cambiar roles o estados de asignación de usuarios en empresas'),
('asignaciones.revocar', 'asignaciones', 'revocar', 'Revocar Asignaciones en Empresas', 'Permite dar de baja lógica roles o desvincular usuarios de empresas')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- -----------------------------------------------------------------------------
-- 3. Asignación automática de privilegios al rol SUPERADMIN (sin IDs mágicos)
-- -----------------------------------------------------------------------------
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `privilegios` p
WHERE r.codigo = 'SUPERADMIN'
  AND p.modulo = 'asignaciones'
ON DUPLICATE KEY UPDATE `rol_id` = `rol_id`;
