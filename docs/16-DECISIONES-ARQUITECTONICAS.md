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

---

### ADR-001: Adopción de PHP 8.3
- **Estado:** Aceptado
- **Contexto:** Se requiere un entorno moderno, de alto rendimiento y con seguridad estricta para la gestión empresarial e inmobiliaria.
- **Decisión:** Utilizar PHP 8.3 como versión base mínima con `declare(strict_types=1);`, tipado estricto en métodos, constructor promotion y nuevas funciones del lenguaje.
- **Consecuencias:** Mayor robustez en tiempo de compilación/ejecución, código auto-documentado y prevención temprana de errores de tipo.

### ADR-002: Patrón MVC Propio Desacoplado
- **Estado:** Aceptado
- **Contexto:** Evitar la dependencia de frameworks monolíticos (Laravel, Symfony) que imponen deuda técnica y sobrecarga de procesamiento innecesaria.
- **Decisión:** Desarrollar un MVC ligero y específico para CasaPRO compuesto por Front Controller (`public/index.php`), Enrutador, Middlewares, Controladores, Servicios, Repositorios y Vistas.
- **Consecuencias:** Control absoluto sobre el ciclo de vida de la petición, rendimiento óptimo en servidores compartidos o VPS y claridad estructural.

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
- **Decisión:** Alina Bootstrap 5 (ubicada en `admin-dashboard\alina\`) es la única referencia visual del proyecto. `blank.html` es la plantilla base obligatoria para toda pantalla.
- **Consecuencias:** Experiencia visual unificada, cero dispersión estética y reutilización eficiente de componentes probados.

### ADR-007: JavaScript Modular Moderno sin Dependencia de jQuery en Código Propio
- **Estado:** Aceptado
- **Contexto:** Aunque la plantilla Alina incluye jQuery para plugins de legado, el desarrollo de nueva lógica en CasaPRO debe ser moderno, modular y mantenible.
- **Decisión:** Escribir el código JS propio en módulos nativos ES6+ con Fetch API, sin usar sintaxis `$` o acoplamiento a jQuery en la lógica de negocio.
- **Consecuencias:** Código frontend ligero, estándar, fácil de testear y desacoplado de dependencias obsoletas.

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

### ADR-010: DataTables Dinámicos con Paginación Server-Side
- **Estado:** Aceptado
- **Contexto:** Tablas con miles de registros (personas, lotes, cuotas, auditorías) no pueden cargarse completas en el navegador sin degradar la memoria y el tiempo de respuesta.
- **Decisión:** Emplear DataTables conectado a endpoints JSON que implementan el protocolo server-side (`draw`, `start`, `length`, `search`, `order`).
- **Consecuencias:** Respuestas sub-segundo independientemente del volumen total de registros en la base de datos.

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
