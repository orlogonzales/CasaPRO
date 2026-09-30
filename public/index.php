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

// Autocargador Composer para paquetes externos (como vlucas/phpdotenv)
if (file_exists(CASAPRO_RAIZ . '/vendor/autoload.php')) {
    require CASAPRO_RAIZ . '/vendor/autoload.php';
}

// Autocargador PSR-4 nativo de respaldo para el namespace App\
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
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Enrutador;
use App\Core\GestorSesion;
use App\Core\ContextoPeticion;
use App\Middlewares\CsrfMiddleware;

// Cargar variables de entorno del sistema
CargadorEntorno::cargar(CASAPRO_RAIZ);

// Iniciar sesión segura HTTP
GestorSesion::iniciar();

// Inicializar ciclo de vida de la petición y contexto de seguridad
$peticion = new Peticion();
$contexto = ContextoPeticion::crearDesdeEntorno($peticion);
$respuesta = new Respuesta();

// Inyectar cabeceras transversales de seguridad HTTP y trazabilidad
$respuesta->agregarCabecera('X-Content-Type-Options', 'nosniff');
$respuesta->agregarCabecera('X-Frame-Options', 'SAMEORIGIN');
$respuesta->agregarCabecera('X-XSS-Protection', '1; mode=block');
$respuesta->agregarCabecera('Referrer-Policy', 'strict-origin-when-cross-origin');
$respuesta->agregarCabecera('X-Correlation-ID', $contexto->obtenerIdCorrelacion());

// Inicializar enrutador y middlewares globales
$enrutador = new Enrutador();
$enrutador->agregarMiddlewareGlobal(CsrfMiddleware::class);

// Cargar definición de rutas
$configuradorRutas = require CASAPRO_RAIZ . '/config/rutas.php';
$configuradorRutas($enrutador);

// Despachar la petición de forma controlada y segura
try {
    $enrutador->despachar($peticion, $respuesta, $contexto);
} catch (\App\Core\ExcepcionHttp $excepcionHttp) {
    \App\Controladores\ErrorControlador::responder(
        $excepcionHttp->obtenerCodigoEstado(),
        $peticion,
        $respuesta,
        $excepcionHttp->getMessage(),
        $contexto->obtenerIdCorrelacion()
    );
} catch (\Throwable $excepcion) {
    $idCorrelacion = $contexto->obtenerIdCorrelacion();

    // Registrar contexto completo en log interno del servidor
    error_log(sprintf(
        "[%s] [%s] %s: %s en %s:%d\nTraza:\n%s",
        date('Y-m-d H:i:s'),
        $idCorrelacion,
        get_class($excepcion),
        $excepcion->getMessage(),
        $excepcion->getFile(),
        $excepcion->getLine(),
        $excepcion->getTraceAsString()
    ));

    $codigo = ($excepcion->getCode() >= 400 && $excepcion->getCode() <= 599)
        ? (int) $excepcion->getCode()
        : 500;

    \App\Controladores\ErrorControlador::responder(
        $codigo,
        $peticion,
        $respuesta,
        'Ocurrió un error inesperado al procesar la solicitud.',
        $idCorrelacion
    );
}
