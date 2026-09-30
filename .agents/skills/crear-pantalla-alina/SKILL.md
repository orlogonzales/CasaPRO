---
name: crear-pantalla-alina
description: Ensambla una vista PHP completa basada estrictamente en la anatomía de blank.html de Alina.
---

# Skill: crear-pantalla-alina

## Propósito
Generar una nueva pantalla en CasaPRO ensamblando el layout maestro Alina con fidelidad absoluta a la plantilla base obligatoria `admin-dashboard\alina\template\blank.html`.

## Procedimiento

1. **Verificar Contrato Previo:**
   - Asegurarse de que el skill `inspeccionar-alina` haya emitido el contrato de pantalla correspondiente.

2. **Estructura del Archivo de Vista (`app/Vistas/modulos/{modulo}/{pantalla}.php`):**
   - Definir variables de layout: `$tituloPagina`, `$migaPan`, `$cssAdicionales`, `$jsAdicionales`.
   - Incluir la sección de contenido encapsulada en `<main><div class="container-fluid">...</div></main>`.

3. **Inclusión de Iconografía y Componentes:**
   - Usar Tabler Icons (`<i class="ti ti-{icono}"></i>`) con clases de Alina (`f-s-18`, `text-primary`, etc.).
   - Utilizar cards estándar (`<div class="card"><div class="card-header">...</div><div class="card-body">...</div></div>`).

4. **Inyección de Scripts Modulares:**
   - Vincular el módulo JavaScript propio correspondiente en `public/assets/js/modulos/{pantalla}.js`.
