---
name: crear-privilegio
description: Registra nuevos privilegios granulares en el catálogo RBAC y los asigna a los roles correspondientes.
---

# Skill: crear-privilegio

## Propósito
Incorporar nuevos permisos de acceso atómicos en la estructura de autorización funcional de CasaPRO.

## Procedimiento

1. **Formato del Código:**
   - Estructura `modulo.accion` en minúsculas (e.g. `lotes.crear`, `lotes.cambiar_precio`, `caja.reabrir`).

2. **Inserción en Base de Datos:**
   - Registrar en la tabla `privilegios` especificando nombre, código y módulo padre:
     ```sql
     INSERT INTO `privilegios` (`modulo`, `codigo`, `descripcion`) 
     VALUES ('lotes', 'lotes.cambiar_precio', 'Permite modificar el precio de lista o m2 de un lote');
     ```

3. **Asociación con Roles (`rol_privilegios`):**
   - Asignar el nuevo privilegio a los roles autorizados (e.g. `Superadministrador`, `Gerente General`).

4. **Verificación de Middlewares:**
   - Documentar el privilegio para su uso en rutas con `RbacMiddleware('lotes.cambiar_precio')`.
