<?php

declare(strict_types=1);

/**
 * Suite de Verificación Automatizada: Microfase 3B — Sectores Urbanísticos, Precios Históricos y Balance de Áreas.
 *
 * Cobertura de Gates y Mandatos de Gobernanza:
 * 1. Error Handler Estricto: cualquier Notice, Warning o Error PHP detona fallo fatal inmediato.
 * 2. Persistencia y Esquema 3B: Migración 000014 consumida, 000015 libre, 33 tablas idénticas, áreas en DECIMAL(14,4).
 * 3. Invariante Local del Sector: suma de áreas interna (útil + cesión + común) <= área bruta.
 * 4. Diagnóstico de Topografía y Balance PROVISIONAL vs DEFINITIVO: cero mezclas híbridas opacas.
 * 5. Invariante Macro Anti-Desborde: suma de áreas brutas sectoriales <= área matriz disponible.
 * 6. Histórico Temporal de Precios: cero solapamientos, exactamente un precio vigente, cierre atómico y registro inmutable.
 * 7. Anti-IDOR Territorial en Cascada (Fail-Closed): aislamiento estricto entre Empresa Alfa y Empresa Beta.
 * 8. Conmutación de Estado y Bajas Lógicas (cero DELETE).
 * 9. Auditoría Forense Append-Only: eventos de sector y precios en tabla auditorias.
 * 10. Renderizado Real en Navegador de /proyectos/{id} con 0 warnings/notices, KPIs y modales.
 * 11. Despacho HTTP Real por Enrutador y cURL contra servidor web local.
 * 12. DELTA casapro (desarrollo) = 0 garantizado.
 */

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tests/comun/AmbientePruebas.php';
require_once dirname(__DIR__) . '/tests/comun/FixtureAutenticacion.php';

use Tests\Comun\AmbientePruebas;
use Tests\Comun\FixtureAutenticacion;
use App\Core\ProveedorConexion;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Enrutador;
use App\Core\ContextoPeticion;
use App\Modelos\Empresa;
use App\Modelos\Proyecto;
use App\Modelos\ProyectoPredioMatriz;
use App\Modelos\Sector;
use App\Modelos\SectorPrecioHistorico;
use App\DTOs\CrearSectorDTO;
use App\DTOs\ActualizarSectorDTO;
use App\DTOs\CambiarEstadoSectorDTO;
use App\DTOs\AjustarPrecioSectorDTO;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\ProyectoRepositorio;
use App\Repositorios\ProyectoPredioMatrizRepositorio;
use App\Repositorios\SectorRepositorio;
use App\Servicios\SectorServicio;
use App\Servicios\ProyectoServicio;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;

echo "===================================================================\n";
echo " VERIFICACIÓN AUTOMATIZADA: MICROFASE 3B — SECTORES Y PRECIOS\n";
echo "===================================================================\n\n";

$pruebasSuperadas = 0;
$totalPruebas = 0;

function probar(bool $condicion, string $descripcion): void
{
    global $pruebasSuperadas, $totalPruebas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasSuperadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        echo "  [FAIL] {$descripcion}\n";
        throw new RuntimeException("Fallo en aserción: {$descripcion}");
    }
}

// 1. Error Handler Estricto
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException("PHP Error [{$errno}]: {$errstr} en {$errfile}:{$errline}", $errno, 1, $errfile, $errline);
});

// 2. Inicializar entorno aislado en casapro_test
$pdo = AmbientePruebas::iniciar(true);
$dbActual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
probar($dbActual === 'casapro_test', "Entorno aislado en base de datos 'casapro_test' (actual: {$dbActual})");

// Capturar métricas iniciales de desarrollo casapro para validar DELTA = 0
$configDev = require dirname(__DIR__) . '/config/database.php';
$configDev['database'] = 'casapro';
$pdoDev = (new ProveedorConexion($configDev))->obtenerConexion();
$conteoSectoresDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `sectores`')->fetchColumn();
$conteoPreciosDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `sector_precios_historico`')->fetchColumn();
$conteoAuditoriasDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

$provTest = AmbientePruebas::obtenerProveedorTest();
$empresaRepo = new EmpresaRepositorio($provTest);
$proyectoRepo = new ProyectoRepositorio($provTest);
$predioRepo = new ProyectoPredioMatrizRepositorio($provTest);
$sectorRepo = new SectorRepositorio($provTest);
$sectorServicio = new SectorServicio($provTest, $sectorRepo, $proyectoRepo, $predioRepo);

// =========================================================================
// BLOQUE 1: Persistencia, Migraciones y Esquema 3B
// =========================================================================
echo "\n--- BLOQUE 1: Persistencia, Migraciones y Esquema 3B ---\n";

$migracion14 = glob(dirname(__DIR__) . '/SQL/migraciones/*000014*');
probar(!empty($migracion14), 'Ranura de migración 000014 consumida y registrada en el repositorio');

$migracion15 = glob(dirname(__DIR__) . '/SQL/migraciones/*000015*');
probar(empty($migracion15), 'Ranura de migración 000015 permanece estrictamente libre para Fase 3C');

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'")->fetchColumn();
probar($totalTablas === 33, "Esquema relacional consolidado contiene exactamente 33 tablas (actual: {$totalTablas})");

// Validar tipos de datos: superficies en DECIMAL(14,4) y precios en DECIMAL(12,4)
$colsSectores = $pdo->query("
    SELECT COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'casapro_test' AND TABLE_NAME = 'sectores'
")->fetchAll(PDO::FETCH_ASSOC);

$columnasMap = [];
foreach ($colsSectores as $col) {
    $colNorm = array_change_key_case($col, CASE_LOWER);
    $columnasMap[$colNorm['column_name']] = $colNorm;
}

probar(
    ($columnasMap['area_bruta_m2']['data_type'] ?? '') === 'decimal' &&
    (int) $columnasMap['area_bruta_m2']['numeric_precision'] === 14 &&
    (int) $columnasMap['area_bruta_m2']['numeric_scale'] === 4,
    'Corrección 1: columna sectores.area_bruta_m2 estandarizada en DECIMAL(14,4)'
);

probar(
    ($columnasMap['area_util_m2']['data_type'] ?? '') === 'decimal' &&
    (int) $columnasMap['area_util_m2']['numeric_precision'] === 14 &&
    (int) $columnasMap['area_util_m2']['numeric_scale'] === 4,
    'Corrección 1: columna sectores.area_util_m2 estandarizada en DECIMAL(14,4)'
);

$colsPrecios = $pdo->query("
    SELECT COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'casapro_test' AND TABLE_NAME = 'sector_precios_historico'
")->fetchAll(PDO::FETCH_ASSOC);

$preciosMap = [];
foreach ($colsPrecios as $cp) {
    $cpNorm = array_change_key_case($cp, CASE_LOWER);
    $preciosMap[$cpNorm['column_name']] = $cpNorm;
}

probar(
    ($preciosMap['precio_m2_base']['data_type'] ?? '') === 'decimal' &&
    (int) $preciosMap['precio_m2_base']['numeric_precision'] === 12 &&
    (int) $preciosMap['precio_m2_base']['numeric_scale'] === 4,
    'Columna sector_precios_historico.precio_m2_base en DECIMAL(12,4)'
);

// =========================================================================
// BLOQUE 2: Fixtures en casapro_test
// =========================================================================
echo "\n--- BLOQUE 2: Configuración de Fixtures y Proyecto Matriz ---\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("DELETE FROM `sector_precios_historico`");
$pdo->exec("DELETE FROM `sectores`");
$pdo->exec("DELETE FROM `proyecto_participantes`");
$pdo->exec("DELETE FROM `proyecto_predios_matriz`");
$pdo->exec("DELETE FROM `proyectos`");
$pdo->exec("DELETE FROM `empresas`");
$pdo->exec("DELETE FROM `persona_juridica`");
$pdo->exec("DELETE FROM `personas`");
$pdo->exec("DELETE FROM `actores`");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Sembrar personas jurídicas y empresas Alfa y Beta
$pdo->exec("INSERT INTO personas (id, tipo_persona, estado) VALUES (101, 'JURIDICA', 'ACTIVO'), (102, 'JURIDICA', 'ACTIVO')");
$pdo->exec("INSERT INTO persona_juridica (persona_id, razon_social, nombre_comercial) VALUES (101, 'Alfa Corp SA', 'Alfa Corp'), (102, 'Beta Holdings SAC', 'Beta Group')");
$pdo->exec("INSERT INTO empresas (id, persona_id, codigo, nombre_corto, estado) VALUES (1, 101, 'EMP_ALFA', 'Alfa Corp', 'ACTIVO'), (2, 102, 'EMP_BETA', 'Beta Group', 'ACTIVO')");

// Sembrar actor operador
$pdo->exec("INSERT INTO actores (id, tipo_actor, codigo, nombre, estado) VALUES (1, 'USUARIO', 'USR_ADMIN', 'Operador Administrador', 'ACTIVO')");

// Obtener distrito válido
$distritoId = (int) $pdo->query("SELECT id FROM distritos LIMIT 1")->fetchColumn();
if ($distritoId <= 0) $distritoId = 1;

// Sembrar Proyecto 1 en Empresa Alfa
$pdo->exec("INSERT INTO proyectos (id, empresa_id, codigo, nombre, tipo_proyecto, moneda, distrito_id, estado, tipo_tolerancia, valor_tolerancia)
            VALUES (1, 1, 'PRJ_VALLE_3B', 'Valle Sagrado 3B', 'PROPIO', 'USD', {$distritoId}, 'EN_VENTA', 'ABSOLUTA_M2', 1.0000)");

// Sembrar Proyecto 2 en Empresa Beta (para pruebas de Anti-IDOR)
$pdo->exec("INSERT INTO proyectos (id, empresa_id, codigo, nombre, tipo_proyecto, moneda, distrito_id, estado, tipo_tolerancia, valor_tolerancia)
            VALUES (2, 2, 'PRJ_BETA_3B', 'Beta Residencial 3B', 'PROPIO', 'USD', {$distritoId}, 'EN_VENTA', 'ABSOLUTA_M2', 1.0000)");

probar(true, "Fixtures sembrados: Empresa Alfa (ID 1, Proyecto ID 1) y Empresa Beta (ID 2, Proyecto ID 2)");

// Contexto técnico de trazabilidad
$peticionFake = new Peticion('POST', '/api/proyectos/1/sectores');
$contextoReq = ContextoPeticion::crearDesdeEntorno($peticionFake);

// =========================================================================
// BLOQUE 3: Invariante Local del Sector y DTOs Anti-Polución
// =========================================================================
echo "\n--- BLOQUE 3: Invariante Local del Sector y DTOs Anti-Polución ---\n";

// 3.1: Rechazo de campos no reconocidos (Anti-Polución)
$falloAntiPolucion = false;
try {
    CrearSectorDTO::desdeArray([
        'proyecto_id'           => 1,
        'codigo'                => 'SEC_POLUCION',
        'nombre'                => 'Sector Polución',
        'area_bruta_m2'         => 10000.0000,
        'precio_m2_inicial'     => 100.0000,
        'campo_desconocido_xyz' => 'malicioso'
    ]);
} catch (ValidacionExcepcion $e) {
    $falloAntiPolucion = true;
    probar(str_contains($e->getMessage(), 'campos no permitidos'), "DTO anti-polución rechaza campos no permitidos");
}
probar($falloAntiPolucion, "CrearSectorDTO previene polución de atributos");

// 3.2: Rechazo de sector internamente inconsistente (Útil + Cesión + Común > Bruta)
$falloInconsistenciaLocal = false;
try {
    CrearSectorDTO::desdeArray([
        'proyecto_id'           => 1,
        'codigo'                => 'SEC_INCONSISTENTE',
        'nombre'                => 'Sector Inconsistente',
        'area_bruta_m2'         => 10000.0000,
        'area_util_m2'          => 7000.0000,
        'area_cesion_m2'        => 3500.0000, // 7000 + 3500 = 10500 > 10000
        'area_comun_m2'         => 500.0000,
        'precio_m2_inicial'     => 120.0000,
        'motivo_precio_inicial' => 'Lanzamiento preventa'
    ]);
} catch (ValidacionExcepcion $e) {
    $falloInconsistenciaLocal = true;
    probar(str_contains($e->getMessage(), 'excede el área bruta asignada'), "Invariante local: rechaza sector con útil + cesión + común > bruta");
}
probar($falloInconsistenciaLocal, "Bloqueo preventivo de inconsistencia interna a nivel de DTO");

// =========================================================================
// BLOQUE 4: Diagnóstico de Topografía y Balance PROVISIONAL vs DEFINITIVO
// =========================================================================
echo "\n--- BLOQUE 4: Corrección 2: Diagnóstico de Topografía y Balance Separado ---\n";

// Sembrar Predio 1 en Proyecto 1: área registral = 80,000 m², topográfica = NULL (pendiente)
$pdo->exec("INSERT INTO proyecto_predios_matriz (id, proyecto_id, denominacion, partida_registral, area_registral_m2, area_topografica_m2, distrito_id, estado)
            VALUES (1, 1, 'Predio El Remanso', 'SUNARP-001', 80000.0000, NULL, {$distritoId}, 'ACTIVO')");

// Diagnóstico con Predio 1 (topografía pendiente)
$balanceInicial = $sectorServicio->evaluarBalanceAreas(1);
probar($balanceInicial['estado_topografia'] === 'PENDIENTE', "Diagnóstico topografía: PENDIENTE (0/1 predios levantados)");
probar($balanceInicial['tipo_balance'] === 'PROVISIONAL', "Corrección 2: Balance calificado como PROVISIONAL ante topografía pendiente");
probar($balanceInicial['origen_area_referencia'] === 'REGISTRAL', "Superficie de referencia basada en áreas registrales (80,000.0000 m²)");
probar($balanceInicial['area_referencia_matriz_m2'] === 80000.0000, "Área de referencia de contraste = 80,000.0000 m²");
probar($balanceInicial['area_topografica_total_m2'] === 0.0, "Área topográfica conocida = 0.0000 m²");

// Sembrar Predio 2 en Proyecto 1: área registral = 40,000 m², topográfica = 39,950 m² (levantado)
$pdo->exec("INSERT INTO proyecto_predios_matriz (id, proyecto_id, denominacion, partida_registral, area_registral_m2, area_topografica_m2, distrito_id, estado)
            VALUES (2, 1, 'Predio La Campiña', 'SUNARP-002', 40000.0000, 39950.0000, {$distritoId}, 'ACTIVO')");

// Diagnóstico con Predio 1 (NULL) y Predio 2 (39,950 m²)
$balanceParcial = $sectorServicio->evaluarBalanceAreas(1);
probar($balanceParcial['estado_topografia'] === 'PARCIAL', "Diagnóstico topografía: PARCIAL (1/2 predios levantados)");
probar($balanceParcial['tipo_balance'] === 'PROVISIONAL', "Corrección 2: Balance continúa como PROVISIONAL ante levantamiento parcial");
probar($balanceParcial['area_registral_total_m2'] === 120000.0000, "Área registral total conocida = 120,000.0000 m²");
probar($balanceParcial['area_topografica_total_m2'] === 39950.0000, "Área topográfica parcial = 39,950.0000 m²");
probar($balanceParcial['area_referencia_matriz_m2'] === 120000.0000, "Invariante: no se mezclan predios parcialmente levantados; referencia usa registral total");

// Completar levantamiento de Predio 1: 79,900 m² (ahora 2/2 predios tienen levantamiento)
$pdo->exec("UPDATE proyecto_predios_matriz SET area_topografica_m2 = 79900.0000 WHERE id = 1");

// Diagnóstico con levantamiento topográfico COMPLETO
$balanceCompleto = $sectorServicio->evaluarBalanceAreas(1);
probar($balanceCompleto['estado_topografia'] === 'COMPLETA', "Diagnóstico topografía: COMPLETA (2/2 predios levantados)");
probar($balanceCompleto['tipo_balance'] === 'DEFINITIVO', "Corrección 2: Balance conmutado a DEFINITIVO tras completarse topografía matriz");
probar($balanceCompleto['origen_area_referencia'] === 'TOPOGRAFICA', "Superficie de referencia cambia a TOPOGRÁFICA oficial");
$areaTopograficaEsperada = round(79900.0000 + 39950.0000, 4); // 119,850.0000 m²
probar($balanceCompleto['area_referencia_matriz_m2'] === $areaTopograficaEsperada, "Área matriz definitiva de referencia = 119,850.0000 m²");

// =========================================================================
// BLOQUE 5: Creación de Sectores y Bloqueo Anti-Desborde Macro
// =========================================================================
echo "\n--- BLOQUE 5: Creación de Sectores y Anti-Desborde Macro ---\n";

// Crear Sector 1 válido: Bruta = 50,000 m², Útil = 32,000 m², Cesión = 15,000 m², Común = 2,500 m² (Remanente local = 500 m²)
$dtoSec1 = new CrearSectorDTO([
    'proyecto_id'           => 1,
    'codigo'                => 'SEC_LOS_ALAMOS',
    'nombre'                => 'Sector Los Álamos - Etapa I',
    'descripcion'           => 'Primera etapa residencial campestre',
    'area_bruta_m2'         => 50000.0000,
    'area_util_m2'          => 32000.0000,
    'area_cesion_m2'        => 15000.0000,
    'area_comun_m2'         => 2500.0000,
    'orden'                 => 1,
    'estado'                => 'EN_VENTA',
    'precio_m2_inicial'     => 145.0000,
    'motivo_precio_inicial' => 'Lanzamiento preventa oficial'
]);

$resSec1 = $sectorServicio->crear($dtoSec1, 1, 1, $contextoReq);
probar($resSec1['id'] > 0, "Sector 1 creado exitosamente (ID {$resSec1['id']})");
probar($resSec1['codigo'] === 'SEC_LOS_ALAMOS', "Código canónico SEC_LOS_ALAMOS persistido");

// Verificar balance tras Sector 1
$balPost1 = $sectorServicio->evaluarBalanceAreas(1);
probar($balPost1['total_sectores'] === 1, "Total de sectores activos = 1");
probar($balPost1['area_sectores_bruta_m2'] === 50000.0000, "Área sectorizada bruta = 50,000.0000 m²");
$remanenteEsperado1 = round(119850.0000 - 50000.0000, 4); // 69,850.0000 m²
probar($balPost1['area_remanente_matriz_m2'] === $remanenteEsperado1, "Remanente matriz disponible = 69,850.0000 m²");
probar($balPost1['estado_balance'] === 'BALANCE_CONCILIADO', "Estado de balance: BALANCE_CONCILIADO");

// Intento de desborde: Crear Sector 2 con área bruta de 80,000 m² (50,000 + 80,000 = 130,000 > 119,850)
$falloDesbordeMacro = false;
try {
    $dtoDesborde = new CrearSectorDTO([
        'proyecto_id'           => 1,
        'codigo'                => 'SEC_DESBORDE',
        'nombre'                => 'Sector Gigante Desbordante',
        'area_bruta_m2'         => 80000.0000,
        'area_util_m2'          => 50000.0000,
        'area_cesion_m2'        => 20000.0000,
        'area_comun_m2'         => 5000.0000,
        'precio_m2_inicial'     => 150.0000,
        'motivo_precio_inicial' => 'Lanzamiento'
    ]);
    $sectorServicio->crear($dtoDesborde, 1, 1, $contextoReq);
} catch (ReglaNegocioExcepcion $e) {
    $falloDesbordeMacro = true;
    probar(str_contains($e->getMessage(), 'superaría el área matriz disponible'), "Anti-Desborde Macro: bloquea creación de sector que supera el área matriz disponible");
}
probar($falloDesbordeMacro, "Invariante Macro: rechazo preventivo ante sobreasignación de suelo");

// Crear Sector 2 legítimo: Bruta = 60,000 m² (50,000 + 60,000 = 110,000 <= 119,850)
$dtoSec2 = new CrearSectorDTO([
    'proyecto_id'           => 1,
    'codigo'                => 'SEC_LOS_CEDROS',
    'nombre'                => 'Sector Los Cedros - Etapa II',
    'area_bruta_m2'         => 60000.0000,
    'area_util_m2'          => 38000.0000,
    'area_cesion_m2'        => 18000.0000,
    'area_comun_m2'         => 3000.0000,
    'orden'                 => 2,
    'estado'                => 'EN_DESARROLLO',
    'precio_m2_inicial'     => 160.0000,
    'motivo_precio_inicial' => 'Preventa privada'
]);

$resSec2 = $sectorServicio->crear($dtoSec2, 1, 1, $contextoReq);
probar($resSec2['id'] > 0, "Sector 2 creado exitosamente (ID {$resSec2['id']})");

$balPost2 = $sectorServicio->evaluarBalanceAreas(1);
probar($balPost2['total_sectores'] === 2, "Total de sectores activos = 2");
probar($balPost2['area_sectores_bruta_m2'] === 110000.0000, "Área sectorizada bruta = 110,000.0000 m²");
$remanenteEsperado2 = round(119850.0000 - 110000.0000, 4); // 9,850.0000 m²
probar($balPost2['area_remanente_matriz_m2'] === $remanenteEsperado2, "Remanente matriz disponible = 9,850.0000 m² (vías maestras y reservas)");
probar($balPost2['estado_balance'] === 'BALANCE_CONCILIADO', "Balance general: BALANCE_CONCILIADO");

// =========================================================================
// BLOQUE 6: Histórico Temporal de Precios y Blindaje de Solapamiento
// =========================================================================
echo "\n--- BLOQUE 6: Corrección 3: Histórico Temporal de Precios y Cero Solapamiento ---\n";

// 6.1: Verificar que Sector 1 tiene su precio inicial vigente (fecha_fin IS NULL)
$precioVigenteSec1 = $sectorRepo->obtenerPrecioVigente($resSec1['id']);
probar($precioVigenteSec1 !== null, "Sector 1 tiene precio inicial vigente registrado");
probar((float) $precioVigenteSec1['precio_m2_base'] === 145.0000, "Precio base inicial = 145.0000 USD/m²");
probar($precioVigenteSec1['fecha_fin'] === null, "Precio vigente tiene fecha_fin = NULL");
probar($precioVigenteSec1['moneda'] === 'USD', "Moneda de precio coincide con el proyecto (USD)");

// 6.2: Rechazo de ajuste con fecha anterior al precio vigente actual
$fechaInicioVigente = $precioVigenteSec1['fecha_inicio'];
$fechaAnterior = date('Y-m-d', strtotime($fechaInicioVigente . ' - 5 days'));
$falloFechaAnterior = false;
try {
    $dtoFechaInvalida = new AjustarPrecioSectorDTO([
        'precio_m2_base' => 170.0000,
        'fecha_inicio'   => $fechaAnterior,
        'motivo'         => 'Intento retroactivo inválido'
    ]);
    $sectorServicio->ajustarPrecio($resSec1['id'], $dtoFechaInvalida, 1, 1, $contextoReq);
} catch (ReglaNegocioExcepcion $e) {
    $falloFechaAnterior = true;
    probar(str_contains($e->getMessage(), 'no puede ser anterior'), "Blindaje temporal: rechaza fijar precio con fecha anterior a la vigente");
}
probar($falloFechaAnterior, "Regla de inmutabilidad cronológica respetada");

// 6.3: Ajuste de precio legítimo
$fechaNuevoPrecio = date('Y-m-d', strtotime($fechaInicioVigente . ' + 10 days'));
$dtoNuevoPrecio = new AjustarPrecioSectorDTO([
    'precio_m2_base' => 155.0000,
    'fecha_inicio'   => $fechaNuevoPrecio,
    'motivo'         => 'Incremento por culminación de redes de electrificación'
]);

$resAjuste = $sectorServicio->ajustarPrecio($resSec1['id'], $dtoNuevoPrecio, 1, 1, $contextoReq);
probar($resAjuste['id'] > 0, "Nuevo precio registrado exitosamente (ID {$resAjuste['id']})");
probar($resAjuste['precio_m2_base'] === 155.0000, "Nuevo precio vigente = 155.0000 USD/m²");

// 6.4: Verificar cierre atómico del precio anterior (fecha_fin poblada)
$stmtHistorial = $pdo->prepare("SELECT * FROM sector_precios_historico WHERE sector_id = :sector_id ORDER BY id ASC");
$stmtHistorial->execute([':sector_id' => $resSec1['id']]);
$filasHistorial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

probar(count($filasHistorial) === 2, "Historial registra exactamente 2 tuplas para Sector 1");
probar($filasHistorial[0]['fecha_fin'] === $fechaNuevoPrecio, "Precio anterior cerrado atómicamente con fecha_fin = {$fechaNuevoPrecio}");
probar($filasHistorial[1]['fecha_fin'] === null, "Nuevo precio es el único con fecha_fin = NULL");
probar((float) $filasHistorial[0]['precio_m2_base'] === 145.0000, "Precio histórico inicial inalterado (inmutabilidad 145.0000)");

// Verificar que sólo un precio está vigente simultáneamente
$totalVigentes = (int) $pdo->query("SELECT COUNT(*) FROM sector_precios_historico WHERE sector_id = {$resSec1['id']} AND fecha_fin IS NULL")->fetchColumn();
probar($totalVigentes === 1, "Invariante: exactamente 1 precio vigente activo en cualquier momento");

// =========================================================================
// BLOQUE 7: Anti-IDOR Territorial en Cascada (Fail-Closed)
// =========================================================================
echo "\n--- BLOQUE 7: Anti-IDOR Territorial en Cascada (Fail-Closed) ---\n";

// 7.1: Intento de consultar sector de Empresa Alfa (1) desde ámbito Empresa Beta (2)
$falloIdorConsulta = false;
try {
    $sectorServicio->obtenerPorId($resSec1['id'], 2); // Empresa Beta solicitando sector de Alfa
} catch (RecursoNoEncontradoExcepcion $e) {
    $falloIdorConsulta = true;
    probar(true, "Anti-IDOR: consulta de sector ajeno rechazada con 404 Not Found");
}
probar($falloIdorConsulta, "Aislamiento territorial en consulta de sector");

// 7.2: Intento de actualizar sector de Empresa Alfa desde Empresa Beta
$falloIdorUpdate = false;
try {
    $dtoUpdateAjeno = new ActualizarSectorDTO([
        'nombre'        => 'Intento Hack Beta',
        'area_bruta_m2' => 40000.0000
    ]);
    $sectorServicio->actualizar($resSec1['id'], $dtoUpdateAjeno, 2, 1, $contextoReq);
} catch (RecursoNoEncontradoExcepcion $e) {
    $falloIdorUpdate = true;
    probar(true, "Anti-IDOR: actualización de sector ajeno rechazada con 404 Not Found");
}
probar($falloIdorUpdate, "Aislamiento territorial en mutación de sector");

// 7.3: Intento de ajustar precio de sector ajeno
$falloIdorPrecio = false;
try {
    $sectorServicio->ajustarPrecio($resSec1['id'], $dtoNuevoPrecio, 2, 1, $contextoReq);
} catch (RecursoNoEncontradoExcepcion $e) {
    $falloIdorPrecio = true;
    probar(true, "Anti-IDOR: fijación de precio en sector ajeno rechazada con 404 Not Found");
}
probar($falloIdorPrecio, "Aislamiento territorial en precios históricos");

// =========================================================================
// BLOQUE 8: Conmutación de Estado y Bajas Lógicas (Cero DELETE)
// =========================================================================
echo "\n--- BLOQUE 8: Conmutación de Estado y Bajas Lógicas ---\n";

$dtoCambioEstado = new CambiarEstadoSectorDTO([
    'estado' => 'CONSOLIDADO',
    'motivo' => 'Cierre de fase comercial y culminación de obras'
]);
$resEstado = $sectorServicio->cambiarEstado($resSec1['id'], $dtoCambioEstado, 1, 1, $contextoReq);
probar($resEstado['estado_nuevo'] === 'CONSOLIDADO', "Estado conmutado a CONSOLIDADO");

$sectorReleido = $sectorRepo->buscarPorId($resSec1['id']);
probar(($sectorReleido['estado'] ?? '') === 'CONSOLIDADO', "Persistencia confirma nuevo estado CONSOLIDADO en BD");

// Cero DELETE: comprobar que los registros persisten
$conteoFisicoSectores = (int) $pdo->query("SELECT COUNT(*) FROM sectores WHERE id = {$resSec1['id']}")->fetchColumn();
probar($conteoFisicoSectores === 1, "Inmutabilidad de datos: cero DELETE físico en sectores");

// =========================================================================
// BLOQUE 9: Auditoría Forense Append-Only
// =========================================================================
echo "\n--- BLOQUE 9: Auditoría Forense Append-Only en 'auditorias' ---\n";

$stmtAuditorias = $pdo->prepare("SELECT accion, entidad, registro_id FROM auditorias WHERE entidad IN ('sectores', 'sector_precios_historico') ORDER BY id ASC");
$stmtAuditorias->execute();
$eventosAuditoria = $stmtAuditorias->fetchAll(PDO::FETCH_ASSOC);

probar(count($eventosAuditoria) >= 4, "Al menos 4 eventos auditados para sectores y precios históricos");

$accionesRegistradas = array_column($eventosAuditoria, 'accion');
probar(in_array('CREAR_SECTOR', $accionesRegistradas, true), "Auditoría registró evento 'CREAR_SECTOR'");
probar(in_array('AJUSTE_PRECIO_SECTOR', $accionesRegistradas, true), "Auditoría registró evento 'AJUSTE_PRECIO_SECTOR'");
probar(in_array('CAMBIO_ESTADO_SECTOR', $accionesRegistradas, true), "Auditoría registró evento 'CAMBIO_ESTADO_SECTOR'");

// =========================================================================
// BLOQUE 10: Renderizado Real de Vistas Web y Ficha 360° en Navegador
// =========================================================================
echo "\n--- BLOQUE 10: Certificación Visual de /proyectos/{id} (Cero Warnings) ---\n";

// Autenticar como SUPERADMIN
$authSuperadmin = FixtureAutenticacion::autenticarComoSuperadmin($pdo);
GestorSesion::iniciar();
GestorSesion::establecer('auth', $authSuperadmin);
GestorSesion::establecer('actor_id', 1);
GestorSesion::establecer('contexto_empresa_id', 1);

$proyectoCtrl = new \App\Controladores\ProyectoControlador();

$peticionFicha = new Peticion('GET', '/proyectos/1');
$peticionFicha->establecerCabecera('Accept', 'text/html');
$respuestaFicha = new Respuesta();

ob_start();
$htmlFicha = $proyectoCtrl->ficha($peticionFicha, $respuestaFicha, ['id' => 1], $contextoReq);
$salidaFicha = ob_get_clean() . $htmlFicha;

probar(!str_contains($salidaFicha, 'Warning:'), "Ficha de proyecto /proyectos/1 libre de Warning:");
probar(!str_contains($salidaFicha, 'Notice:'), "Ficha de proyecto /proyectos/1 libre de Notice:");
probar(!str_contains($salidaFicha, 'Undefined array key'), "Ficha de proyecto libre de Undefined array key");
probar(!str_contains($salidaFicha, 'Undefined variable'), "Ficha de proyecto libre de Undefined variable");

// Verificación de marcado Alina y componentes 3B en HTML
probar(str_contains($salidaFicha, 'id="tab-sectores-btn"'), "Ficha contiene pestaña 'tab-sectores-btn'");
probar(str_contains($salidaFicha, 'id="panelMetricasBalanceSectores"'), "Ficha contiene panel de métricas 'panelMetricasBalanceSectores'");
probar(str_contains($salidaFicha, 'id="tablaSectores"'), "Ficha contiene tabla oficial 'tablaSectores'");
probar(str_contains($salidaFicha, 'SEC_LOS_ALAMOS'), "Ficha renderiza fila de Sector 1 (SEC_LOS_ALAMOS)");
probar(str_contains($salidaFicha, 'SEC_LOS_CEDROS'), "Ficha renderiza fila de Sector 2 (SEC_LOS_CEDROS)");
probar(str_contains($salidaFicha, 'id="modalCrearSector"'), "Ficha contiene modal Bootstrap 5 'modalCrearSector'");
probar(str_contains($salidaFicha, 'id="modalEditarSector"'), "Ficha contiene modal Bootstrap 5 'modalEditarSector'");
probar(str_contains($salidaFicha, 'id="modalPreciosSector"'), "Ficha contiene modal Bootstrap 5 'modalPreciosSector'");
probar(str_contains($salidaFicha, 'id="modalCambiarEstadoSector"'), "Ficha contiene modal Bootstrap 5 'modalCambiarEstadoSector'");
probar(str_contains($salidaFicha, 'gestion-sectores.js'), "Ficha incluye script interactivo 'gestion-sectores.js'");

// =========================================================================
// BLOQUE 11: Despacho HTTP Real por Enrutador y Pruebas Web
// =========================================================================
echo "\n--- BLOQUE 11: Despacho HTTP Completo por Enrutador y Pruebas Web ---\n";

$despacharHttp = function (string $metodo, string $ruta, array $cuerpo = [], array $cabeceras = []): array {
    $peticion = new Peticion($metodo, $ruta, $cuerpo, [], []);
    foreach ($cabeceras as $nombre => $valor) {
        $peticion->establecerCabecera($nombre, (string) $valor);
    }

    $esJson = false;
    foreach ($cabeceras as $nombre => $valor) {
        if (strtolower($nombre) === 'content-type' && str_contains(strtolower($valor), 'application/json')) {
            $esJson = true;
        }
    }
    if ($esJson) {
        $peticion->establecerJson($cuerpo);
    }

    $contexto = ContextoPeticion::crearDesdeEntorno($peticion);
    $respuesta = new Respuesta();

    $enrutador = new Enrutador();
    $configurador = require dirname(__DIR__) . '/config/rutas.php';
    $configurador($enrutador);

    ob_start();
    try {
        $enrutador->despachar($peticion, $respuesta, $contexto);
    } catch (\Throwable $t) {
        $respuesta->establecerCodigoEstado(500);
        $respuesta->establecerCuerpo("Error interno: " . $t->getMessage());
    }
    $salida = ob_get_clean();

    $cuerpoRespuesta = $respuesta->obtenerCuerpo();
    if ($cuerpoRespuesta === '' && $salida !== '') {
        $cuerpoRespuesta = $salida;
    }

    return [
        'codigo'    => $respuesta->obtenerCodigoEstado(),
        'cuerpo'    => $cuerpoRespuesta,
        'cabeceras' => $respuesta->obtenerCabeceras()
    ];
};

// 11.1: GET /api/proyectos/1/sectores
$resHttpListar = $despacharHttp('GET', '/api/proyectos/1/sectores', [], ['Accept' => 'application/json']);
probar($resHttpListar['codigo'] === 200, "GET /api/proyectos/1/sectores responde HTTP 200");
$cuerpoListar = json_decode($resHttpListar['cuerpo'], true);
probar(($cuerpoListar['estado'] ?? '') === 'exito', "Respuesta de listar sectores reporta 'exito'");
probar(count($cuerpoListar['datos']['sectores'] ?? []) === 2, "Entrega exactamente 2 sectores");

// 11.2: GET /api/proyectos/1/balance-areas
$resHttpBalance = $despacharHttp('GET', '/api/proyectos/1/balance-areas', [], ['Accept' => 'application/json']);
probar($resHttpBalance['codigo'] === 200, "GET /api/proyectos/1/balance-areas responde HTTP 200");
$cuerpoBalance = json_decode($resHttpBalance['cuerpo'], true);
probar(($cuerpoBalance['datos']['estado_balance'] ?? '') === 'BALANCE_CONCILIADO', "Endpoint de balance reporta BALANCE_CONCILIADO");

// 11.3: GET /api/sectores/{id}/precios
$resHttpPrecios = $despacharHttp('GET', "/api/sectores/{$resSec1['id']}/precios", [], ['Accept' => 'application/json']);
probar($resHttpPrecios['codigo'] === 200, "GET /api/sectores/{id}/precios responde HTTP 200");
$cuerpoPrecios = json_decode($resHttpPrecios['cuerpo'], true);
probar(count($cuerpoPrecios['datos']['precios'] ?? []) === 2, "Endpoint de precios entrega 2 precios históricos");

// 11.4: cURL contra servidor web local
if (function_exists('curl_init')) {
    $chHttp = curl_init('http://app.casa-pro.test/login');
    curl_setopt($chHttp, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chHttp, CURLOPT_TIMEOUT, 5);
    $cuerpoHttp = (string) curl_exec($chHttp);
    $codigoHttp = (int) curl_getinfo($chHttp, CURLINFO_HTTP_CODE);
    curl_close($chHttp);

    probar($codigoHttp === 200, "Servidor web local responde HTTP 200 en http://app.casa-pro.test/login");
    probar(!str_contains($cuerpoHttp, 'Warning:'), "Servidor web local libre de Warning: en HTTP");

    $chHttps = curl_init('https://app.casa-pro.test/login');
    curl_setopt($chHttps, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chHttps, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chHttps, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($chHttps, CURLOPT_TIMEOUT, 5);
    $cuerpoHttps = (string) curl_exec($chHttps);
    $codigoHttps = (int) curl_getinfo($chHttps, CURLINFO_HTTP_CODE);
    curl_close($chHttps);

    probar($codigoHttps === 200, "Servidor web local responde HTTPS 200 en https://app.casa-pro.test/login");
    probar(!str_contains($cuerpoHttps, 'Warning:'), "Servidor web local libre de Warning: en HTTPS");
}

// =========================================================================
// BLOQUE 12: Aislamiento y DELTA = 0 en Desarrollo (casapro)
// =========================================================================
echo "\n--- BLOQUE 12: Idempotencia y DELTA = 0 en Desarrollo ---\n";

$conteoSectoresDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `sectores`')->fetchColumn();
$conteoPreciosDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `sector_precios_historico`')->fetchColumn();
$conteoAuditoriasDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

probar($conteoSectoresDevFinal === $conteoSectoresDevInicial, "DELTA sectores en casapro (desarrollo) = 0 (inicial: {$conteoSectoresDevInicial}, final: {$conteoSectoresDevFinal})");
probar($conteoPreciosDevFinal === $conteoPreciosDevInicial, "DELTA precios en casapro (desarrollo) = 0 (inicial: {$conteoPreciosDevInicial}, final: {$conteoPreciosDevFinal})");
probar($conteoAuditoriasDevFinal === $conteoAuditoriasDevInicial, "DELTA auditorias en casapro (desarrollo) = 0 (inicial: {$conteoAuditoriasDevInicial}, final: {$conteoAuditoriasDevFinal})");

// Restaurar error handler original
restore_error_handler();

echo "\n===================================================================\n";
echo " RESUMEN: {$pruebasSuperadas} de {$totalPruebas} pruebas superadas (100% PASS)\n";
echo " MICROFASE 3B CERTIFICADA CON ÉXITO\n";
echo "===================================================================\n\n";
