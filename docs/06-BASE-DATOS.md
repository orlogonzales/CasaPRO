# 06 — Base de Datos y Persistencia

## 1. Lineamientos del Motor de Base de Datos

- **Motor Recomendado:** MySQL 8.0+ / MariaDB 10.6+ (bajo Laragon en desarrollo).
- **Codificación de Caracteres:** `utf8mb4` con intercalación `utf8mb4_unicode_ci` (soporte completo para acentos, caracteres internacionales y emojis).
- **Motor de Almacenamiento:** `InnoDB` exclusivamente (soporte ACID, transacciones y claves foráneas).
- **Modo SQL Estricto:** `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO`.

---

## 2. Convenciones de Diseño de Esquema

1. **Tablas:** Nombres en español, minúsculas, plural y formato `snake_case` (e.g., `personas`, `proyectos`, `cronograma_cuotas`).
2. **Columnas:** Minúsculas y formato `snake_case` (e.g., `numero_documento`, `fecha_vencimiento`).
3. **Claves Primarias (PK):** Columna única `id` de tipo `BIGINT UNSIGNED AUTO_INCREMENT`.
4. **Claves Foráneas (FK):** Formato `{tabla_singular}_id` (e.g., `persona_id`, `lote_id`, `caja_id`).
   - Nombre de restricción: `fk_{tabla_origen}_{columna_origen}`.
   - Regla por defecto: `ON DELETE RESTRICT ON UPDATE CASCADE`.
   - **Prohibido:** `ON DELETE CASCADE` en tablas financieras, comerciales o de auditoría.
5. **Columnas de Auditoría Base:**
   ```sql
   `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   `eliminado_en` TIMESTAMP NULL DEFAULT NULL
   ```
6. **Estados:** Uso de columnas `VARCHAR(30)` con valores predecibles en mayúsculas (e.g., `'DISPONIBLE'`, `'RESERVADO'`, `'PAGADO'`) o tablas maestras catalogadas para estados dinámicos.

---

## 3. Tipos de Datos y Precisión

| Concepto | Tipo SQL | Justificación |
| :--- | :--- | :--- |
| **Montos Dinero / Precios** | `DECIMAL(12, 2)` | Precisión exacta sin errores de redondeo de punto flotante. |
| **Metrajes / Áreas (m²)** | `DECIMAL(10, 4)` | Permite fracciones de metro cuadrado con alta precisión topográfica. |
| **Porcentajes / Tasas** | `DECIMAL(6, 4)` | Soporte para tasas como 12.5000% o 0.0825%. |
| **Documentos Identidad** | `VARCHAR(20)` | Soporta DNI (8 dígitos), RUC (11 dígitos) y Pasaportes internacionales. |
| **Teléfonos** | `VARCHAR(30)` | Permite prefijos internacionales y formatos con espacios. |
| **Fechas de Operación** | `DATE` o `DATETIME` | Tipos nativos para indexación y funciones temporales de SQL. |
| **Payloads / Auditoría** | `JSON` o `LONGTEXT` | Almacenamiento semiestructurado de estados anteriores y posteriores. |

---

## 4. Estructura Oficial de Persistencia y Migraciones SQL

A partir de la decisión vinculante de gobernanza de CasaPRO, la persistencia relacional se organiza bajo una única estructura oficial:

```text
D:\laragon\www\app.casa-pro\
└── SQL\
    ├── casa-pro.sql
    └── migraciones\
        ├── 2026_09_29_000001_crear_tabla_migraciones.sql
        ├── 2026_09_29_000002_crear_catalogos_identidad.sql
        ├── 2026_09_29_000003_crear_estructura_ubigeo.sql
        ├── 2026_09_29_000004_cargar_datos_ubigeo.sql
        └── 2026_09_29_000005_crear_modelo_persona.sql
```

### Fuentes de Verdad y Responsabilidades:
1. **`SQL/casa-pro.sql` (Esquema Consolidado Oficial Vigente):**
   - Representa el estado consolidado completo y actual de la base de datos CasaPRO.
   - Permite reconstruir una base de datos limpia desde cero correspondiente al micro-baseline vigente.
   - Contiene tablas, columnas, tipos, claves primarias, claves foráneas, restricciones, índices y catálogos estructurales indispensables.
   - No es un registro de parches ni contiene datos demo u operativos.
2. **`SQL/migraciones/` (Historial Incremental Secuencial):**
   - Registra cronológicamente la evolución de la estructura mediante scripts SQL secuenciales (`{TIMESTAMP}_{DESCRIPCION}.sql`).
   - Cada migración es ejecutada de forma determinista mediante PDO en orden ascendente y registrada en la tabla `migraciones`.

### Regla de Sincronización Obligatoria (Gate SQL):
Cada modificación estructural de base de datos debe reflejarse simultáneamente en:
$$\text{Migración Incremental en } \texttt{SQL/migraciones/} \quad + \quad \text{Esquema Consolidado en } \texttt{SQL/casa-pro.sql}$$
Si una modificación no está reflejada idénticamente en ambos archivos, el **Gate SQL = FAIL** y queda prohibido cerrar el micro-baseline.

### Prohibiciones Expresas:
- Prohibido crear ubicaciones paralelas (`database/`, `db/`, `schema/`, `sql/`, `backup/`, `dump/`).
- Prohibido crear esquemas duplicados (`casa-pro-final.sql`, `casa-pro-v2.sql`, `casapro.sql`, etc.).
- Prohibido versionar contraseñas, credenciales, tokens o datos personales reales en los archivos SQL.

### Tabla de Control de Migraciones:
```sql
CREATE TABLE IF NOT EXISTS `migraciones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migracion` VARCHAR(255) NOT NULL UNIQUE,
    `lote` INT UNSIGNED NOT NULL,
    `ejecutado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Estrategia de Indexación

1. **Índices Únicos:**
   - `personas(tipo_documento, numero_documento)`: Garantiza que un mismo DNI/RUC no se duplique en el sistema.
   - `usuarios(username)`: Unicidad de credencial de acceso.
   - `lotes(manzana_id, numero_lote)`: Impide duplicar el Lote 5 en la misma Manzana A.
   - `ventas(codigo_contrato)`: Unicidad del expediente contractual.
2. **Índices de Búsqueda Rápida:**
   - Claves foráneas para optimizar joins relacionales.
   - Campos de filtrado de DataTables: `personas(nombres, apellido_paterno)`, `lotes(estado)`, `cronograma_cuotas(estado, fecha_vencimiento)`.
