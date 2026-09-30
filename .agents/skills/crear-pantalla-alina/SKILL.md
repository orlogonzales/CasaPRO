---
name: crear-pantalla-alina
description: Ensambla una vista PHP completa basada estrictamente en la anatomía de blank.html de Alina.
---

# Skill: crear-pantalla-alina

## Propósito
Generar una nueva pantalla en CasaPRO ensamblando el layout maestro Alina con fidelidad absoluta a la plantilla base obligatoria `admin-dashboard\alina\template\blank.html` y los estándares de diseño oficiales (Fira Sans, Font Awesome exclusivo, ADR-023, ADR-024).

---

## Procedimiento de Ensamblado

### 1. Verificar Contrato Previo
- Asegurarse de que el skill `inspeccionar-alina` haya emitido el contrato de pantalla correspondiente.

### 2. Estructura del Archivo de Vista (`app/Vistas/modulos/{modulo}/{pantalla}.php`)
- Definir variables de layout requeridas:
  ```php
  <?php
  declare(strict_types=1);

  use App\Core\Vista;

  // Variables requeridas por el layout maestro
  $tituloPagina = 'Directorio de Entidad | CasaPRO';
  $migaPan = [
      ['texto' => 'Inicio', 'url' => Vista::url()],
      ['texto' => 'Módulo', 'url' => Vista::url('{modulo}')],
      ['texto' => 'Directorio', 'url' => '']
  ];
  $cssAdicionales = ['vendor/datatable/jquery.dataTables.min.css'];
  $jsAdicionales = [
      'vendor/datatable/jquery.dataTables.min.js',
      'vendor/datatable/dataTables.responsive.min.js',
      'js/modulos/{modulo}/{pantalla}.js'
  ];
  ?>
  ```

### 3. Iconografía y Componentes Oficiales
- **Iconografía Exclusiva Font Awesome:** Utilizar únicamente clases Font Awesome (`<i class="fa-solid fa-{icono}"></i>`, `<i class="fa-brands fa-{icono}"></i>`). Prohibido terminantemente el uso de clases `ti ti-*` de Tabler Icons.
- **Tipografía Oficial:** Todo el contenido hereda la tipografía oficial **Fira Sans** (`"Fira Sans", sans-serif`).
- **Tooltips Delegados:** En botones de acción, usar `title="..."` semántico con `data-bs-toggle="tooltip"` y `data-bs-placement="top"`.
- **Estructura para Interfaces CRUD:** La vista aloja tanto el contenedor de la tabla DataTables como los modales Alina correspondientes (Ficha y Formulario), evitando páginas separadas.

### 4. Inyección de Scripts Modulares
- Vincular el módulo JavaScript propio correspondiente en `public/assets/js/modulos/{modulo}/{pantalla}.js` encapsulado bajo `window.CasaPro{Modulo}`.
