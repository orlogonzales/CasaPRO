-- =============================================================================
-- Migración 000014: Crear Tablas de Sectores Urbanísticos e Histórico de Precios
-- Microfase: 3B (Sectores, Precios Históricos y Balance de Áreas)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Tabla: sectores (Etapas y sectores urbanísticos del proyecto)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sectores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proyecto_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a proyectos (ámbito del proyecto matriz)',
    `codigo` VARCHAR(32) NOT NULL COMMENT 'Código alfanumérico inmutable del sector (ej: SEC_LOS_ALAMOS, ETAPA_1)',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre comercial u operativo del sector',
    `descripcion` TEXT NULL COMMENT 'Memoria descriptiva o características del sector',
    `area_bruta_m2` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Área total física asignada al sector',
    `area_util_m2` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Área neta vendible proyectada para lotes',
    `area_cesion_m2` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Vías locales, parques y aportes reglamentarios',
    `area_comun_m2` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Áreas comunes privadas y servidumbres',
    `orden` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Orden determinista para visualización',
    `estado` ENUM('EN_DESARROLLO', 'EN_VENTA', 'CONSOLIDADO', 'CERRADO', 'INACTIVO') NOT NULL DEFAULT 'EN_DESARROLLO' COMMENT 'Ciclo de vida del sector',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_sectores_proyecto_codigo` (`proyecto_id`, `codigo`),
    KEY `idx_sectores_proyecto` (`proyecto_id`),
    KEY `idx_sectores_estado` (`estado`),
    KEY `idx_sectores_orden` (`orden`),
    CONSTRAINT `fk_sectores_proyecto`
        FOREIGN KEY (`proyecto_id`)
        REFERENCES `proyectos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sectores y etapas urbanísticas de proyectos inmobiliarios';

-- -----------------------------------------------------------------------------
-- 2. Tabla: sector_precios_historico (Histórico temporal de precios base por m2)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sector_precios_historico` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sector_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a sectores',
    `precio_m2_base` DECIMAL(12,4) NOT NULL COMMENT 'Precio base por metro cuadrado',
    `moneda` ENUM('PEN', 'USD') NOT NULL COMMENT 'Moneda soberana coincidente con el proyecto',
    `fecha_inicio` DATE NOT NULL COMMENT 'Fecha desde la cual rige el precio',
    `fecha_fin` DATE NULL COMMENT 'Fecha fin de vigencia (NULL = precio vigente)',
    `motivo` VARCHAR(255) NOT NULL COMMENT 'Justificación comercial o técnica del precio',
    `creado_por` BIGINT UNSIGNED NOT NULL COMMENT 'FK a actores (usuario que fijó el precio)',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_sph_sector` (`sector_id`),
    KEY `idx_sph_vigencia` (`sector_id`, `fecha_fin`),
    CONSTRAINT `fk_sph_sector`
        FOREIGN KEY (`sector_id`)
        REFERENCES `sectores` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_sph_actor`
        FOREIGN KEY (`creado_por`)
        REFERENCES `actores` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico inmutable de precios base por m2 por sector';

-- -----------------------------------------------------------------------------
-- 3. Catálogo Oficial de Privilegios RBAC para Sectores
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
('sectores.ver', 'catastro', 'ver_sectores', 'Ver Sectores', 'Permite consultar sectores, precios y balance de áreas'),
('sectores.crear', 'catastro', 'crear_sectores', 'Crear Sectores', 'Permite incorporar nuevos sectores en proyectos'),
('sectores.editar', 'catastro', 'editar_sectores', 'Editar Sectores', 'Permite modificar metrajes y datos del sector'),
('sectores.cambiar_estado', 'catastro', 'estado_sectores', 'Cambiar Estado de Sectores', 'Permite conmutar el ciclo operativo del sector'),
('sectores.precios', 'catastro', 'gestionar_precios', 'Gestionar Precios de Sector', 'Permite actualizar el histórico de precios por m2')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- -----------------------------------------------------------------------------
-- 4. Asignación automática de privilegios al rol SUPERADMIN
-- -----------------------------------------------------------------------------
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `privilegios` p
WHERE r.codigo = 'SUPERADMIN'
  AND p.codigo IN ('sectores.ver', 'sectores.crear', 'sectores.editar', 'sectores.cambiar_estado', 'sectores.precios')
ON DUPLICATE KEY UPDATE `privilegio_id` = VALUES(`privilegio_id`);

SET FOREIGN_KEY_CHECKS = 1;
