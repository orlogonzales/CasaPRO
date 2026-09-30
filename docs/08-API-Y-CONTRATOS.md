# 08 — API, Contratos de Comunicación y Respuestas JSON

## 1. Estándar de Respuesta JSON Unificada

Todos los endpoints JSON internos y APIs de CasaPRO responden con una estructura predecible, tipada y uniforme.

### 1.1. Respuesta Exitosa
```json
{
  "estado": "exito",
  "codigo": 200,
  "mensaje": "Persona registrada exitosamente.",
  "datos": {
    "id": 142,
    "tipo_documento": "DNI",
    "numero_documento": "45892147",
    "nombres": "Carlos Alberto",
    "apellido_paterno": "Mendoza"
  },
  "meta": {
    "timestamp": "2026-09-29T19:00:00Z"
  }
}
```

### 1.2. Respuesta de Error de Validación (HTTP 422)
```json
{
  "estado": "error",
  "codigo": 422,
  "mensaje": "Los datos proporcionados no superaron las validaciones requeridas.",
  "errores": {
    "numero_documento": [
      "El número de documento ya se encuentra registrado en el sistema."
    ],
    "email": [
      "El formato de correo electrónico es inválido."
    ]
  },
  "datos": null
}
```

### 1.3. Respuesta de Error de Autorización (HTTP 403)
```json
{
  "estado": "error",
  "codigo": 403,
  "mensaje": "No dispone de los privilegios requeridos o el proyecto se encuentra fuera de su ámbito asignado.",
  "errores": null,
  "datos": null
}
```

---

## 2. Protocolo de DataTables Server-Side

Para tablas con paginación en base de datos, el backend implementa el protocolo nativo de DataTables:

### Parámetros de Entrada (Query String / POST):
- `draw`: Entero identificador de secuencia para evitar respuestas cruzadas desordenadas.
- `start`: Registro inicial (desplazamiento / offset).
- `length`: Cantidad de registros por página (limit).
- `search[value]`: Término de búsqueda global.
- `order[0][column]`: Índice de la columna a ordenar.
- `order[0][dir]`: Dirección de orden (`asc` | `desc`).

### Respuesta del Backend:
```json
{
  "draw": 1,
  "recordsTotal": 1540,
  "recordsFiltered": 23,
  "data": [
    {
      "id": 101,
      "documento": "DNI 45892147",
      "nombre_completo": "Mendoza Carlos Alberto",
      "contacto": "987654321 / cmendoza@gmail.com",
      "tipo_persona": "NATURAL",
      "estado_badge": "<span class=\"badge bg-success\">ACTIVO</span>",
      "acciones": "<button class=\"btn btn-sm btn-outline-primary\" data-id=\"101\"><i class=\"ti ti-eye\"></i></button>"
    }
  ]
}
```

---

## 3. Cliente Fetch Estandarizado (`cliente-http.js`)

El frontend de CasaPRO cuenta con una función utilitaria nativa (`clienteHttp`) que abstrae las llamadas asíncronas:

```javascript
/**
 * Cliente HTTP estandarizado sobre Fetch API.
 */
export async function peticionHttp(url, opciones = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    const cabecerasPorDefecto = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
    };

    if (!(opciones.body instanceof FormData)) {
        cabecerasPorDefecto['Content-Type'] = 'application/json';
        if (opciones.body && typeof opciones.body === 'object') {
            opciones.body = JSON.stringify(opciones.body);
        }
    }

    const configuracion = {
        ...opciones,
        headers: {
            ...cabecerasPorDefecto,
            ...(opciones.headers || {})
        }
    };

    const respuesta = await fetch(url, configuracion);
    const json = await respuesta.json();

    if (!respuesta.ok) {
        throw new ErrorHttp(respuesta.status, json.mensaje || 'Error en la petición', json.errores);
    }

    return json;
}
```

---

## 4. Catálogo de Endpoints de Identidad (Personas — Microfase 1D)

Todos los endpoints del módulo de identidad implementan **Deny by Default** (`GuardiaActorMiddleware`), requiriendo una sesión de actor autenticado. Toda mutación HTTP exige protección anti-CSRF activa (`CsrfMiddleware`).

### 4.1. Resumen de Endpoints

| Método | Ruta | Middleware | Responsabilidad | Código Éxito |
|---|---|---|---|---|
| `GET` | `/api/personas` | `GuardiaActor` | Listado paginado server-side para DataTables con whitelist SQL rígida. | 200 OK |
| `GET` | `/api/personas/{id}` | `GuardiaActor` | Detalle compuesto 360 (datos civiles, satélites, colecciones activas, UBIGEO). | 200 OK |
| `POST` | `/api/personas` | `Csrf`, `GuardiaActor` | Registro atómico de Persona (Natural o Jurídica) con DTO allowlist estricto y auditoría. | 201 Created |
| `PUT` | `/api/personas/{id}` | `Csrf`, `GuardiaActor` | Actualización integral de ficha. Prohíbe cambio de tipo_persona. Sincroniza colecciones. | 200 OK |
| `PATCH` | `/api/personas/{id}/estado` | `Csrf`, `GuardiaActor` | Transición controlada de estado (ACTIVO <-> INACTIVO) con motivo auditable obligatorio. | 200 OK |

> [!IMPORTANT]
> **Prohibición Total de DELETE:** No existe ni existirá un endpoint `DELETE /api/personas/{id}`. La desactivación de registros se realiza exclusivamente mediante transición de estado con trazabilidad en bitácora de auditoría.

### 4.2. Contrato de Entrada para Creación (`POST /api/personas`)
El payload admite campos estrictamente definidos en `CrearPersonaDTO`. Cualquier campo no reconocido genera **HTTP 422**.
- `tipo_persona`: `"NATURAL"` | `"JURIDICA"` (Requerido)
- `notas`: `string` opcional
- Si `tipo_persona == "NATURAL"`:
  - `nombres`: `string` (Requerido)
  - `apellido_paterno`: `string` (Requerido)
  - `apellido_materno`: `string` opcional
  - `sexo_id`, `estado_civil_id`, `pais_nacimiento_id`, `fecha_nacimiento`, `profesion_ocupacion`: opcionales
- Si `tipo_persona == "JURIDICA"`:
  - `razon_social`: `string` (Requerido)
  - `nombre_comercial`, `fecha_constitucion`, `objeto_social`: opcionales
  - `representantes`: array opcional de `[{ persona_natural_id, cargo, fecha_inicio, partida_registral, es_representante_actual }]`
- Colecciones (0..N permitidos; si se informan, máximo 1 activo principal):
  - `documentos`: `[{ tipo_documento_id, numero_documento, es_principal, pais_emision_id, fecha_emision, fecha_vencimiento }]`
  - `contactos`: `[{ tipo_contacto_id, valor, etiqueta, es_principal }]`
  - `direcciones`: `[{ tipo_direccion_id, distrito_id, direccion, referencia, codigo_postal, es_principal }]`

### 4.3. Semántica de Actualización (`PUT /api/personas/{id}`)
- `tipo_persona`: Inmutable (intento de cambio retorna 422).
- Colecciones en payload:
  - Si la clave se omite (`null`): Se preservan los registros existentes en base de datos.
  - Si se envía array vacío (`[]`): Se desactivan todos los registros previos de la persona en dicha colección.
  - Si se envía array con elementos: Se desactivan los anteriores y se inserta el nuevo conjunto sincronizado.
