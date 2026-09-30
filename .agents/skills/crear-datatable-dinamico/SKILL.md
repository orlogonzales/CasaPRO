---
name: crear-datatable-dinamico
description: Conecta una tabla HTML estilizada con Alina a un endpoint JSON server-side con paginación y búsqueda rápida.
---

# Skill: crear-datatable-dinamico

## Propósito
Implementar listados interactivos de alto rendimiento en CasaPRO siguiendo la arquitectura DataTables server-side con conector Fetch nativo (ADR-010, ADR-022, ADR-024).

---

## Procedimiento de Implementación

### 1. Configurar Repositorio PDO
- Método `listarParaDataTables(int $inicio, int $longitud, ?string $busqueda, string $columnaOrden, string $direccionOrden, ...$filtros): array`.
- Ejecutar consulta `COUNT(*)` optimizada para `recordsTotal` (total sin filtros) y `recordsFiltered` (total con filtros).
- Consulta de datos con parámetros vinculados (`:inicio`, `:longitud`) y ordenamiento validado contra whitelist.

### 2. Validar con DTO en Backend
- Utilizar `ConsultaDataTablesDTO` para validar y tipar:
  - `draw` (entero positivo).
  - `start` (entero >= 0).
  - `length` (entero positivo, máximo 100).
  - `search` (cadena sanitizada o null).
  - `order_column` (validada contra allowlist estricta `MAPA_COLUMNAS`; HTTP 400 ante manipulación).
  - `order_dir` ('asc' o 'desc').

### 3. Estructura HTML en la Vista (`app/Vistas/modulos/...`)
- Envolver la tabla en el contenedor scrollable de Alina:
  ```html
  <div class="app-datatable-default overflow-auto app-scroll p-3">
      <table id="tablaRegistros" class="display app-data-table default-data-table table table-hover align-middle w-100" data-api-url="<?= Vista::url('api/{recurso}') ?>">
          <thead>
              <tr>
                  <th scope="col">Columna 1</th>
                  <th scope="col">Columna 2</th>
                  <th scope="col" class="text-center">Acciones</th>
              </tr>
          </thead>
          <tbody></tbody>
      </table>
  </div>
  ```

### 4. Conector JavaScript con Fetch Nativo (Cero `$.ajax`)
- Configurar DataTables adaptando la opción `ajax` a `window.fetch()`:
  ```javascript
  ajax: function (data, callback, settings) {
      const url = document.getElementById('tablaRegistros').dataset.apiUrl;
      const params = new URLSearchParams({
          draw: data.draw,
          start: data.start,
          length: data.length,
          search: data.search.value,
          order_column: data.columns[data.order[0].column].data,
          order_dir: data.order[0].dir
      });

      fetch(`${url}?${params.toString()}`, {
          method: 'GET',
          headers: { 'Accept': 'application/json' }
      })
      .then(res => res.json())
      .then(json => {
          callback({
              draw: json.draw,
              recordsTotal: json.recordsTotal,
              recordsFiltered: json.recordsFiltered,
              data: json.data
          });
      })
      .catch(err => console.error('Error al consultar datos:', err));
  }
  ```

### 5. Conservación de Contexto y Refresco Asíncrono
- Tras cualquier mutación (crear, editar, cambiar estado), refrescar la tabla sin recargar la página:
  ```javascript
  tabla.ajax.reload(null, false);
  ```
- **Gestión de Página Vacía:** Si la acción (baja lógica) provoca que la página actual quede sin registros pero existen páginas previas:
  ```javascript
  tabla.on('xhr', function () {
      const json = tabla.ajax.json();
      if (json && json.data.length === 0 && tabla.page() > 0) {
          tabla.page('previous').draw('page');
      }
  });
  ```

### 6. Inmunidad contra XSS y Renderers
- Sanitizar contextualmente todo valor devuelto por la API antes de incrustarlo en el DOM.
- Asignar texto mediante `.textContent` o funciones de escape HTML (`escaparHtml()`).
