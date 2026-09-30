---
name: crear-servicio-dominio
description: Desarrolla clases de lógica de negocio en PHP 8.3 con transacciones PDO y reglas de auditoría.
---

# Skill: crear-servicio-dominio

## Propósito
Centralizar las reglas de negocio complejas en la capa `app/Servicios/` manteniendo los controladores limpios y desacoplados.

## Procedimiento

1. **Inyección de Dependencias:**
   - Inyectar repositorios requeridos y `AuditoriaServicio` mediante Constructor Property Promotion.

2. **Control Transaccional:**
   - Para operaciones que involucren más de una mutación en base de datos:
     ```php
     $pdo = $this->conexion->obtenerPdo();
     $pdo->beginTransaction();
     try {
         // Paso 1: Actualizar entidad principal
         // Paso 2: Registrar detalle / movimiento
         // Paso 3: Registrar auditoría
         $pdo->commit();
     } catch (\Throwable $e) {
         $pdo->rollBack();
         throw new DominioExcepcion("Error al procesar la operación: " . $e->getMessage(), 0, $e);
     }
     ```

3. **Validación de Reglas de Negocio:**
   - Verificar estados permitidos (e.g. un lote solo puede reservarse si su estado es `DISPONIBLE`).
   - Validar coherencia matemática (e.g. cuota inicial no puede exceder el precio pactado).
