<?php

declare(strict_types=1);

/**
 * Script de Verificación para Gate SQL e Infraestructura de Persistencia (Microfase 1B).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\MigradorSQL;

CargadorEntorno::cargar(dirname(__DIR__));

echo "===============================================================\n";
echo " PRUEBA DE GATE SQL Y RECONSTRUCCIÓN LIMPIA (MICROFASE 1B)\n";
echo "===============================================================\n";

$proveedorBase = new ProveedorConexion();
$pdoServidor = $proveedorBase->crearConexion(false);

$dbA = 'casapro_test_camino_a_migraciones';
$dbB = 'casapro_test_camino_b_consolidado';

echo "1. Preparando bases de datos de prueba limpias...\n";
$pdoServidor->exec("DROP DATABASE IF EXISTS `{$dbA}`");
$pdoServidor->exec("DROP DATABASE IF EXISTS `{$dbB}`");
$pdoServidor->exec("CREATE DATABASE `{$dbA}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdoServidor->exec("CREATE DATABASE `{$dbB}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// --- CAMINO A: Migraciones ---
echo "2. Ejecutando Camino A (Motor de migraciones en {$dbA})...\n";
$cfgA = $proveedorBase->obtenerConfiguracion();
$cfgA['database'] = $dbA;
$provA = new ProveedorConexion($cfgA);
$migA = new MigradorSQL($provA);
$resA = $migA->ejecutar();
$totalAplicadasA = count($resA['aplicadas']);
echo "   + Migraciones aplicadas: {$totalAplicadasA} (Lote #{$resA['lote']})\n";

// --- CAMINO B: Consolidado ---
echo "3. Ejecutando Camino B (Importación de SQL/casa-pro.sql en {$dbB})...\n";
$cfgB = $proveedorBase->obtenerConfiguracion();
$cfgB['database'] = $dbB;
$provB = new ProveedorConexion($cfgB);
$migB = new MigradorSQL($provB);
$rutaConsolidado = dirname(__DIR__) . '/SQL/casa-pro.sql';
$migB->ejecutarConsolidado($rutaConsolidado);
echo "   + Esquema consolidado ejecutado exitosamente.\n";

// --- COMPARACIÓN ESTRUCTURAL ---
echo "4. Extrayendo metadatos estructurales de information_schema...\n";

function obtenerEstructuraTablas(PDO $pdo, string $baseDatos): array
{
    // Tablas
    $stmtTablas = $pdo->prepare("
        SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME
    ");
    $stmtTablas->execute([$baseDatos]);
    $tablas = $stmtTablas->fetchAll(PDO::FETCH_ASSOC);

    // Columnas
    $stmtColumnas = $pdo->prepare("
        SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $stmtColumnas->execute([$baseDatos]);
    $columnas = $stmtColumnas->fetchAll(PDO::FETCH_ASSOC);

    // Índices
    $stmtIndices = $pdo->prepare("
        SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
    ");
    $stmtIndices->execute([$baseDatos]);
    $indices = $stmtIndices->fetchAll(PDO::FETCH_ASSOC);

    // Claves foráneas y reglas referenciales
    $stmtFks = $pdo->prepare("
        SELECT
            kcu.TABLE_NAME,
            kcu.COLUMN_NAME,
            kcu.CONSTRAINT_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME,
            rc.UPDATE_RULE,
            rc.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE kcu
        JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
            AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
        WHERE kcu.TABLE_SCHEMA = ?
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ");
    $stmtFks->execute([$baseDatos]);
    $fks = $stmtFks->fetchAll(PDO::FETCH_ASSOC);

    return [
        'tablas' => $tablas,
        'columnas' => $columnas,
        'indices' => $indices,
        'fks' => $fks,
    ];
}

$estructuraA = obtenerEstructuraTablas($pdoServidor, $dbA);
$estructuraB = obtenerEstructuraTablas($pdoServidor, $dbB);

$tablasIguales = ($estructuraA['tablas'] == $estructuraB['tablas']);
$columnasIguales = ($estructuraA['columnas'] == $estructuraB['columnas']);
$indicesIguales = ($estructuraA['indices'] == $estructuraB['indices']);
$fksIguales = ($estructuraA['fks'] == $estructuraB['fks']);

echo "\n--- RESULTADO DE COMPARACIÓN ESTRUCTURAL (GATE SQL) ---\n";
echo "Tablas:          " . ($tablasIguales ? "[PASS] 100% Idénticas (" . count($estructuraA['tablas']) . " tablas)" : "[FAIL] Discrepancia detectada") . "\n";
echo "Columnas:        " . ($columnasIguales ? "[PASS] 100% Idénticas (" . count($estructuraA['columnas']) . " columnas)" : "[FAIL] Discrepancia detectada") . "\n";
echo "Índices:         " . ($indicesIguales ? "[PASS] 100% Idénticos (" . count($estructuraA['indices']) . " índices)" : "[FAIL] Discrepancia detectada") . "\n";
echo "Claves Foráneas: " . ($fksIguales ? "[PASS] 100% Idénticas (" . count($estructuraA['fks']) . " FKs)" : "[FAIL] Discrepancia detectada") . "\n";

// --- PRUEBA DE IDEMPOTENCIA ---
echo "\n5. Verificando Idempotencia en Camino A (segunda corrida)...\n";
$resAIdempotente = $migA->ejecutar();
$idempotenciaPass = (count($resAIdempotente['aplicadas']) === 0 && count($resAIdempotente['omitidas']) === $totalAplicadasA);
echo "Idempotencia:    " . ($idempotenciaPass ? "[PASS] 0 aplicadas, {$totalAplicadasA} omitidas" : "[FAIL] Comportamiento no idempotente") . "\n";

// --- LIMPIEZA DE BASES TEMPORALES ---
echo "6. Limpiando bases de datos de prueba...\n";
$pdoServidor->exec("DROP DATABASE IF EXISTS `{$dbA}`");
$pdoServidor->exec("DROP DATABASE IF EXISTS `{$dbB}`");
echo "   + Bases temporales eliminadas.\n";

$todoPass = $tablasIguales && $columnasIguales && $indicesIguales && $fksIguales && $idempotenciaPass;
echo "===============================================================\n";
if ($todoPass) {
    echo " RESULTADO FINAL GATE SQL: [PASS] (ESQUEMA A == ESQUEMA B)\n";
} else {
    echo " RESULTADO FINAL GATE SQL: [FAIL]\n";
}
echo "===============================================================\n";

exit($todoPass ? 0 : 1);
