-- =============================================================================
-- CasaPRO - Migración 000008: Alterar usuarios para cambio obligatorio de password
--                             y registrar catálogo de privilegios de usuarios
-- Microfase: 1G-2 (Administración de Usuarios y Accesos)
-- Fecha: 2026-09-30
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Modificación Estructural: Flag de Cambio Obligatorio de Contraseña
-- -----------------------------------------------------------------------------
ALTER TABLE `usuarios`
    ADD COLUMN `debe_cambiar_password` TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT '1 si el usuario está obligado a cambiar su contraseña antes de operar'
    AFTER `version_autorizacion`;

-- -----------------------------------------------------------------------------
-- 2. Catálogo Oficial de Privilegios para el Módulo de Usuarios
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
('usuarios.ver', 'usuarios', 'ver', 'Ver Listado y Ficha de Usuarios', 'Permite consultar el padrón de usuarios y sus perfiles de seguridad'),
('usuarios.crear', 'usuarios', 'crear', 'Crear Cuentas de Usuario', 'Permite aprovisionar nuevas credenciales de acceso para personas naturales'),
('usuarios.editar', 'usuarios', 'editar', 'Editar Datos de Usuario', 'Permite modificar nombres de usuario y correos electrónicos'),
('usuarios.cambiar_estado', 'usuarios', 'cambiar_estado', 'Activar y Desactivar Usuarios', 'Permite realizar la baja lógica o reactivación administrativa de cuentas'),
('usuarios.desbloquear', 'usuarios', 'desbloquear', 'Desbloquear Cuentas por Fuerza Bruta', 'Permite levantar anticipadamente bloqueos temporales de seguridad defensiva'),
('usuarios.asignar_roles', 'usuarios', 'asignar_roles', 'Asignar Roles a Usuarios', 'Permite asociar y desasociar roles funcionales a las cuentas de usuario'),
('usuarios.resetear_password', 'usuarios', 'resetear_password', 'Resetear Contraseñas Administrativamente', 'Permite forzar contraseñas temporales y cambio obligatorio para terceros');

-- -----------------------------------------------------------------------------
-- 3. Asignación al Rol SUPERADMIN (Localizado Dinámicamente por Código Canónico)
-- -----------------------------------------------------------------------------
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `privilegios` p
WHERE r.codigo = 'SUPERADMIN'
  AND p.modulo = 'usuarios';
