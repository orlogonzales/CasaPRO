# Bitácora de Cambios (CHANGELOG) — CasaPRO

Todas las modificaciones notables de este proyecto se registrarán cronológicamente en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a la gestión de **Micro-Baselines**.
## [Microfase 2C: Administración Web de Empresas] — 2026-09-30

### Añadido
- **Migración DML 000012 y Menú Corporativo (`2026_09_30_000012_sembrar_menu_empresas.sql`):**
  - Siembra determinista sin IDs mágicos en `menu_opciones` para el módulo de empresas bajo `MOD_IDENTIDAD`.
  - Agrupador `GRP_EMPRESAS` (ID 8) y enlace `OPC_EMPRESAS_LISTADO` (ID 9, ruta `empresas`, icono `fa-solid fa-building`).
  - Vinculación canónica con `privilegio_id = (SELECT id FROM privilegios WHERE codigo = 'empresas.ver')`.
  - Ranura `000012` consumida formalmente. Siguiente ranura libre soberana: `000013`.
  - Sincronización estricta en `SQL/casa-pro.sql` (Gate SQL Camino A == Camino B: 28 tablas, 239 columnas, 122 índices, 34 FKs, 12 migraciones idempotentes).
- **Controlador y Rutas Web y API (`EmpresaControlador`, `config/rutas.php`):**
  - Ruta web `GET /empresas` protegida con `AutenticacionMiddleware` y `AutorizacionMiddleware::exigir('empresas.ver')`.
  - Endpoints REST protegidos:
    - `GET /api/empresas` (DataTables server-side con búsqueda, paginación y filtros).
    - `GET /api/empresas/personas-juridicas-disponibles` (Select2 para personas jurídicas activas no vinculadas).
    - `GET /api/empresas/{id}` (Ficha 360° integral).
    - `POST /api/empresas` (Alta vinculada u orquestada de empresas con DTO `CrearEmpresaDTO`).
    - `PUT /api/empresas/{id}` (Edición inmutable de `nombre_corto` con DTO `ActualizarEmpresaDTO`).
    - `PATCH /api/empresas/{id}/estado` (Conmutación de estado con DTO `CambiarEstadoEmpresaDTO`).
- **Repositorio y Servicio de Dominio (`EmpresaRepositorio`, `EmpresaServicio`):**
  - `contarTotal()`, `contarFiltrados()`, `obtenerListadoDataTables()` con whitelist estricta de ordenamiento y búsqueda multi-campo (código, RUC, razón social, nombre comercial, nombre corto).
  - `obtenerPersonasJuridicasDisponibles()` con filtro dinámico y límite seguro.
  - `obtenerDetalleCompleto()` integrando los datos de empresa y la ficha 360° del padrón central (`PersonaRepositorio::obtenerDetalleCompleto360`).
- **Vista Web Alineada con Alina Bootstrap 5 (`app/Vistas/modulos/empresas/index.php`):**
  - Basada en `blank.html`, `data_table.html` y `modals.html` de Alina.
  - Tabla principal `#tablaEmpresas` con atributos `data-api-*` y token CSRF.
  - Barra de filtros rápidos por estado (`ACTIVO` / `INACTIVO`).
  - Modal de alta `#modalCrearEmpresa` (`modal-lg` scrollable) con pestañas duales:
    - Pestaña 1: Vinculación de Persona Jurídica existente con Select2 asistido y preview de datos civiles.
    - Pestaña 2: Alta orquestada con contrato integral de `CrearPersonaDTO` (RUC, botón SUNAT asistido, razón social, nombre comercial, fecha constitución, objeto social, domicilio fiscal con cascada UBIGEO de 3 niveles y contactos institucionales).
  - Modal de edición inmutable `#modalEditarEmpresa`: `persona_id`, `codigo` y RUC bloqueados/readonly; solo `nombre_corto` editable.
  - Modal de Ficha 360° `#modalFichaEmpresa`: navegación por pestañas (Empresa, Identidad Jurídica, Domicilios, Contactos y Representantes Legales).
- **Módulo JavaScript Vanilla ES6+ (`public/assets/js/modulos/empresas/gestion-empresas.js`):**
  - Encapsulado bajo el namespace global `window.CasaProEmpresas`.
  - Adaptador asíncrono para DataTables server-side mediante `window.fetch()` nativo.
  - Cero llamadas a `location.reload()` / `window.location.reload()`. Todas las mutaciones son 100% asíncronas con refresco vía `tabla.ajax.reload(null, false)` conservando página y orden.
  - Cero llamadas a `$.ajax()`, `$.get()`, `$.post()`, `$.getJSON()` en código propio (jQuery exclusivo para vendor DataTables y Select2).
  - Select2 configurado con `dropdownParent: $('#modalCrearEmpresa')` garantizando foco correcto dentro de modales.
  - Cascada dinámica UBIGEO conectada a endpoints `/api/ubigeo/provincias` y `/api/ubigeo/distritos`.
  - Integración con consulta asistida SUNAT (`/api/personas/consultar-documento`).
  - Notificaciones y alertas estandarizadas con SweetAlert2 (`Swal.fire`).
  - Formateo de código corporativo a mayúsculas y máscara numérica en RUC.
- **Inmutabilidad y Cero DELETE Físico:**
  - `persona_id` y `codigo` inmutables tras su creación (rechazo con HTTP 422 ante cualquier intento de alteración).
  - Cero métodos de eliminación física (`eliminar`, `destroy`, `delete`) en controlador y servicio.
  - Cero rutas HTTP DELETE asociadas a empresas en el enrutador.
- **Auditoría Forense Append-Only:**
  - Registro de eventos `CREAR`, `ACTUALIZAR` y `CAMBIAR_ESTADO` en la tabla `auditorias` con Actor USER real del operador en sesión.
- **Suite de Pruebas Automatizadas 2C (`tests/verificar_administracion_empresas_2c.php`):**
  - 87 pruebas automatizadas cubriendo los 11 bloques normativos de la microfase (100% PASS).

---

## [Microfase 2B: Asignaciones Usuario ↔ Empresa y Scopes Territoriales] — 2026-09-30

### Añadido
- **Esquema Relacional y Catálogo de Privilegios 2B (Migración `2026_09_30_000011_crear_asignaciones_usuario_empresa_roles.sql`):**
  - Creación de la tabla soberana 28 `usuario_empresa_roles` en motor InnoDB, charset UTF8mb4 y collation `utf8mb4_unicode_ci`.
  - Columnas canónicas: `id`, `usuario_id` (FK RESTRICT contra `usuarios.id`), `empresa_id` (FK RESTRICT contra `empresas.id`), `rol_id` (FK RESTRICT contra `roles.id`), `estado` (`ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO'`), `asignado_por` (FK RESTRICT contra `actores.id`), `asignado_en` (`DATETIME NOT NULL`), `revocado_por` (FK RESTRICT contra `actores.id` NULL), `revocado_en` (`DATETIME NULL`), `actualizado_en` (`DATETIME NOT NULL`).
  - Restricción de unicidad estricta `uq_usuario_empresa_rol (usuario_id, empresa_id, rol_id)`.
  - Índices optimizados para resolución en tiempo constante: `idx_uer_usuario_empresa_estado`, `idx_uer_empresa_rol_estado`, `idx_uer_estado`.
  - 4 Privilegios granulares del catálogo RBAC: `asignaciones.ver`, `asignaciones.crear`, `asignaciones.editar` y `asignaciones.revocar`.
  - Asignación dinámica de los 4 privilegios al rol `SUPERADMIN` (`WHERE codigo = 'SUPERADMIN'`).
  - Sincronización idéntica y estricta en `SQL/casa-pro.sql` (Gate SQL Camino A == Camino B: 28 tablas, 239 columnas, 122 índices, 34 FKs, 11 migraciones).
  - Cero seeds ficticios.
- **Separación de Tuplas y Autorización Multidimensional:**
  - `usuario_roles` permanece reservado para roles verdaderamente globales (`SUPERADMIN`).
  - `usuario_empresa_roles` gestiona la asignación territorial `(usuario_id, empresa_id, rol_id)`.
  - Un usuario puede tener roles diferenciados según la empresa (ej. Administrador en Empresa 1 y Vendedor en Empresa 2).
  - SUPERADMIN mantiene bypass universal funcional, condicionado a que la empresa requerida se encuentre en estado `ACTIVO` (fail-closed estricto si está inactiva).
- **Gate Anti-IDOR / Anti-Tampering y Origen Soberano de Sesión:**
  - `ScopeMiddleware`: el contexto empresarial de peticiones web se resuelve exclusivamente de la sesión del servidor (`$_SESSION['contexto_empresa_id']`).
  - Cualquier identificador provisto por el cliente (`empresa_id` en query string, body o payload JSON) es tratado como dato no confiable.
  - Si el cliente envía un `empresa_id` y difiere de la empresa activa en sesión, la petición es interceptada y denegada inmediatamente con HTTP 403 Forbidden y telemetría en `eventos_seguridad`.
  - Matriz rigurosa de errores HTTP:
    - `401 Unauthorized`: ausencia de sesión autenticada.
    - `403 Forbidden`: discordancia territorial cliente vs sesión (Anti-IDOR) o falta de privilegio funcional RBAC.
    - `404 Not Found`: empresa o usuario inexistente.
    - `409 Conflict`: ausencia de empresa seleccionada en sesión o empresa inactiva.
    - `422 Unprocessable Entity`: errores de validación de formato/DTO o intento de asignar SUPERADMIN por empresa.
- **Inmutabilidad Financiera y Bajas Lógicas:**
  - Prohibición total de DELETE físico en asignaciones: las bajas operan mediante conmutación de estado a `INACTIVO`, con registro forense de `revocado_por` y `revocado_en`.
  - Reactivación transparente de asignaciones preexistentes inactivadas actualizando `asignado_por` y limpiando campos de revocación.
  - Integridad referencial reforzada con `ON DELETE RESTRICT` hacia `actores.id` impidiendo la eliminación accidental de actores con historial de asignaciones.
- **Control de Versión de Sesión e Invalidación de Caché:**
  - Las asignaciones, revocaciones y reactivaciones incrementan atómicamente la columna `version_autorizacion` del usuario objetivo, revocando de inmediato sesiones remotas desactualizadas.
  - La inactivación de una empresa NO incrementa masivamente las versiones de todos los usuarios; se evalúa en tiempo real en capa de middleware.
  - `AutorizacionServicio` invalida en memoria la caché local del usuario objetivo ante mutaciones territoriales (`invalidarCacheUsuario`).
- **Preparación de Consultas para Selector Corporativo (2D):**
  - Métodos soberanos en `AutorizacionServicio`: `obtenerEmpresasDisponiblesParaUsuario`, `puedeAccederEmpresa`, `obtenerRolesUsuarioEnEmpresa` y `obtenerPrivilegiosUsuarioEnEmpresa`.
  - Integración en `ContextoPeticion` de `$empresaId` y `$scopeTipo`.
- **Suite de Pruebas Automatizadas 2B (`tests/verificar_asignaciones_scopes_2b.php`):**
  - 67 pruebas exhaustivas en 9 bloques validando dominio `UsuarioEmpresaRol`, fixtures aislados, servicio de asignaciones, resolución multidimensional, consultas de ámbito, bajas lógicas con cero DELETE, middlewares de Scope y Autorización con Gate Anti-IDOR, auditoría forense con FKs RESTRICT y DELTA en desarrollo `casapro = 0`.

---

## [Microfase 2A: Dominio de Empresas y Vinculación Corporativa] — 2026-09-30

### Añadido
- **Esquema Relacional y Catálogo de Privilegios 2A (Migración `2026_09_30_000010_crear_tabla_empresas.sql`):**
  - Creación de la tabla soberana 27 `empresas` en motor InnoDB, charset UTF8mb4 y collation `utf8mb4_unicode_ci`.
  - Columnas esenciales: `id`, `persona_id` (FK 1:1 RESTRICT hacia `personas.id`), `codigo` (`VARCHAR(32) UNIQUE`), `nombre_corto` (`VARCHAR(64)`), `estado` (`ENUM('ACTIVO', 'INACTIVO')`), `creado_en`, `actualizado_en`.
  - Descarte preventivo de `ruta_logo` y `color_hex` por principio YAGNI y desacoplamiento con el sistema de archivos.
  - Eliminación de directivas `SET FOREIGN_KEY_CHECKS = 0/1` garantizando consistencia relacional fail-fast.
  - 4 Privilegios granulares del catálogo RBAC: `empresas.ver`, `empresas.crear`, `empresas.editar` y `empresas.cambiar_estado`.
  - Asignación dinámica de los 4 privilegios al rol `SUPERADMIN` (`WHERE codigo = 'SUPERADMIN'`).
  - Sincronización idéntica en `SQL/casa-pro.sql` (Gate SQL Camino A == Camino B: 27 tablas, 229 columnas, 111 índices, 29 FKs, 10 migraciones).
  - Cero seeds de empresas ficticias.
- **Principio de Identidad Raíz (`Persona Jurídica 1 : 0..1 Empresa`):**
  - Toda Empresa es una Persona Jurídica, pero no toda Persona Jurídica es una Empresa.
  - Cero duplicación de RUC, razón social, nombre comercial legal ni domicilio fiscal en `empresas`.
- **Capa de Dominio y Transaccionalidad Atómica (`app/Servicios/EmpresaServicio.php`):**
  - Unicidad del propietario de transacción: `EmpresaServicio` abre y controla la transacción PDO; `PersonaServicio` participa como transacción subordinada sin commit independiente.
  - Rollback total probado: si la inserción de Empresa o el registro de Auditoría fallan, la Persona Jurídica creada por orquestación es completamente revertida.
  - Cero duplicación de SQL del dominio Persona dentro de `EmpresaServicio`.
  - Semántica rigurosa de errores: HTTP 422 ante Persona Natural o Persona Jurídica inactiva; HTTP 409 Conflict ante duplicidad 1:1 o código repetido.
  - Inmutabilidad estricta de `persona_id` y `codigo`; `nombre_corto` editable.
  - Empresa inactiva permanece consultable históricamente y no se elimina.
  - Prohibición total de DELETE físico (cero métodos públicos `eliminar()` en repositorio y servicio).
- **DTOs con Allowlist y Validación:**
  - `CrearEmpresaDTO`: validación de modalidad vinculada (`persona_id`) u orquestada (`datos_persona`), código `[A-Z0-9_]{3,32}` y nombre corto.
  - `ActualizarEmpresaDTO`: allowlist exclusiva para `nombre_corto` y rechazo explícito de atributos inmutables.
  - `CambiarEstadoEmpresaDTO`: conmutación validada `ACTIVO`/`INACTIVO`.
- **Suite de Pruebas Automatizadas 2A (`tests/verificar_dominio_empresas_2a.php`):**
  - 45 pruebas exhaustivas certificando persistencia, DTOs, altas vinculadas y orquestadas, semántica 422/409, rollbacks atómicos de dos fases, inmutabilidad, consultabilidad histórica de inactivas, inexistencia de DELETE y auditoría.

---

## [Microfase 1G-3: Gestión de Menú Dinámico y Navegación Jerárquica] — 2026-09-30

### Añadido
- **Esquema Relacional y Catálogo de Privilegios 1G-3 (Migración `2026_09_30_000009_crear_tabla_menu_opciones.sql`):**
  - Creación de la tabla soberana 26 `menu_opciones` con motor InnoDB, charset UTF8mb4 y collation `utf8mb4_unicode_ci`.
  - Columnas: `id`, `padre_id` (FK auto-referencial RESTRICT), `tipo` (`AGRUPADOR`/`ENLACE`), `codigo` (`VARCHAR(64) UNIQUE`), `etiqueta` (`VARCHAR(100)`), `ruta` (`VARCHAR(191) NULL`), `icono` (`VARCHAR(100) NULL`), `orden` (`INT NOT NULL DEFAULT 1`), `privilegio_id` (FK RESTRICT contra `privilegios.id`), `estado` (`ACTIVO`/`INACTIVO`), `creado_en`, `actualizado_en`.
  - 6 Privilegios granulares en el catálogo RBAC: `menu.ver`, `menu.crear`, `menu.editar`, `menu.eliminar`, `menu.reordenar`, `menu.cambiar_estado`.
  - Asignación dinámica de los 6 privilegios al rol `SUPERADMIN` (`WHERE codigo = 'SUPERADMIN'`), con cero IDs mágicos.
  - Seed mínimo oficial de 7 nodos de navegación (MOD_INICIO, MOD_IDENTIDAD, GRP_PERSONAS, OPC_PERSONAS_LISTADO, GRP_SEGURIDAD, OPC_USUARIOS_LISTADO, OPC_MENU_LISTADO).
  - Sincronización idéntica en `SQL/casa-pro.sql` (Gate SQL Camino A == Camino B: 26 tablas, 222 columnas, 107 índices, 28 FKs, 9 migraciones).
- **Regla Inviolable: Menú ≠ Autorización:**
  - El menú es presentación y confort visual; el acceso directo por URL está protegido independientemente por `AutorizacionMiddleware::exigir('menu.ver')` con HTTP 403 innegociable.
- **Lógica de Negocio y Dominio de Menú (`app/Servicios/MenuServicio.php`):**
  - Profundidad variable estricta de hasta 3 niveles (Nivel 0: Raíz, Nivel 1: Submódulo, Nivel 2: Opción final). Rechazo estricto de nivel 4+.
  - Prevención de ciclos directos (`padre_id == id`) e indirectos (mover nodo a su propia descendencia).
  - Reordenamiento jerárquico masivo con transacción PDO atómica y rollback ante error.
  - Normalización determinista de orden $1..N$ entre hermanos en PHP/PDO (cero variables de sesión MySQL `@seq`).
  - Algoritmo de poda Fail-Closed ante privilegios inconsistentes/revocados y poda Bottom-Up de ramas vacías.
  - Regla de eliminación segura: nodos hoja permitidos; nodos con sub-elementos activos rechazados con HTTP 409 Conflict.
- **DTOs con Whitelist Estricta y Validación de Seguridad:**
  - `CrearMenuOpcionDTO`: validación de rutas internas relativas (rechazo de `http://`, `javascript:`, etc.), iconos Font Awesome Free (rechazo de Tabler/scripts) y allowlist estricta.
  - `ActualizarMenuOpcionDTO`: inmutabilidad de tipo y código canónico.
  - `ReordenarMenuDTO`: lote jerárquico para sincronización drag-and-drop.
- **Interfaz y Adaptación Alina (100% Font Awesome Free y ES6+):**
  - Vista administrativa `/menu` (`app/Vistas/modulos/menu/index.php`) con lista interactiva `.list-group.nested-sortable`.
  - Integración del plugin oficial de Alina `Sortable.min.js` (`public/assets/vendor/sortable/Sortable.min.js`).
  - Script frontend modular `public/assets/js/modulos/menu/gestion-menu.js` (Vanilla JS ES6, Fetch API, SweetAlert2, cero llamadas AJAX jQuery propias).
  - Integración dinámica en el layout `navegacion-lateral.php` con fallback estático accesible.
- **Suite de Pruebas Automatizadas 1G-3 (`tests/verificar_menu_dinamico_1g3.php`):**
  - 48 pruebas en 12 bloques certificando DDL, RBAC, profundidad, anti-ciclos, validación DTO, orden determinista, reordenamiento atómico, poda fail-closed, eliminación conflictiva, seguridad middleware, endpoints HTTP y auditoría.

---

## [Microfase 1G-2: Administración de Usuarios, Accesos y Contraseñas] — 2026-09-30

### Añadido
- **Esquema Relacional y Catálogo de Privilegios 1G-2 (Migración `2026_09_30_000008_crear_privilegios_y_cambio_password_usuarios.sql`):**
  - Incorporación de columna soberana `usuarios.debe_cambiar_password` (`TINYINT(1) NOT NULL DEFAULT 0`).
  - Sembrado de 7 privilegios granulares en el catálogo de seguridad: `usuarios.ver`, `usuarios.crear`, `usuarios.editar`, `usuarios.cambiar_estado`, `usuarios.desbloquear`, `usuarios.asignar_roles` y `usuarios.resetear_password`.
  - Asignación automática de los 7 privilegios al rol `SUPERADMIN` mediante subquery dinámica (`WHERE codigo = 'SUPERADMIN'`), con cero IDs mágicos.
  - Sincronización idéntica y verificación formal en `SQL/casa-pro.sql` (Gate SQL Camino A == Camino B: 25 tablas, 209 columnas, 100 índices, 26 FKs, 8 migraciones).
- **Servicio Soberano de Política de Contraseñas (`app/Servicios/PoliticaContrasenaServicio.php`):**
  - Autoridad soberana y unificada para validación y generación de contraseñas.
  - Reglas robustas: 8 a 128 caracteres, mayúscula, minúscula, número, carácter especial, sin espacios al inicio o final.
  - Generador criptográfico de contraseñas temporales (`generarTemporal()`) garantizando el 100% de cumplimiento de requisitos.
  - Consumo transversal en First Bootstrap CLI, creación de usuario, reseteo administrativo y cambio de contraseña obligatorio y voluntario.
- **Capa de Dominio y Gestión Integral de Usuarios (`app/Servicios/UsuarioServicio.php`):**
  - Vinculación estricta 1:1 entre Persona Natural y cuenta de usuario, con bloqueo explícito a Personas Jurídicas.
  - Generación desacoplada del Actor `USER` con código canónico inmutable `ACT_USR_<HEX16>` antes de persistir la cuenta.
  - Desacoplamiento estricto entre estado administrativo (`ACTIVO`/`INACTIVO`) y bloqueo defensivo (`bloqueado_hasta`, `intentos_fallidos`).
  - Desbloqueo administrativo independiente (`usuarios.desbloquear`): restablece intentos a 0 y `bloqueado_hasta` a NULL con motivo obligatorio, generando evento `DESBLOQUEO_ADMINISTRATIVO`.
  - Reseteo administrativo de contraseña: genera clave temporal, activa `debe_cambiar_password = 1`, incrementa `version_autorizacion++` para revocar sesiones remotas y prohíbe el auto-reseteo.
  - Cambio de contraseña obligatorio y voluntario: exige y valida criptográficamente `password_actual`, actualiza `debe_cambiar_password = 0`, incrementa `version_autorizacion++` e implementa renovación en caliente de la sesión activa en el mismo ciclo de petición sin auto-expulsar al usuario actual.
  - Sincronización de roles con validación de existencia, protección de jerarquía y anti-orfandad del último `SUPERADMIN` activo.
  - Prohibición estricta de auto-desactivación de cuentas `SUPERADMIN`.
  - Ficha de Seguridad 360 con roles asignados, auditoría de cambios y timeline de telemetría forense en `eventos_seguridad`.
- **Aislamiento Soberano por Base de Datos en `AutenticacionMiddleware`:**
  - Consulta en tiempo constante `$control['debe_cambiar_password']` directo en BD.
  - Si el valor es `1`, confina el acceso del usuario exclusivamente a `/cambiar-password-obligatorio`, `/api/mi-cuenta/cambiar-password` y `/logout`.
  - Cualquier otro intento de navegación o llamada AJAX es interceptado con redirección 302 o código 403 Forbidden.
- **DTOs con Validación Estricta y Constructor Flexible:**
  - `CrearUsuarioDTO`, `ActualizarUsuarioDTO`, `CambiarEstadoUsuarioDTO`, `DesbloquearUsuarioDTO`, `SincronizarRolesDTO`, `ResetearPasswordDTO` y `CambiarPasswordPersonalDTO`.
  - Soporte de constructor bidireccional (`new DTO($array)` y método factoría `desdeArray($array)`).
- **Capa HTTP y Rutas Protegidas:**
  - Controlador `app/Controladores/UsuarioControlador.php` con endpoints para listado DataTables, alta, edición, cambio de estado, desbloqueo, reseteo, sincronización de roles, ficha y cambio personal.
  - Matriz en `config/rutas.php` protegida mediante `AutorizacionMiddleware::exigir('usuarios.{accion}')`.
- **Interfaz Visual Adaptada de Alina (100% Font Awesome Free y Vanilla JS):**
  - Módulo independiente `/usuarios`: `app/Vistas/modulos/usuarios/index.php` con grilla interactiva DataTables server-side (la IP de último acceso se omite de la grilla principal y se reserva para la Ficha).
  - Modales adaptados de Alina: creación de usuario con selector de personas sin cuenta, edición de credenciales, sincronización de roles, reseteo de clave con visualización segura SweetAlert2, desbloqueo con motivo y confirmación SweetAlert2.
  - Ficha de Seguridad `/usuarios/{id}` (`app/Vistas/modulos/usuarios/ficha.php`) con resumen de perfil, roles, detalle de bloqueo y timeline visual de eventos de seguridad.
  - Pantalla de cambio obligatorio `/cambiar-password-obligatorio` (`app/Vistas/modulos/autenticacion/cambiar-password-obligatorio.php`) con verificador interactivo en tiempo real de los requisitos de política.
  - Enlace al módulo Usuarios en la navegación lateral (`app/Vistas/layouts/parciales/navegacion-lateral.php`).
  - Vínculo contextual hacia la Ficha de Seguridad en la ficha de personas (`public/assets/js/modulos/personas/listado-personas.js`) cuando la persona posee cuenta de usuario y el operador dispone del privilegio `usuarios.ver`.
  - Cero jQuery propio en nuevos módulos; Fetch nativo, PristineJS y SweetAlert2.
- **Suite de Pruebas Automatizadas 1G-2 (`tests/verificar_usuarios_1g2.php`):**
  - 88 pruebas exhaustivas en 12 bloques cubriendo DDL, política de claves, alta y Actor USER, edición, desbloqueo, reseteo, cambio personal con renovación de sesión, aislamiento en middleware, roles y anti-orfandad, ciclo de vida, DataTables y contratos de UI.

---

## [Microfase 1G-1: Núcleo de Autenticación, RBAC y Autorización Multidimensional] — 2026-09-30

### Añadido
- **Esquema Relacional Soberano de Seguridad y RBAC (Migración `2026_09_30_000007_crear_seguridad_rbac_usuarios.sql`):**
  - Creación de 6 tablas relacionales normalizadas: `usuarios`, `roles`, `privilegios`, `rol_privilegios`, `usuario_roles` y `eventos_seguridad`.
  - Gate SQL Camino A == Camino B 100% superado: 25 tablas, 208 columnas, 100 índices y 26 FKs idénticas en ambas vías.
  - Relación 1:1 estricta e inviolable entre `Persona` (Natural) y `usuarios` (`persona_id NOT NULL UNIQUE`), respaldada por FK `ON DELETE RESTRICT`.
  - Vinculación obligatoria e inmutable con `actores` de tipo `USER` (`actor_id NOT NULL UNIQUE`), utilizando código canónico generado previo (`ACT_USR_...`) garantizando independencia de autoincrementales.
  - Semilla del rol raíz `SUPERADMIN` (ID 1) y los 5 privilegios iniciales del catálogo `personas.*` (`personas.ver`, `personas.crear`, `personas.editar`, `personas.cambiar_estado`, `personas.consultar_documento`).
- **Motor de Autenticación Criptográfica Soberana (`app/Servicios/AutenticacionServicio.php`):**
  - Autenticación dual (correo electrónico o nombre de usuario).
  - Algoritmo de hashing seguro `PASSWORD_DEFAULT` con verificación `password_verify` y soporte automático de rehash dinámico (`password_needs_rehash`).
  - Mitigación de temporización (timing attack) mediante hash dummy precalculado válido en PHP 8.3 (`$2y$10$sh1uvtgEbxH52dcRl9C6rOjHQok8L3aw1DCMukGWUPdMf7RicS5Rm`).
  - Mitigación de fuerza bruta con ventana móvil y bloqueo temporal (`AUTH_MAX_INTENTOS=5`, `AUTH_VENTANA_INTENTOS=15`, `AUTH_TIEMPO_BLOQUEO=15`), serializado con `SELECT ... FOR UPDATE`.
  - Separación entre bloqueo temporal por seguridad y estado administrativo inactivo (`ACTIVO`/`INACTIVO`).
  - Principio estricto de anti-enumeración de usuarios: respuesta pública uniforme e idéntica (401) ante usuario inexistente, contraseña errónea o cuenta inactiva/bloqueada.
  - Telemetría interna profunda en `eventos_seguridad` registrando IP, User-Agent, correlación y motivo granular del fallo (`USUARIO_INEXISTENTE`, `CREDENCIALES_INVALIDAS`, `BLOQUEO_TEMPORAL`, etc.).
- **Autorización RBAC, Revocación de Sesiones y Scopes (`app/Servicios/AutorizacionServicio.php`):**
  - Control de sesiones en tiempo constante $O(1)$ por PK con revocación inmediata ante incremento de `version_autorizacion` o desactivación administrativa.
  - Consulta y cacheo en memoria de privilegios efectivos `modulo.accion` por usuario.
  - Bypass universal de RBAC exclusivo para `SUPERADMIN` (sin bypass de autenticación, sesión, CSRF ni auditoría).
  - Semántica territorial de ámbito inicial `GLOBAL`.
- **First Bootstrap CLI Soberano (`bin/casapro-bootstrap-admin.php`):**
  - Detección de arranque inicial cuando `COUNT(SUPERADMIN) === 0`.
  - Solicitud interactiva de datos civiles y credenciales con ocultamiento de contraseña en terminal.
  - Rechazo estricto e inflexible de `--password=...` en argumentos de línea de comandos.
  - Transaccionalidad atómica externa reutilizando `PersonaServicio` (con propagación y preservación de rollback/commit soberano).
- **Capa HTTP, Middlewares y Enrutamiento Protegido:**
  - `AutenticacionMiddleware`: Intercepta peticiones anónimas redirigiendo a `/login` en peticiones web estándar o emitiendo JSON 401 en peticiones AJAX/API.
  - `AutorizacionMiddleware`: Implementación de factoría estática `exigir(string $privilegio, string $scope = 'GLOBAL')` aplicando Deny by Default.
  - `config/rutas.php`: Matriz actualizada con `/login`, `/logout` y protección integral de `/`, `/inicio`, `/personas` y `/api/personas/*`.
- **Interfaz Visual Institucional Alina Adaptada (`sign_in.html`):**
  - Vista `app/Vistas/modulos/autenticacion/login.php` y script `public/assets/js/modulos/autenticacion/login.js`.
  - 100% Font Awesome Free 6.3.0, tipografía Fira Sans, Vanilla JS con Fetch API, Bootstrap 5 y validación declarativa PristineJS.
  - Protección obligatoria anti-CSRF con token pre-autenticación.
  - Reutilización de asset fotográfico oficial `admin-dashboard/alina/assets/images/login/01.jpg` hacia `public/assets/images/login/01.jpg` manteniendo `admin-dashboard/` intacto.
- **Suite de Pruebas Automatizadas 1G-1 (`tests/verificar_autenticacion_rbac_1g1.php`):**
  - 43 pruebas exhaustivas en 10 bloques (Identidad 1:1, Actor USER, Transaccionalidad, Hashing, Anti-enumeración, Fuerza bruta, Revocación de sesión, RBAC, Deny by Default HTTP/CSRF, Auditoría forense con Actor USER real, CLI First Bootstrap).

---

## [Microfix Visual y Consolidación Documental: Escala de Texto, Tooltips y Menú Dinámico 1G-3] — 2026-09-30

### Añadido / Restaurado
- **Restauración de Escala de Texto en Personalizador de Tema (`public/assets/js/theme_customizer.js`):**
  - Incorporación del bloque oficial Alina `theme-sizing-list` con opciones 100% en español: `Pequeño` (`small-text`), `Mediano` (`medium-text`) y `Grande` (`large-text`).
  - Activación visual única con check Font Awesome (`fa-solid fa-check`) sobre selector nativo Alina.
  - Modificación real de la tipografía mediante atributo `body[text="..."]` sobre **Fira Sans**.
  - Persistencia de selección en `localStorage` con clave `font-size`.
  - Restauración automática del valor predeterminado real de Alina (`medium-text`) al presionar `Restablecer`.
- **Corrección de Tooltips en `navbar-menu-list` (`script.js`, `navegacion-lateral.php`, `style.css`):**
  - Subsanada causa raíz: excepciones no capturadas en `script.js` prevenían el registro del listener de tooltips.
  - Inicialización robusta de Bootstrap Tooltip con `selector: '[data-bs-toggle="tooltip"]'` y `container: 'body'` asegurando flotación libre por encima del z-index del sidebar.
  - Inclusión de `aria-label` en enlaces de navegación para accesibilidad.
  - Regla CSS `pointer-events: none` en iconos `<i>` dentro de `.nav-link` para garantizar focalización directa del disparador.
- **Consolidación Documental del Menú Dinámico 1G-3 (`docs/13-ROADMAP.md` y `docs/16-DECISIONES-ARQUITECTONICAS.md`):**
  - Desglose oficial de la Fase 1G en: `1G-1` (Autenticación y RBAC), `1G-2` (Administración de usuarios) y `1G-3` (Gestión de Menú Dinámico y Navegación).
  - Especificación formal de 1G-3: jerarquía máxima estricta de 3 niveles, regla `MENÚ ≠ AUTORIZACIÓN`, consumo directo de RBAC sin duplicidad, y dependencia lineal `1G-1 → 1G-2 → 1G-3`.

---

## [Microfase 1F: Alta, Edición, Estados de Personas y Consulta Documental Asistida] — 2026-09-30

### Añadido
- **Alta y Edición Integral de Personas (`app/Vistas/modulos/personas/index.php`):**
  - Modal interactivo oficial Alina (`#modalFormularioPersona`, `modal-xl centered scrollable`).
  - Conmutador reactivo de identidad raíz (Persona Natural vs Persona Jurídica) con alternancia dinámica de campos y obligatoriedades.
  - Pestañas Alina (`nav-tabs nav-bottom-line`): Datos Principales, Documentos y Contactos, Domicilio y Representación Legal (para Jurídicas).
  - Precarga de catálogos en JSON seguro (`#datosCatalogosCasaPro`) respetando soberanía relacional de `tipos_documento`, `sexos`, `estados_civiles`, `tipos_contacto`, `tipos_direccion` y `departamentos`.
  - Cascada geográfica dependiente UBIGEO (Departamento -> Provincias -> Distritos) con endpoints auxiliares `/api/ubigeo/provincias` y `/api/ubigeo/distritos`.
- **Módulo de Consulta Documental Desacoplada (`app/Servicios/ConsultaDocumentoServicio.php`):**
  - Endpoint seguro `POST /api/personas/consultar-documento` protegido por `GuardiaActorMiddleware` y CSRF.
  - DTO de consulta `ConsultaDocumentoDTO` con allowlist estricta (`tipo_documento_id`, `numero_documento`).
  - **Regla de Oro Anti-Duplicidad Primero:** Verificación previa en `persona_documentos`; si el documento ya existe localmente, retorna `DUPLICADO_LOCAL` con datos mínimos de identidad sin consumir la API externa.
  - Adaptador desacoplado `ApisPeruAdaptador` implementando `ProveedorDocumentoInterface` con timeout controlado (4s), sanitización de credenciales y mapeo estricto del contrato oficial vigente de `apiperu.dev`.
  - Fallback manual transparente ante documentos no encontrados, timeouts o fallas del proveedor.
- **JavaScript Modular Desacoplado:**
  - `consulta-documento.js`: Cliente reusable `window.CasaProConsultaDocumento` con caché de sesión y prevención de abusos.
  - `formulario-persona.js`: Controlador `window.CasaProFormularioPersona` con validación declarativa PristineJS, máscaras CleaveJS, selector de fechas Flatpickr, prevención de doble submit con spinner Font Awesome y tratamiento riguroso de HTTP 422 (preservando datos y activando pestaña con error).
  - `listado-personas.js`: Integración de botones "Ver Ficha", "Editar Persona" y "Cambiar Estado", con diálogo modal SweetAlert2, solicitud de motivo auditable obligatorio y recarga DataTables con preservación de contexto y retroceso si se vacía la página.
- **Suite de Pruebas Automatizadas 1F (`tests/verificar_formulario_personas_1f.php`):**
  - 54 pruebas unitarias y de integración cubriendo seguridad, DTOs, anti-duplicidad, mocks de proveedor, alta, edición, estados, cascada UBIGEO, assets Alina y cumplimiento anti-Tabler.

---

## [Corrección Visual: Migración de Indicadores de Submenú y Pseudo-Elementos a Font Awesome] — 2026-09-30

### Corregido
- **Indicadores de Submenú en Navegación Lateral (`public/assets/css/style.css`):**
  - Erradicación del cuadro vacío en opciones con submenús (e.g. "Tableros de Control", "Gestión de Personas", "Estructura Catastral").
  - Sustitución de `font-family: "tabler-icons"` y glifos `\eb0b` (plus) / `\eaf2` (minus) por `Font Awesome 6 Free` con peso `900`.
  - Glifo para submenú cerrado: `\f054` (`fa-chevron-right`).
  - Glifo para submenú abierto (`[aria-expanded=true]::after`): `\f078` (`fa-chevron-down`).
  - Adaptación simétrica para diseño RTL (`.layout-rtl`): `\f053` (`fa-chevron-left`) cerrado y `\f078` abierto.
  - Adición de `padding-right: 2.2rem` en enlaces con submenús para garantizar separación clara con badges y texto.
- **Erradicación Integral de `tabler-icons` en Pseudo-Elementos CSS (`style.css` y `responsive.css`):**
  - Separadores de migas de pan (`.breadcrumb li + li::before` y `.breadcrumbs li + li::before`): migrados de `\ea61` a `\f054` (`fa-chevron-right`) con `Font Awesome 6 Free` 900.
  - Acordeones interactivos (`.app-accordion .accordion-button::after` y variantes): migrados a `\f078` (down) y `\f077` (up).
  - Flechas de Slick Slider (`.app-arrow .slick-prev:before`, `.slick-next:before`): migradas a `\f053` (left) y `\f054` (right).
  - Iconos de copia en galería (`.icon-list .icon-box:hover::after`): migrado a `\f0c5` (`fa-copy`).
  - Controles de formulario personalizados (`dash-form-check`, `light-form-check`, `form-image-box`, `filled-checkbox-list`, `event-list`): migrados a `\f00c` (`fa-check`) y `\f111` (`fa-circle`).
  - Flecha de selección Select2 (`.select2-selection__arrow b:after`): migrada a `\f078` (`fa-chevron-down`).
  - Listas de características en tarjetas de precios (`.plans-section .features li::before`): migrada a `\f00c` (`fa-check`).
  - Checkmarks del Theme Customizer (`.theme-color-list li:after`, `.theme-layout-list li .layout:before`): migrados a `\f00c` (`fa-check`).
  - Eliminación de la variable CSS residual `--tabler-icons` en `style.css` y `responsive.css`.
- **Suite de Pruebas de Normalización Visual (`tests/verificar_normalizacion_visual.php`):**
  - Incorporación del Bloque 7 para auditar exhaustivamente la ausencia total de `tabler-icons` y de los glifos `\eb0b` / `\eaf2` en hojas de estilo adaptadas, elevando la suite a 33/33 PASS.

---

## [Microfase: Oficialización del Patrón CRUD Asíncrono en Gobernanza y Arquitectura] — 2026-09-30

### Añadido
- **Gobernanza del Patrón CRUD Asíncrono Oficial (`docs/01-GOBERNANZA.md`):**
  - Incorporación de la Sección 5: "Patrón CRUD Asíncrono Oficial de CasaPRO".
  - Definición de la tríada arquitectónica: `LISTADO + DATATABLE + MODAL ALINA + PRISTINEJS + FETCH/JSON + SINCRONIZACIÓN ASÍNCRONA`.
  - Erradicación explícita del flujo sincrónico arcaico (`crear.php -> guardar -> redirect -> listado`).
  - Protocolo para Creación (limpieza, reseteo PristineJS, foco), Edición (población asíncrona, spinner) y Baja Lógica/Anulación (inmutabilidad por criterio de dominio; DELETE físico restringido a datos temporales descartables).
  - Confirmación interactiva obligatoria con SweetAlert2 temático de Alina.
  - Sincronización DataTables con `tabla.ajax.reload(null, false)` preservando página, búsqueda con debounce (350-400 ms), orden y longitud de visualización. Manejo de retroceso de página si se vacía la página actual tras baja lógica.
  - Política de Modal por defecto para CRUDs administrativos estándar vs justificación proporcional en especificación de microfase para procesos multietapa (reservando ADRs para decisiones arquitectónicas relevantes).
  - Prevención de doble envío mediante bloqueo de botón (`disabled`), spinner Alina (`spinner-border spinner-border-sm`) y neutralización de Enter durante peticiones en curso.
  - Manejo seguro de errores: ante error HTTP 422 de backend, el modal permanece abierto con los valores ingresados intactos y mapeo de errores en inputs; ante HTTP 500, mensaje genérico seguro sin trazas.
- **Estándares Frontend Actualizados (`docs/03-CONVENCIONES.md`):**
  - Prohibición categórica de `window.location.reload()`, `location.reload()` y `$.ajax()` en código propio de CasaPRO.
  - Estandarización de módulos JavaScript ES6+ con Fetch nativo, DTOs JSON estructurados y manejo de tokens CSRF vía cabecera `X-CSRF-Token` o payload.
- **Patrón Visual y Componentes Modales Alina (`docs/07-UI-UX-ALINA.md`):**
  - Incorporación de la Sección 10: "Patrón Visual y Componentes para CRUDs Asíncronos con Modales Alina y DataTables".
  - Especificación de anatomía modal Alina (`modal-dialog-centered`, `modal-dialog-scrollable`, `modal-lg`, `modal-xl`), botones compactos de acción en tablas con Font Awesome (`fa-solid fa-eye`, `fa-solid fa-pen-to-square`, `fa-solid fa-toggle-on/off`) y notificaciones con SweetAlert2.
- **Batería de Gates para CRUDs Asíncronos (`docs/11-PRUEBAS-Y-GATES.md`):**
  - Incorporación de la batería especializada G-CRUD-1 a G-CRUD-6:
    - `G-CRUD-1 (Asincronía Real)`: Cero recargas completas o redirecciones síncronas.
    - `G-CRUD-2 (Conservación de Contexto)`: `ajax.reload(null, false)` con debounce y preservación de estado.
    - `G-CRUD-3 (Validación Dual y Preservación)`: PristineJS en modal + preservación de campos ante 422.
    - `G-CRUD-4 (Prevención de Doble Envío)`: Deshabilitación de botón submit, spinner y bloqueo de tecla Enter.
    - `G-CRUD-5 (Confirmación Destructiva)`: SweetAlert2 para baja lógica, inmutabilidad y cero DELETE físico.
    - `G-CRUD-6 (Seguridad y Auditoría Atómica)`: CSRF, RBAC, scope territorial y auditoría transaccional append-only en la misma conexión.
- **Decisiones Arquitectónicas Oficializadas (`docs/16-DECISIONES-ARQUITECTONICAS.md`):**
  - `ADR-023: Estandarización de Identidad Visual: Tipografía Fira Sans, Font Awesome Exclusivo y Theme Customizer Adaptado`.
  - `ADR-024: Patrón CRUD Asíncrono Oficial: Listado DataTables + Modal Alina + PristineJS + Fetch/JSON + Sincronización sin Recarga Completa`.
- **Evolución del Catálogo de Skills (`docs/15-SKILLS.md` y `.agents/skills/`):**
  - Actualización de `crear-crud`: alineada al flujo asíncrono con modales Alina, PristineJS y recarga sin salto de página.
  - Actualización de `crear-datatable-dinamico`: fetch nativo, `tabla.ajax.reload(null, false)`, debounce y conservación de contexto.
  - Actualización de `crear-formulario`: validación PristineJS, máscaras CleaveJS, control de doble submit y preservación modal ante 422.
  - Actualización de `crear-modal`: política modal por defecto, tamaños Alina (`modal-lg`, `modal-xl`), scrollable y eventos de ciclo de vida.
  - Actualización de `crear-pantalla-alina`: ensamble de vistas basadas en `blank.html` con modales Alina, Fira Sans y Font Awesome exclusivo.
  - Actualización de `inspeccionar-alina`: inventario de modales (`modals.html`), formularios (`form_*.html`), tablas y sweetalerts (`sweet_alert.html`).
  - Actualización de `ejecutar-gates`: verificación de la batería G-CRUD-1 a G-CRUD-6 y Gate G11 con Fira Sans y Font Awesome.
- **Alineación del Roadmap General (`docs/13-ROADMAP.md`):**
  - Registro de la microfase de Normalización Visual Global (`mb-ui-normalizacion-global`) y de Gobernanza CRUD Asíncrono (`mb-gobernanza-crud-asincrono`) como requisitos normativos completados con anterioridad al inicio de la Microfase 1F.

### Estado
- Micro-baseline documental y normativo cerrado formalmente (`mb-gobernanza-crud-asincrono`).

---

## [Microfase: Normalización Visual Global de Alina] — 2026-09-29

### Añadido
- **Iconografía Oficial Font Awesome (Sistema Iconográfico Único):**
  - Traslado de assets verificados de Alina desde `admin-dashboard/alina/assets/vendor/fontawesome/` a `public/assets/vendor/fontawesome/` y fuentes a `public/assets/fonts/fontawesome/`.
  - Carga oficial de `vendor/fontawesome/css/all.css` en `app/Vistas/layouts/parciales/cabecera-head.php` y `app/Vistas/layouts/error.php`.
  - Migración exhaustiva de todas las clases `ti ti-*` a sus equivalentes canónicos de Font Awesome en menús, barras, botones de acción DataTables, fichas modales y páginas de error.
  - Eliminación de dependencias huérfanas de Tabler Icons en `public/assets/vendor/tabler-icons/` y `public/assets/fonts/tabler/`.
- **Tipografía Oficial Fira Sans:**
  - Importación oficial de Google Fonts de las familias `Fira Sans`, `Fira Sans Condensed` y `Fira Sans Extra Condensed` en `cabecera-head.php` y `error.php`.
  - Definición de `--theme-fonts: "Fira Sans", sans-serif;` en `public/assets/css/style.css` y `public/assets/css/responsive.css`.
  - Erradicación total de `Lexend Deca` en vistas, scripts y hojas de estilo.
- **Tooltips de Bootstrap 5 en el Menú Principal:**
  - Incorporación de `data-bs-toggle="tooltip"` y `data-bs-placement="right"` con atributos semánticos `title` en todos los ítems de `ul.navbar-menu-list` y el avatar de usuario en `navegacion-lateral.php`.
  - Inicialización global delegada de `bootstrap.Tooltip` en `public/assets/js/script.js`.
- **Theme Customizer Adaptado de Alina:**
  - Implementación de `public/assets/js/theme_customizer.js` traducido 100% al español.
  - Contenedor `#theme-customizer-box` inyectado en `app/Vistas/layouts/maestro.php` y script cargado en `pie-scripts.php`.
  - Opciones acotadas respetando la directiva de diseño: Colores del tema (gradientes 1-6), Diseños del tema (LTR, RTL, Box) y botón Restablecer (`resetCustomizer()`).
  - Omitidas de forma deliberada las dos últimas opciones originales de Alina (Sidebar Variant y Font Sizing) y erradicado el botón "Buy Now" y enlaces comerciales externos.
  - Activador flotante con icono Font Awesome `fa-solid fa-gear`.
- **Suite Automatizada de Verificación Visual:**
  - `tests/verificar_normalizacion_visual.php`: 27/27 pruebas superadas validando Fira Sans, Font Awesome físico, ausencia de `ti ti-*`, ausencia de `Lexend`, tooltips delegados, theme customizer en español y la inmutabilidad de `admin-dashboard/`.

### Estado
- Micro-baseline cerrado formalmente (`mb-ui-normalizacion-global`).

---

## [Fase 1E: Listado y Consulta Visual de Personas con Alina + DataTables Server-Side] — 2026-09-29

### Añadido
- **Pantalla y Vista de Directorio de Personas:**
  - `app/Vistas/modulos/personas/index.php`: Vista integrada sobre la anatomía de `blank.html`, `data_table.html` y `profile.html` de Alina Bootstrap 5.
  - Implementación de la tabla interactiva `#tablaPersonas` con contenedor scrolleable `.app-datatable-default .overflow-auto .app-scroll` y clases oficiales `.app-data-table .default-data-table`.
  - Barra de filtros de servidor por Tipo de Persona (`filtroTipoPersona`) y Estado (`filtroEstado`), con botón de limpieza rápida.
  - Diálogo modal scrollable centrado `#modalFichaPersona` (`modal-lg modal-dialog-centered modal-dialog-scrollable`) para la Ficha de Identidad de Persona.
  - Estructuración por pestañas (`nav-tabs nav-bottom-line`): Datos Generales, Documentos y Contactos, Domicilios y Representación Legal.
  - Exclusión estricta de controles muertos de 1F (sin botones deshabilitados "+ Nueva Persona" ni "Editar").
- **Módulo JavaScript Modular (Vanilla ES6+ sin jQuery Propio):**
  - `public/assets/js/modulos/personas/listado-personas.js`: Módulo encapsulado bajo `window.CasaProPersonasListado` con inicialización idempotente.
  - Adaptador asíncrono sobre DataTables 1.13.3 utilizando exclusivamente `window.fetch()` nativo (cero llamadas a `$.ajax()`, `$.get()`, `$.post()`, `$.getJSON()`).
  - Resolución dinámica de la URL base desde `data-api-personas-url` generada por `Vista::url('api/personas')`, garantizando portabilidad absoluta en `https://app.casa-pro.test/` y `https://localhost/app.casa-pro/`.
  - Mapeo de columnas de ordenamiento DataTables a la whitelist segura de `ConsultaDataTablesDTO` (`MAPA_COLUMNAS`).
  - Mecanismo de debouncing de 400 ms en el campo de búsqueda global para evitar sobrecarga en MySQL.
  - Manejo elegante de respuestas HTTP de la API (401 sesión inválida, 403 autorización, 500 genérico seguro).
  - Población de la Ficha de Identidad con asignación segura mediante `.textContent` y sanitización contextual estricta contra XSS.
  - Despliegue de estados mediante *Variants of badge* de Alina (`text-light-success`, `text-light-secondary`, nunca dotted) y tipos mediante *Variants of chip* (`bg-light-primary`, `bg-light-info`).
- **Assets Vendor Trasladados y Reutilizados:**
  - `public/assets/vendor/datatable/jquery.dataTables.min.js`, `jquery.dataTables.min.css` y `dataTables.responsive.min.js` trasladados exclusivamente sin duplicar dependencias redundantes (`jquery-3.5.1.js` omitido; se reutiliza `jquery-3.6.3.min.js`).
  - Verificación de que `admin-dashboard/` permanece 100% de solo lectura e intacto.
- **Rutas y Enrutamiento Visual Protegido:**
  - Registro de `GET /personas` en `config/rutas.php` protegido por `GuardiaActorMiddleware` bajo política estricta de *Deny by Default*.
  - Método `index()` en `App\Controladores\PersonaControlador` inyectando migas de pan y assets modales.
  - Enlace oficial actualizado en la navegación lateral de Alina (`app/Vistas/layouts/parciales/navegacion-lateral.php`).
- **Suite Integral de Pruebas Automatizadas 1E:**
  - `tests/verificar_listado_personas_1e.php`: 51/51 pruebas superadas al 100% cubriendo protección de la vista (401), renderizado Alina, assets vendor, inspección de script modular sin `$.ajax()`, DataTables server-side con ordenamiento y filtros, Ficha de Identidad sin exposición de auditoría, pruebas reales de inmunidad XSS y manejo de errores 400/404/500.

### Corregido
- `app/Repositorios/PersonaRepositorio.php`: Desambiguación de marcadores de posición (`:busqueda1` a `:busqueda7`) en la cláusula de búsqueda textual `LIKE` para compatibilidad estricta con sentencias preparadas nativas (`PDO::ATTR_EMULATE_PREPARES => false`).
- `docs/16-DECISIONES-ARQUITECTONICAS.md`: Corrección de la denominación oficial a "catálogo UBIGEO vigente de CasaPRO; fuente primaria INEI pendiente de certificación documental directa" en ADR-020.

### Estado
- Micro-baseline 1E cerrado satisfactoriamente (`mb-fase1e-listado-personas`).

---

## [Fase 1D: Repositorio, Servicio y API JSON de Personas] — 2026-09-29

### Añadido
- **Excepciones de Dominio y Errores HTTP Normalizados:**
  - `App\Excepciones\PeticionIncorrectaExcepcion`: Manejo de parámetros de consulta o ruta malformados (HTTP 400).
  - `App\Excepciones\RecursoNoEncontradoExcepcion`: Recurso no encontrado (HTTP 404).
  - `App\Excepciones\ReglaNegocioExcepcion`: Violación de invariantes de negocio o conflictos de concurrencia (HTTP 422 / 409).
  - `App\Excepciones\ValidacionExcepcion`: Fallos de validación estructural y semántica con array asociativo de errores (HTTP 422).
- **Capa de DTOs con Allowlist Inviolable y Whitelist SQL:**
  - `App\DTOs\CrearPersonaDTO`: Validación rígida de campos permitidos en raíz y colecciones anidadas (`documentos`, `contactos`, `direcciones`, `representantes`). Rechazo inmediato HTTP 422 a campos desconocidos (blindaje anti-*Mass Assignment*).
  - `App\DTOs\ActualizarPersonaDTO`: Prohibición estricta de cambio de `tipo_persona` (HTTP 422). Diferenciación semántica entre colecciones omitidas (`null`, se preservan) y colecciones vacías (`[]`, se desactivan).
  - `App\DTOs\CambiarEstadoPersonaDTO`: Validación estricta para transición de estado y motivo.
  - `App\DTOs\ConsultaDataTablesDTO`: Whitelist estricta de ordenamiento SQL (`MAPA_COLUMNAS`), validación de `order_dir` (`ASC` / `DESC`), `start` no negativo, `length` acotado a un máximo de 100 filas, y rechazo HTTP 400 a columnas manipuladas o inyecciones SQL.
- **Capa de Persistencia PDO (Repositorio Desacoplado):**
  - `App\Repositorios\PersonaRepositorio`: Persistencia exclusiva con sentencias preparadas nativas PDO y parámetros vinculados (`bindValue`). Métodos atómicos de inserción, actualización, desactivación en lote, verificación de unicidad, consulta 360 multientidad y paginación DataTables.
- **Capa de Servicio de Dominio Transaccional:**
  - `App\Servicios\PersonaServicio`: Soberanía completa de lógica de negocio, validaciones semánticas cruzadas con catálogos oficiales, regla de multiplicidad de identidad central (0..N documentos permitidos; como máximo 1 principal activo), transacciones multientidad atómicas con inyección del mismo PDO hacia `AuditoriaServicio::registrar`, traducción específica de MySQL 1062 en documentos hacia HTTP 409 Conflict, y serialización limpia de ficha 360.
- **Controlador REST y Enrutamiento Central:**
  - `App\Controladores\PersonaControlador`: Orquestación HTTP sin SQL directo, retorno de respuestas JSON estructuradas según `docs/08-API-Y-CONTRATOS.md` y captura de excepciones con cero fugas de SQL interno (HTTP 500 genérico con correlation ID en `error_log`).
  - Registro de 5 rutas REST en `config/rutas.php`:
    - `GET /api/personas`: DataTables server-side con `GuardiaActorMiddleware`.
    - `GET /api/personas/{id}`: Detalle 360 con `GuardiaActorMiddleware`.
    - `POST /api/personas`: Creación atómica con `GuardiaActorMiddleware` y `CsrfMiddleware`.
    - `PUT /api/personas/{id}`: Actualización integral con `GuardiaActorMiddleware` y `CsrfMiddleware`.
    - `PATCH /api/personas/{id}/estado`: Transición de estado con `GuardiaActorMiddleware` y `CsrfMiddleware`.
  - Método `patch()` en `App\Core\Enrutador` y propagación del objeto `ContextoPeticion`.
  - Prohibición y omisión total de endpoints destructivos `DELETE`.
- **Suite Integral de Pruebas de la API de Identidad:**
  - `tests/verificar_api_personas.php`: 63/63 pruebas superadas al 100% abarcando Deny by Default (401), CSRF (403), DTO allowlist (422), DataTables whitelist (400), multiplicidad de documentos (0..N), unicidad (409), consultas 360 (200), PUT integral con preservación de colecciones omitidas, PATCH de estado, no-DELETE físico, auditoría en BD y rollback transaccional ante fallos.
- **Decisiones Arquitectónicas (ADRs) y Documentación:**
  - `ADR-022: Arquitectura Desacoplada de Personas, DTOs con Allowlist y DataTables Server-Side con Whitelist Rígida` en `docs/16-DECISIONES-ARQUITECTONICAS.md`.
  - Actualización de `docs/02-ARQUITECTURA.md` y `docs/08-API-Y-CONTRATOS.md`.

### Modificado
- `app/Core/Enrutador.php`: Agregado soporte para verbo HTTP PATCH y paso de `$contexto` a controladores y middlewares.
- `app/Core/Peticion.php`: Getters y setters configurables para pruebas de integración automatizadas.
- `app/Core/Respuesta.php`: Getters de código de estado, cabeceras y cuerpo para validación de aserciones.

### Estado
- Micro-baseline 1D cerrado satisfactoriamente (`mb-fase1d-api-personas`).

---

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
