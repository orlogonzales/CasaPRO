-- =============================================================================
-- CasaPRO - Migración 000007: Crear tablas de seguridad, RBAC y usuarios
-- Microfase: 1G-1 (Núcleo de Autenticación, RBAC y Autorización Multidimensional)
-- Fecha: 2026-09-30
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: usuarios (Cuentas de acceso de usuarios del sistema vinculadas a Persona)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `persona_id` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Relación 1:1 obligatoria con Persona Natural',
    `actor_id` BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'Actor asociado para trazabilidad en auditorías',
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `nombre_usuario` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO | INACTIVO',
    `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ultimo_intento_fallido` DATETIME NULL,
    `bloqueado_hasta` DATETIME NULL COMMENT 'Bloqueo defensivo temporal por fuerza bruta',
    `version_autorizacion` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incremento para invalidación inmediata de sesiones',
    `ultimo_login_en` DATETIME NULL,
    `ultimo_login_ip` VARCHAR(45) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_usuarios_actor` FOREIGN KEY (`actor_id`) REFERENCES `actores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_usuarios_estado` (`estado`),
    INDEX `idx_usuarios_bloqueado_hasta` (`bloqueado_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas de acceso de usuarios del sistema';

-- -----------------------------------------------------------------------------
-- Tabla: roles (Catálogo de roles funcionales del sistema)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador canónico del rol',
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `es_sistema` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si es rol protegido del sistema no eliminable',
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO | INACTIVO',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roles_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Roles funcionales del sistema';

-- -----------------------------------------------------------------------------
-- Tabla: privilegios (Catálogo de privilegios funcionales granulares modulo.accion)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privilegios` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Sintaxis canónica modulo.accion',
    `modulo` VARCHAR(50) NOT NULL,
    `accion` VARCHAR(50) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_privilegios_modulo` (`modulo`),
    INDEX `idx_privilegios_modulo_accion` (`modulo`, `accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de privilegios funcionales granulares';

-- -----------------------------------------------------------------------------
-- Tabla: rol_privilegios (Asociación N:M entre roles y privilegios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rol_privilegios` (
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `privilegio_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`rol_id`, `privilegio_id`),
    CONSTRAINT `fk_rol_privilegios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rol_privilegios_privilegio` FOREIGN KEY (`privilegio_id`) REFERENCES `privilegios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asociación N:M entre roles y privilegios';

-- -----------------------------------------------------------------------------
-- Tabla: usuario_roles (Asignación de roles a usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_roles` (
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `rol_id` BIGINT UNSIGNED NOT NULL,
    `asignado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`usuario_id`, `rol_id`),
    CONSTRAINT `fk_usuario_roles_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_usuario_roles_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de roles a usuarios';

-- -----------------------------------------------------------------------------
-- Tabla: eventos_seguridad (Bitácora técnica de seguridad y barrera de entrada)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventos_seguridad` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_evento` VARCHAR(50) NOT NULL COMMENT 'LOGIN_EXITO | LOGIN_FALLO | LOGOUT | BLOQUEO_TEMPORAL | SESION_INVALIDADA | CSRF_FALLIDO | ACCESO_DENEGADO | BOOTSTRAP_ADMIN',
    `usuario_id` BIGINT UNSIGNED NULL COMMENT 'Null si el usuario no existe o el evento es anónimo',
    `identificador_intento` VARCHAR(191) NULL COMMENT 'Email o username ingresado solo en intentos de acceso',
    `ip` VARCHAR(45) NULL COMMENT 'Dirección IP del cliente según el evento',
    `user_agent` VARCHAR(500) NULL COMMENT 'Agente de usuario de la petición',
    `metadatos` JSON NULL COMMENT 'Telemetría contextual mínima en JSON nativo',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eventos_seguridad_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_eventos_seguridad_tipo` (`tipo_evento`),
    INDEX `idx_eventos_seguridad_usuario` (`usuario_id`),
    INDEX `idx_eventos_seguridad_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bitácora de eventos y telemetría de seguridad técnica';

-- -----------------------------------------------------------------------------
-- Semillas Iniciales Mínimas
-- -----------------------------------------------------------------------------

-- 1. Rol protegido SUPERADMIN
INSERT INTO `roles` (`id`, `codigo`, `nombre`, `descripcion`, `es_sistema`, `estado`) VALUES
(1, 'SUPERADMIN', 'Superadministrador del Sistema', 'Acceso irrestricto de administración soberana del sistema', 1, 'ACTIVO');

-- 2. Privilegios de funcionalidades activas de Personas
INSERT INTO `privilegios` (`id`, `codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
(1, 'personas.ver', 'personas', 'ver', 'Ver personas', 'Permite consultar el listado y detalle de personas'),
(2, 'personas.crear', 'personas', 'crear', 'Crear personas', 'Permite registrar nuevas personas naturales o jurídicas'),
(3, 'personas.editar', 'personas', 'editar', 'Editar personas', 'Permite modificar información de personas existentes'),
(4, 'personas.cambiar_estado', 'personas', 'cambiar_estado', 'Cambiar estado de personas', 'Permite activar o desactivar personas'),
(5, 'personas.consultar_documento', 'personas', 'consultar_documento', 'Consultar padrón documental', 'Permite invocar servicios de consulta DNI/RUC');

-- 3. Asignación inicial de privilegios al rol SUPERADMIN
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5);

SET FOREIGN_KEY_CHECKS = 1;
