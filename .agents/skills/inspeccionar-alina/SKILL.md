---
name: inspeccionar-alina
description: Inspecciona físicamente plantillas HTML, CSS y plugins de Alina para garantizar reutilización sin invención.
---

# Skill: inspeccionar-alina

## Propósito
Ejecutar la regla anti-invención antes de diseñar o programar cualquier elemento de interfaz gráfica para CasaPRO, identificando componentes exactos en `admin-dashboard/alina/template/` y librerías en `admin-dashboard/alina/assets/vendor/`.

---

## Procedimiento Obligatorio

### 1. Recepción del Requerimiento
- Identificar los componentes visuales necesarios (e.g. tabla con paginación server-side, modal de formulario centrado, selector select2 con búsqueda, diálogo de confirmación sweetalert).

### 2. Búsqueda Física en `admin-dashboard/alina/template/`
- Ubicar el archivo HTML representativo entre las 116 plantillas:
  - **Tablas y Listados:** `blank.html`, `data_table.html`, `ready_to_use_table.html`, `basic_table.html`.
  - **Formularios y Controles:** `default_forms.html`, `ready_to_use_form.html`, `base_inputs.html`, `input_groups.html`, `select.html`, `date_picker.html`, `file_upload.html`.
  - **Modales para CRUD Asíncrono:** `modals.html` (modal sizing: `modal-lg`, `modal-xl` con `.modal-dialog-centered` y `.modal-dialog-scrollable`).
  - **Diálogos de Confirmación:** `sweetalert.html` (para anulaciones y bajas lógicas).
  - **Pestañas y Perfiles:** `profile.html`, `nav_tabs.html`.
  - **Iconografía Oficial:** Inspeccionar `admin-dashboard/alina/assets/vendor/fontawesome/css/all.css` (Font Awesome es el único sistema oficial de iconografía de CasaPRO).

### 3. Inspección de Dependencias en `admin-dashboard/alina/assets/vendor/`
- Verificar qué CSS y JS específicos requiere el componente (e.g. `fontawesome/css/all.css`, `datatable/jquery.dataTables.min.css`, `sweetalert/sweetalert.js`).

### 4. Emisión del Contrato de Pantalla
- Redactar el reporte que declare:
  - Plantilla inspeccionada y rango de líneas.
  - Clases CSS nativas a reutilizar.
  - Librerías vendor asociadas.
  - Declaración explícita de fallbacks autorizados (e.g. PristineJS para validación declarativa frontend según ADR-009).
