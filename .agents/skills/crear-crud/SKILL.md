---
name: crear-crud
description: Genera el flujo integral de una entidad de negocio bajo la arquitectura MVC desacoplada de CasaPRO.
---

# Skill: crear-crud

## Propósito
Implementar un módulo CRUD completo respetando el desacoplamiento de capas, tipado estricto PHP 8.3, convenciones en español y validaciones de seguridad.

## Flujo de Trabajo

1. **Definir DTOs de Entrada (`app/DTOs/`):**
   - Crear clase de transferencia de datos con propiedades tipadas para creación y actualización.
   - Implementar método de validación o normalización.

2. **Crear Repositorio PDO (`app/Repositorios/`):**
   - Métodos con sentencias preparadas: `obtenerPorId()`, `crear()`, `actualizar()`, `eliminarLogico()`, `listarPaginado()`.

3. **Crear Servicio de Dominio (`app/Servicios/`):**
   - Validar unicidad de identificadores de negocio (e.g. DNI o RUC).
   - Iniciar transacción PDO si la operación afecta múltiples tablas.
   - Disparar auditoría mediante `AuditoriaServicio`.

4. **Crear Controlador (`app/Controladores/`):**
   - Métodos HTTP: `index()`, `crear()`, `guardar()`, `editar()`, `actualizar()`, `eliminar()`.
   - Aplicar middlewares de autenticación, CSRF, RBAC y scope.

5. **Registrar Rutas (`config/rutas.php`):**
   - Asignar rutas kebab-case y vincular controlador.

6. **Crear Vistas con Alina (`app/Vistas/modulos/{entidad}/`):**
   - Vista de listado con DataTables y vista/modal de formulario.
