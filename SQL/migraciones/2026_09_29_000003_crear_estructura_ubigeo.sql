-- =============================================================================
-- CasaPRO - Migración 000003: Estructura Normalizada de Países y UBIGEO
-- Microfase: 1B (Catálogos Estructurales y Modelo Normalizado de Persona)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: paises
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paises` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo_iso2` CHAR(2) NOT NULL UNIQUE,
    `codigo_iso3` CHAR(3) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `nacionalidad` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_paises_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo normalizado de países y nacionalidades';

-- -----------------------------------------------------------------------------
-- Tabla: departamentos (Nivel 1 UBIGEO INEI)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departamentos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pais_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `codigo_ubigeo` CHAR(2) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_departamentos_activo` (`activo`),
    INDEX `idx_departamentos_pais` (`pais_id`),
    CONSTRAINT `fk_departamentos_pais` FOREIGN KEY (`pais_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo oficial de departamentos del Perú (INEI)';

-- -----------------------------------------------------------------------------
-- Tabla: provincias (Nivel 2 UBIGEO INEI)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `provincias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `departamento_id` INT UNSIGNED NOT NULL,
    `codigo_ubigeo` CHAR(4) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_provincias_departamento` (`departamento_id`),
    INDEX `idx_provincias_activo` (`activo`),
    CONSTRAINT `fk_provincias_departamento` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo oficial de provincias del Perú (INEI)';

-- -----------------------------------------------------------------------------
-- Tabla: distritos (Nivel 3 UBIGEO INEI)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `distritos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provincia_id` INT UNSIGNED NOT NULL,
    `codigo_ubigeo` CHAR(6) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_distritos_provincia` (`provincia_id`),
    INDEX `idx_distritos_activo` (`activo`),
    CONSTRAINT `fk_distritos_provincia` FOREIGN KEY (`provincia_id`) REFERENCES `provincias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo oficial de distritos del Perú (INEI)';

SET FOREIGN_KEY_CHECKS = 1;
