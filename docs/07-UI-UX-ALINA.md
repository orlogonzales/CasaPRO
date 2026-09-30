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
| **fontawesome** | `css/all.css`, fuentes en `fonts/fontawesome/` | **Único sistema oficial de iconografía de CasaPRO** (`fa-solid`, `fa-brands`, `fa-regular`). |
| **simplebar** | `simplebar.css`, `simplebar.js` | Scrollbars personalizados en menús y contenedores. |
| **datatable** | `jquery.dataTables.min.js`, `dataTables.responsive.min.js` | Tablas interactivas con paginación server-side, búsqueda rápida y ordenamiento. |
| **select** | `select2.min.css`, `select2.min.js` | Selects avanzados con autocompletado y búsqueda rápida. |
| **flatpickr** | `flatpickr.min.css`, `flatpickr.js` | Selectores de fecha y rangos temporales. |
| **sweetalert** | `sweetalert.js` | Modales de confirmación interactiva para acciones destructivas/anulaciones. |
| **notifications** | `toastify.min.css`, `toastify-js.js` | Notificaciones flotantes no intrusivas tipo toast. |
| **filepond** | `filepond.css`, `filepond.min.js`, plugins | Carga de archivos y documentos con previsualización. |
| **leaflet-maps** | `leaflet.css`, `leaflet.js` | Mapas interactivos para visualización planimétrica y GIS de lotes. |
| **apexcharts** | `apexcharts.css`, `apexcharts.min.js` | Gráficos interactivos de dashboards comerciales y financieros. |
| **cleavejs** | `cleave.min.js` | Máscaras de entrada para moneda, teléfonos y números de documento. |

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
    <!-- 1. Google Fonts: Fira Sans (Normal, Condensed, Extra Condensed) oficial de CasaPRO -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans+Extra+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- 2. Font Awesome oficial de Alina (sistema iconográfico único de CasaPRO) -->
    <link href="/assets/vendor/fontawesome/css/all.css" rel="stylesheet">
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

Tras la inspección física rigurosa de `admin-dashboard/alina/template/data_table.html` y `admin-dashboard/alina/assets/js/data_table.js`:
- **DataTables — VERIFICADO EN ALINA:** Alina incluye DataTables tradicional en modo client-side (`jquery.dataTables.min.js`, extensiones `buttons.html5.min.js`, `jszip.min.js`, `pdfmake.min.js`) inicializado sobre tablas HTML completas o arrays JS estáticos en memoria.
- **Procesamiento Server-Side — ARQUITECTURA PROPIA CASAPRO:** Alina **no** incluye implementación server-side. El protocolo server-side (`serverSide: true`, `processing: true`, conector Fetch/Ajax contra endpoints JSON con `LIMIT` y `COUNT(*)` en Repositorios PDO) constituye una **arquitectura propia de CasaPRO** diseñada para garantizar alto rendimiento en listados masivos (Personas, Lotes, Pagos, Auditoría).

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

---

## 6. Política sobre jQuery Heredado de Alina

CasaPRO no utilizará jQuery ni `$.ajax()` para desarrollar lógica propia. Sin embargo, se permite conservar jQuery (`jquery-3.6.3.min.js`) exclusivamente cuando constituya una dependencia técnica heredada y verificada de Alina (como en `script.js` para el desvanecimiento del preloader `.loader-wrapper` o inicializadores internos de plugins de la plantilla). Su presencia no autoriza bajo ningún concepto utilizarlo en nuevos módulos de CasaPRO. Toda lógica nueva propia continuará utilizando JavaScript moderno (ES6+), Fetch API nativo y JSON estructurado.

---

## 7. Catálogo Oficial de Páginas de Error

CasaPRO estandariza sus respuestas de error a partir de las plantillas originales verificadas físicamente en `admin-dashboard\alina\template\`:

| Código HTTP | Plantilla Alina Original | Vista CasaPRO | Imagen Oficial (`public/assets/images/error/`) | Botón de Acción Alina | Título en Español |
| :---: | :--- | :--- | :--- | :--- | :--- |
| **400** | `error_400.html` | `modulos/errores/400.php` | `error-400.png` | `bg-gradient-warning` | Solicitud Incorrecta |
| **403** | `error_403.html` | `modulos/errores/403.php` | `error-403.png` | `bg-gradient-danger` | Acceso Denegado |
| **404** | `error_404.html` | `modulos/errores/404.php` | `error-404.png` | `bg-gradient-primary` | Página No Encontrada |
| **500** | `error_500.html` | `modulos/errores/500.php` | `error-500.png` | `bg-gradient-success` | Error Interno del Servidor |
| **503** | `error_503.html` | `modulos/errores/503.php` | `error-503.png` | `bg-gradient-primary` | Servicio No Disponible |

### Arquitectura de Manejo de Errores:
1. **Layout Aislado (`layouts/error.php`):** Las páginas de error no cargan el sidebar ni el header administrativo; utilizan la clase nativa `.error-container` de Alina, que centra el contenido vertical y horizontalmente con la imagen de fondo oficial.
2. **Sincronización HTTP Real:** Cada vista visual emite su código HTTP real en la cabecera del servidor (`http_response_code`), garantizando que un error 403 retorne HTTP 403, un 404 retorne HTTP 404, etc.
3. **Soporte de Peticiones Ajax/JSON:** Si la petición entrante es Ajax (`X-Requested-With: XMLHttpRequest`) o solicita JSON, el controlador emite una estructura JSON con `{ estado: 'error', codigo, mensaje }` y el código HTTP correspondiente.
4. **Seguridad y Trazabilidad:** Las vistas públicas nunca revelan trazas de ejecución (stack traces), sentencias SQL, contraseñas, variables de entorno ni rutas físicas locales (`D:\laragon\...`). Para errores 500, se genera un identificador de correlación seguro (ej. `ERR-7B0B619D`) que se muestra al usuario y se registra en los logs técnicos internos del servidor.

---

## 8. Patrón Visual Oficial del Perfil de Usuario (`profile.html`)

Tras la inspección física de `admin-dashboard\alina\template\profile.html` (2,079 líneas, 143 KB), queda formalmente adoptada como el **patrón visual oficial para el Perfil de Usuario de CasaPRO**.

### Componentes y Estructura Identificada:
1. **Cabecera de Perfil:**
   - Avatar de usuario con control de edición rápida (`#imageUpload`) e icono `ti ti-photo-heart`.
   - Insignia de estado / verificación (`instagram-check-mark.png` o equivalente Tabler).
   - Datos resumidos: Nombre completo, cargo/rol institucional y empresa asignada.
   - Contadores rápidos de actividad (publicaciones, clientes gestionados, operaciones asignadas).
2. **Navegación por Pestañas (Tabs Bootstrap 5):**
   - Pestaña de Perfil / Resumen de Datos (`#profileApp` con clase `.app-tabs-primary`).
   - Pestaña de Actividades y Auditoría Reciente (`#activities`).
   - Pestaña de Proyectos / Asignaciones Territoriales (`#profileProjects`).
   - Pestañas complementarias según el perfil de seguridad del usuario.
3. **Distribución en Cuadrícula (Grid):**
   - **Columna Lateral (`col-xl-4`):** Información personal de contacto, correo corporativo, teléfono, sede de trabajo, habilidades y enlaces institucionales.
   - **Columna Principal (`col-xl-8`):** Paneles de detalle, formularios de actualización de preferencias permitidas y líneas de tiempo de eventos.

### Directrices de Gobernanza para el Perfil de Usuario:
- **Separación de Identidad:** Se reafirma el principio `Persona != Personal != Usuario`. El perfil de usuario es una interfaz de visualización y gestión del `Usuario` del sistema; jamás debe almacenar ni duplicar datos civiles propios de la entidad raíz `Persona`.
- **Inmutabilidad de Permisos por Frontend:** Un usuario no podrá alterar su rol, privilegios, empresa asignada ni ámbito territorial desde la pantalla de perfil. Dichas modificaciones son potestad exclusiva del módulo de Administración bajo autorización RBAC backend.
- **Gestión Segura de Fotografía:** La asignación definitiva de foto de perfil no se realizará en esta fase ni mediante Base64 en base de datos. Se integrará posteriormente con el subsistema de almacenamiento privado y descarga controlada fuera del webroot de CasaPRO.
- **Confirmación de Alcance:** En esta Fase 0B **NO** se implementa el "Perfil 360" funcional ni su persistencia en base de datos. Se deja establecido y documentado exclusivamente su patrón visual y arquitectónico para su desarrollo en la Fase 1.

---

## 9. Directorio de Personas y Ficha de Identidad (Microfase 1E)

Implementado en la ruta visual `/personas` (`app/Vistas/modulos/personas/index.php`) bajo política estricta de *Deny by Default* (`GuardiaActorMiddleware`):

### 1. Tabla DataTables Server-Side con Fetch Nativo:
- **Librería Base:** DataTables 1.13.3 y extensión Responsive 2.4.0 verificadas en Alina (`assets/vendor/datatable/`).
- **Arquitectura Frontend:** Cero llamadas a `$.ajax()`, `$.get()`, `$.post()` o `$.getJSON()`. La función `ajax` de DataTables actúa como adaptador invocando a `window.fetch()` nativo en JavaScript ES6+.
- **Resolución Dinámica de URL:** La URL hacia la API se resuelve desde el atributo `data-api-personas-url="<?= Vista::url('api/personas') ?>"`, garantizando portabilidad idéntica tanto en `https://app.casa-pro.test/` como en `https://localhost/app.casa-pro/`.
- **Contrato Server-Side:** Envía `draw`, `start`, `length`, `search` (con debouncing de 400 ms), `order_column`, `order_dir`, `tipo_persona` y `estado` hacia `ConsultaDataTablesDTO`.

### 2. Estándares Visuales Alina (Anti-Invención):
- **Estados de Persona:** Implementados con *Variants of badge* de Alina (`.badge .text-light-success` para `ACTIVO`, `.badge .text-light-secondary` para `INACTIVO`). Se prohíbe el uso de bordes punteados (*dotted*) o estilos ajenos a la plantilla.
- **Tipos de Persona:** Implementados con *Variants of chip* de Alina (`.chip .bg-light-primary .text-primary` para `NATURAL`, `.chip .bg-light-info .text-info` para `JURÍDICA`).
- **Acciones Funcionales:** Un único botón iconográfico probado (`.btn .btn-outline-primary .btn-sm .icon-btn .b-r-4` con icono `fa-solid fa-eye`) para consultar la Ficha de Identidad. Sin controles muertos ni botones deshabilitados anunciando fases futuras.

### 3. Ficha de Identidad de Persona:
- **Estructura Modal:** Modal grande scrollable centrado (`.modal-dialog .modal-lg .modal-dialog-centered .modal-dialog-scrollable`) según `modals.html` y `profile.html`.
- **Organización por Pestañas:** Pestañas `nav-tabs nav-bottom-line` para *Datos Generales*, *Documentos y Contactos*, *Domicilios* y *Representación Legal*.
- **Gobernanza y Privacidad:** La ficha consulta exclusivamente datos de identidad entregados legítimamente por `GET /api/personas/{id}` (1D). Queda estrictamente excluida la exposición de auditoría forense, snapshots o histórico de estados.
- **Inmunidad XSS:** Manipulación del DOM mediante `document.createElement()` y asignación exclusiva a través de `.textContent` y sanitización contextual de strings en celdas de tabla.
- **Información Territorial:** Despliegue limpio de Departamento, Provincia, Distrito, Dirección y Referencia sin incorporar al usuario leyendas técnicas de certificación.

---

## 10. Patrón Visual y Componentes para CRUDs Asíncronos con Modales Alina y DataTables

### 1. Diálogos Modales Oficiales de Alina (`modals.html`):
- **Tamaños Permitidos:** Se utilizan estrictamente las clases estándar de Bootstrap 5 verificadas en Alina:
  - `modal-sm`: Diálogos breves de confirmación o solicitud de motivo/comentario.
  - `modal-dialog` (por defecto, ~500px): Formularios cortos (1 a 4 campos).
  - `modal-lg` (~800px): Formularios administrativos estándar (Personas, Proveedores, Lotes).
  - `modal-xl` (~1140px): Formularios multisección o extensos que conservan aptitud modal.
  - `modal-fullscreen`: Exclusivo para vistas de diseño planimétrico o visores documentales.
  *Regla Anti-Invención:* Queda prohibido inventar anchos en píxeles personalizados mediante CSS inline o clases arbitrarias.
- **Comportamiento Centrado y Scrolleable Obligatorio:** Todo modal de formulario o ficha debe incorporar:
  ```html
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
  ```
  Esto garantiza que formularios con múltiples secciones o desplegables nunca desborden la ventana gráfica del usuario ni queden cortados en resoluciones laptop o tablet.

### 2. Estructura Canónica de Formulario en Modal:
```html
<div class="modal fade" id="modalFormulario" tabindex="-1" aria-labelledby="modalFormularioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalFormularioTitulo">Registrar Nueva Entidad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEntidad" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" id="entidadId" name="id" value="">
                    <!-- Campos organizados en grid Bootstrap row g-3 -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="campoTexto" class="form-label f-s-13 text-secondary">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="campoTexto" name="nombre" class="form-control" required>
                            <div class="invalid-feedback">El nombre es obligatorio.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnGuardarEntidad" class="btn btn-primary btn-sm">
                        <span id="btnGuardarSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <span id="btnGuardarTexto"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 3. Botones de Acción en Filas DataTables:
Para preservar la limpieza visual y ergonomía de las tablas Alina, las acciones por fila se diseñan mediante botones iconográficos compactos con tooltips:
- **Ver Ficha / Detalle:** `.btn .btn-outline-primary .btn-sm .icon-btn .b-r-4` con `<i class="fa-solid fa-eye"></i>`.
- **Editar:** `.btn .btn-outline-warning .btn-sm .icon-btn .b-r-4` con `<i class="fa-solid fa-pen-to-square"></i>`.
- **Desactivar / Activar:** `.btn .btn-outline-danger .btn-sm .icon-btn .b-r-4` con `<i class="fa-solid fa-toggle-on"></i>` o `<i class="fa-solid fa-toggle-off"></i>`.
- **Accesibilidad:** Cada botón debe incluir atributo `title` semántico y `data-bs-toggle="tooltip"` delegable.

### 4. Diálogos de Confirmación con SweetAlert2 de Alina (`sweetalert.html`):
Toda operación sensible (desactivación de persona, anulación de recibo, baja lógica) dispara una alerta interactiva antes de emitir la petición:
```javascript
Swal.fire({
    title: '¿Confirmar operación?',
    text: 'Esta acción actualizará el estado de la entidad en el sistema.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#082f32',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, confirmar',
    cancelButtonText: 'Cancelar'
}).then((result) => {
    if (result.isConfirmed) {
        ejecutarCambioEstado(id, nuevoEstado);
    }
});
```

