<?php

declare(strict_types=1);

use App\Core\CargadorEntorno;

/**
 * Configuración de Base de Datos para CasaPRO.
 *
 * Lee dinámicamente los parámetros de entorno definidos en .env.
 * No contiene contraseñas ni credenciales en texto plano.
 */
return [
    'driver' => CargadorEntorno::obtener('DB_CONNECTION', 'mysql'),
    'host' => CargadorEntorno::obtener('DB_HOST', '127.0.0.1'),
    'port' => (int) CargadorEntorno::obtener('DB_PORT', 3306),
    'database' => CargadorEntorno::obtener('DB_DATABASE', 'casapro'),
    'username' => CargadorEntorno::obtener('DB_USERNAME', 'root'),
    'password' => CargadorEntorno::obtener('DB_PASSWORD', ''),
    'charset' => CargadorEntorno::obtener('DB_CHARSET', 'utf8mb4'),
    'collation' => CargadorEntorno::obtener('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
