-- =============================================================================
-- Migración 000012: Sembrado Oficial de Opciones de Menú para Empresas
-- Jerarquía de 3 Niveles: MOD_IDENTIDAD -> GRP_EMPRESAS -> OPC_EMPRESAS_LISTADO
-- Principio: Cero IDs mágicos. Resolución determinista por códigos canónicos.
-- Microfase: 2C — Administración Web de Empresas
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Nivel 1: Sub-agrupador Estructura Corporativa (bajo MOD_IDENTIDAD)
-- -----------------------------------------------------------------------------
INSERT INTO `menu_opciones` (
    `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`
)
SELECT 
    p.id AS padre_id,
    'AGRUPADOR' AS tipo,
    'GRP_EMPRESAS' AS codigo,
    'Estructura Corporativa' AS etiqueta,
    NULL AS ruta,
    NULL AS icono,
    3 AS orden,
    priv.id AS privilegio_id,
    'ACTIVO' AS estado,
    1 AS visible
FROM `menu_opciones` p
CROSS JOIN `privilegios` priv
WHERE p.codigo = 'MOD_IDENTIDAD'
  AND priv.codigo = 'empresas.ver'
ON DUPLICATE KEY UPDATE
    `etiqueta` = VALUES(`etiqueta`),
    `orden` = VALUES(`orden`),
    `privilegio_id` = VALUES(`privilegio_id`),
    `estado` = VALUES(`estado`),
    `visible` = VALUES(`visible`);

-- -----------------------------------------------------------------------------
-- 2. Nivel 2: Opción Navegable Empresas (bajo GRP_EMPRESAS)
-- -----------------------------------------------------------------------------
INSERT INTO `menu_opciones` (
    `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`
)
SELECT 
    p.id AS padre_id,
    'ENLACE' AS tipo,
    'OPC_EMPRESAS_LISTADO' AS codigo,
    'Empresas' AS etiqueta,
    'empresas' AS ruta,
    'fa-solid fa-building' AS icono,
    1 AS orden,
    priv.id AS privilegio_id,
    'ACTIVO' AS estado,
    1 AS visible
FROM `menu_opciones` p
CROSS JOIN `privilegios` priv
WHERE p.codigo = 'GRP_EMPRESAS'
  AND priv.codigo = 'empresas.ver'
ON DUPLICATE KEY UPDATE
    `etiqueta` = VALUES(`etiqueta`),
    `ruta` = VALUES(`ruta`),
    `icono` = VALUES(`icono`),
    `orden` = VALUES(`orden`),
    `privilegio_id` = VALUES(`privilegio_id`),
    `estado` = VALUES(`estado`),
    `visible` = VALUES(`visible`);
