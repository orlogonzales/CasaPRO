---
name: cerrar-microbaseline
description: Consolida el cierre formal de una microfase con actualización de CHANGELOG, commit atómico y tag Git.
---

# Skill: cerrar-microbaseline

## Propósito
Congelar formalmente el estado del repositorio tras la aprobación unánime de los gates de calidad, estableciendo un nuevo micro-baseline de referencia.

## Procedimiento

1. **Verificación de Limpieza Git:**
   - Ejecutar `git status`.
   - Comprobar que no existan archivos sin seguimiento no deseados, logs temporales ni modificaciones ajenas al alcance de la microfase.

2. **Actualización de Documentación:**
   - Registrar los cambios en `CHANGELOG.md` indicando el micro-baseline y los componentes añadidos/modificados.

3. **Ejecución del Commit Atómico:**
   - Preparar archivos modificados: `git add .`
   - Realizar el commit siguiendo la convención:
     `git commit -m "feat({modulo}): {descripcion precisa de la microfase}"`

4. **Creación del Tag del Micro-Baseline:**
   - Crear tag anotado o ligero:
     `git tag -a mb-{fase}-{nombre} -m "Micro-baseline aprobado: {descripcion}"`

5. **Notificación de Cierre:**
   - Reportar al Orquestador que el micro-baseline ha sido establecido y el entorno está listo para la siguiente microfase.
