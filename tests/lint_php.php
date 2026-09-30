<?php

declare(strict_types=1);

/**
 * Verificador de Sintaxis PHP (PHP Lint) para CasaPRO.
 * Analiza todos los archivos PHP propios del proyecto.
 */

$directorio = dirname(__DIR__);
$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directorio, RecursiveDirectoryIterator::SKIP_DOTS)
);

$archivosPhp = [];

foreach ($iterador as $archivo) {
    if ($archivo->isFile() && $archivo->getExtension() === 'php') {
        $ruta = $archivo->getPathname();
        // Excluir dependencias externas y plantillas solo-lectura
        if (
            str_contains($ruta, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
            str_contains($ruta, DIRECTORY_SEPARATOR . 'admin-dashboard' . DIRECTORY_SEPARATOR)
        ) {
            continue;
        }
        $archivosPhp[] = $ruta;
    }
}

sort($archivosPhp);
$total = count($archivosPhp);
$pass = 0;
$fail = 0;

echo "====================================================\n";
echo " Verificación de Sintaxis PHP 8.3 (PHP Lint)\n";
echo "====================================================\n";
echo "Total de archivos analizados: {$total}\n\n";

foreach ($archivosPhp as $archivo) {
    $salida = [];
    $retorno = 0;
    $rutaRelativa = str_replace($directorio . DIRECTORY_SEPARATOR, '', $archivo);
    exec('php -l ' . escapeshellarg($archivo) . ' 2>&1', $salida, $retorno);

    if ($retorno === 0) {
        $pass++;
        echo "  [PASS] {$rutaRelativa}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$rutaRelativa}\n";
        echo "         " . implode("\n         ", $salida) . "\n";
    }
}

echo "\n====================================================\n";
echo " RESULTADO LINT: {$pass}/{$total} PASS, {$fail} FAIL\n";
echo "====================================================\n";

exit($fail === 0 ? 0 : 1);
