---
name: crear-modal
description: Diseña diálogos modales Bootstrap 5 reutilizando la estructura verificada de modals.html de Alina.
---

# Skill: crear-modal

## Propósito
Diseñar e integrar ventanas modales Bootstrap 5 con estilos de Alina (`modals.html`) para el patrón CRUD asíncrono oficial de CasaPRO (ADR-024).

---

## Directrices Arquitectónicas

### 1. Política de Uso: Modal como Patrón por Defecto
- Para registros y catálogos administrativos simples (Personas, Proveedores, Lotes, Bancos, Categorías, Contactos), las acciones de Crear y Editar se resuelven mediante un diálogo modal en la misma vista de la DataTable.
- **Regla de Excepción:** Procesos multietapa complejos (Ventas inmobiliarias, financiamiento directo, expedientes documentales extensos) utilizan páginas independientes o Wizards, documentando brevemente la justificación en la microfase.

### 2. Tamaños Oficiales de Alina (`modals.html`)
Se prohíben anchos personalizados en CSS. Se utilizan exclusivamente las clases estándar:
- `modal-sm`: Confirmaciones cortas.
- `modal-dialog` (por defecto): Formularios breves (1 a 4 campos).
- `modal-lg`: Formularios administrativos estándar (Personas, Lotes).
- `modal-xl`: Formularios con múltiples secciones o tablas secundarias.
- `modal-fullscreen`: Visores planimétricos o planos GIS.

### 3. Estructura HTML Canónica
Todo modal de formulario o ficha debe incorporar `.modal-dialog-centered` y `.modal-dialog-scrollable`:
```html
<div class="modal fade" id="modalOperacion" tabindex="-1" aria-labelledby="modalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content b-r-16">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalTitulo">Título de la Operación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Formulario o Contenido -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnAccionModal">Guardar</button>
            </div>
        </div>
    </div>
</div>
```

### 4. Ciclo de Vida en JavaScript (Bootstrap 5 Nativo)
- **Instanciación:**
  ```javascript
  const modalEl = document.getElementById('modalOperacion');
  const modalBs = new bootstrap.Modal(modalEl);
  ```
- **Apertura para Crear:**
  - Limpiar inputs del formulario (`form.reset()`).
  - Restablecer clases de validación (`.is-invalid`, `.is-valid`) y mensajes de PristineJS (`pristine.reset()`).
  - Actualizar título a "Registrar Nuevo...".
  - Mostrar modal: `modalBs.show();`.
- **Apertura para Editar:**
  - Obtener datos por ID mediante `fetch('GET', '/api/.../{id}')`.
  - Poblar campos con los datos recibidos.
  - Actualizar título a "Editar...".
  - Mostrar modal: `modalBs.show();`.
- **Regla Inviolable de Validación:**
  - Ante error HTTP 422 devuelto por el servidor, **el modal permanece abierto**. Queda terminantemente prohibido invocar `modalBs.hide()` o limpiar el formulario ante un fallo de validación.
- **Cierre tras Éxito:**
  - Tras recibir HTTP 200/201, cerrar modal (`modalBs.hide()`), resetear formulario y refrescar DataTable con `tabla.ajax.reload(null, false)`.
