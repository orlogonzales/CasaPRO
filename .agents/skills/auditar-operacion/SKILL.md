---
name: auditar-operacion
description: Inyecta el registro de auditoría transversal en operaciones sensibles del sistema.
---

# Skill: auditar-operacion

## Propósito
Garantizar la trazabilidad absoluta de las mutaciones de datos en CasaPRO mediante `AuditoriaServicio`.

## Procedimiento

1. **Identificar la Operación:**
   - Determinar módulo (`comercial`, `caja`, `personas`, `catastro`), entidad (`lotes`, `ventas`), `entidad_id` y acción (`CREAR`, `ACTUALIZAR`, `ANULAR`, `ELIMINAR`).

2. **Obtener Snapshots:**
   - En actualizaciones: obtener el array asociativo del registro antes de mutar (`$datosPrevios`) y después de mutar (`$datosNuevos`).
   - Calcular el diferencial para registrar únicamente los campos que variaron.

3. **Invocación del Servicio:**
   ```php
   $this->auditoriaServicio->registrar([
       'empresa_id' => $empresaId,
       'usuario_id' => $usuarioId,
       'modulo' => 'ventas',
       'entidad' => 'ventas',
       'entidad_id' => $ventaId,
       'accion' => 'ANULAR',
       'descripcion' => 'Anulación de venta por rescisión acordada',
       'datos_previos' => $datosPrevios,
       'datos_nuevos' => $datosNuevos,
       'ip_origen' => $peticion->obtenerIp(),
       'user_agent' => $peticion->obtenerUserAgent()
   ]);
   ```
