# Bitácora de Cambios (CHANGELOG) — CasaPRO

Todas las modificaciones notables de este proyecto se registrarán cronológicamente en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a la gestión de **Micro-Baselines**.

## [Fase 1C: Actores, Auditoría Transversal, CSRF y Seguridad de Mutaciones] — 2026-09-29

### Añadido
- **Directiva Transversal Vinculante de Seguridad:**
  - Actualización oficial en `docs/04-SEGURIDAD.md`: 10 principios rectores (Deny by default, soberanía de backend, defensa IDOR/BOLA multiempresa, sentencias preparadas nativas PDO con lista blanca interna, prevención de asignación masiva, protección CSRF sin bypass Bearer, escape contextual XSS, sesiones seguras, correlación transversal y pruebas negativas obligatorias).
- **Catálogo de Actores del Sistema:**
  - Migración `SQL/migraciones/2026_09_29_000006_crear_tablas_actores_y_auditoria.sql`:
    - Creación de tabla `actores` (id, tipo_actor, codigo UNIQUE, nombre, estado, metadatos JSON). Sin columna prematura `usuario_id`.
    - Siembra inicial del actor raíz del sistema: `SISTEMA_CASAPRO` (ID 1).
  - Entidad de dominio `App\Modelos\Actor`.
- **Bitácora Inmutable de Auditoría Forense:**
  - Tabla `auditorias` en base de datos con columnas JSON nativas (`datos_anteriores`, `datos_nuevos`, `metadatos`), vinculada por clave foránea `RESTRICT` hacia `actores`.
  - Entidad de dominio `App\Modelos\AuditoriaRegistro`.
  - Servicio transversal `App\Servicios\AuditoriaServicio`:
    - Obligatoriedad de operar dentro de la misma transacción PDO del servicio de dominio.
    - Falla atómica forzando rollback en caso de error de persistencia de auditoría.
    - Minimización de snapshots en actualizaciones (almacenando únicamente los campos mutados).
    - Sanitización recursiva de claves sensibles (`password`, `token`, `clave`, etc. a `[PROTEGIDO]`).
    - Inmutabilidad estricta a nivel de aplicación (append-only: sin métodos de modificación o eliminación).
- **Administración Segura de Sesiones y Protección Anti-CSRF:**
  - `App\Core\GestorSesion`: Sesiones estrictas (`HttpOnly`, `Secure=true`, `SameSite=Lax`, `use_strict_mode=1`).
  - `App\Core\ContextoPeticion`: Generación de ID de correlación criptográfico (`REQ-...`) y extracción segura de IP y User-Agent.
  - `App\Core\CsrfServicio`: Generación de tokens aleatorios de 32 bytes (`random_bytes(32)`) y validación segura con `hash_equals`.
  - `App\Middlewares\CsrfMiddleware`: Intercepción obligatoria de mutaciones (`POST`, `PUT`, `PATCH`, `DELETE`) con prohibición absoluta de bypass por cabecera Bearer genérica.
  - Helpers de asistencia CSRF: `Vista::csrfToken()`, `Vista::csrfCampo()`, y meta tag `<meta name="csrf-token">` en `cabecera-head.php`.
  - Cabeceras de seguridad globales en Front Controller (`public/index.php`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Correlation-ID`.
- **Esquema Consolidado Oficial Sincronizado:**
  - `SQL/casa-pro.sql`: Actualizado al micro-baseline 1C (19 tablas, 164 columnas, 75 índices, 19 FKs).
- **Pruebas Automatizadas de Seguridad y Auditoría:**
  - `tests/verificar_seguridad_auditoria.php`: 43 pruebas exhaustivas (sesiones, correlación, ciclo de vida CSRF, intercepción de mutaciones, prohibición de bypass Bearer, modelo de actores, sanitización recursiva, inmutabilidad append-only y atomicidad transaccional compartida con rollback).
  - `tests/verificar_persistencia.php`: Gate SQL 100% PASS (19 tablas, 164 columnas, 75 índices, 19 FKs idénticas entre Camino A y Camino B).
  - `tests/lint_php.php`: 49/49 archivos PHP PASS con sintaxis estricta PHP 8.3.
- **Decisiones Arquitectónicas (ADRs):**
  - Incorporación de `ADR-021: Arquitectura Transversal de Actores, Bitácora Inmutable de Auditoría Forense y Protección Estricta Anti-CSRF`.

### Estado
- Micro-baseline 1C cerrado satisfactoriamente (`mb-fase1c-seguridad-auditoria`).

---

## [Fase 1B: Catálogos Estructurales y Modelo Normalizado de Persona] — 2026-09-29

### Añadido
- **Catálogos Estructurales de Identidad:**
  - Migración `SQL/migraciones/2026_09_29_000002_crear_catalogos_identidad.sql`: Creación y siembra de tablas maestras normalizadas sin ENUMs (`sexos`, `estados_civiles`, `tipos_documento`, `tipos_contacto`, `tipos_direccion`).
  - Catálogo `tipos_documento`: Parámetros de validación y reglas para DNI, RUC, CE y Pasaporte.
- **Catálogo Oficial Completo del INEI de UBIGEO y Países:**
  - Migración `SQL/migraciones/2026_09_29_000003_crear_estructura_ubigeo.sql`: Estructura jerárquica relacional de `paises`, `departamentos`, `provincias` y `distritos` con claves foráneas `RESTRICT`.
  - Migración `SQL/migraciones/2026_09_29_000004_cargar_datos_ubigeo.sql`: Carga masiva de datos oficiales del INEI del Perú: 11 países, 25 departamentos/regiones, 196 provincias y 1,874 distritos. Cero catálogos incompletos o piloto.
- **Modelo Relacional Normalizado de Identidad Persona:**
  - Migración `SQL/migraciones/2026_09_29_000005_crear_modelo_persona.sql`:
    - `personas`: Entidad raíz indivisible con estado restringido exclusivamente a `ACTIVO | INACTIVO` (prohibido `BLOQUEADO`).
    - `persona_natural`: Extensión de atributos civiles (nombres, apellidos, nacimiento, sexo, estado civil, nacionalidad, profesión).
    - `persona_juridica`: Extensión corporativa (razón social, nombre comercial, fecha de constitución, objeto social) sin RUC incrustado.
    - `persona_documentos`: Subsistema 1:N de documentos oficiales con clave única estricta `UNIQUE (tipo_documento_id, numero_documento)` e indicador de documento principal.
    - `persona_contactos`: Medios de contacto multicanal 1:N (email, móvil, fijo, WhatsApp).
    - `persona_direcciones`: Direcciones físicas y fiscales 1:N vinculadas a distritos del UBIGEO oficial.
    - `persona_representantes`: Historial explícito de representación legal entre Persona Jurídica y Persona Natural (cargo, partida registral, vigencia).
- **Esquema Consolidado Oficial Sincronizado:**
  - `SQL/casa-pro.sql`: Actualizado con el esquema consolidado completo y vigente (17 tablas, 141 columnas, 63 índices, 18 FKs).
- **Entidades de Dominio en PHP 8.3:**
  - Clases fuertemente tipadas en `app/Modelos/`: `Persona`, `PersonaNatural`, `PersonaJuridica`, `PersonaDocumento`, `PersonaContacto`, `PersonaDireccion`, `PersonaRepresentante`.
- **Pruebas Automatizadas de Integridad:**
  - `tests/verificar_integridad_identidad.php`: 22 pruebas de integridad referencial, unicidad de documentos, relaciones 1:1 y 1:N, representación legal, transiciones de estado y política `ON DELETE RESTRICT`, ejecutadas en transacción revertida (cero basura residual).
  - `tests/verificar_persistencia.php`: Suite ampliada para Gate SQL comparando tablas, columnas, índices, claves foráneas y reglas referenciales (100% PASS: Esquema A == Esquema B).
- **Decisiones Arquitectónicas (ADRs):**
  - Incorporación de `ADR-020: Modelo Normalizado de Identidad Persona, Estados Restringidos y Catálogo Oficial INEI de UBIGEO`.

### Estado
- Micro-baseline 1B cerrado satisfactoriamente (`mb-fase1b-identidad`).

---

## [Fase 1A: Persistencia PDO y Motor de Migraciones] — 2026-09-29

### Añadido
- **Configuración de Entorno:**
  - `.env.example`: Plantilla oficial versionada con variables de configuración de aplicación y base de datos sin secretos.
  - `.env`: Archivo local privado excluido de Git mediante `.gitignore`.
  - `composer.json` y `composer.lock`: Adopción formal de `vlucas/phpdotenv` (v5.7+) y configuración de proyecto PHP 8.3 con PSR-4.
  - `App\Core\CargadorEntorno`: Módulo de carga determinista de variables de entorno con integración prioritaria de `vlucas/phpdotenv` y respaldo nativo seguro sin dependencias externas.
  - `config/database.php`: Archivo de configuración que lee dinámicamente las credenciales de entorno mediante `CargadorEntorno`.
- **Capa de Persistencia PDO Inyectable:**
  - `App\Core\ProveedorConexion`: Proveedor/Fábrica inyectable de conexiones PDO desacoplado de Singletons rígidos globales, con opciones estrictas de conexión (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false`) y registro seguro de errores sin fuga de contraseñas.
- **Motor Determinista de Migraciones SQL:**
  - `App\Core\MigradorSQL`: Motor en PHP 8.3 que lee ordenadamente scripts incrementales en `SQL/migraciones/`, registra lotes de ejecución en la tabla de control `migraciones`, garantiza idempotencia y asegura detención inmediata ante errores.
  - `bin/migrador.php`: Herramienta CLI de consola para ejecutar migraciones, consultar su estado (`estado`) e importar el esquema consolidado (`consolidado`).
- **Esquema Relacional Inicial y Migraciones Oficiales:**
  - `SQL/migraciones/2026_09_29_000001_crear_tabla_migraciones.sql`: Primera migración incremental de infraestructura creando la tabla de control `migraciones`.
  - `SQL/casa-pro.sql`: Esquema consolidado oficial vigente que permite la reconstrucción limpia desde cero de la base de datos CasaPRO correspondiente al micro-baseline 1A.
- **Pruebas y Verificación:**
  - `tests/verificar_persistencia.php`: Suite automatizada de verificación del Gate SQL comparando metadatos estructurales de `information_schema` entre reconstrucción por migraciones (Camino A) y por consolidado (Camino B), validando 100% de identidad en tablas, columnas e índices, así como idempotencia en ejecuciones sucesivas.
- **Decisiones Arquitectónicas (ADRs):**
  - Incorporación de `ADR-018: Carga de Variables de Entorno con vlucas/phpdotenv y Respaldo Determinista`.
  - Incorporación de `ADR-019: Proveedor Inyectable de Conexión PDO sin Singleton Rígido y Motor Determinista de Migraciones`.
- **Blindaje de Servidor Web:**
  - `.htaccess` y `public/.htaccess`: Protección activa y explícita del nuevo directorio `SQL/` y archivos `.sql` frente a accesos web directos.

### Estado
- Micro-baseline 1A cerrado satisfactoriamente (`mb-fase1a-persistencia`).

---

## [Fase 0A: Gobernanza y Arquitectura] — 2026-09-29

### Añadido
- **Inspección Física de Alina:** Inspección real de 116 plantillas HTML en `admin-dashboard\alina\template\`, recursos en `assets\vendor\` y documentación en `documentation\index.html`.
- **Regla Anti-Invención:** Establecimiento del protocolo estricto: Buscar $\rightarrow$ Verificar $\rightarrow$ Reutilizar $\rightarrow$ Adaptar $\rightarrow$ Extender $\rightarrow$ Excepcionalmente Crear.
- **Control de Versiones Git:** Inicialización del repositorio Git local y configuración de `.gitignore`.
- **Paquete Documental Maestro (`docs/`):**
  - `docs/00-FICHA-MAESTRA-CASAPRO.md`: Definición funcional completa de la Matriz Bonifacio (Multiempresa, Personas, Proyectos, GIS, CRM, Ventas, Tesorería, Postventa, APV).
  - `docs/01-GOBERNANZA.md`: Jerarquía de fuentes, regla anti-invención y rol del orquestador.
  - `docs/02-ARQUITECTURA.md`: Arquitectura MVC desacoplada en PHP 8.3 nativo, PDO, sin ORM.
  - `docs/03-CONVENCIONES.md`: Convenciones de nomenclatura completa en español y tipado estricto.
  - `docs/04-SEGURIDAD.md`: Modelo RBAC con Scopes territoriales, CSRF, XSS, rate limiting y sesiones seguras.
  - `docs/05-MODELO-DOMINIO.md`: Entidad Persona central y desacoplamiento Persona $\neq$ Cliente $\neq$ Personal $\neq$ Usuario.
  - `docs/06-BASE-DATOS.md`: Lineamientos de diseño relacional, UTF8mb4, tipos DECIMAL y migraciones secuenciales.
  - `docs/07-UI-UX-ALINA.md`: Inventario físico de Alina, anatomía de `blank.html` y contrato de pantallas.
  - `docs/08-API-Y-CONTRATOS.md`: Respuestas JSON normalizadas y protocolo server-side de DataTables.
  - `docs/09-AUDITORIA.md`: Registro transversal e inmutabilidad financiera.
  - `docs/10-ARCHIVOS-Y-DOCUMENTOS.md`: Carga segura fuera del webroot y validación binaria MIME.
  - `docs/11-PRUEBAS-Y-GATES.md`: Los 12 gates de calidad universales y pruebas transaccionales.
  - `docs/12-GIT-Y-MICROBASELINES.md`: Gestión atómica de commits y micro-baselines.
  - `docs/13-ROADMAP.md`: Cronograma de ejecución por fases (Fase 0A hasta Fase 8).
  - `docs/14-AGENTES.md`: Definición formal de los 10 agentes especializados con implementador único.
  - `docs/15-SKILLS.md`: Catálogo y contratos operativos de los 16 skills propios de CasaPRO.
  - `docs/16-DECISIONES-ARQUITECTONICAS.md`: Registro oficial de ADR-001 al ADR-017.
- **Configuración de Agentes:** Creación de reglas y agentes en el espacio de trabajo.

### Estado
- Micro-baseline de Gobernanza cerrado satisfactoriamente (`mb-fase0a-gobernanza`).

---

## [Fase 0B: Plantilla Maestra Alina y Núcleo MVC] — 2026-09-29

### Añadido
- **Núcleo MVC Desacoplado en PHP 8.3:**
  - `public/index.php`: Front Controller con autocargador PSR-4 nativo para `App\`.
  - `app/Core/Peticion.php`: Abstracción HTTP de método, URI, parámetros, JSON, headers e IP cliente.
  - `app/Core/Respuesta.php`: Emisión estandarizada de respuestas HTML, JSON y manejo de solicitudes HEAD.
  - `app/Core/Enrutador.php`: Router con soporte GET, POST, PUT, DELETE, regex dinámico y 404 integrado.
  - `app/Core/Vista.php`: Motor de renderizado con inyección en layout maestro y función de escape XSS (`Vista::e()`).
  - `app/Controladores/BaseControlador.php` e `InicioControlador.php`: Controlador base y acción de verificación inicial.
  - `config/rutas.php`: Enrutamiento centralizado y endpoint seguro `/api/salud`.
- **Plantilla Maestra Alina (`app/Vistas/layouts/maestro.php`):**
  - Ensamblada a partir de la anatomía física de `admin-dashboard/alina/template/blank.html`.
  - Componentes parciales desacoplados: `cabecera-head.php`, `preloader.php`, `navegacion-lateral.php` (menú 3 niveles), `barra-superior.php` (modo oscuro, toggle, perfil), `migas-pan.php`, `pie-pagina.php` y `pie-scripts.php`.
  - Vistas iniciales: `app/Vistas/modulos/inicio/index.php` (verificación de layout) y `app/Vistas/modulos/errores/404.php`.
- **Seguridad Web:**
  - `.htaccess` y `public/.htaccess`: Blindaje contra acceso directo a directorios internos (`app/`, `config/`, `database/`, `docs/`, `storage/`, `.git/`, `.agents/`) y archivos sensibles (`.env`, `.log`, `.sql`, `.lock`).
  - Compatibilidad simultánea probada en `https://app.casa-pro.test/` y `https://localhost/app.casa-pro/`.
- **Assets de Producción:**
  - Copia selectiva de dependencias mínimas en `public/assets/` (Bootstrap 5, Tabler Icons con fuentes woff2, SimpleBar, jQuery de Alina para preloader, script.js compilado y logos).
- **Gobernanza:**
  - Formalizada la excepción de jQuery en `AGENTS.md`, `07-UI-UX-ALINA.md` y `ADR-007`.
  - Clarificada la separación entre DataTables (Alina client-side) y procesamiento server-side (arquitectura propia CasaPRO) en `07-UI-UX-ALINA.md` y `ADR-010`.
- **Catálogo Oficial de Errores Alina:**
  - Vistas adaptadas al español en `app/Vistas/modulos/errores/`: `400.php`, `403.php`, `404.php`, `500.php`, `503.php`.
  - Layout dedicado `app/Vistas/layouts/error.php` con el estilo y background nativo `.error-container` de Alina.
  - Controlador centralizado `app/Controladores/ErrorControlador.php` y excepción `app/Core/ExcepcionHttp.php`.
  - Sincronización estricta de códigos de estado HTTP reales (400, 403, 404, 500, 503) con soporte JSON para Ajax.
  - Blindaje de seguridad en excepciones internas (cero fuga de stack traces, SQL o rutas del servidor) y emisión de códigos de correlación técnicos para soporte.
  - Incorporación de imágenes oficiales en `public/assets/images/error/` (`error-400.png` a `error-503.png`).
- **Patrón Visual de Perfil de Usuario:**
  - Inspección y documentación exhaustiva de `admin-dashboard/alina/template/profile.html` como patrón visual oficial para la Fase 1.
  - Declaración del desacoplamiento `Persona != Personal != Usuario` y reglas de inmutabilidad frontend.
- **Soporte Multi-Entorno:**
  - Ajuste de extracción y normalización de ruta en `Peticion.php` y `Vista::url()` para compatibilidad transparente en `https://app.casa-pro.test/` y `https://localhost/app.casa-pro/`.

### Estado
- Micro-baseline de Plantilla Maestra, Errores y Núcleo MVC completado y consolidado (`mb-fase0b-plantilla-maestra`).
