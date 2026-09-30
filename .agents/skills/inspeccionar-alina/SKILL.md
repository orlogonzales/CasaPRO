---
name: inspeccionar-alina
description: Inspecciona físicamente plantillas HTML, CSS y plugins de Alina para garantizar reutilización sin invención.
---

# Skill: inspeccionar-alina

## Propósito
Ejecutar la regla anti-invención antes de diseñar o programar cualquier elemento de interfaz gráfica para CasaPRO.

## Procedimiento Obligatorio

1. **Recepción del Requerimiento:**
   - Identificar los componentes visuales necesarios (e.g. tabla con botones de exportación, selector con búsqueda, modal responsivo, tarjeta de indicador métrico).

2. **Búsqueda Física en `admin-dashboard/alina/template/`:**
   - Ubicar el archivo HTML representativo entre las 116 plantillas:
     - Tablas y listados: `blank.html`, `data_table.html`, `advance_table.html`, `basic_table.html`, `ready_to_use_table.html`.
     - Formularios: `default_forms.html`, `ready_to_use_form.html`, `base_inputs.html`, `select.html`, `date_picker.html`, `file_upload.html`.
     - Modales y diálogos: `modals.html`, `sweetalert.html`.
     - Mapas y geolocalización: `leaflet_map.html`.
     - Indicadores y gráficos: `widget.html`, `crypto_dashboard.html`, `index.html`.

3. **Inspección de Dependencias en `admin-dashboard/alina/assets/vendor/`:**
   - Verificar qué CSS y JS específicos requiere el componente (e.g. `select/select2.min.css`, `datatable/jquery.dataTables.min.css`).

4. **Emisión del Contrato de Pantalla:**
   - Redactar el reporte que declare:
     - Plantilla inspeccionada y rango de líneas.
     - Clases CSS nativas a reutilizar.
     - Librerías vendor asociadas.
     - Declaración explícita si se requiere un fallback autorizado (ADR-009).
