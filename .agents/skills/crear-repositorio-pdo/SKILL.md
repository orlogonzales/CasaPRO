---
name: crear-repositorio-pdo
description: Construye clases de persistencia PDO con sentencias preparadas nativas y cero concatenación SQL.
---

# Skill: crear-repositorio-pdo

## Propósito
Implementar la capa de acceso a datos en `app/Repositorios/` garantizando máxima seguridad contra inyecciones SQL y alto rendimiento.

## Procedimiento

1. **Uso de Tipado Estricto:**
   - Iniciar con `declare(strict_types=1);`.
   - Inyectar la instancia de conexión PDO.

2. **Sentencias Preparadas Obligatorias:**
   - Todo parámetro externo debe pasar por `:marcador` y ser vinculado mediante `bindValue()` o pasar en el array de `execute()`:
     ```php
     public function buscarPorDocumento(string $tipoDoc, string $numDoc): ?array
     {
         $sql = "SELECT * FROM personas WHERE tipo_documento = :tipo AND numero_documento = :num LIMIT 1";
         $stmt = $this->pdo->prepare($sql);
         $stmt->execute(['tipo' => $tipoDoc, 'num' => $numDoc]);
         $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
         return $resultado ?: null;
     }
     ```

3. **Mapeo Limpio:**
   - Retornar arrays asociativos o instancias de modelos de dominio.
