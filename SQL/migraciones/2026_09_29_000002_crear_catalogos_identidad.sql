-- =============================================================================
-- CasaPRO - Migración 000002: Catálogos Estructurales de Identidad
-- Microfase: 1B (Catálogos Estructurales y Modelo Normalizado de Persona)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: sexos
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sexos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL UNIQUE,
    `abreviatura` VARCHAR(5) NOT NULL UNIQUE,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sexos` (`id`, `codigo`, `abreviatura`, `nombre`, `activo`, `orden`) VALUES
(1, 'MASCULINO', 'M', 'Masculino', 1, 1),
(2, 'FEMENINO', 'F', 'Femenino', 1, 2),
(3, 'NO_ESPECIFICADO', 'N/A', 'No Especificado', 1, 3);

-- -----------------------------------------------------------------------------
-- Tabla: estados_civiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `estados_civiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `estados_civiles` (`id`, `codigo`, `nombre`, `activo`, `orden`) VALUES
(1, 'SOLTERO', 'Soltero(a)', 1, 1),
(2, 'CASADO', 'Casado(a)', 1, 2),
(3, 'VIUDO', 'Viudo(a)', 1, 3),
(4, 'DIVORCIADO', 'Divorciado(a)', 1, 4),
(5, 'CONVIVIENTE', 'Conviviente', 1, 5);

-- -----------------------------------------------------------------------------
-- Tabla: tipos_documento
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_documento` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(20) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `tipo_persona` VARCHAR(20) NOT NULL DEFAULT 'AMBOS',
    `longitud_minima` INT UNSIGNED NOT NULL DEFAULT 1,
    `longitud_maxima` INT UNSIGNED NOT NULL DEFAULT 20,
    `longitud_exacta` INT UNSIGNED NULL DEFAULT NULL,
    `patron_regex` VARCHAR(100) NULL DEFAULT NULL,
    `alfanumerico` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_documento` (`id`, `codigo`, `nombre`, `tipo_persona`, `longitud_minima`, `longitud_maxima`, `longitud_exacta`, `patron_regex`, `alfanumerico`, `estado`, `orden`) VALUES
(1, 'DNI', 'Documento Nacional de Identidad', 'NATURAL', 8, 8, 8, '^[0-9]{8}$', 0, 'ACTIVO', 1),
(2, 'RUC', 'Registro Único de Contribuyentes', 'AMBOS', 11, 11, 11, '^[0-9]{11}$', 0, 'ACTIVO', 2),
(3, 'CE', 'Carné de Extranjería', 'NATURAL', 4, 12, NULL, '^[a-zA-Z0-9]{4,12}$', 1, 'ACTIVO', 3),
(4, 'PASAPORTE', 'Pasaporte', 'NATURAL', 4, 15, NULL, '^[a-zA-Z0-9]{4,15}$', 1, 'ACTIVO', 4);

-- -----------------------------------------------------------------------------
-- Tabla: tipos_contacto
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_contacto` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_contacto` (`id`, `codigo`, `nombre`, `activo`, `orden`) VALUES
(1, 'EMAIL', 'Correo Electrónico', 1, 1),
(2, 'TELEFONO_MOVIL', 'Teléfono Móvil / Celular', 1, 2),
(3, 'TELEFONO_FIJO', 'Teléfono Fijo', 1, 3),
(4, 'WHATSAPP', 'WhatsApp', 1, 4);

-- -----------------------------------------------------------------------------
-- Tabla: tipos_direccion
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipos_direccion` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(30) NOT NULL UNIQUE,
    `nombre` VARCHAR(50) NOT NULL,
    `activo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `orden` INT UNSIGNED NOT NULL DEFAULT 0,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_direccion` (`id`, `codigo`, `nombre`, `activo`, `orden`) VALUES
(1, 'DOMICILIO', 'Domicilio Legal / Residencia', 1, 1),
(2, 'FISCAL', 'Domicilio Fiscal (SUNAT)', 1, 2),
(3, 'CORRESPONDENCIA', 'Dirección de Correspondencia', 1, 3),
(4, 'SUCURSAL', 'Sucursal / Establecimiento Anexo', 1, 4);

SET FOREIGN_KEY_CHECKS = 1;
