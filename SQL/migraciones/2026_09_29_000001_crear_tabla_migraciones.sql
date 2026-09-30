-- =============================================================================
-- CasaPRO - Migración 000001: Crear tabla de control de migraciones
-- Microfase: 1A (Persistencia PDO y Motor de Migraciones)
-- Fecha: 2026-09-29
-- =============================================================================

CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL,
    `ejecutado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
