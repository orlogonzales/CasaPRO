---
name: crear-migracion
description: Genera scripts de migración SQL secuenciales respetando UTF8mb4, tipos DECIMAL y claves foráneas RESTRICT.
---

# Skill: crear-migracion

## Propósito
Crear archivos de migración SQL ordenados cronológicamente en `database/migraciones/` para mantener el esquema de base de datos reproducible.

## Procedimiento

1. **Nomenclatura del Archivo:**
   - Formato: `YYYY_MM_DD_HHMMSS_{nombre_accion}.sql`.
   - Ejemplo: `2026_09_29_193000_crear_tabla_personas.sql`.

2. **Estándares SQL:**
   - Codificación: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
   - Clave Primaria: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
   - Dinero: `DECIMAL(12, 2) NOT NULL DEFAULT 0.00`.
   - Áreas topográficas: `DECIMAL(10, 4) NOT NULL DEFAULT 0.0000`.
   - Restricciones FK: `CONSTRAINT fk_{tabla}_{columna} FOREIGN KEY ({columna}) REFERENCES {tabla_padre}(id) ON DELETE RESTRICT ON UPDATE CASCADE`.
   - Columnas temporales: `creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

3. **Verificación de Idempotencia:**
   - Usar cláusulas `CREATE TABLE IF NOT EXISTS` o verificaciones de existencia en alteraciones.
