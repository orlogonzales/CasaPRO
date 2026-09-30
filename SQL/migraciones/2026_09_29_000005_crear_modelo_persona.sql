-- =============================================================================
-- CasaPRO - Migración 000005: Modelo Normalizado de Identidad Persona
-- Microfase: 1B (Catálogos Estructurales y Modelo Normalizado de Persona)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: personas (Entidad raíz común de identidad civil y tributaria)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_persona` VARCHAR(20) NOT NULL COMMENT 'NATURAL o JURIDICA',
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO o INACTIVO',
    `notas` TEXT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_personas_tipo` (`tipo_persona`),
    INDEX `idx_personas_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entidad raíz de identidad civil y tributaria';

-- -----------------------------------------------------------------------------
-- Tabla: persona_natural (Extensión de atributos propios de personas naturales)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_natural` (
    `persona_id` BIGINT UNSIGNED PRIMARY KEY,
    `nombres` VARCHAR(100) NOT NULL,
    `apellido_paterno` VARCHAR(100) NOT NULL,
    `apellido_materno` VARCHAR(100) NULL,
    `fecha_nacimiento` DATE NULL,
    `sexo_id` INT UNSIGNED NULL,
    `estado_civil_id` INT UNSIGNED NULL,
    `pais_nacimiento_id` INT UNSIGNED NULL,
    `profesion_ocupacion` VARCHAR(150) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_persona_natural_apellidos` (`apellido_paterno`, `apellido_materno`),
    INDEX `idx_persona_natural_nombres` (`nombres`),
    INDEX `idx_persona_natural_sexo` (`sexo_id`),
    INDEX `idx_persona_natural_estado_civil` (`estado_civil_id`),
    INDEX `idx_persona_natural_pais` (`pais_nacimiento_id`),
    CONSTRAINT `fk_persona_natural_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_natural_sexo` FOREIGN KEY (`sexo_id`) REFERENCES `sexos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_natural_estado_civil` FOREIGN KEY (`estado_civil_id`) REFERENCES `estados_civiles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_natural_pais` FOREIGN KEY (`pais_nacimiento_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extensión de atributos exclusivos para personas naturales';

-- -----------------------------------------------------------------------------
-- Tabla: persona_juridica (Extensión de atributos propios de personas jurídicas)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_juridica` (
    `persona_id` BIGINT UNSIGNED PRIMARY KEY,
    `razon_social` VARCHAR(200) NOT NULL,
    `nombre_comercial` VARCHAR(200) NULL,
    `fecha_constitucion` DATE NULL,
    `objeto_social` TEXT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_persona_juridica_razon` (`razon_social`),
    INDEX `idx_persona_juridica_nombre_comercial` (`nombre_comercial`),
    CONSTRAINT `fk_persona_juridica_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extensión de atributos exclusivos para personas jurídicas';

-- -----------------------------------------------------------------------------
-- Tabla: persona_documentos (Subsistema de documentos oficiales de identidad)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_documentos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_documento_id` INT UNSIGNED NOT NULL,
    `numero_documento` VARCHAR(30) NOT NULL,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `pais_emision_id` INT UNSIGNED NULL,
    `fecha_emision` DATE NULL,
    `fecha_vencimiento` DATE NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_persona_documento_tipo_numero` (`tipo_documento_id`, `numero_documento`),
    INDEX `idx_persona_documentos_persona` (`persona_id`),
    INDEX `idx_persona_documentos_numero` (`numero_documento`),
    INDEX `idx_persona_documentos_pais` (`pais_emision_id`),
    INDEX `idx_persona_documentos_estado` (`estado`),
    CONSTRAINT `fk_persona_documentos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_documentos_tipo` FOREIGN KEY (`tipo_documento_id`) REFERENCES `tipos_documento` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_documentos_pais` FOREIGN KEY (`pais_emision_id`) REFERENCES `paises` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos de identidad oficiales vinculados a la persona';

-- -----------------------------------------------------------------------------
-- Tabla: persona_contactos (Medios de contacto multicanal)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_contactos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_contacto_id` INT UNSIGNED NOT NULL,
    `valor` VARCHAR(150) NOT NULL,
    `etiqueta` VARCHAR(50) NULL,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_persona_contactos_persona` (`persona_id`),
    INDEX `idx_persona_contactos_tipo` (`tipo_contacto_id`),
    INDEX `idx_persona_contactos_valor` (`valor`),
    INDEX `idx_persona_contactos_estado` (`estado`),
    CONSTRAINT `fk_persona_contactos_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_contactos_tipo` FOREIGN KEY (`tipo_contacto_id`) REFERENCES `tipos_contacto` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Medios de contacto (emails, teléfonos, whatsapp) vinculados a la persona';

-- -----------------------------------------------------------------------------
-- Tabla: persona_direcciones (Direcciones físicas, fiscales y postales)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_direcciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL,
    `tipo_direccion_id` INT UNSIGNED NOT NULL,
    `distrito_id` INT UNSIGNED NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `referencia` VARCHAR(255) NULL,
    `codigo_postal` VARCHAR(20) NULL,
    `es_principal` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_persona_direcciones_persona` (`persona_id`),
    INDEX `idx_persona_direcciones_tipo` (`tipo_direccion_id`),
    INDEX `idx_persona_direcciones_distrito` (`distrito_id`),
    INDEX `idx_persona_direcciones_estado` (`estado`),
    CONSTRAINT `fk_persona_direcciones_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_direcciones_tipo` FOREIGN KEY (`tipo_direccion_id`) REFERENCES `tipos_direccion` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_direcciones_distrito` FOREIGN KEY (`distrito_id`) REFERENCES `distritos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Direcciones físicas, fiscales y postales vinculadas a la persona';

-- -----------------------------------------------------------------------------
-- Tabla: persona_representantes (Historial de representación legal)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persona_representantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_juridica_id` BIGINT UNSIGNED NOT NULL,
    `persona_natural_id` BIGINT UNSIGNED NOT NULL,
    `cargo` VARCHAR(100) NOT NULL,
    `partida_registral` VARCHAR(50) NULL,
    `fecha_inicio` DATE NOT NULL,
    `fecha_fin` DATE NULL,
    `es_representante_actual` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_persona_rep_juridica` (`persona_juridica_id`),
    INDEX `idx_persona_rep_natural` (`persona_natural_id`),
    INDEX `idx_persona_rep_estado` (`estado`),
    CONSTRAINT `fk_persona_rep_juridica` FOREIGN KEY (`persona_juridica_id`) REFERENCES `persona_juridica` (`persona_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_persona_rep_natural` FOREIGN KEY (`persona_natural_id`) REFERENCES `persona_natural` (`persona_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de representación legal entre Persona Natural y Persona Jurídica';

SET FOREIGN_KEY_CHECKS = 1;
