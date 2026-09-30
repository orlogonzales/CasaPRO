<?php

declare(strict_types=1);

/**
 * Punto de entrada único (Front Controller) para CasaPRO.
 */

// Definir constantes base si no están definidas
defined('CASAPRO_INICIO') || define('CASAPRO_INICIO', microtime(true));
defined('CASAPRO_RAIZ') || define('CASAPRO_RAIZ', dirname(__DIR__));

// Configuración de zona horaria y reporte de errores
date_default_timezone_set('America/Lima');
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autocargador PSR-4 nativo para el namespace App\
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

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Enrutador;

// Inicializar ciclo de vida de la petición
$peticion = new Peticion();
$respuesta = new Respuesta();
$enrutador = new Enrutador();

// Cargar definición de rutas
$configuradorRutas = require CASAPRO_RAIZ . '/config/rutas.php';
$configuradorRutas($enrutador);

// Despachar la petición
try {
    $enrutador->despachar($peticion, $respuesta);
} catch (\Throwable $excepcion) {
    http_response_code(500);
    if ($peticion->esAjax()) {
        $respuesta->json([
            'estado' => 'error',
            'codigo' => 500,
            'mensaje' => 'Error interno del servidor.',
            'detalle' => $excepcion->getMessage()
        ], 500);
    } else {
        echo '<h1>Error Interno del Servidor (500)</h1>';
        echo '<p>' . htmlspecialchars($excepcion->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<pre>' . htmlspecialchars($excepcion->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
}
