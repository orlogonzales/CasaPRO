---
name: crear-modal
description: Diseña diálogos modales Bootstrap 5 reutilizando la estructura verificada de modals.html de Alina.
---

# Skill: crear-modal

## Propósito
Crear ventanas modales integradas y responsivas para operaciones rápidas o confirmaciones en CasaPRO.

## Procedimiento

1. **Referencia de Alina:**
   - Inspeccionar `admin-dashboard/alina/template/modals.html` para la estructura y clases específicas de Alina (`modal-dialog-centered`, `modal-xl`, `modal-content b-r-16`).

2. **Estructura Estándar:**
   ```html
   <div class="modal fade" id="modalOperacion" tabindex="-1" aria-labelledby="modalTitulo" aria-hidden="true">
       <div class="modal-dialog modal-dialog-centered modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title" id="modalTitulo">Título Modal</h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
               </div>
               <div class="modal-body">
                   <!-- Contenido -->
               </div>
               <div class="modal-footer">
                   <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                   <button type="button" class="btn btn-primary" id="btnGuardarModal">Guardar</button>
               </div>
           </div>
       </div>
   </div>
   ```

3. **Ciclo de Vida JS:**
   - Inicializar mediante la API nativa de Bootstrap 5: `const modal = new bootstrap.Modal(document.getElementById('modalOperacion'));`.
   - Limpiar el formulario y estados de error al disparar el evento `hidden.bs.modal`.
