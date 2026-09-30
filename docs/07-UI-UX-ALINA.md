# 07 — Sistema de Diseño UI/UX: Plantilla Alina

## 1. Inventario Físico Verificado de Alina

Tras la inspección física directa en `admin-dashboard\alina\`, se certifica la composición real del sistema de diseño oficial de CasaPRO:

- **Ruta de Plantillas HTML:** `admin-dashboard\alina\template\` (116 plantillas de referencia).
- **Plantilla Base Obligatoria:** `admin-dashboard\alina\template\blank.html`.
- **Ruta de Recursos:** `admin-dashboard\alina\assets\`.
- **Ruta de Documentación:** `admin-dashboard\documentation\index.html`.

---

## 2. Inventario de Librerías y Plugins en `assets/vendor/`

| Plugin / Librería | Archivos Clave | Propósito en CasaPRO |
| :--- | :--- | :--- |
| **bootstrap** | `bootstrap.bundle.min.js`, `bootstrap.min.css` | Framework base Bootstrap 5 (grid, modales, alertas, offcanvas). |
| **tabler-icons** | `tabler-icons.css` | Iconografía principal del sistema (`ti ti-*`). |
| **phosphor** | `phosphor-*.css`, `phosphor.js` | Iconografía complementaria de alta fidelidad. |
| **fontawesome** | `css/all.css` | Iconografía estándar de soporte. |
| **ionio-icon** | `css/iconoir.css` | Iconografía lineal y minimalista. |
| **simplebar** | `simplebar.css`, `simplebar.js` | Scrollbars personalizados en menús y contenedores. |
| **datatable** | `jquery.dataTables.min.js`, `dataTables.responsive.min.js`, `datatable2/*` (buttons, pdfmake, jszip) | Tablas interactivas con paginación, búsqueda, exportación a Excel/PDF. |
| **select** | `select2.min.css`, `select2.min.js` | Selects avanzados con autocompletado y búsqueda rápida. |
| **flatpickr** | `flatpickr.min.css`, `flatpickr.js` | Selectores de fecha y rangos temporales. |
| **sweetalert** | `sweetalert.js` | Modales de confirmación, diálogos de éxito, advertencia y error. |
| **notifications** | `toastify.min.css`, `toastify-js.js` | Notificaciones flotantes no intrusivas tipo toast. |
| **filepond** | `filepond.css`, `filepond.min.js`, plugins de preview y validación | Carga de archivos y documentos con previsualización arrastrar y soltar. |
| **leaflet-maps** | `leaflet.css`, `leaflet.js`, imágenes de marcadores | Mapas interactivos para visualización planimétrica y GIS de lotes. |
| **apexcharts** | `apexcharts.css`, `apexcharts.min.js` | Gráficos interactivos de dashboards comerciales y financieros. |
| **chartjs** | `chart.js` | Gráficos estadísticos secundarios. |
| **cleavejs** | `cleave.min.js` | Máscaras de entrada para moneda, teléfonos y números de documento. |
| **dual_list_boxes**| `dual-listbox.css`, `dual-listbox.js` | Asignación de permisos a roles y lotes a asesores. |
| **jstree** | `style.min.css`, `jstree.min.js` | Árboles jerárquicos de proyectos, sectores y organigramas. |
| **sortable** | `Sortable.min.js` | Reordenamiento interactivo de elementos. |
| **trumbowyg** | `trumbowyg.min.css`, `trumbowyg.min.js` | Editor de texto enriquecido para contratos, cartas y plantillas. |

---

## 3. Anatomía Estructural de `blank.html`

Toda vista del sistema CasaPRO debe derivarse de la estructura definida en `admin-dashboard\alina\template\blank.html`:

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Título Pantalla | CasaPRO</title>
    <!-- 1. Google Fonts: Lexend Deca -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
    <!-- 2. Tabler Icons -->
    <link href="/assets/vendor/tabler-icons/tabler-icons.css" rel="stylesheet">
    <!-- 3. Bootstrap 5 CSS -->
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- 4. Simplebar CSS -->
    <link href="/assets/vendor/simplebar/simplebar.css" rel="stylesheet">
    <!-- 5. Vendor CSS específico (ej. datatable, select2, flatpickr) -->
    <!-- 6. App CSS Alina -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <!-- 7. Responsive CSS Alina -->
    <link href="/assets/css/responsive.css" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <!-- Loader Alina -->
    <div class="loader-wrapper"><div class="loader-box"><div class="loader-6"><div></div><div></div></div></div></div>

    <!-- Navegación Lateral Alina (app-navbar) -->
    <!-- Semi side nav (iconos nivel 1) + Main side nav (submenús nivel 2 y 3) -->

    <!-- Contenedor Principal (app-content / main) -->
    <div class="app-content">
        <!-- Header superior (búsqueda, notificaciones, perfil de usuario) -->
        <!-- Breadcrumbs -->
        <main>
            <div class="container-fluid">
                <!-- CONTENIDO ESPECÍFICO DE LA PANTALLA -->
            </div>
        </main>
        <!-- Footer -->
    </div>
</div>
<!-- Scripts base obligatorios -->
<script src="/assets/js/jquery-3.6.3.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/simplebar/simplebar.js"></script>
<!-- Scripts de vendor específicos -->
<script src="/assets/js/theme_customizer.js"></script>
<script src="/assets/js/script.js"></script>
<!-- Script modular propio de CasaPRO -->
</body>
</html>
```

---

## 4. Contrato de Pre-Implementación de Pantallas

Antes de escribir código HTML para cualquier pantalla, el agente debe declarar formalmente su contrato de ensamble:

```text
PANTALLA: [Nombre de la pantalla]
Tipo: [CRUD | Listado | Formulario | Dashboard | Detalle]
Plantilla Alina Base: [blank.html]
Referencias Alina Inspeccionadas:
- Componente A: [ej. data_table.html líneas 45-80]
- Modal B: [ej. modals.html líneas 120-160]
- Selector C: [ej. select.html líneas 200-230]
Dependencias CSS requeridas: [select2.min.css, jquery.dataTables.min.css]
Dependencias JS requeridas: [select2.min.js, jquery.dataTables.min.js, sweetalert.js]
Fallback o Extensión declarada: [Ninguno | PristineJS para validación declarativa]
```

---

## 5. Implementación Estandarizada de DataTables

Para listados medianos o grandes (Personas, Clientes, Lotes, Pagos, Auditoría), se utiliza DataTables con carga asíncrona server-side:

- **Estructura HTML en vista:**
  ```html
  <div class="table-responsive">
      <table id="tablaPersonas" class="table table-striped table-hover align-middle w-100">
          <thead>
              <tr>
                  <th>Doc. Identidad</th>
                  <th>Nombres y Apellidos</th>
                  <th>Contacto</th>
                  <th>Tipo Persona</th>
                  <th>Estado</th>
                  <th class="text-end">Acciones</th>
              </tr>
          </thead>
          <tbody></tbody>
      </table>
  </div>
  ```
- **Conector JS:** Inicialización mediante Fetch/Ajax contra el endpoint JSON correspondiente, pasando parámetros de búsqueda, ordenamiento y paginación.
