# 11 — Pruebas, Control de Calidad y Gates Obligatorios

## 1. Filosofía de Calidad: El Agente QA como Opositor

En CasaPRO, una microfase no se aprueba simplemente porque "se ve bien" o "funciona en el caso ideal". El rol del Agente QA consiste activamente en intentar **refutar la validez de la entrega** antes de conceder la autorización para el cierre del micro-baseline.

---

## 2. Checklist Universal de Gates de Calidad

Toda entrega funcional debe someterse y superar formalmente el siguiente checklist:

| # | Gate de Calidad | Criterio de Aceptación | Estado Mínimo |
| :---: | :--- | :--- | :---: |
| **G1** | **FUNCIONALIDAD** | El requerimiento resuelve completamente el flujo de usuario sin excepciones no controladas. | `PASS` |
| **G2** | **VALIDACIÓN FRONTEND** | Validación inmediata de campos requeridos, tipos y máscaras sin enviar datos inválidos. | `PASS` |
| **G3** | **VALIDACIÓN BACKEND** | Validación estricta en servidor mediante DTOs; no confía en datos del cliente. Retorna HTTP 422 en fallo. | `PASS` |
| **G4** | **SEGURIDAD CSRF** | Tokens CSRF validados en toda mutación de estado. Solicitudes sin token retornan HTTP 419/403. | `PASS` |
| **G5** | **AUTORIZACIÓN RBAC** | Verificación explícita de privilegios `modulo.accion` en el backend. Denegación estricta con HTTP 403. | `PASS` |
| **G6** | **ÁMBITO TERRITORIAL (SCOPE)** | Imposibilidad de consultar o alterar registros de empresas o proyectos ajenos al usuario. | `PASS` |
| **G7** | **SENTENCIAS PREPARADAS** | 100% de consultas SQL parametrizadas con PDO. Cero concatenación de variables en queries. | `PASS` |
| **G8** | **AUDITORÍA TRANSVERSAL** | Registro automático en la tabla `auditorias` con datos previos, datos nuevos, IP y usuario. | `PASS` |
| **G9** | **MANEJO DE ERRORES** | Excepciones capturadas limpiamente; no se exponen stack traces en entornos de producción. | `PASS` |
| **G10** | **RESPONSIVIDAD** | Verificación en resoluciones móvil (375px), tablet (768px) y escritorio (1200px+). | `PASS` |
| **G11** | **FIDELIDAD ALINA** | Uso de clases nativas de Alina, tipografía Fira Sans, iconografía Font Awesome exclusiva y cero CSS/JS inventado. | `PASS` |
| **G12** | **NO REGRESIÓN** | La nueva funcionalidad no rompe pruebas preexistentes ni afecta módulos previos. | `PASS` |

---

## 3. Gates Adicionales para Operaciones Financieras y Transaccionales

Para microfases que involucren cajas, cuotas, contratos, comisiones o pagos, es obligatorio superar la batería extendida de pruebas:

1. **Prueba de Transacción Atómica:** Si una operación de 3 pasos falla en el paso 3, la base de datos debe revertir completamente (`rollBack`) los pasos 1 y 2 sin dejar huérfanos.
2. **Prueba de No Duplicidad:** Intentos dobles simultáneos de registro de pago no deben duplicar el abono a la cuota.
3. **Prueba de Inmutabilidad Histórica:** Comprobación de que no existen sentencias `DELETE` ni actualización destructiva en tablas financieras.
4. **Prueba de Balance y Saldo:** Comprobación matemática exacta:
   $$\text{Saldo Pendiente} = \text{Monto Total} - \sum \text{Pagos Válidos}$$
5. **Prueba de Arqueo de Cierre:** El saldo final de caja debe coincidir exactamente con el balance de transacciones registradas durante la jornada.

---

## 4. Protocolo Obligatorio de Gates de Calidad para CRUDs Asíncronos

Toda microfase que implemente un CRUD (iniciando por la Microfase 1F) debe superar adicionalmente la batería de 6 gates asíncronos:

| # | Gate CRUD Asíncrono | Criterio de Aceptación Obligatorio | Estado |
| :---: | :--- | :--- | :---: |
| **G-CRUD-1** | **ASINCRONÍA REAL** | Creación, edición y cambio de estado se ejecutan mediante `fetch()` nativo, modales Alina y sincronización DataTables (`tabla.ajax.reload(null, false)`). Queda prohibido el uso de `location.reload()` o `window.location.reload()`. | `PASS` |
| **G-CRUD-2** | **CONSERVACIÓN DE CONTEXTO** | Tras una mutación exitosa, la DataTable preserva la página actual, la longitud, el término de búsqueda y el ordenamiento. Si la baja lógica deja la página actual con 0 registros, se reposiciona automáticamente a la página anterior válida. | `PASS` |
| **G-CRUD-3** | **VALIDACIÓN DUAL Y PRESERVACIÓN** | Validación declarativa frontend con PristineJS + validación backend estricta mediante DTO Allowlist (rechazo 422 ante campos desconocidos). Ante error 422, **el modal permanece abierto** con los datos ingresados intactos y mensajes de error asociados a cada campo. | `PASS` |
| **G-CRUD-4** | **PREVENCIÓN DE DOBLE ENVÍO** | El botón de submit se deshabilita inmediatamente al enviar (`disabled = true`) y adopta un spinner de carga (`.spinner-border`). En caso de error de red o validación, el botón se rehabilita automáticamente. | `PASS` |
| **G-CRUD-5** | **CONFIRMACIÓN DESTRUCTIVA** | Toda acción destructiva, desactivación o anulación requiere confirmación visual obligatoria previa mediante **SweetAlert2** de Alina. Se prohíbe la ejecución de sentencias `DELETE` físico en registros históricos del negocio. | `PASS` |
| **G-CRUD-6** | **SEGURIDAD Y AUDITORÍA ATÓMICA** | Token CSRF validado en cabecera `X-CSRF-Token`, actor autenticado en sesión, PDO 100% parametrizado y registro en `auditorias` en la misma transacción de base de datos. | `PASS` |
