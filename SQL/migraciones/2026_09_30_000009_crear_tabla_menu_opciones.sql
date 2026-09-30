-- =============================================================================
-- CasaPRO - Migración 000009: Crear tabla menu_opciones, privilegios del módulo
--                             y sembrar estructura mínima funcional de navegación
-- Microfase: 1G-3 (Gestión de Menú Dinámico y Navegación)
-- Fecha: 2026-09-30
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Tabla: menu_opciones (Catálogo y jerarquía del menú dinámico)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_opciones` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `padre_id` BIGINT UNSIGNED NULL COMMENT 'FK reflexiva a menu_opciones.id (NULL = Nivel 0 Raíz)',
    `tipo` VARCHAR(20) NOT NULL DEFAULT 'ENLACE' COMMENT 'AGRUPADOR | ENLACE',
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador canónico inmutable para siembras y pruebas',
    `etiqueta` VARCHAR(100) NOT NULL COMMENT 'Texto visible de la opción en la interfaz',
    `ruta` VARCHAR(191) NULL COMMENT 'Ruta interna relativa canónica (ej: personas, usuarios, menu). Requerida en ENLACE',
    `icono` VARCHAR(100) NULL COMMENT 'Clase Font Awesome local validada (ej: fa-solid fa-users). Requerida en Nivel 0',
    `orden` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Secuencia ordinal determinista entre hermanos del mismo padre',
    `privilegio_id` BIGINT UNSIGNED NULL COMMENT 'Privilegio RBAC requerido (NULL = visible a cualquier usuario autenticado)',
    `estado` VARCHAR(20) NOT NULL DEFAULT 'ACTIVO' COMMENT 'ACTIVO | INACTIVO',
    `visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = visible en navegación lateral, 0 = oculto del menú',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_menu_opciones_padre` FOREIGN KEY (`padre_id`) REFERENCES `menu_opciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_menu_opciones_privilegio` FOREIGN KEY (`privilegio_id`) REFERENCES `privilegios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_menu_opciones_padre` (`padre_id`),
    INDEX `idx_menu_opciones_orden` (`orden`),
    INDEX `idx_menu_opciones_estado` (`estado`),
    INDEX `idx_menu_opciones_visible` (`visible`),
    INDEX `idx_menu_opciones_privilegio` (`privilegio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opciones jerárquicas y configuración de navegación dinámica';

-- -----------------------------------------------------------------------------
-- 2. Catálogo Oficial de Privilegios para el Módulo de Menú
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
('menu.ver', 'menu', 'ver', 'Ver Estructura de Menú', 'Permite visualizar la jerarquía y catálogo de opciones de navegación'),
('menu.crear', 'menu', 'crear', 'Crear Opciones de Menú', 'Permite registrar nuevas opciones y agrupadores de navegación'),
('menu.editar', 'menu', 'editar', 'Editar Opciones de Menú', 'Permite modificar etiquetas, rutas, iconos y privilegios asociados'),
('menu.cambiar_estado', 'menu', 'cambiar_estado', 'Activar y Desactivar Opciones de Menú', 'Permite alternar la disponibilidad de opciones en la navegación'),
('menu.reordenar', 'menu', 'reordenar', 'Reordenar Estructura de Menú', 'Permite cambiar la secuencia y jerarquía de opciones entre hermanos'),
('menu.eliminar', 'menu', 'eliminar', 'Eliminar Opciones de Menú', 'Permite la baja física de nodos hojas sin descendientes');

-- -----------------------------------------------------------------------------
-- 3. Asignación al Rol SUPERADMIN (Localizado Dinámicamente por Código Canónico)
-- -----------------------------------------------------------------------------
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `privilegios` p
WHERE r.codigo = 'SUPERADMIN'
  AND p.modulo = 'menu';

-- -----------------------------------------------------------------------------
-- 4. Siembra Mínima Inicial de Opciones Funcionales de CasaPRO (Cero Ficticias)
-- -----------------------------------------------------------------------------
-- 4.1 Nivel 0 (Raíces)
INSERT INTO `menu_opciones` (`id`, `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`) VALUES
(1, NULL, 'ENLACE', 'MOD_INICIO', 'Inicio', '/inicio', 'fa-solid fa-house', 1, NULL, 'ACTIVO', 1),
(2, NULL, 'AGRUPADOR', 'MOD_IDENTIDAD', 'Identidad y Seguridad', NULL, 'fa-solid fa-user-shield', 2, NULL, 'ACTIVO', 1);

-- 4.2 Nivel 1 (Sub-agrupadores de Identidad y Seguridad)
INSERT INTO `menu_opciones` (`id`, `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`) VALUES
(3, 2, 'AGRUPADOR', 'GRP_PERSONAS', 'Gestión de Personas', NULL, NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'personas.ver'), 'ACTIVO', 1),
(4, 2, 'AGRUPADOR', 'GRP_SEGURIDAD', 'Seguridad y Accesos', NULL, NULL, 2, NULL, 'ACTIVO', 1);

-- 4.3 Nivel 2 (Opciones Hojas Navegables)
INSERT INTO `menu_opciones` (`id`, `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`) VALUES
(5, 3, 'ENLACE', 'OPC_PERSONAS_LISTADO', 'Directorio de Personas', 'personas', NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'personas.ver'), 'ACTIVO', 1),
(6, 4, 'ENLACE', 'OPC_USUARIOS_LISTADO', 'Usuarios y Accesos', 'usuarios', NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'usuarios.ver'), 'ACTIVO', 1),
(7, 4, 'ENLACE', 'OPC_MENU_LISTADO', 'Gestión de Menú', 'menu', NULL, 2, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'menu.ver'), 'ACTIVO', 1);
