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
