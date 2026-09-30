---
name: ejecutar-gates
description: Ejecuta la batería de 12 gates de calidad universales y pruebas financieras antes de aprobar una entrega.
---

# Skill: ejecutar-gates

## Propósito
Someter el trabajo completado en una microfase a la verificación exhaustiva de los criterios de aceptación técnicos y funcionales definidos en `docs/11-PRUEBAS-Y-GATES.md`.

## Procedimiento de Validación

1. **Revisión de Gates Universales (G1 - G12):**
   - **G1 (Funcionalidad):** ¿El flujo resuelve el caso de uso sin errores no capturados?
   - **G2 (Validación Frontend):** ¿El formulario valida datos antes del submit?
   - **G3 (Validación Backend):** ¿El servidor valida exhaustivamente y rechaza entradas anómalas con HTTP 422?
   - **G4 (CSRF):** ¿Toda petición POST/PUT/DELETE requiere token válido?
   - **G5 (RBAC):** ¿Se valida el permiso `modulo.accion` en el backend?
   - **G6 (Scope):** ¿Se restringe la consulta al ámbito territorial del usuario?
   - **G7 (Prepared Statements):** ¿Todas las consultas SQL son parametrizadas con PDO?
   - **G8 (Auditoría):** ¿Se registran inserciones y actualizaciones en `auditorias`?
   - **G9 (Manejo de Errores):** ¿No se exponen excepciones ni trazas al usuario final?
   - **G10 (Responsividad):** ¿La interfaz se adapta a móvil, tablet y escritorio?
   - **G11 (Alina & Diseño):** ¿Se respeta la fidelidad visual, tipografía Fira Sans, iconografía exclusiva Font Awesome (`fa-solid`, `fa-regular`, etc., sin `ti ti-*`) y componentes de Alina?
   - **G12 (No Regresión):** ¿Las pruebas preexistentes continúan pasando?

2. **Verificación de Gates para CRUDs Asíncronos (G-CRUD-1 a G-CRUD-6):**
   - **G-CRUD-1 (Asincronía Real):** Creación, edición y baja lógica operan 100% vía fetch/JSON asíncrono y modales Alina. Prohibido `window.location.reload()`, redirecciones síncronas o `$.ajax()`.
   - **G-CRUD-2 (Conservación de Contexto):** La sincronización post-operación utiliza `tabla.ajax.reload(null, false)`. Se preservan número de página, ordenamiento, longitud y término de búsqueda (con debounce de 350-400 ms). Manejo de retroceso de página si se elimina el único elemento de la página activa.
   - **G-CRUD-3 (Validación Dual y Preservación):** Validación frontend con PristineJS en el modal; ante error HTTP 422 de backend, el modal permanece abierto, los campos ingresados se preservan intactos y los errores específicos se asocian a cada input.
   - **G-CRUD-4 (Prevención de Doble Envío):** El botón de envío se desactiva y muestra spinner de Alina (`spinner-border spinner-border-sm`) durante la petición de red, deshabilitando envíos duplicados por doble clic o pulsación de Enter.
   - **G-CRUD-5 (Confirmación Destructiva):** Acciones de baja lógica, desactivación o anulación requieren confirmación previa mediante SweetAlert2 integrado con estilo Alina. Cero sentencias `DELETE` físico en tablas financieras y operativas protegidas.
   - **G-CRUD-6 (Seguridad y Auditoría Atómica):** Inclusión obligatoria del token CSRF en cabeceras (`X-CSRF-Token`) o payload, autorización RBAC/Scope en backend y registro transaccional en `auditorias` en la misma conexión PDO.

3. **Pruebas Financieras Adicionales (si aplica):**
   - Transaccionalidad, no duplicidad, inmutabilidad y cuadre de balances.

4. **Dictamen Final:**
   - Emitir informe con calificación `PASS` para cada ítem. Si al menos uno resulta `FAIL`, la entrega es rechazada para corrección inmediata.
