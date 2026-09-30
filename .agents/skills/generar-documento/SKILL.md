---
name: generar-documento
description: Genera documentos oficiales en PDF (recibos, contratos, cronogramas, estados de cuenta) a partir de plantillas.
---

# Skill: generar-documento

## Propósito
Emitir representaciones imprimibles y descargables de documentos contractuales y comprobantes financieros en CasaPRO.

## Procedimiento

1. **Selección de Plantilla:**
   - Ubicar la plantilla HTML del documento en `app/Vistas/documentos/{tipo_documento}.php` (ej. `recibo-pago.php`, `cronograma-cuotas.php`).

2. **Inyección de Datos Dinámicos:**
   - Proveer datos auditados del cliente, empresa, proyecto, lotes y desgloses monetarios.
   - Formatear montos a 2 decimales y fechas en formato legible (`d/m/Y`).

3. **Conversión y Emisión:**
   - Renderizar el HTML en memoria con estilos CSS imprimibles optimizados.
   - Generar el PDF mediante el motor disponible (e.g. dompdf o mPDF ligero).
   - Agregar marca de agua o código QR con hash de verificación.

4. **Archivado del Documento Emitido:**
   - Guardar una copia en `storage/uploads/{empresa_id}/documentos_emitidos/` para garantizar reproducibilidad histórica.
