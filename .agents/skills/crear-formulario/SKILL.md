---
name: crear-formulario
description: Construye un formulario estandarizado con componentes Alina, máscaras CleaveJS y validación frontend.
---

# Skill: crear-formulario

## Propósito
Diseñar e implementar formularios coherentes, accesibles y validados para el flujo asíncrono de CasaPRO, reutilizando componentes físicos de Alina (`default_forms.html`, `base_inputs.html`, `select.html`, `date_picker.html`) y aplicando validación declarativa con PristineJS (ADR-009, ADR-024).

---

## Procedimiento de Implementación

### 1. Estructura HTML y Componentes Alina
- Formulario con clases oficiales `.needs-validation` y atributo `novalidate`.
- Distribución en rejilla Bootstrap `row g-3` con labels claros (`.form-label .f-s-13 .text-secondary`) e indicadores de obligatoriedad (`<span class="text-danger">*</span>`).
- Grupos de entrada (`.input-group`) con iconos Font Awesome exclusivos (`fa-solid fa-*`).
- **Selects:** Inicializar Select2 con `.select2()` para catálogos y UBIGEO.
- **Fechas:** Inicializar Flatpickr en inputs con selector `.flatpickr-input`.
- **Máscaras:** Usar CleaveJS para importes monetarios (`S/ 0.00`, `$ 0.00`) y números de documento (DNI 8 dígitos, RUC 11 dígitos).

### 2. Validación Declarativa con PristineJS
- Inicializar PristineJS configurando las clases estándar de Alina y Bootstrap 5:
  ```javascript
  const validador = new Pristine(formElemento, {
      classTo: 'col-md-6', // o contenedor del input
      errorClass: 'is-invalid',
      successClass: 'is-valid',
      errorTextParent: 'col-md-6',
      errorTextTag: 'div',
      errorTextClass: 'invalid-feedback d-block'
  });
  ```
- *Regla de Gobernanza:* PristineJS es una ayuda ergonómica para el usuario en frontend; jamás sustituye la validación estricta del backend mediante DTOs.

### 3. Estados de Carga y Prevención de Doble Envío
- Todo formulario asíncrono debe transicionar por:
  $$\text{NORMAL} \longrightarrow \text{PROCESANDO} \longrightarrow \text{ÉXITO / ERROR}$$
- Al disparar el evento `submit`:
  ```javascript
  if (!validador.validate()) {
      return;
  }
  // Deshabilitar submit y activar spinner
  btnSubmit.disabled = true;
  btnSpinner.classList.remove('d-none');
  btnTexto.textContent = ' Procesando...';
  ```
- En caso de error (red o validación 422/500), rehabilitar inmediatamente el botón y ocultar el spinner:
  ```javascript
  btnSubmit.disabled = false;
  btnSpinner.classList.add('d-none');
  btnTexto.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar';
  ```

### 4. Despacho Asíncrono con Fetch Nativo y CSRF
- Leer token CSRF desde `<meta name="csrf-token" content="...">`.
- Despachar `window.fetch()` con payload JSON:
  ```javascript
  const respuesta = await fetch(urlEndpoint, {
      method: metodoHttp, // POST para crear, PUT para actualizar
      headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(datosFormulario)
  });
  ```

### 5. Manejo de Errores y Preservación de Datos
- **Errores de Validación Backend (HTTP 422):**
  - **EL FORMULARIO / MODAL PERMANECE ABIERTO.**
  - Queda terminantemente prohibido vaciar o resetear los campos del formulario cuando el servidor rechaza la entrada por validación.
  - Los mensajes de error devueltos por el servidor (`json.errores`) se vinculan a cada campo correspondiente marcándolo con `.is-invalid` y mostrando el texto en `.invalid-feedback`.
- **Errores Inesperados (HTTP 500):**
  - Mostrar notificación segura con identificador de correlación. Cero exposición de consultas SQL o stack traces.
