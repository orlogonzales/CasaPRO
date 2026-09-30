# 15 — Catálogo de Skills Propios de CasaPRO

## 1. Concepto de Skill en CasaPRO

Un **Skill** en CasaPRO es un procedimiento operativo estándar reutilizable y determinista, diseñado para ejecutar tareas de desarrollo complejas siguiendo estrictamente la gobernanza, las convenciones y los patrones arquitectónicos del proyecto.

---

## 2. Catálogo de los 16 Skills Oficiales

### 1. `inspeccionar-alina`
- **Objetivo:** Inspeccionar físicamente las 116 plantillas de `admin-dashboard/alina/template/` y `documentation/` para ubicar el componente, HTML, CSS, plugin o variante requerida.
- **Entrada:** Requerimiento funcional o visual (ej. *formulario con select múltiple y fecha*).
- **Salida:** Reporte con archivo plantilla real, líneas exactas, clases CSS y dependencias de vendor requeridas.

### 2. `crear-pantalla-alina`
- **Objetivo:** Ensamblar una nueva vista basada en `blank.html` incorporando cabecera, sidebar dinámico con tooltips delegados, iconografía Font Awesome exclusiva, DataTables y modales Alina.
- **Entrada:** Contrato de pantalla verificado por `inspeccionar-alina`.
- **Salida:** Archivo de vista PHP en `app/Vistas/modulos/{modulo}/{pantalla}.php`.

### 3. `crear-crud`
- **Objetivo:** Generar el flujo integral de una entidad bajo el patrón oficial asíncrono: Listado DataTables + Modal Alina + Formulario + PristineJS + Fetch/JSON + sincronización `ajax.reload(null, false)` sin recarga de página completa.
- **Entrada:** Nombre de la entidad, atributos de negocio y política modal/página.
- **Salida:** Módulo funcional completo desacoplado (Controlador, DTOs allowlist, Servicio transaccional, Repositorio PDO, Vista con Modal y módulo JS modular).

### 4. `crear-datatable-dinamico`
- **Objetivo:** Implementar un listado interactivo server-side conectando DataTables con endpoints JSON mediante `window.fetch()` nativo, con debouncing de 400 ms, conservación estricta de contexto (página, búsqueda, orden) y sincronización con `tabla.ajax.reload(null, false)`.
- **Entrada:** Endpoint API, columnas mapeadas en DTO allowlist y filtros de búsqueda.
- **Salida:** Conector JavaScript estandarizado y tabla HTML Alina responsive.

### 5. `crear-formulario`
- **Objetivo:** Construir un formulario estilizado con componentes Alina (`needs-validation`), validación declarativa con PristineJS, máscaras CleaveJS, prevención de doble envío (botón submit deshabilitado + spinner) y permanencia del formulario ante errores 422.
- **Entrada:** Campos requeridos, reglas de validación y endpoint de destino.
- **Salida:** Fragmento HTML y controlador JS asíncrono con manejo de estados `NORMAL -> PROCESANDO -> ÉXITO/ERROR`.

### 6. `crear-modal`
- **Objetivo:** Crear un diálogo modal Bootstrap 5 centrado y scrolleable (`modal-dialog-centered modal-dialog-scrollable modal-lg/modal-xl`) según `modals.html` de Alina para creación, edición rápida y confirmación con SweetAlert2.
- **Entrada:** Título, tamaño estándar Alina y contenido del cuerpo.
- **Salida:** Componente modal en vista y ciclo de vida JS (apertura, poblado por fetch, reseteo).

### 7. `crear-endpoint-json`
- **Objetivo:** Implementar una ruta y método de controlador que retorne una respuesta JSON normalizada (`estado`, `codigo`, `mensaje`, `datos`, `errores`) validando con DTO Allowlist estricto (422 ante campos desconocidos).
- **Entrada:** Ruta HTTP, método (GET, POST, PUT, PATCH) y DTO de entrada.
- **Salida:** Acción de controlador protegida por GuardiaActorMiddleware, CsrfMiddleware y RBAC.

### 8. `crear-servicio-dominio`
- **Objetivo:** Encapsular la lógica de negocio, validaciones complejas, transacciones PDO y emisión de eventos de auditoría para una operación específica.
- **Entrada:** Requerimiento de negocio (ej. *formalizar contrato de venta*).
- **Salida:** Clase de servicio en `app/Servicios/` con tipado estricto.

### 9. `crear-repositorio-pdo`
- **Objetivo:** Construir la capa de persistencia PDO con consultas 100% preparadas y métodos atómicos de lectura y escritura.
- **Entrada:** Tabla SQL y operaciones de acceso requeridas.
- **Salida:** Clase de repositorio en `app/Repositorios/`.

### 10. `crear-migracion`
- **Objetivo:** Generar un archivo SQL secuencial con timestamp que defina o altere tablas respetando tipos `DECIMAL`, claves foráneas con `RESTRICT`, índices y sincronización obligatoria con `SQL/casa-pro.sql`.
- **Entrada:** Definición del esquema de base de datos.
- **Salida:** Archivo SQL incremental en `SQL/migraciones/` y actualización del consolidado `SQL/casa-pro.sql`.

### 11. `crear-privilegio`
- **Objetivo:** Registrar un nuevo privilegio granular (`modulo.accion`) en la base de datos y asignarlo a los roles autorizados.
- **Entrada:** Código de privilegio, módulo y roles destinatarios.
- **Salida:** Migración o semilla SQL que actualiza el catálogo RBAC.

### 12. `auditar-operacion`
- **Objetivo:** Inyectar el registro de auditoría transversal en una operación sensible, capturando el snapshot previo, el nuevo valor, usuario e IP.
- **Entrada:** Entidad, entidad_id, acción y payloads.
- **Salida:** Inserción en la tabla `auditorias`.

### 13. `gestionar-archivos`
- **Objetivo:** Implementar la carga, almacenamiento privado en `storage/uploads/`, validación de firma binaria MIME y descarga autenticada de expedientes.
- **Entrada:** Archivo subido, entidad vinculada y ámbito de empresa.
- **Salida:** Registro en `documentos` y archivo físico renombrado con UUID.

### 14. `generar-documento`
- **Objetivo:** Ensamblar y emitir un documento PDF formal (contrato, cronograma, recibo, estado de cuenta) a partir de una plantilla HTML y datos dinámicos.
- **Entrada:** Tipo de documento y datos de la transacción.
- **Salida:** Archivo PDF generado y firmado con hash de integridad.

### 15. `ejecutar-gates`
- **Objetivo:** Ejecutar la verificación rigurosa de los 12 gates de calidad universales, la batería de 6 gates de CRUD asíncrono (G-CRUD-1 al G-CRUD-6) y las pruebas financieras antes de aprobar una entrega.
- **Entrada:** Código modificado en la microfase.
- **Salida:** Reporte formal de auditoría de calidad con dictamen `PASS` o `FAIL`.

### 16. `cerrar-microbaseline`
- **Objetivo:** Consolidar el estado del proyecto tras la aprobación unánime de QA, actualizar `CHANGELOG.md`, realizar el commit atómico y crear la etiqueta Git.
- **Entrada:** Identificador de la microfase y resumen de cambios.
- **Salida:** Tag Git y nuevo baseline congelado.
