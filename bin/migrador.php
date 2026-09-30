<?php

declare(strict_types=1);

/**
 * Utilidad de Línea de Comandos para Migraciones SQL en CasaPRO.
 *
 * Uso:
 *   php bin/migrador.php           Ejecuta todas las migraciones pendientes
 *   php bin/migrador.php estado    Muestra el estado de cada migración
 *   php bin/migrador.php consolidado  Ejecuta SQL/casa-pro.sql directamente
 */

define('CASAPRO_RAIZ', dirname(__DIR__));

// Autoloader Composer si existe
if (file_exists(CASAPRO_RAIZ . '/vendor/autoload.php')) {
    require CASAPRO_RAIZ . '/vendor/autoload.php';
}

// Autoloader PSR-4 nativo de respaldo
spl_autoload_register(function (string $clase) {
    $prefijo = 'App\\';
    $directorioBase = CASAPRO_RAIZ . '/app/';

    $longitudPrefijo = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitudPrefijo) !== 0) {
        return;
    }

    $claseRelativa = substr($clase, $longitudPrefijo);
    $archivo = $directorioBase . str_replace('\\', '/', $claseRelativa) . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    }
});

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\MigradorSQL;

// Cargar variables de entorno
CargadorEntorno::cargar(CASAPRO_RAIZ);

$comando = $argv[1] ?? 'migrar';

try {
    $proveedor = new ProveedorConexion();
    $migrador = new MigradorSQL($proveedor);

    echo "=======================================================\n";
    echo " CasaPRO - Motor Determinista de Migraciones SQL\n";
    echo "=======================================================\n";

    if ($comando === 'estado') {
        $estado = $migrador->obtenerEstado();
        echo sprintf("%-50s | %-10s | %-6s | %s\n", "Migración", "Estado", "Lote", "Fecha Ejecución");
        echo str_repeat("-", 90) . "\n";
        foreach ($estado as $item) {
            echo sprintf(
                "%-50s | %-10s | %-6s | %s\n",
                $item['migracion'],
                $item['estado'],
                $item['lote'] !== null ? (string) $item['lote'] : '-',
                $item['ejecutado_en'] ?? '-'
            );
        }
        echo "\nTotal migraciones registradas en carpeta: " . count($estado) . "\n";
    } elseif ($comando === 'consolidado') {
        $ruta = CASAPRO_RAIZ . '/SQL/casa-pro.sql';
        echo "Importando esquema consolidado oficial desde:\n{$ruta}\n";
        $migrador->ejecutarConsolidado($ruta);
        echo "[EXITO] Esquema consolidado ejecutado correctamente.\n";
    } else {
        echo "Ejecutando migraciones pendientes...\n";
        $resultado = $migrador->ejecutar();

        if (count($resultado['aplicadas']) === 0) {
            echo "[OK] No hay migraciones pendientes. La base de datos se encuentra actualizada.\n";
            echo "Migraciones previamente aplicadas: " . count($resultado['omitidas']) . "\n";
        } else {
            echo "[EXITO] Se aplicaron " . count($resultado['aplicadas']) . " migracion(es) en el Lote #" . $resultado['lote'] . ":\n";
            foreach ($resultado['aplicadas'] as $migracion) {
                echo "  + [APLICADA] {$migracion}\n";
            }
        }
    }
    echo "=======================================================\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n[ERROR CRITICO] " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
