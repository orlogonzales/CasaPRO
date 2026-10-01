-- =============================================================================
-- CasaPRO - Migración 000013: Módulo de Proyectos, Predios Matrices y Participantes
-- Microfase: 3A (Dominio de Proyectos y Terrenos Matrices)
-- Fecha: 2026-10-01
-- Codificación: utf8mb4 | Intercalación: utf8mb4_unicode_ci | Motor: InnoDB
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Tabla: proyectos (Unidad de desarrollo inmobiliario bajo ámbito de Empresa)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proyectos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a empresas (ámbito territorial corporativo soberano)',
    `codigo` VARCHAR(32) NOT NULL COMMENT 'Identificador canónico corporativo inmutable (ej: PRJ_LOS_CEDROS)',
    `nombre` VARCHAR(150) NOT NULL COMMENT 'Nombre comercial público del proyecto',
    `descripcion` TEXT NULL COMMENT 'Memoria descriptiva o alcances del proyecto',
    `tipo_proyecto` ENUM('PROPIO', 'CONVENIO_APV', 'ASOCIATIVO') NOT NULL DEFAULT 'PROPIO' COMMENT 'Modalidad legal y comercial del proyecto',
    `moneda` ENUM('PEN', 'USD') NOT NULL DEFAULT 'PEN' COMMENT 'Moneda oficial y soberana para cotizaciones y ventas',
    `distrito_id` INT UNSIGNED NOT NULL COMMENT 'FK a distritos (ubicación política principal según UBIGEO INEI)',
    `direccion_referencia` VARCHAR(255) NULL COMMENT 'Ubicación física, paraje, camino o km de carretera',
    `tipo_tolerancia` ENUM('ABSOLUTA_M2', 'PORCENTUAL') NOT NULL DEFAULT 'ABSOLUTA_M2' COMMENT 'Política determinista para evaluación de discrepancias de áreas',
    `valor_tolerancia` DECIMAL(8,4) NOT NULL DEFAULT 0.5000 COMMENT 'Valor numérico de la tolerancia (m² si es ABSOLUTA_M2, % si es PORCENTUAL)',
    `latitud` DECIMAL(10,8) NULL COMMENT 'Coordenada central latitud para mapas Leaflet',
    `longitud` DECIMAL(11,8) NULL COMMENT 'Coordenada central longitud para mapas Leaflet',
    `zoom_mapa` TINYINT UNSIGNED NOT NULL DEFAULT 16 COMMENT 'Nivel de zoom por defecto en visualizadores',
    `estado` ENUM('PLANIFICACION', 'EN_VENTA', 'CONSOLIDADO', 'CERRADO', 'INACTIVO') NOT NULL DEFAULT 'PLANIFICACION' COMMENT 'Estado del ciclo de vida del proyecto',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_proyectos_empresa_codigo` (`empresa_id`, `codigo`),
    KEY `idx_proyectos_empresa` (`empresa_id`),
    KEY `idx_proyectos_distrito` (`distrito_id`),
    KEY `idx_proyectos_estado` (`estado`),
    KEY `idx_proyectos_tipo` (`tipo_proyecto`),
    CONSTRAINT `fk_proyectos_empresa`
        FOREIGN KEY (`empresa_id`)
        REFERENCES `empresas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_proyectos_distrito`
        FOREIGN KEY (`distrito_id`)
        REFERENCES `distritos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proyectos inmobiliarios y desarrollos urbanísticos bajo una Empresa';

-- -----------------------------------------------------------------------------
-- Tabla: proyecto_predios_matriz (Terrenos y partidas registrales de origen 1:N)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proyecto_predios_matriz` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proyecto_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a proyectos',
    `denominacion` VARCHAR(150) NOT NULL COMMENT 'Denominación o nombre físico del predio matriz (ej. Predio San Jerónimo B-1)',
    `partida_registral` VARCHAR(50) NULL COMMENT 'Número de partida electrónica en SUNARP (NULL si está en saneamiento)',
    `tomo_ficha` VARCHAR(50) NULL COMMENT 'Tomo, Ficha o Folio registral para asientos físicos antiguos',
    `area_registral_m2` DECIMAL(14,4) NOT NULL COMMENT 'Superficie en m² que figura en la partida registral o título',
    `area_topografica_m2` DECIMAL(14,4) NULL COMMENT 'Superficie en m² levantada en campo (NULL si está pendiente de levantamiento)',
    `distrito_id` INT UNSIGNED NOT NULL COMMENT 'FK a distritos (ubicación territorial del predio matriz)',
    `antecedente_dominial` TEXT NULL COMMENT 'Reseña de títulos, escrituras públicas o antecedentes de adquisición',
    `poligono_geojson` JSON NULL COMMENT 'Geometría perimétrica del predio matriz en coordenadas GeoJSON WGS84',
    `procedencia_topografica` JSON NULL COMMENT 'Metadatos técnicos: sistema coordenadas origen, topógrafo, colegiatura, archivo fuente',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado físico y registral del predio matriz',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_predios_proyecto_partida` (`proyecto_id`, `partida_registral`),
    KEY `idx_predios_proyecto` (`proyecto_id`),
    KEY `idx_predios_distrito` (`distrito_id`),
    KEY `idx_predios_estado` (`estado`),
    CONSTRAINT `fk_predios_proyecto`
        FOREIGN KEY (`proyecto_id`)
        REFERENCES `proyectos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_predios_distrito`
        FOREIGN KEY (`distrito_id`)
        REFERENCES `distritos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Terrenos y partidas registrales matrices que componen los proyectos';

-- -----------------------------------------------------------------------------
-- Tabla: proyecto_participantes (Contrapartes y participantes del proyecto)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `proyecto_participantes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `proyecto_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a proyectos',
    `persona_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK a personas (padron central: natural o jurídica)',
    `tipo_vinculo` ENUM('PROPIETARIO_TERRENO', 'APV_CONVENIO', 'COMUNIDAD_CAMPESINA', 'EMPRESA_ASOCIADA', 'INVERSIONISTA', 'OTRO') NOT NULL DEFAULT 'PROPIETARIO_TERRENO' COMMENT 'Naturaleza del vínculo comercial o asociativo',
    `porcentaje_participacion` DECIMAL(5,2) NULL COMMENT 'Porcentaje de participación si aplica (0.01 a 100.00)',
    `representante_legal_id` BIGINT UNSIGNED NULL COMMENT 'FK a personas (Persona natural representante si aplica)',
    `partida_registral_poder` VARCHAR(50) NULL COMMENT 'Partida registral del poder o vigencia',
    `fecha_inicio` DATE NOT NULL COMMENT 'Fecha de suscripción o inicio del convenio',
    `fecha_fin` DATE NULL COMMENT 'Fecha de vigencia final o vencimiento del convenio',
    `observaciones` TEXT NULL COMMENT 'Detalles adicionales o acuerdos especiales',
    `estado` ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO' COMMENT 'Estado del vínculo asociativo',
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_proyecto_persona_vinculo` (`proyecto_id`, `persona_id`, `tipo_vinculo`),
    KEY `idx_participantes_proyecto` (`proyecto_id`),
    KEY `idx_participantes_persona` (`persona_id`),
    KEY `idx_participantes_representante` (`representante_legal_id`),
    KEY `idx_participantes_estado` (`estado`),
    CONSTRAINT `fk_participantes_proyecto`
        FOREIGN KEY (`proyecto_id`)
        REFERENCES `proyectos` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_participantes_persona`
        FOREIGN KEY (`persona_id`)
        REFERENCES `personas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT `fk_participantes_representante`
        FOREIGN KEY (`representante_legal_id`)
        REFERENCES `personas` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contrapartes, comunidades, APVs y participantes asociados a proyectos';

-- -----------------------------------------------------------------------------
-- Siembra de Privilegios RBAC para Proyectos y Predios Matrices
-- -----------------------------------------------------------------------------
INSERT INTO `privilegios` (`id`, `codigo`, `modulo`, `accion`, `nombre`, `descripcion`) VALUES
(27, 'proyectos.ver', 'proyectos', 'ver', 'Ver Proyectos', 'Permite consultar el catálogo y detalle operativo de proyectos inmobiliarios'),
(28, 'proyectos.crear', 'proyectos', 'crear', 'Crear Proyectos', 'Permite dar de alta nuevos proyectos inmobiliarios'),
(29, 'proyectos.editar', 'proyectos', 'editar', 'Editar Proyectos', 'Permite modificar la configuración y metadatos del proyecto'),
(30, 'proyectos.cambiar_estado', 'proyectos', 'cambiar_estado', 'Cambiar Estado de Proyecto', 'Permite alternar el ciclo de vida del proyecto (Planificación, En Venta, Cerrado)'),
(31, 'predios.ver', 'predios', 'ver', 'Ver Predios Matrices', 'Permite consultar predios y partidas registrales matrices del proyecto'),
(32, 'predios.crear', 'predios', 'crear', 'Crear Predio Matriz', 'Permite incorporar nuevos predios matrices al proyecto'),
(33, 'predios.editar', 'predios', 'editar', 'Editar Predio Matriz', 'Permite actualizar datos registrales, topográficos y georreferenciación de predios'),
(34, 'predios.cambiar_estado', 'predios', 'cambiar_estado', 'Cambiar Estado de Predio Matriz', 'Permite alternar el estado operativo del predio matriz');

-- Asignar los nuevos privilegios al rol SUPERADMIN (rol_id = 1)
INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`) VALUES
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34);

-- -----------------------------------------------------------------------------
-- Siembra del Menú Dinámico para Proyectos Inmobiliarios (3 Niveles Estrictos)
-- -----------------------------------------------------------------------------
INSERT INTO `menu_opciones` (`id`, `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`) VALUES
(10, NULL, 'AGRUPADOR', 'MOD_CATASTRO', 'Catastro y Territorio', NULL, 'fa-solid fa-map-location-dot', 3, 27, 'ACTIVO', 1),
(11, 10, 'AGRUPADOR', 'GRP_PROYECTOS', 'Gestión Inmobiliaria', NULL, NULL, 1, 27, 'ACTIVO', 1),
(12, 11, 'ENLACE', 'OPC_PROYECTOS_LISTADO', 'Proyectos', 'proyectos', 'fa-solid fa-city', 1, 27, 'ACTIVO', 1);

SET FOREIGN_KEY_CHECKS = 1;
