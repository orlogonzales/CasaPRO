---
name: crear-crud
description: Genera el flujo integral de una entidad de negocio bajo la arquitectura MVC desacoplada de CasaPRO.
---

# Skill: crear-crud

## Propósito
Implementar un módulo CRUD empresarial completo bajo el **Patrón CRUD Asíncrono Oficial de CasaPRO** (ADR-024):
$$\text{LISTADO} + \text{DATATABLE} + \text{MODAL ALINA} + \text{PRISTINEJS} + \text{FETCH/JSON} + \text{SINCRONIZACIÓN ASÍNCRONA}$$
Erradica las recargas completas de página (`crear.php -> guardar -> redirect -> listado`) y garantiza una experiencia de usuario moderna, ágil y protegida por todas las capas de seguridad de CasaPRO.

---

## Flujo de Trabajo Estandarizado

### 1. Definir DTOs con Allowlist Estricta (`app/DTOs/`)
- Crear `Crear{Entidad}DTO`, `Actualizar{Entidad}DTO` y `CambiarEstado{Entidad}DTO`.
- Aplicar allowlist rígida en `desdeArray()`: cualquier campo desconocido en raíz o colecciones lanza inmediatamente `ValidacionExcepcion` (HTTP 422) para blindar contra Mass Assignment.
- Validar tipos, longitudes, rangos y obligatoriedad.

### 2. Crear Repositorio PDO Parametrizado (`app/Repositorios/`)
- Sentencias preparadas nativas (`PDO::ATTR_EMULATE_PREPARES => false`).
- Métodos atómicos: `obtenerPorId()`, `insertar()`, `actualizar()`, `cambiarEstado()`, `listarParaDataTables()`.
- Consulta server-side optimizada con `COUNT(*)` para `recordsTotal` y `recordsFiltered`.
- **Inmutabilidad:** Prohibido el uso de sentencias `DELETE` físico en registros históricos del dominio.

### 3. Crear Servicio de Dominio Transaccional (`app/Servicios/`)
- Validar reglas e invariantes de negocio (unicidad, estados válidos, coherencia de importes).
- Coordinar la transacción PDO única que envuelve las mutaciones de la entidad y la inserción atómica en `auditorias` mediante `AuditoriaServicio`.
- Garantizar `rollBack()` total ante cualquier fallo de persistencia.

### 4. Crear Controlador Web y Endpoints REST JSON (`app/Controladores/`)
- Método visual `index()`: renderiza la vista contenedora del módulo inyectando títulos, breadcrumbs, assets CSS/JS y modales Alina.
- Endpoints JSON REST:
  - `GET /api/{entidad}`: consulta paginada para DataTables validando con `ConsultaDataTablesDTO` (whitelist rígida).
  - `GET /api/{entidad}/{id}`: ficha 360 o datos de edición por ID (HTTP 200 / 404).
  - `POST /api/{entidad}`: creación con DTO allowlist (HTTP 201).
  - `PUT /api/{entidad}/{id}`: actualización integral con DTO allowlist (HTTP 200).
  - `PATCH /api/{entidad}/{id}/estado`: baja lógica o transición de estado auditable (HTTP 200).
- Aplicar middlewares: `GuardiaActorMiddleware` (*Deny by Default*), `CsrfMiddleware` (en toda mutación) y autorización RBAC/Scope.

### 5. Diseñar la Vista Integrada Alina (`app/Vistas/modulos/{modulo}/index.php`)
- **Política Modal vs Página:**
  - *Modal Alina por Defecto:* Para entidades y catálogos administrativos estándar (Personas, Proveedores, Lotes, Bancos, Categorías).
  - *Página Independiente / Wizard:* Exclusivo para procesos comerciales multietapa o financieros complejos (Ventas, Financiamiento directo, múltiples titulares), justificando brevemente la excepción.
- **Estructura:**
  - Contenedor DataTables `.app-datatable-default .overflow-auto .app-scroll` con tabla responsive.
  - Filtros rápidos superiores y botón de adición `[+ Nuevo]` que abre el modal.
  - Diálogo modal centrado y scrolleable (`modal-dialog-centered modal-dialog-scrollable modal-lg/modal-xl`) con estructura Alina (`modals.html`).
  - Formulario con `.needs-validation`, campos estructurados en `row g-3`, grupos de entrada con iconos Font Awesome y contenedor `.invalid-feedback`.

### 6. Desarrollar el Módulo JavaScript Modular Propio (`public/assets/js/modulos/{modulo}/...js`)
- Código encapsulado bajo espacio de nombres `window.CasaPro{Entidad}` (ES6+, sin `var`).
- **DataTables Server-Side:** conector `ajax` adaptado a `window.fetch()` nativo (cero llamadas a `$.ajax()`, `$.get()`, `$.post()`, `$.getJSON()`), debouncing de 400 ms en búsqueda y resolución dinámica de URL mediante `data-api-...`.
- **Validación Frontend:** Inicializar PristineJS sobre el formulario modal.
- **Prevención de Doble Envío:** Deshabilitar botón submit (`disabled = true`) y mostrar spinner (`.spinner-border`) durante la petición.
- **Manejo de Errores (422):** Ante fallo de validación backend, **el modal permanece abierto**, los datos ingresados se conservan intactos y los errores se mapean a los campos.
- **Sincronización Asíncrona:** Al completar la mutación, cerrar el modal, resetear formulario y refrescar la tabla mediante `tabla.ajax.reload(null, false)`. Prohibido el uso de `location.reload()`.
- **Tratamiento de Página Vacía:** Si la baja lógica deja la página actual con 0 registros, reposicionar a la página previa válida.
- **Confirmación Visual:** Disparar **SweetAlert2** oficial de Alina antes de enviar cualquier petición de desactivación o anulación.
