---
name: gestionar-archivos
description: Gestiona la carga segura, almacenamiento privado fuera del webroot y descarga controlada de documentos.
---

# Skill: gestionar-archivos

## Propósito
Implementar la gestión segura de archivos físicos adjuntos a expedientes de clientes, contratos o proyectos en CasaPRO.

## Procedimiento

1. **Recepción en Backend:**
   - Validar que el archivo subido no contenga errores (`UPLOAD_ERR_OK`).
   - Validar extensión y firma binaria real con `finfo_file(FILEINFO_MIME_TYPE)`.
   - Limitar tamaño máximo (e.g., 10 MB para documentos, 25 MB para planos DWG/PDF).

2. **Almacenamiento Seguro:**
   - Destino privado: `storage/uploads/{empresa_id}/{entidad}/{año}/{mes}/`.
   - Generar nombre de archivo único aleatorio (UUIDv7).
   - Mover el archivo mediante `move_uploaded_file()`.

3. **Registro en Base de Datos:**
   - Insertar metadatos en la tabla `documentos`: nombre original, nombre almacenado, ruta relativa, mime-type, tamaño en bytes y hash SHA-256.

4. **Descarga Segura:**
   - Servir el archivo exclusivamente a través de un endpoint autenticado que verifique la propiedad y el scope del usuario.
