<?php

declare(strict_types=1);

/**
 * Punto de entrada raíz para entornos con DocumentRoot en el directorio base del proyecto.
 * Redirige la ejecución hacia el Front Controller de producción en public/index.php.
 */
require __DIR__ . '/public/index.php';
