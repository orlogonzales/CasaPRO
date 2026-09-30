# 03 — Convenciones y Estándares de Código

## 1. Idioma Oficial del Código

Todo el código propio del sistema CasaPRO (nombres de clases, métodos, atributos, variables, nombres de tablas, columnas de base de datos, rutas de la aplicación, comentarios y documentación técnica) se redactará **exclusivamente en español**.

Se exceptúan únicamente las palabras clave reservadas de los lenguajes (PHP, SQL, JS) y las interfaces impuestas por librerías externas o protocolos (e.g., `SELECT`, `FROM`, `function`, `class`, `return`, `DOMContentLoaded`, etc.).

---

## 2. Nomenclatura por Capa y Artefacto

| Elemento | Convención | Ejemplo |
| :--- | :--- | :--- |
| **Clases / Interfaces / Traits** | `PascalCase` | `PersonaControlador`, `ClienteServicio`, `AuditoriaRepositorio` |
| **Métodos y Funciones** | `camelCase` | `registrarPersona()`, `calcularCronograma()`, `anularPago()` |
| **Variables y Propiedades** | `camelCase` | `$persona`, `$saldoPendiente`, `$cuotaInicial`, `$numeroDocumento` |
| **Constantes** | `UPPER_SNAKE_CASE` | `ESTADO_ACTIVO`, `ROL_SUPERADMINISTRADOR`, `MAX_INTENTOS_LOGIN` |
| **Tablas de Base de Datos** | `snake_case` (plural) | `personas`, `proyectos`, `lotes`, `ventas`, `pagos`, `cajas` |
| **Columnas de Base de Datos** | `snake_case` | `id`, `persona_id`, `numero_documento`, `creado_en`, `saldo_actual` |
| **Claves Foráneas (FK)** | `tabla_singular_id` | `persona_id`, `proyecto_id`, `cliente_id`, `usuario_id` |
| **Rutas HTTP (Web / API)** | `kebab-case` | `/personas`, `/lotes-disponibles`, `/api/v1/cajas/apertura` |
| **Archivos de Vistas** | `kebab-case.php` | `lista-personas.php`, `formulario-lote.php` |
| **Archivos JavaScript** | `kebab-case.js` | `datatable-helper.js`, `personas-modulo.js` |

---

## 3. Estándares PHP 8.3

1. **Tipado Estricto Obligatorio:** Todo archivo PHP debe iniciar con:
   ```php
   <?php

   declare(strict_types=1);
   ```
2. **Tipado Completo de Propiedades y Retornos:** Cada propiedad de clase, parámetro de función y valor de retorno debe contar con tipo explícito (incluyendo tipos unión o nulos si aplica):
   ```php
   public function registrarPago(int $ventaId, float $monto, string $metodoPago): ResultadoPagoDto
   ```
3. **Constructor Property Promotion:** Se fomenta el uso de promoción de propiedades en constructor para clases de servicio, repositorios y DTOs:
   ```php
   public function __construct(
       private readonly PersonaRepositorio $personaRepositorio,
       private readonly AuditoriaServicio $auditoriaServicio
   ) {}
   ```
4. **Manejo de Errores y Excepciones:** No se silencian errores (`@`). Se lanzan excepciones de dominio especializadas (`ValidacionExcepcion`, `NoEncontradoExcepcion`, `AutorizacionExcepcion`, `SaldoInsuficienteExcepcion`).

---

## 4. Estándares JavaScript

1. **ES6+ Moderno:** Código modular, uso de `const` y `let` (prohibido `var`), arrow functions y desestructuración.
2. **Fetch API Nativo:** Toda comunicación asíncrona se realiza mediante `fetch` nativo con `async/await` y cabeceras de seguridad (`X-CSRF-TOKEN`, `Accept: application/json`).
3. **Prohibición de jQuery en Código Nuevo:** Aunque Alina incluye jQuery para plugins de legado de la plantilla, el código de los módulos de CasaPRO se escribe en JavaScript estándar y modular.
4. **Estructura de Módulos Frontend:**
   ```javascript
   // public/assets/js/modulos/personas.js
   document.addEventListener('DOMContentLoaded', () => {
       inicializarTabla();
       inicializarFormulario();
   });
   ```

---

## 5. Documentación en Código (PHPDoc / JSDoc)

Cada clase, método público relevante y función JS debe contar con bloque de documentación conciso indicando propósito, parámetros y retorno:

```php
/**
 * Procesa la amortización de una cuota de financiamiento directo.
 *
 * @param int $cuotaId Identificador de la cuota a cancelar.
 * @param float $montoImporte Monto percibido en caja o banco.
 * @param int $usuarioId Usuario cajero que efectúa la operación.
 * @return ReciboPagoDto
 * @throws SaldoInsuficienteExcepcion Si el monto es inválido.
 */
public function procesarAmortizacion(int $cuotaId, float $montoImporte, int $usuarioId): ReciboPagoDto
```
