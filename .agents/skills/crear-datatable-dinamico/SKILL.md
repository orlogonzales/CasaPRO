---
name: crear-datatable-dinamico
description: Conecta una tabla HTML estilizada con Alina a un endpoint JSON server-side con paginación y búsqueda rápida.
---

# Skill: crear-datatable-dinamico

## Propósito
Implementar listados de alto rendimiento en CasaPRO siguiendo el protocolo server-side de DataTables (ADR-010).

## Procedimiento

1. **Configurar Repositorio:**
   - Implementar método `listarParaDataTables(int $inicio, int $longitud, ?string $busqueda, string $columnaOrden, string $direccionOrden): array`.
   - Consulta `COUNT(*)` optimizada para `recordsTotal` y `recordsFiltered`.
   - Consulta principal con `LIMIT :inicio, :longitud`.

2. **Crear Endpoint en Controlador:**
   - Capturar parámetros de la petición (`draw`, `start`, `length`, `search`, `order`).
   - Retornar JSON con la estructura:
     ```json
     {
       "draw": 1,
       "recordsTotal": 100,
       "recordsFiltered": 10,
       "data": [...]
     }
     ```

3. **Configurar HTML en Vista:**
   - Tabla con encabezados definidos y clase `table table-striped table-hover align-middle`.

4. **Inicializar en JavaScript:**
   - Usar DataTables con `serverSide: true`, `processing: true`, `ajax` apuntando al endpoint, configurando renderers seguros para evitar XSS.
