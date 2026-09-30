# 16 — Registro de Decisiones Arquitectónicas (ADR)

## Índice de Decisiones

- [ADR-001: Adopción de PHP 8.3](#adr-001-adopción-de-php-83)
- [ADR-002: Patrón MVC Propio Desacoplado](#adr-002-patrón-mvc-propio-desacoplado)
- [ADR-003: Persistencia Exclusiva con PDO](#adr-003-persistencia-exclusiva-con-pdo)
- [ADR-004: Prescindir de ORMs Monolíticos](#adr-004-prescindir-de-orms-monolíticos)
- [ADR-005: Idioma Español en Código Propio](#adr-005-idioma-español-en-código-propio)
- [ADR-006: Alina Bootstrap 5 como Sistema de Diseño Oficial](#adr-006-alina-bootstrap-5-como-sistema-de-diseño-oficial)
- [ADR-007: JavaScript Modular Moderno sin Dependencia de jQuery en Código Propio](#adr-007-javascript-modular-moderno-sin-dependencia-de-jquery-en-código-propio)
- [ADR-008: Fetch API y Respuestas JSON Estandarizadas](#adr-008-fetch-api-y-respuestas-json-estandarizadas)
- [ADR-009: Validación Frontend con HTML5/Bootstrap y PristineJS como Fallback Autorizado](#adr-009-validación-frontend-con-html5bootstrap-y-pristinejs-como-fallback-autorizado)
- [ADR-010: DataTables Dinámicos con Paginación Server-Side](#adr-010-datatables-dinámicos-con-paginación-server-side)
- [ADR-011: Persona como Identidad Raíz y Única](#adr-011-persona-como-identidad-raíz-y-única)
- [ADR-012: Desacoplamiento Persona ≠ Cliente ≠ Personal ≠ Usuario](#adr-012-desacoplamiento-persona--cliente--personal--usuario)
- [ADR-013: Autorización Multidimensional RBAC + Scopes Territoriales](#adr-013-autorización-multidimensional-rbac--scopes-territoriales)
- [ADR-014: Independencia de Menú Dinámico respecto a Autorización Backend](#adr-014-independencia-de-menú-dinámico-respecto-a-autorización-backend)
- [ADR-015: Auditoría Transversal e Inmutable](#adr-015-auditoría-transversal-e-inmutable)
- [ADR-016: Prohibición de Eliminación Destructiva en Tablas Financieras](#adr-016-prohibición-de-eliminación-destructiva-en-tablas-financieras)
- [ADR-017: Módulo APV Desacoplado de la Operadora Inmobiliaria](#adr-017-módulo-apv-desacoplado-de-la-operadora-inmobiliaria)
- [ADR-018: Carga de Variables de Entorno con vlucas/phpdotenv y Respaldo Determinista](#adr-018-carga-de-variables-de-entorno-con-vlucasphpdotenv-y-respaldo-determinista)
- [ADR-019: Proveedor Inyectable de Conexión PDO sin Singleton Rígido y Motor Determinista de Migraciones](#adr-019-proveedor-inyectable-de-conexión-pdo-sin-singleton-rígido-y-motor-determinista-de-migraciones)
- [ADR-020: Modelo Normalizado de Identidad Persona, Estados Restringidos y Catálogo UBIGEO Vigente de CasaPRO](#adr-020-modelo-normalizado-de-identidad-persona-estados-restringidos-y-catálogo-ubigeo-vigente-de-casapro)
- [ADR-021: Arquitectura Transversal de Actores, Bitácora Inmutable de Auditoría Forense y Protección Estricta Anti-CSRF](#adr-021-arquitectura-transversal-de-actores-bitácora-inmutable-de-auditoría-forense-y-protección-estricta-anti-csrf)
- [ADR-022: Arquitectura Desacoplada de Personas, DTOs con Allowlist y DataTables Server-Side con Whitelist Rígida](#adr-022-arquitectura-desacoplada-de-personas-dtos-con-allowlist-y-datatables-server-side-con-whitelist-rígida)
- [ADR-023: Estandarización de Identidad Visual: Tipografía Fira Sans, Font Awesome Exclusivo y Theme Customizer Adaptado](#adr-023-estandarización-de-identidad-visual-tipografía-fira-sans-font-awesome-exclusivo-y-theme-customizer-adaptado)
- [ADR-024: Patrón CRUD Asíncrono Oficial: Listado DataTables + Modal Alina + PristineJS + Fetch/JSON + Sincronización sin Recarga Completa](#adr-024-patrón-crud-asíncrono-oficial-listado-datatables--modal-alina--pristinejs--fetchjson--sincronización-sin-recarga-completa)

---

### ADR-001: Adopción de PHP 8.3
- **Estado:** Aceptado
- **Contexto:** Se requiere un entorno moderno, de alto rendimiento y con seguridad estricta para la gestión empresarial e inmobiliaria.
- **Decisión:** Utilizar PHP 8.3 como versión base mínima con `declare(strict_types=1);`, tipado estricto en métodos, constructor promotion y nuevas funciones del lenguaje.
- **Consecuencias:** Mayor robustez en tiempo de compilación/ejecución, código auto-documentado y prevención temprana de errores de tipo.

### ADR-002: Patrón MVC Propio Desacoplado
- **Estado:** Aceptado
- **Contexto:** Evitar la dependencia de frameworks monolíticos (Laravel, Symfony) que imponen deuda técnica y sobrecarga de procesamiento innecesaria.
- **Decisión:** Desarrollar un MVC ligero y específico para CasaPRO compuesto por Front Controller (`public/index.php`), Enrutador, Middlewares, Controladores, Servicios, Repositorios, Vistas y Manejador Centralizado de Errores (`ErrorControlador` y `ExcepcionHttp` para 400, 403, 404, 500 y 503 con emisión HTTP real y códigos de correlación seguros).
- **Consecuencias:** Control absoluto sobre el ciclo de vida de la petición, rendimiento óptimo en servidores compartidos o VPS, respuestas de error unificadas y cero fuga de información técnica sensible.

### ADR-003: Persistencia Exclusiva con PDO
- **Estado:** Aceptado
- **Contexto:** La seguridad contra inyecciones SQL y la portabilidad de base de datos son fundamentales.
- **Decisión:** Toda comunicación con la base de datos se realizará mediante la extensión nativa PDO utilizando parámetros vinculados obligatorios en sentencias preparadas.
- **Consecuencias:** Eliminación total del vector de ataque SQLi y óptimo rendimiento en transacciones.

### ADR-004: Prescindir de ORMs Monolíticos
- **Estado:** Aceptado
- **Contexto:** Los ORMs automáticos ocultan consultas lentas, generan problemas de N+1 queries y complican el control fino de transacciones financieras complejas.
- **Decisión:** No utilizar Eloquent ni Doctrine. La persistencia se organiza en la capa de Repositorios con consultas SQL explícitas e indexadas.
- **Consecuencias:** Consultas transparentes, auditables, de máxima velocidad y facilidad de optimización directa de índices en el motor de base de datos.

### ADR-005: Idioma Español en Código Propio
- **Estado:** Aceptado
- **Contexto:** El dominio de negocio inmobiliario y tributario local (linderos, hectáreas, cuota inicial, adenda, minuta, libro de reclamaciones) pierde claridad al traducirse forzadamente al inglés.
- **Decisión:** Todo el código propio (clases, variables, métodos, tablas, columnas, rutas) se escribirá estrictamente en español.
- **Consecuencias:** Coherencia absoluta con la terminología de los usuarios, operadores legales y auditores del negocio.

### ADR-006: Alina Bootstrap 5 como Sistema de Diseño Oficial
- **Estado:** Aceptado
- **Contexto:** Es necesario mantener una interfaz profesional, consistente y responsiva sin inventar estilos arbitrarios.
- **Decisión:** Alina Bootstrap 5 (ubicada en `admin-dashboard\alina\`) es la única referencia visual del proyecto. `blank.html` es la plantilla base obligatoria para toda pantalla administrativa, el conjunto `error_400.html` a `error_503.html` para pantallas de error (con layout `.error-container`), y `profile.html` como patrón visual oficial para el Perfil de Usuario.
- **Consecuencias:** Experiencia visual unificada, cero dispersión estética y reutilización eficiente de componentes probados.

### ADR-007: JavaScript Modular Moderno sin Dependencia de jQuery en Código Propio
- **Estado:** Aceptado
- **Contexto:** La plantilla Alina incluye `jquery-3.6.3.min.js` y comportamientos de `script.js` (como el desvanecimiento del preloader `.loader-wrapper`) dependen de él. Sin embargo, el desarrollo propio de CasaPRO debe ser moderno, modular y libre de dependencias obsoletas.
- **Decisión:** CasaPRO no utilizará jQuery ni `$.ajax()` para desarrollar lógica propia. Sin embargo, se permite conservar jQuery exclusivamente cuando constituya una dependencia técnica heredada y verificada de Alina o de alguno de sus plugins originales. Su presencia no autoriza utilizarlo en nuevos módulos de CasaPRO. Toda lógica propia se escribe en módulos ES6+ nativos con Fetch API y respuestas JSON estructuradas.
- **Consecuencias:** Código frontend ligero, estándar y desacoplado, sin alterar innecesariamente scripts centrales de Alina ni introducir regresiones visuales.

### ADR-008: Fetch API y Respuestas JSON Estandarizadas
- **Estado:** Aceptado
- **Contexto:** La comunicación asíncrona entre cliente y servidor debe ser homogénea para facilitar el manejo de errores y estados de carga.
- **Decisión:** Utilizar Fetch API con el envoltorio `clienteHttp` y un formato uniforme de respuesta JSON (`estado`, `codigo`, `mensaje`, `datos`, `errores`).
- **Consecuencias:** Tratamiento consistente de errores HTTP 422 de validación y retroalimentación inmediata al usuario vía Toastify o SweetAlert.

### ADR-009: Validación Frontend con HTML5/Bootstrap y PristineJS como Fallback Autorizado
- **Estado:** Aceptado
- **Contexto:** La inspección física certificó que Alina implementa validación nativa HTML5/Bootstrap 5 (`.needs-validation`, `checkValidity()`). Para formularios complejos multietapa o validaciones asíncronas sin jQuery, se requiere una librería declarativa ligera.
- **Decisión:** Utilizar primordialmente la validación nativa de Alina/Bootstrap 5, formalizando la incorporación de PristineJS (vanilla JS, sin jQuery, <4KB) como extensión autorizada para validación declarativa avanzada.
- **Consecuencias:** Cumplimiento de la regla anti-invención: se declara formalmente la búsqueda en Alina y se justifica la extensión técnica.

### ADR-010: DataTables (Alina) con Procesamiento Server-Side (Arquitectura Propia CasaPRO)
- **Estado:** Aceptado
- **Contexto:** La inspección física de `admin-dashboard/alina/template/data_table.html` y `admin-dashboard/alina/assets/js/data_table.js` demostró que Alina únicamente incluye DataTables en modalidad tradicional / client-side (con renderizado sobre HTML preexistente o arrays JS estáticos). Tablas de negocio masivas (personas, lotes, cuotas, auditorías) no pueden cargarse completas en el navegador.
- **Decisión:**
  1. *DataTables — Verificado en Alina:* Reutilizar los assets visuales y librerías base de Alina (`jquery.dataTables.min.js`, extensiones de botones).
  2. *Procesamiento Server-Side — Arquitectura Propia CasaPRO:* Implementar un protocolo server-side propio desacoplado (`serverSide: true`, `processing: true`), alimentado por endpoints JSON que ejecutan paginación con `LIMIT` y `COUNT(*)` en Repositorios PDO.
- **Consecuencias:** Se aprovecha el sistema de diseño visual de Alina sin atribuirle erróneamente la lógica server-side, garantizando respuestas sub-segundo con millones de registros.

### ADR-011: Persona como Identidad Raíz y Única
- **Estado:** Aceptado
- **Contexto:** Los clientes, asesores de ventas, empleados y usuarios del sistema comparten atributos civiles y de contacto.
- **Decisión:** La tabla `personas` es la entidad raíz de identidad civil/tributaria (DNI/RUC único). Las demás tablas actúan como roles de negocio vinculados por `persona_id`.
- **Consecuencias:** Eliminación total de datos redundantes o inconsistencias al actualizar direcciones o teléfonos.

### ADR-012: Desacoplamiento Persona ≠ Cliente ≠ Personal ≠ Usuario
- **Estado:** Aceptado
- **Contexto:** Una persona física puede comprar lotes (cliente), trabajar en la empresa (personal) y tener acceso a la plataforma (usuario).
- **Decisión:** Separar las entidades `clientes`, `personal` y `usuarios` en tablas independientes con claves foráneas apuntando a `personas`.
- **Consecuencias:** Flexibilidad total del modelo de dominio para representar la realidad operativa de las empresas.

### ADR-013: Autorización Multidimensional RBAC + Scopes Territoriales
- **Estado:** Aceptado
- **Contexto:** Un usuario puede tener permiso funcional para vender, pero solo en el Proyecto Las Palmeras de la Empresa Inmobiliaria Norte.
- **Decisión:** La autorización requiere validar tanto el privilegio funcional (RBAC: `modulo.accion`) como el ámbito territorial (Scope: `GLOBAL`, `EMPRESA`, `PROYECTO`, `SECTOR`).
- **Consecuencias:** Aislamiento estricto de información entre empresas y proyectos dentro del mismo sistema multiempresa.

### ADR-014: Independencia de Menú Dinámico respecto a Autorización Backend
- **Estado:** Aceptado
- **Contexto:** Ocultar un botón o ítem de menú no garantiza que el usuario no intente enviar la petición HTTP al endpoint.
- **Decisión:** El menú dinámico es una interfaz de navegación adaptativa; toda acción del backend valida de forma independiente la sesión, el token CSRF, el privilegio RBAC y el scope territorial.
- **Consecuencias:** Seguridad en profundidad contra ataques de escalamiento de privilegios o navegación forzada.

### ADR-015: Auditoría Transversal e Inmutable
- **Estado:** Aceptado
- **Contexto:** Se requiere trazabilidad forense de cada cambio de datos y operación financiera en el sistema.
- **Decisión:** Registrar automáticamente en la tabla inmutable `auditorias` todo insert, update o delete, guardando estado previo, nuevo, usuario, IP y timestamp.
- **Consecuencias:** Cumplimiento normativo, prevención de fraudes internos y facilidad de diagnóstico técnico.

### ADR-016: Prohibición de Eliminación Destructiva en Tablas Financieras
- **Estado:** Aceptado
- **Contexto:** La eliminación de registros contables o de pagos corrompe los balances históricos y destruye la cadena de custodia.
- **Decisión:** Prohibir terminantemente sentencias `DELETE` en tablas financieras (`pagos`, `cuotas`, `cajas`, `asientos`). Toda rectificación se realiza por anulación y contrapartida.
- **Consecuencias:** Integridad financiera absoluta e imposibilidad de discrepancias en arqueos de caja o auditorías contables.

### ADR-017: Módulo APV Desacoplado de la Operadora Inmobiliaria
- **Estado:** Aceptado
- **Contexto:** La Asociación de Propietarios de Vivienda (APV) es una entidad civil independiente de la empresa desarrolladora del proyecto inmobiliario.
- **Decisión:** El módulo APV opera de manera desacoplada, compartiendo la referencia al lote y al propietario pero con sus propios balances comunales, directivas y cuotas vecinales.
- **Consecuencias:** Autonomía comunal para los vecinos y deslinde de responsabilidades operativas y contables para la empresa inmobiliaria.

### ADR-018: Carga de Variables de Entorno con vlucas/phpdotenv y Respaldo Determinista
- **Estado:** Aceptado
- **Contexto:** La configuración de base de datos, credenciales y claves no debe versionarse en el repositorio de código ni quemarse en scripts PHP. Se requiere una solución estándar en PHP para cargar archivos `.env` sin inventar analizadores sintácticos frágiles.
- **Decisión:**
  1. Incorporar la librería estándar de la industria `vlucas/phpdotenv` (v5.7+) mediante Composer.
  2. Encapsular la lectura a través de `App\Core\CargadorEntorno`, manteniendo un cargador determinista nativo como mecanismo de respaldo seguro en caso de ausencia del directorio `vendor/`.
  3. Proporcionar un archivo `.env.example` versionado sin credenciales sensibles y excluir `.env` del control de versiones mediante `.gitignore`.
- **Consecuencias:** Configuración portable, cumplimiento de Twelve-Factor App, cero filtración de secretos a Git y resiliencia ante entornos con o sin Composer instalado en producción.

### ADR-019: Proveedor Inyectable de Conexión PDO sin Singleton Rígido y Motor Determinista de Migraciones
- **Estado:** Aceptado
- **Contexto:** Los patrones Singleton globales rígidos dificultan la inyección de dependencias, las pruebas automatizadas con múltiples bases de datos y el aislamiento modular. Asimismo, la base de datos requiere evolución secuencial determinista e idempotente.
- **Decisión:**
  1. Implementar `App\Core\ProveedorConexion` como una fábrica/proveedor inyectable que administra conexiones `\PDO` configuradas estrictamente (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false`).
  2. Implementar `App\Core\MigradorSQL` para ejecutar migraciones cronológicas desde `SQL/migraciones/`, registrar el lote de ejecución en la tabla `migraciones` y detenerse inmediatamente ante cualquier fallo DDL/DML.
  3. Sincronizar obligatoriamente todo cambio estructural entre la migración incremental en `SQL/migraciones/` y el esquema consolidado en `SQL/casa-pro.sql` (Gate SQL).
- **Consecuencias:** Desacoplamiento total de la capa de persistencia, alta testabilidad, idempotencia garantizada y consistencia binaria entre entornos reconstruidos desde cero o migrados incrementalmente.

### ADR-020: Modelo Normalizado de Identidad Persona, Estados Restringidos y Catálogo UBIGEO Vigente de CasaPRO
- **Estado:** Aceptado
- **Contexto:** En sistemas inmobiliarios y corporativos multiempresa, los datos civiles y tributarios tienden a duplicarse erróneamente entre clientes, empleados y usuarios. Asimismo, el catálogo geográfico del Perú con frecuencia se siembra de manera parcial o incompleta, provocando inconsistencias en direcciones contractuales.
- **Decisión:**
  1. Establecer `personas` como la identidad raíz indivisible, con extensiones relacionales `persona_natural` y `persona_juridica`.
  2. Restringir el estado de la Persona estrictamente a `ACTIVO | INACTIVO`. Queda terminantemente prohibido el estado `BLOQUEADO` en la identidad civil, reservándolo para el acceso de usuarios y credenciales en fases posteriores.
  3. Desacoplar documentos de identidad en `persona_documentos` con restricción de unicidad estricta `UNIQUE (tipo_documento_id, numero_documento)`, prohibiendo almacenar el RUC directamente en `persona_juridica`.
  4. Modelar la representación legal mediante el historial explícito `persona_representantes` (vinculando Persona Jurídica con Persona Natural).
  5. Incorporar el catálogo UBIGEO vigente de CasaPRO (11 países, 25 departamentos/regiones, 196 provincias y 1,874 distritos; fuente primaria INEI pendiente de certificación documental directa) con códigos oficiales y claves foráneas en cascada controlada (`ON DELETE RESTRICT ON UPDATE CASCADE`).
- **Consecuencias:** Modelo de dominio normalizado de alta fidelidad legal y tributaria, imposibilidad de documentos duplicados, trazabilidad histórica de representantes y soporte de georreferenciación oficial en todo el territorio peruano.

### ADR-021: Arquitectura Transversal de Actores, Bitácora Inmutable de Auditoría Forense y Protección Estricta Anti-CSRF
- **Estado:** Aceptado
- **Contexto:** Las mutaciones de estado en el sistema (creación, edición, cambio de estado) requieren trazabilidad forense inmutable y atómica con la operación del dominio. Asimismo, se requiere proteger las mutaciones HTTP contra ataques CSRF sin introducir vulnerabilidades como el bypass genérico por cabeceras Bearer, y registrar al actor responsable (humano o automatizado) desacoplado de la existencia de una tabla de usuarios aún no implementada.
- **Decisión:**
  1. Crear el catálogo `actores` con semilla inicial `SISTEMA_CASAPRO` (ID 1) sin columna `usuario_id` prematura, permitiendo la evolución limpia hacia cuentas de usuario en fases posteriores.
  2. Implementar `auditorias` en base de datos con columnas JSON nativas para snapshots previos y nuevos, vinculada a `actores` mediante clave foránea restrictiva (`ON DELETE RESTRICT ON UPDATE CASCADE`).
  3. Desarrollar `AuditoriaServicio` con obligación de operar dentro de la misma transacción PDO del servicio de dominio, forzando un rollback si el registro de auditoría falla.
  4. Aplicar minimización estricta de snapshots en actualizaciones (almacenando únicamente los campos mutados) y sanitización recursiva de claves sensibles (`password`, `token`, `clave`, etc. a `[PROTEGIDO]`).
  5. Garantizar inmutabilidad a nivel de aplicación (append-only estricto: sin métodos de modificación o eliminación en `AuditoriaServicio`).
  6. Implementar `GestorSesion` con directivas estrictas (`HttpOnly`, `Secure=true`, `SameSite=Lax`, `use_strict_mode=1`).
  7. Implementar `CsrfServicio` y `CsrfMiddleware` exigiendo tokens aleatorios de 32 bytes (`random_bytes(32)`) y comparación segura en tiempo constante (`hash_equals`). Queda terminantemente prohibido omitir la validación CSRF ante la presencia de cabeceras genéricas `Authorization: Bearer`.
  8. Propagar un identificador único de correlación (`ContextoPeticion`, cabecera `X-Correlation-ID`) para trazabilidad transversal entre logs del servidor y registros de auditoría.
- **Consecuencias:** Trazabilidad forense atómica e inmutable, cero riesgo de desincronización entre mutación de datos y su bitácora, blindaje contra ataques CSRF y cumplimiento estricto del principio de denegación por defecto (*deny by default*).

### ADR-022: Arquitectura Desacoplada de Personas, DTOs con Allowlist y DataTables Server-Side con Whitelist Rígida
- **Estado:** Aceptado (Microfase 1D)
- **Contexto:** La capa de identidad (Personas) requiere exponer operaciones REST seguras y desacopladas (listar con paginación server-side, obtener 360, crear atómico, actualizar integral y transicionar estado) protegiendo el sistema contra Mass Assignment, inyecciones SQL en parámetros DataTables, fugas de SQL en respuestas JSON y manteniendo Deny by Default estricto en servidor.
- **Decisión:**
  1. **Separación Estricta de Capas:** Patrón Controlador -> DTO -> Servicio de Dominio -> Repositorio PDO. El Controlador nunca ejecuta sentencias SQL ni alberga lógica de negocio; su rol es orquestar la recepción HTTP, validación estructural y emisión de respuestas JSON normalizadas.
  2. **DTOs con Allowlist Inviolable:** `CrearPersonaDTO`, `ActualizarPersonaDTO`, `CambiarEstadoPersonaDTO`. Todo campo raíz o anidado no incluido en la lista blanca lanza inmediatamente `ValidacionExcepcion` (HTTP 422). Se prohíbe terminantemente ignorar de forma silenciosa claves desconocidas para evitar vulnerabilidades de *Mass Assignment*.
  3. **Deny by Default y Guardia de Actores:** `GuardiaActorMiddleware` intercepta todas las peticiones (lecturas y mutaciones) y exige sesión de actor autenticado (HTTP 401 si es anónimo). Ninguna petición web anónima adquiere privilegios por fallback hacia `SISTEMA_CASAPRO`; dicho actor queda reservado a CLI, mantenimiento y pruebas automatizadas.
  4. **DataTables Server-Side con Whitelist SQL Rígida:** `ConsultaDataTablesDTO` mapea columnas mediante lista blanca estricta (`MAPA_COLUMNAS`). Cualquier columna manipulada o inyección SQL (e.g. `id; DROP TABLE...`), `order_dir` no admitido, o `start`/`length` negativos o no numéricos lanza `PeticionIncorrectaExcepcion` (HTTP 400). El límite de página se acota rígidamente a un máximo de 100 registros.
  5. **Multiplicidad y Principalidad en Identidad Central:** Se permiten 0..N documentos en identidad raíz (admitiendo prospectos y registros preliminares). Si existen documentos informados, como máximo 1 puede encontrarse activo y marcado como principal (`es_principal = 1`). La misma regla aplica a contactos y direcciones.
  6. **Semántica de Actualización (`PUT /api/personas/{id}`):** Se prohíbe la mutación de `tipo_persona` (HTTP 422). En colecciones secundarias: clave omitida (`null`) preserva registros existentes; array vacío (`[]`) desactiva la colección; array con elementos sincroniza los nuevos y desactiva los anteriores.
  7. **Prohibición Total de `DELETE` Físico:** Se omite cualquier ruta `DELETE /api/personas/{id}`. La transición de estado se ejecuta exclusivamente mediante `PATCH /api/personas/{id}/estado` (ACTIVO <-> INACTIVO) con motivo auditable obligatorio. Se prohíbe asignar `BLOQUEADO` o `ELIMINADO` a Persona.
  8. **Auditoría Atómica y Cero Fuga de SQL:** El servicio de dominio coordina la transacción PDO única que envuelve las mutaciones de identidad y el registro de auditoría. Si la base de datos lanza un error, se garantiza `rollBack()` total y captura limpia sin exponer mensajes SQL al cliente (HTTP 500 genérico con correlation ID registrado en error_log).
- **Consecuencias:** Integridad referencial inviolable, protección garantizada contra Mass Assignment e inyecciones SQL en consultas DataTables, seguridad alineada con Deny by Default, y trazabilidad forense 100% auditable sin deuda técnica.

### ADR-023: Estandarización de Identidad Visual: Tipografía Fira Sans, Font Awesome Exclusivo y Theme Customizer Adaptado
- **Estado:** Aceptado (Microfase Normalización Visual Global)
- **Contexto:** La experiencia visual requería unificar la iconografía oficial, consolidar una tipografía corporativa de alta legibilidad técnica en pantallas de gestión inmobiliaria y habilitar el personalizador de tema de Alina sin dependencias de compra ni opciones innecesarias.
- **Decisión:**
  1. **Tipografía Oficial:** Adopción obligatoria de **Fira Sans** (con variantes `Fira Sans`, `Fira Sans Condensed` y `Fira Sans Extra Condensed` importadas de Google Fonts). `--theme-fonts: "Fira Sans", sans-serif;` se define como la fuente base del sistema en `style.css` y `responsive.css`. Lexend Deca queda retirada del código propio.
  2. **Iconografía Oficial Única:** **Font Awesome** pasa a ser el único sistema oficial de iconografía de CasaPRO. Se trasladaron las fuentes web y estilos verificados de Alina (`assets/vendor/fontawesome/css/all.css`, fuentes en `fonts/fontawesome/`). Se migraron exhaustivamente todas las clases `ti ti-*` a sus equivalentes Font Awesome (`fa-solid`, `fa-brands`) y se eliminaron los assets de Tabler Icons del código propio activo.
  3. **Tooltips Reales Bootstrap 5:** Inicialización delegada global en `script.js` con soporte para elementos estáticos y dinámicos (`[data-bs-toggle="tooltip"]`), garantizando tooltips accesibles y semánticos en el menú lateral y botones de acción.
  4. **Theme Customizer Adaptado:** Restaurado sobre `#theme-customizer-box` y traducido 100% al español. Se eliminaron los enlaces comerciales externos ("Buy Now", ThemeForest) y se omitieron las dos últimas opciones originales de Alina (`Sidebar Variant` y `Font Sizing`) para evitar dispersión técnica. Se conservaron: Colores del tema (gradientes 1 a 6), Diseños del tema (LTR, RTL, Box) y botón Restablecer (`resetCustomizer()`).
- **Consecuencias:** Identidad visual coherente, moderna y libre de artefactos comerciales externos, manteniendo la soberanía y fidelidad absoluta a la plantilla Alina.

### ADR-024: Patrón CRUD Asíncrono Oficial: Listado DataTables + Modal Alina + PristineJS + Fetch/JSON + Sincronización sin Recarga Completa
- **Estado:** Aceptado (Gobernanza Vinculante previa a Microfase 1F)
- **Contexto:** Las operaciones CRUD de gestión inmobiliaria (Personas, Proveedores, Lotes, etc.) deben ofrecer una experiencia ágil, empresarial y moderna, evitando recargas completas de página (`crear.php -> guardar -> redirect -> listado`) y garantizando a la vez validación robusta, prevención de envíos duplicados, confirmación visual de bajas y preservación estricta de la seguridad backend (Deny by Default, CSRF, RBAC, DTOs, PDO y Auditoría).
- **Decisión:**
  1. **Patrón Arquitectónico por Defecto:**
     $$\text{LISTADO} + \text{DATATABLE} + \text{MODAL ALINA} + \text{PRISTINEJS} + \text{FETCH/JSON} + \text{SINCRONIZACIÓN ASÍNCRONA}$$
     Para registros y catálogos administrativos simples, las acciones Crear y Editar se resuelven mediante el diálogo modal oficial de Alina (`#modalFormulario`) sin abandonar el listado.
  2. **Política de Excepción Fundamentada:** Se autoriza el uso de páginas independientes o Wizards para procesos multietapa complejos (ventas inmobiliarias, financiamiento directo, múltiples titulares, expedientes documentales extensos), documentando brevemente en la microfase la razón por la cual el modal no es adecuado.
  3. **Flujo de Mutación Asíncrona (Crear/Editar):**
     - Apertura del modal Alina (`modal-dialog-centered modal-dialog-scrollable modal-lg` o `modal-xl`).
     - Validación declarativa frontend obligatoria con PristineJS (complemento que nunca sustituye al backend).
     - Prevención de doble envío: botón submit deshabilitado inmediatamente con spinner Bootstrap 5 (`.spinner-border`) durante la petición.
     - Petición asíncrona mediante `window.fetch()` nativo (prohibido `$.ajax()`) con cabecera `X-CSRF-Token` y payload JSON estructurado.
     - Procesamiento backend estricto: autenticación de actor, autorización, DTO allowlist, servicio transaccional, repositorio PDO parametrizado y bitácora en `auditorias`.
     - Éxito: cierre de modal, reseteo de formulario y actualización de la DataTable mediante `tabla.ajax.reload(null, false)`. Queda prohibido `location.reload()` o `window.location.reload()`.
  4. **Manejo de Errores de Validación (422):** Ante errores de validación de negocio, **el modal permanece abierto**, los datos ingresados por el usuario no se borran y los errores se asocian a los campos mediante `.invalid-feedback`.
  5. **La Acción Eliminar No es DELETE Físico:** Representa Desactivación, Anulación, Archivo o Baja lógica auditable según las reglas del dominio. Requiere confirmación visual obligatoria mediante **SweetAlert2** de Alina antes de enviar la petición asíncrona.
  6. **Conservación del Contexto de DataTables:** El refresco asíncrono debe conservar estrictamente la página actual, búsqueda, ordenamiento y longitud. Si la baja lógica provoca que la página actual se quede sin registros, el conector debe reubicar automáticamente la tabla en la página anterior válida.
  7. **Cero Mega-Frameworks Prematuros:** No se construirán clases universales monolíticas (`CrudManagerUniversal`). La lógica se implementa modularmente por pantalla reutilizando componentes Alina reales y patrones verificados.
- **Consecuencias:** Experiencia de usuario ágil y moderna, eliminación de recargas completas, conservación de filtros y contexto de búsqueda, prevención eficaz de duplicados, y garantía inviolable de las políticas de seguridad y auditoría de CasaPRO.
