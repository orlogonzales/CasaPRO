# 04 — Seguridad y Control de Acceso (RBAC & Scopes)

## 1. Principios de Seguridad y Directiva Transversal Vinculante

La seguridad en CasaPRO no es un añadido superficial, sino un requisito transversal no negociable (*Security by Design*). Toda funcionalidad debe ser verificada contra vectores de ataque comunes antes de ser promovida.

### Directiva Transversal de Seguridad de CasaPRO (Oficial y Vinculante):
1. **Deny by Default (Denegación por Defecto):** Ninguna ruta, recurso o acción se considera permitida a menos que cuente con una regla explícita de autorización aprobada en el backend.
2. **Autorización Backend Soberana:** La visibilidad o invisibilidad de opciones en la interfaz de usuario (menús Alina, botones, enlaces) es meramente informativa. Toda petición es validada de forma autónoma en el servidor sin asumir permisos por el hecho de originarse en una vista legítima.
3. **Defensa Absoluta ante IDOR y BOLA (Multi-empresa y Multi-proyecto):** Conocer o adivinar un ID no confiere acceso. Todo acceso a recursos dependientes debe validar obligatoriamente la pertenencia territorial respecto a la empresa y proyecto activos del actor autenticado.
4. **Sentencias Preparadas Nativas PDO con Lista Blanca Estricta:** Ninguna sentencia SQL admitirá interpolación de datos externos. Cuando se requieran identificadores dinámicos (columnas para ordenamiento `ORDER BY`, nombres de tabla o direcciones `ASC/DESC`), estos deben validarse obligatoriamente contra una lista blanca fija (*whitelist*) codificada en el repositorio.
5. **Prevención de Asignación Masiva (Mass Assignment):** Queda prohibido volcar directamente arreglos crudos de la petición HTTP (`$_POST` o payloads JSON) hacia capas de persistencia. Todo mapeo debe ser explícito atributo por atributo mediante DTOs o modelos de dominio fuertemente tipados.
6. **Protección Anti-CSRF Estricta:** Las solicitudes de mutación (`POST`, `PUT`, `PATCH`, `DELETE`) exigen un token de 32 bytes generado criptográficamente (`random_bytes(32)`) y comparado en tiempo constante (`hash_equals`). Queda **estrictamente prohibido omitir la validación CSRF** ante la presencia genérica de cabeceras `Authorization: Bearer`.
7. **Escape Contextual contra XSS:** Los datos dinámicos impresos en vistas se escapan con `htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Los atributos HTML, código JavaScript embebido y URLs deben utilizar sus respectivos mecanismos de escape contextual.
8. **Gestión Segura de Sesiones:** Parámetros estrictos aplicados vía `GestorSesion`: `session.cookie_httponly = 1`, `session.cookie_secure = true` en HTTPS, `session.cookie_samesite = 'Lax'`, `session.use_strict_mode = 1`, expiración automática por inactividad y regeneración forzada de ID (`session_regenerate_id(true)`) ante inicio de sesión o cambios de privilegios.
9. **Trazabilidad y Correlación Transversal:** Toda petición HTTP y CLI se asocia a un identificador único de correlación (`id_correlacion`, prefijo `REQ-`), propagado en la cabecera `X-Correlation-ID` y grabado en la bitácora de auditoría forense.
10. **Pruebas Negativas Obligatorias:** Cada microfase debe incluir casos de prueba automatizados que intenten violar las restricciones (tokens manipulados, omisión de credenciales, parámetros inexistentes, desbordamientos de ámbito) para comprobar el rechazo seguro y determinista.

---

## 2. Modelo de Autorización: RBAC + Scopes Territoriales

El acceso a los recursos del sistema requiere la validación simultánea de dos dimensiones:

```mermaid
graph LR
    Usuario[Usuario Autenticado] --> Rol[1. Rol y Privilegio Funcional: RBAC]
    Usuario --> Scope[2. Ámbito Territorial: Scope]
    
    Rol -->|¿Puede realizar la acción? E.g. ventas.crear| Check1{RBAC OK}
    Scope -->|¿Tiene acceso a esta entidad? Empresa / Proyecto| Check2{Scope OK}
    
    Check1 --> Operacion[Operación Permitida]
    Check2 --> Operacion
```

### 2.1. Dimensión Funcional (RBAC)
- **Roles:** Agrupaciones lógicas de privilegios (`Superadministrador`, `Gerente General`, `Jefe de Ventas`, `Asesor Comercial`, `Cajero`, `Jefe de Operaciones`, `Auditor`).
- **Privilegios Atómicos:** Claves específicas en formato `modulo.accion` (e.g., `personas.ver`, `personas.crear`, `personas.editar`, `ventas.aprobar`, `caja.cerrar`).
- **Independencia Menú vs Autorización:** La presencia o ausencia de un ítem en el menú es solo una conveniencia visual de navegación; la autorización real se valida en el backend en cada endpoint y controlador.

### 2.2. Dimensión Territorial (Scopes)
- **Alcance Global (`GLOBAL`):** Permite consultar y gestionar registros de todas las empresas y proyectos. Reservado para el Superadministrador corporativo y Auditores.
- **Alcance por Empresa (`EMPRESA`):** Restringe el acceso a los datos vinculados a la(s) empresa(s) asignada(s) al usuario (`usuario_empresas`).
- **Alcance por Proyecto (`PROYECTO`):** Restringe el acceso únicamente a los proyectos habilitados para el usuario (`usuario_proyectos`).
- **Alcance por Sector (`SECTOR`):** Restringe la visibilidad a sectores o etapas específicas de un proyecto.

---

## 3. Autenticación y Gestión de Sesiones

1. **Hash de Contraseñas:** Algoritmo nativo `PASSWORD_ARGON2ID` o `PASSWORD_BCRYPT` (factor de costo 12). Prohibido el uso de MD5, SHA1 o texto plano.
2. **Protección de Sesión (`GestorSesion`):**
   - Regeneración de ID de sesión (`session_regenerate_id(true)`) al autenticarse y en cambios de privilegio para prevenir Session Fixation.
   - Configuración estricta de cookies de sesión:
     - `session.cookie_httponly = 1` (inmune a lectura JS).
     - `session.cookie_secure = 1` (obligatorio bajo HTTPS).
     - `session.cookie_samesite = 'Lax'` (mitigación CSRF).
     - `session.use_strict_mode = 1` (rechazo de IDs de sesión no inicializados por el servidor).
3. **Control de Intentos Fallidos (Rate Limiting):** Bloqueo temporal de IP o cuenta tras 5 intentos fallidos consecutivos de inicio de sesión durante una ventana de 15 minutos.
4. **Cierre por Inactividad:** Expiración de sesión configurable tras 30 minutos de inactividad (`SESSION_LIFETIME=1800`).

---

## 4. Protección contra Ataques Comunes

### 4.1. Inyección SQL (SQLi)
- **Regla Cero Tolerancia:** Ninguna consulta SQL se construye mediante concatenación o interpolación de cadenas de texto con datos de usuario.
- **Uso Exclusivo de Prepared Statements:**
  ```php
  $stmt = $pdo->prepare('SELECT id, nombres FROM personas WHERE numero_documento = :doc AND estado = :estado');
  $stmt->execute(['doc' => $numeroDoc, 'estado' => 'ACTIVO']);
  ```
- **Lista Blanca para Dinamismos:** Nombres de campos para ordenamiento y dirección deben coincidir estrictamente con mapas predefinidos en código.

### 4.2. Cross-Site Request Forgery (CSRF)
- Todo formulario HTML incluye un token CSRF criptográficamente seguro (`Vista::csrfCampo()`) almacenado en sesión.
- Toda solicitud de modificación de estado (`POST`, `PUT`, `DELETE`, `PATCH`) es interceptada por el `CsrfMiddleware`.
- Las llamadas AJAX/Fetch envían el token en la cabecera HTTP `X-CSRF-Token` o a través del meta tag `<meta name="csrf-token">`.
- **Sin Bypass por Bearer:** La presencia de una cabecera `Authorization: Bearer` genérica jamás exime de la validación CSRF para peticiones web.

### 4.3. Cross-Site Scripting (XSS)
- Todo dato variable impreso en vistas HTML se escapa usando `htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` o mediante el helper estandarizado `Vista::e($valor)`.
- Cabeceras HTTP de seguridad obligatorias emitidas en cada respuesta:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `X-XSS-Protection: 1; mode=block`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `X-Correlation-ID: REQ-...`

### 4.4. Carga Segura de Archivos (File Upload)
- Los archivos se almacenan en `storage/uploads/` (fuera del directorio accesible por web `public/`).
- La validación del tipo de archivo se realiza inspeccionando el contenido binario real (`finfo_file(FILEINFO_MIME_TYPE)`), nunca confiando en la extensión enviada por el navegador.
- Los nombres de archivo se reemplazan por identificadores generados (UUIDv7) para prevenir path traversal o colisiones.
- La descarga de archivos pasa por un controlador con validación de autenticación y permisos sobre el expediente.

---

## 5. Salvaguardas en Operaciones Financieras Críticas

1. **Bloqueo Concurrente y Transacciones:** Toda operación financiera multietapa (e.g., registro de venta y bloqueo de lote, cobro de cuota y actualización de saldo de caja) se ejecuta dentro de una transacción PDO con control de aislamiento.
2. **Re-autenticación para Operaciones de Alto Impacto:** Para anular contratos o realizar estornos de caja, se requiere la confirmación de la contraseña del usuario o token de doble autorización de supervisor.
3. **Inmutabilidad y No Eliminación:** Prohibición estricta de sentencias `DELETE` sobre tablas financieras (`pagos`, `cuotas`, `movimientos_caja`, `asientos_contables`). Toda corrección se efectúa mediante registros de contrapartida o cambio de estado con auditoría transversal obligatoria.
