# 10 — Gestión de Archivos, Documentos y Expedientes Digitales

## 1. Arquitectura de Almacenamiento Seguro

El almacenamiento de archivos en CasaPRO está diseñado para garantizar la confidencialidad de la información de clientes, planos y contratos, impidiendo el acceso directo vía URL pública.

```text
storage/
└── uploads/
    └── {empresa_id}/
        ├── expedientes_clientes/
        │   └── {cliente_id}/
        │       ├── dni_anverso.pdf
        │       └── sustento_ingresos.pdf
        ├── contratos/
        │   └── {venta_id}/
        │       ├── contrato_compraventa.pdf
        │       └── cronograma_firmado.pdf
        ├── proyectos/
        │   └── {proyecto_id}/
        │       ├── plano_catastral.pdf
        │       └── memoria_descriptiva.pdf
        └── postventa/
            └── {ticket_id}/
                └── evidencia_foto_01.jpg
```

---

## 2. Esquema de Metadatos Documentales

```sql
CREATE TABLE IF NOT EXISTS `documentos` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` BIGINT UNSIGNED NOT NULL,
    `entidad` VARCHAR(50) NOT NULL COMMENT 'clientes | ventas | proyectos | tickets',
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_almacenado` VARCHAR(255) NOT NULL,
    `ruta_relativa` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `tamanio_bytes` BIGINT UNSIGNED NOT NULL,
    `hash_sha256` CHAR(64) NOT NULL,
    `usuario_subida_id` BIGINT UNSIGNED NOT NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_documento_entidad` (`entidad`, `entidad_id`),
    INDEX `idx_documento_hash` (`hash_sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Protocolo de Carga y Descarga

1. **Recepción en Backend:**
   - Verificación de tamaño máximo permitido (e.g., 10MB por archivo).
   - Detección de MIME type real mediante `finfo_file()`.
   - Cálculo del hash SHA-256 para verificación de integridad y prevención de duplicados.
2. **Almacenamiento Físico:**
   - Asignación de nombre único aleatorio UUIDv7 con extensión autorizada.
   - Creación controlada de directorios con permisos restrictivos (0750).
3. **Descarga y Visualización Controlada:**
   - Las solicitudes de visualización pasan por una ruta segura (e.g., `/documentos/descargar/{id}`).
   - El controlador verifica autenticación, rol y ámbito territorial antes de emitir cabeceras `Content-Type` y `Content-Disposition`.
