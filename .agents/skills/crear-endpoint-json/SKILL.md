---
name: crear-endpoint-json
description: Implementa endpoints JSON estandarizados con validación DTO, middlewares de seguridad y códigos HTTP correctos.
---

# Skill: crear-endpoint-json

## Propósito
Construir servicios web internos para comunicación AJAX/Fetch conforme a las especificaciones de `docs/08-API-Y-CONTRATOS.md`.

## Procedimiento

1. **Definir Ruta y Middlewares:**
   - Registrar la ruta en el router asociando `AuthMiddleware`, `CsrfMiddleware`, `RbacMiddleware('privilegio.requerido')` y `ScopeMiddleware`.

2. **Controlador:**
   - Decodificar el payload JSON de la petición: `$datos = $peticion->obtenerJson();`.
   - Validar con el DTO correspondiente. Si falla, emitir HTTP 422:
     ```php
     return $respuesta->json([
         'estado' => 'error',
         'codigo' => 422,
         'mensaje' => 'Datos de entrada inválidos.',
         'errores' => $erroresValidacion
     ], 422);
     ```

3. **Ejecutar Servicio y Retornar Éxito:**
   - Invocar el método del servicio de dominio.
   - Retornar respuesta uniforme con HTTP 200 o 201:
     ```php
     return $respuesta->json([
         'estado' => 'exito',
         'codigo' => 200,
         'mensaje' => 'Operación completada con éxito.',
         'datos' => $resultado
     ]);
     ```
