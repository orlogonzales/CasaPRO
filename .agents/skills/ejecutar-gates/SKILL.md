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
   - **G11 (Alina):** ¿Se respeta la fidelidad visual, tipografía y componentes de Alina?
   - **G12 (No Regresión):** ¿Las pruebas preexistentes continúan pasando?

2. **Pruebas Financieras Adicionales (si aplica):**
   - Transaccionalidad, no duplicidad, inmutabilidad y cuadre de balances.

3. **Dictamen Final:**
   - Emitir informe con calificación `PASS` para cada ítem. Si al menos uno resulta `FAIL`, la entrega es rechazada para corrección inmediata.
