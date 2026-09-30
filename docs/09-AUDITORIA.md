# 09 — Auditoría Transversal e Inmutabilidad Financiera

## 1. Principio Rector de Auditoría

En CasaPRO, ninguna acción relevante para el negocio ocurre en silencio. El sistema mantiene un registro histórico inmutable de quién, cuándo, desde dónde y qué cambió exactamente en cada entidad del sistema.

---

## 2. Esquema de la Tabla de Auditoría

```sql
CREATE TABLE IF NOT EXISTS `auditorias` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` BIGINT UNSIGNED NULL,
    `usuario_id` BIGINT UNSIGNED NULL,
    `modulo` VARCHAR(50) NOT NULL,
    `entidad` VARCHAR(50) NOT NULL,
    `entidad_id` BIGINT UNSIGNED NOT NULL,
    `accion` VARCHAR(20) NOT NULL COMMENT 'CREAR | ACTUALIZAR | ANULAR | ELIMINAR | ACCESO',
    `descripcion` VARCHAR(255) NOT NULL,
    `datos_previos` JSON NULL,
    `datos_nuevos` JSON NULL,
    `ip_origen` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auditoria_entidad` (`entidad`, `entidad_id`),
    INDEX `idx_auditoria_usuario` (`usuario_id`),
    INDEX `idx_auditoria_empresa` (`empresa_id`),
    INDEX `idx_auditoria_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Reglas de Inmutabilidad de Auditoría

1. **Permisos de Base de Datos:** El usuario de aplicación no debe contar con privilegios `DELETE` ni `UPDATE` sobre la tabla `auditorias`. Solo se permiten operaciones `INSERT` y `SELECT`.
2. **Payload Estructurado:**
   - En una creación (`CREAR`), `datos_previos` es `null` y `datos_nuevos` contiene el snapshot completo de la entidad recién insertada.
   - En una actualización (`ACTUALIZAR`), se almacenan únicamente las columnas que sufrieron modificación real, contrastando el valor anterior con el nuevo valor.
   - En una anulación (`ANULAR`), se guarda el motivo justificado y el estado previo.

---

## 4. Auditoría Financiera Específica

Para las operaciones que involucran dinero, contratos o transferencias de propiedad:

1. **Caja y Pagos:** Cada movimiento de caja genera un registro de auditoría con el desglose exacto de montos, método de pago, número de operación bancaria y saldo resultante de caja.
2. **Cronograma de Cuotas:** Cualquier recalculo o refinanciamiento requiere el registro previo del cronograma original antes de generar la adenda o nuevo plan de pagos.
3. **Anulaciones de Pagos:** Prohibición absoluta de eliminación física. Se genera un asiento de estorno (contraasiento) con referencia obligatoria al movimiento original y motivo explícito firmado por el usuario autorizador.
