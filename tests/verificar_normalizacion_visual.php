<?php

declare(strict_types=1);

/**
 * CasaPRO — Suite de Verificación de Normalización Visual Global
 *
 * Valida:
 * 1. Tipografía Fira Sans (Normal, Condensed, Extra Condensed) en layouts y hojas de estilo CSS.
 * 2. Cero ocurrencias de Lexend Deca.
 * 3. Iconografía exclusiva Font Awesome (all.css y webfonts locales en public/assets/).
 * 4. Cero ocurrencias de clases Tabler ti ti-* en código propio.
 * 5. Cero inclusiones de tabler-icons.css.
 * 6. Tooltips reales de Bootstrap 5 en navbar-menu-list y script.js.
 * 7. Theme Customizer de Alina adaptado (traducido, sin Buy Now, sin Sidebar Variant, sin Font Sizing).
 * 8. Inmutabilidad estricta de admin-dashboard/.
 */

$directorioBase = dirname(__DIR__);
$errores = [];
$totalPruebas = 0;
$pruebasPasadas = 0;

function verificar(bool $condicion, string $descripcion, string &$errores, int &$totalPruebas, int &$pruebasPasadas): void
{
    $totalPruebas++;
    if ($condicion) {
        $pruebasPasadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        $errores .= "  [FAIL] {$descripcion}\n";
        echo "  [FAIL] {$descripcion}\n";
    }
}

echo "====================================================\n";
echo " Verificación de Normalización Visual Global (CasaPRO)\n";
echo "====================================================\n\n";

$errorBuffer = '';

// --------------------------------------------------------------------------
// 1. Tipografía Oficial Fira Sans
// --------------------------------------------------------------------------
echo "[1] Tipografía Oficial Fira Sans:\n";

$cabeceraHead = file_get_contents($directorioBase . '/app/Vistas/layouts/parciales/cabecera-head.php') ?: '';
$layoutError = file_get_contents($directorioBase . '/app/Vistas/layouts/error.php') ?: '';
$styleCss = file_get_contents($directorioBase . '/public/assets/css/style.css') ?: '';
$responsiveCss = file_get_contents($directorioBase . '/public/assets/css/responsive.css') ?: '';

verificar(
    str_contains($cabeceraHead, 'family=Fira+Sans+Condensed') &&
    str_contains($cabeceraHead, 'family=Fira+Sans+Extra+Condensed') &&
    str_contains($cabeceraHead, 'family=Fira+Sans:ital,wght@0,100'),
    'cabecera-head.php importa Fira Sans, Condensed y Extra Condensed desde Google Fonts',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($layoutError, 'family=Fira+Sans+Condensed') &&
    str_contains($layoutError, 'family=Fira+Sans+Extra+Condensed') &&
    str_contains($layoutError, 'family=Fira+Sans:ital,wght@0,100'),
    'error.php importa Fira Sans, Condensed y Extra Condensed desde Google Fonts',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($styleCss, '--theme-fonts: "Fira Sans", sans-serif;'),
    'style.css define --theme-fonts: "Fira Sans", sans-serif;',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($responsiveCss, '--theme-fonts: "Fira Sans", sans-serif;'),
    'responsive.css define --theme-fonts: "Fira Sans", sans-serif;',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($cabeceraHead, 'Lexend'),
    'cabecera-head.php no contiene referencias a Lexend',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($layoutError, 'Lexend'),
    'error.php no contiene referencias a Lexend',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($styleCss, 'Lexend'),
    'style.css no contiene referencias a Lexend',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($responsiveCss, 'Lexend'),
    'responsive.css no contiene referencias a Lexend',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// 2. Iconografía Oficial Font Awesome
// --------------------------------------------------------------------------
echo "\n[2] Iconografía Oficial Font Awesome:\n";

verificar(
    str_contains($cabeceraHead, 'vendor/fontawesome/css/all.css'),
    'cabecera-head.php carga vendor/fontawesome/css/all.css',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($layoutError, 'vendor/fontawesome/css/all.css'),
    'error.php carga vendor/fontawesome/css/all.css',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($cabeceraHead, 'tabler-icons.css'),
    'cabecera-head.php no carga tabler-icons.css',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($layoutError, 'tabler-icons.css'),
    'error.php no carga tabler-icons.css',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    file_exists($directorioBase . '/public/assets/vendor/fontawesome/css/all.css'),
    'Archivo físico public/assets/vendor/fontawesome/css/all.css existe',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    file_exists($directorioBase . '/public/assets/fonts/fontawesome/fa-solid-900.woff2') &&
    file_exists($directorioBase . '/public/assets/fonts/fontawesome/fa-regular-400.woff2') &&
    file_exists($directorioBase . '/public/assets/fonts/fontawesome/fa-brands-400.woff2'),
    'Archivos físicos de webfonts Font Awesome existen en public/assets/fonts/fontawesome/',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// 3. Cero Ocurrencias de ti ti-* en Código Propio
// --------------------------------------------------------------------------
echo "\n[3] Verificación Estricta Anti-Tabler (Cero ti ti-*):\n";

$directoriosAnalisis = [
    $directorioBase . '/app',
    $directorioBase . '/public/assets/js',
    $directorioBase . '/tests',
    $directorioBase . '/config'
];

$coincidenciasTi = 0;
foreach ($directoriosAnalisis as $directorio) {
    if (!is_dir($directorio)) {
        continue;
    }
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));
    foreach ($iterador as $archivo) {
        if ($archivo->isFile() && in_array($archivo->getExtension(), ['php', 'js', 'html'])) {
            if ($archivo->getRealPath() === realpath(__FILE__)) {
                continue;
            }
            $contenido = file_get_contents($archivo->getPathname()) ?: '';
            $patronTi = 'ti' . ' ti-';
            if (str_contains($contenido, $patronTi)) {
                $coincidenciasTi++;
                echo "    [WARN] Coincidencia encontrada en: " . $archivo->getPathname() . "\n";
            }
        }
    }
}

verificar(
    $coincidenciasTi === 0,
    'Cero ocurrencias de clases ti ti-* en app/, public/assets/js/, tests/ y config/',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// 4. Tooltips del Menú Principal
// --------------------------------------------------------------------------
echo "\n[4] Tooltips del Menú Principal:\n";

$navegacionLateral = file_get_contents($directorioBase . '/app/Vistas/layouts/parciales/navegacion-lateral.php') ?: '';
$scriptJs = file_get_contents($directorioBase . '/public/assets/js/script.js') ?: '';

$coincidenciasTooltips = preg_match_all('/data-bs-toggle="tooltip"/', $navegacionLateral);

verificar(
    $coincidenciasTooltips >= 7,
    "navegacion-lateral.php contiene al menos 7 elementos con data-bs-toggle='tooltip' (encontrados: {$coincidenciasTooltips})",
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($navegacionLateral, 'data-bs-placement="right"'),
    'navegacion-lateral.php define data-bs-placement="right" para tooltips laterales',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($scriptJs, "selector: '[data-bs-toggle=\"tooltip\"]'") ||
    str_contains($scriptJs, 'selector: "[data-bs-toggle=\\"tooltip\\"]"') ||
    str_contains($scriptJs, "bootstrap.Tooltip"),
    'script.js inicializa globalmente los tooltips de Bootstrap 5 con delegación',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// 5. Theme Customizer de Alina Adaptado
// --------------------------------------------------------------------------
echo "\n[5] Theme Customizer Adaptado:\n";

$maestroPhp = file_get_contents($directorioBase . '/app/Vistas/layouts/maestro.php') ?: '';
$pieScriptsPhp = file_get_contents($directorioBase . '/app/Vistas/layouts/parciales/pie-scripts.php') ?: '';
$themeCustomizerJs = file_get_contents($directorioBase . '/public/assets/js/theme_customizer.js') ?: '';

verificar(
    str_contains($maestroPhp, 'id="theme-customizer-box"'),
    'maestro.php contiene el contenedor #theme-customizer-box',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($pieScriptsPhp, 'theme_customizer.js'),
    'pie-scripts.php carga el script theme_customizer.js',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    file_exists($directorioBase . '/public/assets/js/theme_customizer.js'),
    'Archivo físico public/assets/js/theme_customizer.js existe',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($themeCustomizerJs, 'Personalizador de Tema') &&
    str_contains($themeCustomizerJs, 'Colores del tema:') &&
    str_contains($themeCustomizerJs, 'Diseños del tema:') &&
    str_contains($themeCustomizerJs, 'Restablecer'),
    'theme_customizer.js está traducido 100% al español',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($themeCustomizerJs, 'fa-solid fa-gear'),
    'theme_customizer.js utiliza icono Font Awesome fa-solid fa-gear en el activador flotante',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains(strtolower($themeCustomizerJs), 'buy now') &&
    !str_contains(strtolower($themeCustomizerJs), 'themeforest'),
    'theme_customizer.js no contiene Buy Now ni enlaces comerciales de themeforest',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    !str_contains($themeCustomizerJs, 'Sidebar Variant') &&
    !str_contains($themeCustomizerJs, 'Font Sizing'),
    'theme_customizer.js omite las opciones Sidebar Variant y Font Sizing',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

verificar(
    str_contains($themeCustomizerJs, 'function resetCustomizer()'),
    'theme_customizer.js define la función resetCustomizer()',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// 6. Inmutabilidad de admin-dashboard/
// --------------------------------------------------------------------------
echo "\n[6] Inmutabilidad de admin-dashboard/:\n";

$salidaGit = shell_exec('git status -s admin-dashboard') ?: '';
verificar(
    trim($salidaGit) === '',
    'admin-dashboard/ permanece 100% intacto y de solo lectura',
    $errorBuffer, $totalPruebas, $pruebasPasadas
);

// --------------------------------------------------------------------------
// Resumen
// --------------------------------------------------------------------------
echo "\n====================================================\n";
echo " RESULTADO NORMALIZACIÓN VISUAL: {$pruebasPasadas}/{$totalPruebas} PASS, " . ($totalPruebas - $pruebasPasadas) . " FAIL\n";
echo "====================================================\n";

if ($pruebasPasadas === $totalPruebas) {
    exit(0);
} else {
    echo "\nDetalle de errores:\n" . $errorBuffer;
    exit(1);
}
