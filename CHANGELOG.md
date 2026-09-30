# Bitácora de Cambios (CHANGELOG) — CasaPRO

Todas las modificaciones notables de este proyecto se registrarán cronológicamente en este archivo.
El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/) y este proyecto se adhiere a la gestión de **Micro-Baselines**.

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
