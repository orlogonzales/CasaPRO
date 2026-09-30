-- =============================================================================
-- CasaPRO - Migración 000006: Crear tablas de actores y auditoría transversal
-- Microfase: 1C (Actores, Auditoría Transversal, CSRF y Seguridad de Mutaciones)
-- Fecha: 2026-09-29
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: actores (Catálogo de actores que interactúan con el sistema)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actores` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_actor` VARCHAR(30) NOT NULL COMMENT 'SISTEMA | USUARIO | SERVICIO_EXTERNO',
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(150) NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO | INACTIVO',
    `metadatos` JSON NULL COMMENT 'Metadatos operativos en formato JSON nativo',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_actores_tipo` (`tipo_actor`),
    INDEX `idx_actores_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de actores del sistema (humanos, automatizados y externos)';

-- Semilla inicial: Actor raíz del sistema
INSERT INTO `actores` (`id`, `tipo_actor`, `codigo`, `nombre`, `estado`, `metadatos`) VALUES
(1, 'SISTEMA', 'SISTEMA_CASAPRO', 'Sistema Automatizado CasaPRO', 'ACTIVO', '{"descripcion": "Actor raíz del sistema para operaciones automáticas y semillas"}');

-- -----------------------------------------------------------------------------
-- Tabla: auditorias (Bitácora transversal e inmutable de operaciones de mutación)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auditorias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `id_correlacion` VARCHAR(64) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `entidad` VARCHAR(50) NOT NULL,
    `registro_id` BIGINT UNSIGNED NULL,
    `accion` VARCHAR(30) NOT NULL COMMENT 'CREAR | ACTUALIZAR | DESACTIVAR | ELIMINAR_LOGICO | ACCESO',
    `resultado` VARCHAR(20) NOT NULL DEFAULT 'EXITO' COMMENT 'EXITO | FALLO',
    `datos_anteriores` JSON NULL COMMENT 'Snapshot previo a la mutación',
    `datos_nuevos` JSON NULL COMMENT 'Snapshot posterior a la mutación',
    `metadatos` JSON NULL COMMENT 'Contexto contextual complementario',
    `origen` VARCHAR(20) NOT NULL DEFAULT 'WEB' COMMENT 'WEB | CLI | API',
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auditorias_modulo_entidad_reg` (`modulo`, `entidad`, `registro_id`),
    INDEX `idx_auditorias_actor` (`actor_id`),
    INDEX `idx_auditorias_correlacion` (`id_correlacion`),
    INDEX `idx_auditorias_accion` (`accion`),
    INDEX `idx_auditorias_creado_en` (`creado_en`),
    CONSTRAINT `fk_auditorias_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora transversal e inmutable de auditoría forense para mutaciones del sistema';

SET FOREIGN_KEY_CHECKS = 1;
