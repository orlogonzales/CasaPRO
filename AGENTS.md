# AGENTS.md — Reglas y Mandatos para Asistentes y Agentes de CasaPRO

Este archivo define las reglas obligatorias e inviolables que cualquier agente de inteligencia artificial (Antigravity, Gemini, ChatGPT o cualquier otro asistente) debe obedecer al operar en el repositorio de CasaPRO (`D:\laragon\www\app.casa-pro`).

---

## 1. Principio Anti-Invención (Inviolable)

Queda estrictamente prohibido inventar código HTML, estilos CSS, selectores, componentes visuales o plugins de terceros basados en suposiciones o hábitos externos.

El flujo de trabajo obligatorio es:
1. **INSPECCIONAR:** Revisar físicamente `admin-dashboard\alina\template\` (116 plantillas) y `admin-dashboard\alina\assets\vendor\`.
2. **VERIFICAR:** Comprobar en `admin-dashboard\documentation\index.html` la sintaxis oficial del componente.
3. **REUTILIZAR:** Usar exactamente las clases de Bootstrap 5 y estilos de Alina (`style.css`).
4. **ADAPTAR:** Ajustar la variante encontrada al contexto funcional de la vista.
5. **DECLARAR:** Si un componente no existe en Alina, declarar la búsqueda y justificar formalmente el fallback en un ADR antes de implementarlo.

**La carpeta `admin-dashboard/` es de solo lectura y debe permanecer intacta.**

---

## 2. Convenciones de Código y Arquitectura

1. **Idioma:** Todo el código propio (clases, métodos, variables, atributos, tablas SQL, columnas, rutas, comentarios y documentación) se escribe **exclusivamente en español**.
2. **PHP 8.3 Nativo:** Todo archivo PHP inicia con `declare(strict_types=1);`. Tipado estricto en parámetros y retornos.
3. **Sin ORM:** Persistencia exclusiva con **PDO** mediante sentencias preparadas y parámetros vinculados (`bindValue`/`execute`). Cero inyecciones SQL.
4. **Plantilla Base Obligatoria:** Toda vista HTML del sistema se ensambla a partir de la anatomía de `admin-dashboard\alina\template\blank.html`.
5. **Frontend Modular sin jQuery en Código Propio:** CasaPRO no utilizará jQuery ni `$.ajax()` para desarrollar lógica propia. Sin embargo, se permite conservar jQuery exclusivamente cuando constituya una dependencia técnica heredada y verificada de Alina (como en `script.js` para el preloader) o de alguno de sus plugins originales. Su presencia no autoriza utilizarlo en nuevos módulos de CasaPRO. Toda lógica propia se escribe en JavaScript ES6+ modular, utilizando `fetch` nativo y respuestas JSON estructuradas.
6. **Validación Frontend:** Utilizar validación nativa HTML5/Bootstrap 5 (`.needs-validation`, `checkValidity()`) y PristineJS como fallback/extensión declarativa autorizada (ADR-009).

---

## 3. Modelo de Identidad y Autorización

1. **Persona como Identidad Raíz:** `Persona != Cliente != Personal != Usuario`. Jamás duplicar datos civiles en tablas satélite.
2. **Autorización Multidimensional:** Todo endpoint valida simultáneamente el privilegio funcional (RBAC: `modulo.accion`) y el ámbito territorial (Scope: `GLOBAL`, `EMPRESA`, `PROYECTO`, `SECTOR`).
3. **Inmutabilidad Financiera:** Prohibido el uso de sentencias `DELETE` en tablas financieras (`pagos`, `cuotas`, `cajas`, `asientos`). Toda corrección se realiza mediante anulación y contrapartida con auditoría.

---

## 4. Gates de Calidad para Cierre de Microfase

Ninguna microfase se considera concluida sin la verificación de los 12 gates de calidad universales:
`FUNCIONALIDAD`, `VALIDACIÓN FRONTEND`, `VALIDACIÓN BACKEND`, `CSRF`, `RBAC`, `SCOPE`, `PREPARED STATEMENTS`, `AUDITORÍA`, `ERRORES`, `RESPONSIVE`, `ALINA` y `REGRESIÓN`.
Consulte `docs/11-PRUEBAS-Y-GATES.md` para el protocolo completo.
