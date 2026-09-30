-- =============================================================================
-- CasaPRO - Esquema Consolidado Oficial Vigente
-- Micro-baseline: mb-fase1a-persistencia
-- Fecha: 2026-09-29
-- Codificación: utf8mb4 | Intercalación: utf8mb4_unicode_ci | Motor: InnoDB
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: migraciones (Registro y control de evolución secuencial del esquema)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL,
    `ejecutado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
