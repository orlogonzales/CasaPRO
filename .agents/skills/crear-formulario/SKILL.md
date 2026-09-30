---
name: crear-formulario
description: Construye un formulario estandarizado con componentes Alina, máscaras CleaveJS y validación frontend.
---

# Skill: crear-formulario

## Propósito
Diseñar e implementar formularios coherentes, accesibles y validados, reutilizando los componentes físicos de Alina (`default_forms.html`, `select.html`, `date_picker.html`).

## Procedimiento

1. **Estructura HTML:**
   - Formularios con clase `needs-validation` y atributo `novalidate`.
   - Campos organizados en filas `row g-3` con labels claros y divs `invalid-feedback`.

2. **Integración de Componentes Alina:**
   - **Selects:** Inicializar Select2 con `.select2()` para catálogos (países, departamentos, tipos de documento).
   - **Fechas:** Inicializar Flatpickr en inputs con selector `.flatpickr-input`.
   - **Máscaras:** Usar CleaveJS para formatos numéricos de moneda (`S/ 0.00`, `$ 0.00`) y números de documento.

3. **Manejo del Envío Asíncrono:**
   - Interceptar evento `submit` con `addEventListener`.
   - Validar con `form.checkValidity()` o PristineJS.
   - Enviar datos mediante `peticionHttp()` con cabecera CSRF.
   - Mostrar mensaje de éxito o feedback de errores con SweetAlert o Toastify.
