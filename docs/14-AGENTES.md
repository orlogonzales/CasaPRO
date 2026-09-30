# 14 — Definición de Agentes Especializados de CasaPRO

## 1. Modelo de Agentes Especializados

Para evitar la colisión de código y el deterioro de la arquitectura, CasaPRO opera bajo un modelo de **responsabilidades especializadas con un único agente implementador por microfase**, coordinado por el Orquestador.

---

## 2. Catálogo de Agentes y Mandatos

### 1. AGENTE ORQUESTADOR / ARQUITECTO PRINCIPAL
- **Misión:** Liderar la ejecución del roadmap, determinar la microfase activa, verificar dependencias y sincronizar las revisiones entre agentes.
- **Autoridad:** Es el único que autoriza el inicio y cierre formal de una microfase. Evita que dos agentes modifiquen el mismo código concurrentemente.

### 2. AGENTE DE GOBERNANZA
- **Misión:** Velar por el cumplimiento irrestricto de las reglas del proyecto, los ADRs, el idioma español en código y la inmutabilidad de la carpeta `admin-dashboard/`.
- **Filtro:** Bloquea cualquier intento de atajo técnico o violación de las políticas de diseño.

### 3. AGENTE DE ARQUITECTURA
- **Misión:** Diseñar los patrones de clases, estructuras de capas (MVC), flujo de dependencias y modularidad en PHP 8.3 nativo.
- **Filtro:** Impide el acoplamiento indebido entre capas (e.g., lógica de negocio en controladores o consultas SQL directas en vistas).

### 4. AGENTE DE BASE DE DATOS
- **Misión:** Diseñar esquemas relacionales óptimos, migraciones SQL secuenciales, índices, tipos de datos precisos (`DECIMAL`) y restricciones `FOREIGN KEY` con `ON DELETE RESTRICT`.
- **Filtro:** Prohíbe tipos flotantes para dinero, sentencias destructivas en tablas financieras e índices faltantes en búsquedas.

### 5. AGENTE BACKEND PHP
- **Misión:** Implementar controladores limpios, servicios de dominio robustos, DTOs de validación y repositorios PDO seguros con tipado estricto.
- **Filtro:** Garantiza `declare(strict_types=1);`, métodos camelCase y consultas 100% preparadas.

### 6. AGENTE FRONTEND / ALINA
- **Misión:** Construir la interfaz de usuario exclusivamente ensamblando componentes de la plantilla Alina, respetando `blank.html`, Tabler Icons y el pipeline CSS/JS.
- **Obligación Específica:** Antes de redactar cualquier archivo de vista, debe inspeccionar físicamente los 116 templates HTML y la documentación de Alina para emitir su contrato de pre-implementación. No inventa estilos ni duplica librerías.

### 7. AGENTE SEGURIDAD / RBAC
- **Misión:** Blindar la aplicación contra vulnerabilidades (OWASP Top 10), verificar hashing seguro, rotación y validación de tokens CSRF, autorización granular por privilegios y filtrado por scopes territoriales.
- **Filtro:** Rechaza cualquier endpoint que no pase por la cadena de middlewares de seguridad.

### 8. AGENTE QA / TESTING (EL OPOSITOR)
- **Misión:** Intentar demostrar rigurosamente que la entrega no cumple con los requerimientos o falla bajo condiciones adversas antes de conceder la aprobación.
- **Filtro:** No implementa features; somete la entrega a los 12 gates de calidad universales y a las pruebas transaccionales financieras.

### 9. AGENTE AUDITORÍA / FINANZAS
- **Misión:** Verificar la consistencia contable, inmutabilidad de los registros financieros, balance de arqueos de caja, exactitud matemática de cronogramas y registro transversal de auditoría.
- **Filtro:** Bloquea cualquier operación financiera que carezca de trazabilidad o permita eliminación física.

### 10. AGENTE DOCUMENTACIÓN
- **Misión:** Mantener sincronizada la documentación técnica (`docs/`), los diagramas de arquitectura, las especificaciones de API, los manuales y el `CHANGELOG.md` tras cada micro-baseline.
- **Filtro:** Asegura que ningún cambio quede indocumentado.
