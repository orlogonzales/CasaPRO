<?php

declare(strict_types=1);

/**
 * CasaPRO — Verificación de Guardia de Seguridad Fail-Closed y Aislamiento de Entorno.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/comun/AmbientePruebas.php';

use Tests\Comun\AmbientePruebas;
use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;

$totalPruebas = 0;
$pruebasSuperadas = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $totalPruebas, $pruebasSuperadas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasSuperadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        echo "  [FAIL] {$mensaje}\n";
        throw new RuntimeException("Fallo en la aserción: {$mensaje}");
    }
}

echo "===================================================================\n";
echo " VERIFICACIÓN DE GUARDIA FAIL-CLOSED Y AISLAMIENTO DE PRUEBAS\n";
echo "===================================================================\n\n";

// 1. Inicialización correcta en casapro_test
$pdoTest = AmbientePruebas::iniciar();
afirmar($pdoTest instanceof PDO, 'AmbientePruebas::iniciar() retorna una instancia válida de PDO');

$dbActual = (string) $pdoTest->query('SELECT DATABASE()')->fetchColumn();
afirmar($dbActual === 'casapro_test', "La base de datos conectada es 'casapro_test' (actual: {$dbActual})");

$appEnv = (string) CargadorEntorno::obtener('APP_ENV');
afirmar($appEnv === 'testing', "El entorno APP_ENV es 'testing' (actual: {$appEnv})");

// 2. Guardia Fail-Closed: Debe pasar sin excepción con casapro_test y testing
$pasoGuardia = false;
try {
    AmbientePruebas::verificarGuardia($pdoTest);
    $pasoGuardia = true;
} catch (\Throwable $e) {
    $pasoGuardia = false;
}
afirmar($pasoGuardia, 'Guardia de seguridad valida exitosamente el entorno legítimo de pruebas (casapro_test + testing)');

// 3. Guardia Fail-Closed: Aborta si APP_ENV !== 'testing'
$bloqueoEnv = false;
$_ENV['APP_ENV'] = 'production';
putenv('APP_ENV=production');
try {
    AmbientePruebas::verificarGuardia($pdoTest);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'GUARDIA DE SEGURIDAD VIOLADA') && str_contains($e->getMessage(), 'production')) {
        $bloqueoEnv = true;
    }
} finally {
    $_ENV['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
}
afirmar($bloqueoEnv, 'Guardia Fail-Closed aborta con RuntimeException si APP_ENV !== testing');

// 4. Guardia Fail-Closed: Aborta si se intenta conectar a base de datos de desarrollo (casapro)
$bloqueoDbDesarrollo = false;
$configDev = require dirname(__DIR__) . '/config/database.php';
$configDev['database'] = 'casapro';
$provDev = new ProveedorConexion($configDev);
try {
    $pdoDev = $provDev->obtenerConexion();
    AmbientePruebas::verificarGuardia($pdoDev);
} catch (RuntimeException $e) {
    if (str_contains($e->getMessage(), 'GUARDIA DE SEGURIDAD VIOLADA') && str_contains($e->getMessage(), 'casapro')) {
        $bloqueoDbDesarrollo = true;
    }
}
afirmar($bloqueoDbDesarrollo, 'Guardia Fail-Closed aborta con RuntimeException si la conexión apunta a BD de desarrollo (casapro)');

// 5. Esquema en casapro_test contiene las 26 tablas consolidadas
$totalTablasTest = (int) $pdoTest->query("
    SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'
")->fetchColumn();
afirmar($totalTablasTest === 26, "casapro_test contiene exactamente las 26 tablas del esquema oficial (actual: {$totalTablasTest})");

// 6. Reset determinista: limpiarTablasMutables restaura el actor SISTEMA_CASAPRO (ID 1)
AmbientePruebas::limpiarTablasMutables($pdoTest);
$stmtActor = $pdoTest->query("SELECT COUNT(*) FROM `actores` WHERE `id` = 1 AND `codigo` = 'SISTEMA_CASAPRO'");
$existeActorSistema = ((int) $stmtActor->fetchColumn()) === 1;
afirmar($existeActorSistema, 'limpiarTablasMutables() preserva/restaura el Actor 1 SISTEMA_CASAPRO');

echo "\n===================================================================\n";
echo " RESUMEN: {$pruebasSuperadas} de {$totalPruebas} pruebas superadas [PASS]\n";
echo "===================================================================\n";

exit($pruebasSuperadas === $totalPruebas ? 0 : 1);
