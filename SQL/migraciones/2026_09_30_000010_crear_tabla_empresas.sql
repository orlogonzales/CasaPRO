-- =============================================================================
-- CasaPRO - Migración 000010: Crear tabla empresas y privilegios del módulo
-- Microfase: 2A (Dominio de Empresas y Vinculación Corporativa)
-- Fecha: 2026-09-30
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Tabla: empresas (Identidad operativa y entidades corporativas administradas)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empresas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK 1:1 a personas (tipo_persona = JURIDICA)',
    `codigo` VARCHAR(32) NOT NULL COMMENT 'Identificador canónico corporativo inmutable (ej: MATRIZ_BONIFACIO, CASAPRO)',
    `nombre_corto` VARCHAR(64) NOT NULL COMMENT 'Nombre operativo compacto para selectores, topbar y reportes',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado operativo en la plataforma',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_empresas_persona` (`persona_id`),
    UNIQUE KEY `uk_empresas_codigo` (`codigo`),
    KEY `idx_empresas_estado` (`estado`),
    CONSTRAINT `fk_empresas_persona`
        FOREIGN KEY (`persona_id`)
        REFERENCES `personas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entidades corporativas bajo administración del sistema';

-- -----------------------------------------------------------------------------
-- 2. Catálogo Oficial de Privilegios para el Módulo de Empresas
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
('empresas.ver', 'empresas', 'ver', 'Ver Empresas', 'Permite consultar el catálogo y detalle operativo de empresas'),
('empresas.crear', 'empresas', 'crear', 'Crear Empresa', 'Permite dar de alta nuevas empresas y vincular personas jurídicas'),
('empresas.editar', 'empresas', 'editar', 'Editar Empresa', 'Permite modificar la configuración operativa de la empresa'),
('empresas.cambiar_estado', 'empresas', 'cambiar_estado', 'Cambiar Estado de Empresa', 'Permite activar o desactivar empresas del sistema')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- -----------------------------------------------------------------------------
-- 3. Asignación automática de privilegios al rol SUPERADMIN (sin IDs mágicos)
-- -----------------------------------------------------------------------------
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `privilegios` p
WHERE r.codigo = 'SUPERADMIN'
  AND p.modulo = 'empresas'
ON DUPLICATE KEY UPDATE `rol_id` = `rol_id`;
