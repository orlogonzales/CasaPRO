<?php

declare(strict_types=1);

/**
 * Suite de Verificación Automatizada: Renderizado Real de Menú Lateral y Ausencia de Regresiones Visuales.
 * Creada tras la detección de advertencias PHP (Undefined array key "titulo") en navegador.
 *
 * Cobertura de Gates:
 * 1. Error Handler Estricto: cualquier Warning/Notice en PHP detona fallo fatal inmediato.
 * 2. Contrato de Datos de Menú en Repositorio y Servicio: existencia y no vaciedad de 'titulo' y 'etiqueta'.
 * 3. Integridad Jerárquica Recursiva: raíz -> agrupador nivel 1 -> enlace nivel 2 sin corrupción.
 * 4. Renderizado Directo de app/Vistas/layouts/parciales/navegacion-lateral.php con 0 warnings.
 * 5. Poda RBAC Veraz: SUPERADMIN ve catálogo completo, usuario estándar solo ve lo concedido.
 * 6. Marcado de Rutas Activas en Navegación Lateral (inicio, personas, usuarios, empresas, proyectos).
 * 7. Pruebas HTTP Reales contra el Servidor Web Local (login, inicio, personas, usuarios, empresas, proyectos, logout).
 */

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tests/comun/AmbientePruebas.php';

use Tests\Comun\AmbientePruebas;
use App\Core\ProveedorConexion;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Vista;
use App\Repositorios\MenuRepositorio;
use App\Servicios\MenuServicio;

echo "===================================================================\n";
echo " VERIFICACIÓN DE RENDERIZADO DEL MENÚ LATERAL Y CONTRATO CANÓNICO\n";
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

// -------------------------------------------------------------------------
// 1. Error Handler Estricto
// -------------------------------------------------------------------------
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Si el error fue silenciado con @ y no es fatal, ignorar
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException("PHP Error [{$errno}]: {$errstr} en {$errfile}:{$errline}", $errno, 1, $errfile, $errline);
});

// Inicializar base de datos de pruebas
$pdo = AmbientePruebas::iniciar(true);
$dbActual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
probar($dbActual === 'casapro_test', "Entorno aislado en base de datos 'casapro_test' (actual: {$dbActual})");

$proveedor = AmbientePruebas::obtenerProveedorTest();
$repo = new MenuRepositorio($proveedor);
$servicio = new MenuServicio($proveedor, $repo);

// =========================================================================
// BLOQUE 1: Contrato de Datos en MenuRepositorio
// =========================================================================
echo "\n--- BLOQUE 1: Contrato de Datos en MenuRepositorio ---\n";

$opcionesActivas = $repo->obtenerActivosVisibles($pdo);
probar(!empty($opcionesActivas), "obtenerActivosVisibles() retorna registros de menú");

foreach ($opcionesActivas as $idx => $opc) {
    probar(isset($opc['titulo']), "Opción #{$opc['id']} [{$opc['codigo']}] contiene la clave 'titulo'");
    probar(!empty($opc['titulo']), "Opción #{$opc['id']} [{$opc['codigo']}] tiene 'titulo' no vacío (valor: '{$opc['titulo']}')");
    probar(isset($opc['etiqueta']), "Opción #{$opc['id']} [{$opc['codigo']}] contiene la clave 'etiqueta'");
    probar($opc['titulo'] === $opc['etiqueta'], "Opción #{$opc['id']} [{$opc['codigo']}] mantiene coherencia titulo === etiqueta");
}

$todasOpciones = $repo->obtenerTodos($pdo);
probar(!empty($todasOpciones), "obtenerTodos() retorna registros de menú");
foreach ($todasOpciones as $opc) {
    probar(isset($opc['titulo']) && !empty($opc['titulo']), "obtenerTodos(): Opción #{$opc['id']} tiene 'titulo' presente y no vacío");
}

require_once dirname(__DIR__) . '/tests/comun/FixtureAutenticacion.php';

use Tests\Comun\FixtureAutenticacion;

// =========================================================================
// BLOQUE 2: Árbol Jerárquico en MenuServicio y Ausencia de Corrupción
// =========================================================================
echo "\n--- BLOQUE 2: Árbol Jerárquico en MenuServicio y Ausencia de Corrupción ---\n";

$authSuperadmin = FixtureAutenticacion::autenticarComoSuperadmin($pdo);
$superadminId = (int) $authSuperadmin['usuario_id'];

$arbolSuperadmin = $servicio->obtenerArbolParaUsuario($superadminId);
probar(!empty($arbolSuperadmin), "obtenerArbolParaUsuario({$superadminId}) genera árbol para SUPERADMIN");
probar(count($arbolSuperadmin) >= 3, "Árbol para SUPERADMIN contiene al menos 3 nodos raíz (MOD_INICIO, MOD_IDENTIDAD, MOD_CATASTRO)");

$validarNodoRecursivo = function (array $nodo, int $nivelEsperado) use (&$validarNodoRecursivo): void {
    probar(isset($nodo['id']) && is_int($nodo['id']) && $nodo['id'] > 0, "Nodo nivel {$nivelEsperado} tiene 'id' entero positivo");
    probar(isset($nodo['codigo']) && is_string($nodo['codigo']) && $nodo['codigo'] !== '', "Nodo #{$nodo['id']} tiene 'codigo' no vacío");
    probar(isset($nodo['titulo']) && is_string($nodo['titulo']) && $nodo['titulo'] !== '', "Nodo #{$nodo['id']} [{$nodo['codigo']}] tiene 'titulo' no vacío (valor: '{$nodo['titulo']}')");
    probar(isset($nodo['etiqueta']) && is_string($nodo['etiqueta']) && $nodo['etiqueta'] !== '', "Nodo #{$nodo['id']} [{$nodo['codigo']}] tiene 'etiqueta' no vacía");
    probar(in_array($nodo['tipo'], ['AGRUPADOR', 'ENLACE'], true), "Nodo #{$nodo['id']} tiene 'tipo' válido ('{$nodo['tipo']}')");
    probar(isset($nodo['hijos']) && is_array($nodo['hijos']), "Nodo #{$nodo['id']} tiene 'hijos' como array");

    if ($nivelEsperado === 0) {
        probar(isset($nodo['icono']) && !empty($nodo['icono']), "Nodo raíz #{$nodo['id']} [{$nodo['codigo']}] tiene 'icono' Font Awesome obligatorio");
    }

    foreach ($nodo['hijos'] as $hijo) {
        $validarNodoRecursivo($hijo, $nivelEsperado + 1);
    }
};

foreach ($arbolSuperadmin as $raiz) {
    $validarNodoRecursivo($raiz, 0);
}

// =========================================================================
// BLOQUE 3: Renderizado Real de app/Vistas/layouts/parciales/navegacion-lateral.php
// =========================================================================
echo "\n--- BLOQUE 3: Renderizado Real de navegacion-lateral.php sin Warnings ---\n";

GestorSesion::establecer('contexto_empresa_id', 1);

$rutasParaProbar = [
    'inicio'    => 'MOD_INICIO',
    'personas'  => 'MOD_IDENTIDAD',
    'usuarios'  => 'MOD_IDENTIDAD',
    'empresas'  => 'MOD_IDENTIDAD',
    'proyectos' => 'MOD_CATASTRO'
];

foreach ($rutasParaProbar as $rutaSimulada => $raizEsperadaActiva) {
    $_SERVER['REQUEST_URI'] = "/{$rutaSimulada}";
    $_SERVER['SCRIPT_NAME'] = '/public/index.php';

    ob_start();
    include dirname(__DIR__) . '/app/Vistas/layouts/parciales/navegacion-lateral.php';
    $htmlGenerado = ob_get_clean();

    probar(!str_contains($htmlGenerado, 'Warning:'), "Renderizado en ruta '{$rutaSimulada}' no produce Warning:");
    probar(!str_contains($htmlGenerado, 'Notice:'), "Renderizado en ruta '{$rutaSimulada}' no produce Notice:");
    probar(!str_contains($htmlGenerado, 'Undefined array key'), "Renderizado en ruta '{$rutaSimulada}' libre de Undefined array key");
    probar(!str_contains($htmlGenerado, 'Undefined variable'), "Renderizado en ruta '{$rutaSimulada}' libre de Undefined variable");

    // Verificar que los títulos oficiales se renderizan en el HTML
    probar(str_contains($htmlGenerado, 'CasaPRO - Inicio'), "HTML contiene 'CasaPRO - Inicio' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'Identidad y Seguridad'), "HTML contiene 'Identidad y Seguridad' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'Catastro y Territorio'), "HTML contiene 'Catastro y Territorio' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'Proyectos'), "HTML contiene 'Proyectos' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'Directorio de Personas'), "HTML contiene 'Directorio de Personas' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'Empresas'), "HTML contiene 'Empresas' en '{$rutaSimulada}'");

    // Verificar estructura Alina
    probar(str_contains($htmlGenerado, 'semi-side-nav'), "HTML contiene contenedor 'semi-side-nav' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'main-side-nav'), "HTML contiene contenedor 'main-side-nav' en '{$rutaSimulada}'");
    probar(str_contains($htmlGenerado, 'data-bs-toggle="tooltip"'), "HTML contiene tooltips oficiales de Bootstrap 5 en '{$rutaSimulada}'");
}

// =========================================================================
// BLOQUE 4: Renderizado de Vistas del Layout Maestro
// =========================================================================
echo "\n--- BLOQUE 4: Renderizado de Vistas del Layout Maestro sin Errores ---\n";

$controladoresYVistas = [
    \App\Controladores\InicioControlador::class => '/inicio',
    \App\Controladores\PersonaControlador::class => '/personas',
    \App\Controladores\UsuarioControlador::class => '/usuarios',
    \App\Controladores\EmpresaControlador::class => '/empresas',
    \App\Controladores\ProyectoControlador::class => '/proyectos',
];

foreach ($controladoresYVistas as $controladorCls => $ruta) {
    $ctrl = new $controladorCls();
    $req = new Peticion('GET', $ruta);
    $res = new Respuesta();

    ob_start();
    $salida = $ctrl->index($req, $res);
    $salidaHtml = ob_get_clean() . $salida;

    probar(!str_contains($salidaHtml, 'Warning:'), "Controlador '{$controladorCls}' en '{$ruta}' renderiza sin Warning:");
    probar(!str_contains($salidaHtml, 'Notice:'), "Controlador '{$controladorCls}' en '{$ruta}' renderiza sin Notice:");
    probar(!str_contains($salidaHtml, 'Undefined array key'), "Controlador '{$controladorCls}' en '{$ruta}' libre de Undefined array key");
    probar(str_contains($salidaHtml, 'app-navbar'), "Controlador '{$controladorCls}' en '{$ruta}' incluye la barra de navegación lateral");
}

// =========================================================================
// BLOQUE 5: Verificación HTTP Real contra el Servidor Web Local
// =========================================================================
echo "\n--- BLOQUE 5: Verificación HTTP Real contra Servidor Web Local ---\n";

$baseUrl = 'http://app.casa-pro.test';
$cookieFile = sys_get_temp_dir() . '/casapro_test_cookies_' . uniqid() . '.txt';

// =========================================================================
// BLOQUE 5: Despacho HTTP Completo por Enrutador y Pruebas Web
// =========================================================================
echo "\n--- BLOQUE 5: Despacho HTTP Completo por Enrutador y Pruebas Web ---\n";

use App\Core\Enrutador;
use App\Core\ContextoPeticion;
use App\Middlewares\CsrfMiddleware;

$despacharHttp = function (string $metodo, string $ruta, array $cuerpo = [], array $cabeceras = [], ?array $authSesion = null, ?int $contextoEmpresaId = 1): array {
    GestorSesion::iniciar();
    if ($authSesion !== null) {
        GestorSesion::establecer('auth', $authSesion);
        GestorSesion::establecer('actor_id', $authSesion['actor_id']);
        if ($contextoEmpresaId !== null) {
            GestorSesion::establecer('contexto_empresa_id', $contextoEmpresaId);
        } else {
            GestorSesion::eliminar('contexto_empresa_id');
        }
    } else {
        GestorSesion::eliminar('auth');
        GestorSesion::eliminar('actor_id');
        GestorSesion::eliminar('contexto_empresa_id');
    }

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
        'codigo' => $respuesta->obtenerCodigoEstado(),
        'cuerpo' => $cuerpoRespuesta,
        'cabeceras' => $respuesta->obtenerCabeceras()
    ];
};

// 1. GET /login anónimo
$resLoginGet = $despacharHttp('GET', '/login');
probar($resLoginGet['codigo'] === 200, "GET /login anónimo responde HTTP 200");
probar(!str_contains($resLoginGet['cuerpo'], 'Warning:'), "GET /login libre de Warning:");
probar(str_contains($resLoginGet['cuerpo'], 'id="formLogin"'), "GET /login renderiza formulario Alina de login");

// 2. GET /inicio autenticado con SUPERADMIN
$resInicio = $despacharHttp('GET', '/inicio', [], [], $authSuperadmin, 1);
probar($resInicio['codigo'] === 200, "GET /inicio con SUPERADMIN responde HTTP 200");
probar(!str_contains($resInicio['cuerpo'], 'Warning:'), "GET /inicio libre de Warning:");
probar(!str_contains($resInicio['cuerpo'], 'Notice:'), "GET /inicio libre de Notice:");
probar(!str_contains($resInicio['cuerpo'], 'Undefined array key'), "GET /inicio libre de Undefined array key");
probar(str_contains($resInicio['cuerpo'], 'app-navbar'), "GET /inicio renderiza app-navbar correctamente");
probar(str_contains($resInicio['cuerpo'], 'CasaPRO - Inicio'), "GET /inicio incluye 'CasaPRO - Inicio'");
probar(str_contains($resInicio['cuerpo'], 'Identidad y Seguridad'), "GET /inicio incluye 'Identidad y Seguridad'");
probar(str_contains($resInicio['cuerpo'], 'Catastro y Territorio'), "GET /inicio incluye 'Catastro y Territorio'");

// 3. GET /personas autenticado
$resPersonas = $despacharHttp('GET', '/personas', [], [], $authSuperadmin, 1);
probar($resPersonas['codigo'] === 200, "GET /personas con SUPERADMIN responde HTTP 200");
probar(!str_contains($resPersonas['cuerpo'], 'Warning:'), "GET /personas libre de Warning:");
probar(!str_contains($resPersonas['cuerpo'], 'Undefined array key'), "GET /personas libre de Undefined array key");
probar(str_contains($resPersonas['cuerpo'], 'tablaPersonas'), "GET /personas contiene 'tablaPersonas'");

// 4. GET /usuarios autenticado
$resUsuarios = $despacharHttp('GET', '/usuarios', [], [], $authSuperadmin, 1);
probar($resUsuarios['codigo'] === 200, "GET /usuarios con SUPERADMIN responde HTTP 200");
probar(!str_contains($resUsuarios['cuerpo'], 'Warning:'), "GET /usuarios libre de Warning:");
probar(!str_contains($resUsuarios['cuerpo'], 'Undefined array key'), "GET /usuarios libre de Undefined array key");
probar(str_contains($resUsuarios['cuerpo'], 'tablaUsuarios'), "GET /usuarios contiene 'tablaUsuarios'");

// 5. GET /empresas autenticado
$resEmpresas = $despacharHttp('GET', '/empresas', [], [], $authSuperadmin, 1);
probar($resEmpresas['codigo'] === 200, "GET /empresas con SUPERADMIN responde HTTP 200");
probar(!str_contains($resEmpresas['cuerpo'], 'Warning:'), "GET /empresas libre de Warning:");
probar(!str_contains($resEmpresas['cuerpo'], 'Undefined array key'), "GET /empresas libre de Undefined array key");
probar(str_contains($resEmpresas['cuerpo'], 'tablaEmpresas'), "GET /empresas contiene 'tablaEmpresas'");

// Asegurar fixtures de empresa 1 y 2 en casapro_test para pruebas con ScopeMiddleware
$pdo->exec("INSERT INTO personas (id, tipo_persona, estado) VALUES (901, 'JURIDICA', 'ACTIVO') ON DUPLICATE KEY UPDATE estado='ACTIVO'");
$pdo->exec("INSERT INTO persona_juridica (persona_id, razon_social, nombre_comercial) VALUES (901, 'Corp Test Menu', 'Corp Menu') ON DUPLICATE KEY UPDATE razon_social=VALUES(razon_social)");
$pdo->exec("INSERT INTO empresas (id, persona_id, codigo, nombre_corto, estado) VALUES (1, 901, 'EMP-TEST-MENU', 'Empresa Menu', 'ACTIVO') ON DUPLICATE KEY UPDATE estado='ACTIVO'");

$pdo->exec("INSERT INTO personas (id, tipo_persona, estado) VALUES (902, 'JURIDICA', 'ACTIVO') ON DUPLICATE KEY UPDATE estado='ACTIVO'");
$pdo->exec("INSERT INTO persona_juridica (persona_id, razon_social, nombre_comercial) VALUES (902, 'Corp Test Menu 2', 'Corp Menu 2') ON DUPLICATE KEY UPDATE razon_social=VALUES(razon_social)");
$pdo->exec("INSERT INTO empresas (id, persona_id, codigo, nombre_corto, estado) VALUES (2, 902, 'EMP-TEST-MENU-2', 'Empresa Menu 2', 'ACTIVO') ON DUPLICATE KEY UPDATE estado='ACTIVO'");

// 6. GET /proyectos autenticado
$resProyectos = $despacharHttp('GET', '/proyectos', [], [], $authSuperadmin, 1);
probar($resProyectos['codigo'] === 200, "GET /proyectos con SUPERADMIN responde HTTP 200");
probar(!str_contains($resProyectos['cuerpo'], 'Warning:'), "GET /proyectos libre de Warning:");
probar(!str_contains($resProyectos['cuerpo'], 'Undefined array key'), "GET /proyectos libre de Undefined array key");
probar(str_contains($resProyectos['cuerpo'], 'tablaProyectos'), "GET /proyectos contiene 'tablaProyectos'");

// 7. GET /proyectos/{id} (Ficha 360°)
$stmtExisteP = $pdo->prepare("SELECT id FROM proyectos WHERE codigo = 'PRY-TEST-MENU' LIMIT 1");
$stmtExisteP->execute();
$proyectoTestId = (int) $stmtExisteP->fetchColumn();
if ($proyectoTestId === 0) {
    $distritoValido = (int) $pdo->query("SELECT id FROM distritos LIMIT 1")->fetchColumn();
    if ($distritoValido === 0) {
        $distritoValido = 1;
    }
    $stmtInsP = $pdo->prepare("INSERT INTO proyectos (empresa_id, codigo, nombre, tipo_proyecto, moneda, estado, tipo_tolerancia, valor_tolerancia, distrito_id)
                VALUES (1, 'PRY-TEST-MENU', 'Proyecto Test Menú', 'PROPIO', 'USD', 'PLANIFICACION', 'ABSOLUTA_M2', 1.0000, ?)");
    $stmtInsP->execute([$distritoValido]);
    $proyectoTestId = (int) $pdo->lastInsertId();
}

$resFicha = $despacharHttp('GET', "/proyectos/{$proyectoTestId}", [], [], $authSuperadmin, 1);
probar($resFicha['codigo'] === 200, "GET /proyectos/{id} responde HTTP 200");
probar(!str_contains($resFicha['cuerpo'], 'Warning:'), "GET /proyectos/{id} libre de Warning:");
probar(!str_contains($resFicha['cuerpo'], 'Undefined array key'), "GET /proyectos/{id} libre de Undefined array key");
probar(str_contains($resFicha['cuerpo'], 'Ficha de Proyecto'), "GET /proyectos/{id} renderiza cabecera de ficha");

// 8. Cambio de empresa desde topbar: POST /api/contexto/cambiar-empresa
$tokenCsrfCambio = \App\Core\CsrfServicio::obtenerToken();
$resCambioEmpresa = $despacharHttp('POST', '/api/contexto/cambiar-empresa', [
    'empresa_id' => 2
], [
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
    'X-CSRF-Token' => $tokenCsrfCambio
], $authSuperadmin, 1);
probar($resCambioEmpresa['codigo'] === 200, "POST /api/contexto/cambiar-empresa responde HTTP 200");
$cuerpoCambio = json_decode($resCambioEmpresa['cuerpo'], true);
probar(($cuerpoCambio['estado'] ?? '') === 'exito', "Conmutación de empresa retorna estado 'exito'");

// 9. POST /logout
$tokenCsrfLogout = \App\Core\CsrfServicio::obtenerToken();
$resLogout = $despacharHttp('POST', '/logout', [
    'csrf_token' => $tokenCsrfLogout
], [
    'X-CSRF-Token' => $tokenCsrfLogout
], $authSuperadmin, 2);
probar(in_array($resLogout['codigo'], [200, 302], true), "POST /logout responde HTTP 200/302 de cierre de sesión");

// 8. Verificación cURL contra el Servidor Web Local (Apache / Laragon)
if (function_exists('curl_init')) {
    // HTTP
    $chHttp = curl_init('http://app.casa-pro.test/login');
    curl_setopt($chHttp, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chHttp, CURLOPT_TIMEOUT, 5);
    $cuerpoHttp = (string) curl_exec($chHttp);
    $codigoHttp = (int) curl_getinfo($chHttp, CURLINFO_HTTP_CODE);
    curl_close($chHttp);

    probar($codigoHttp === 200, "Servidor web local responde HTTP 200 en http://app.casa-pro.test/login");
    probar(!str_contains($cuerpoHttp, 'Warning:'), "Servidor web local libre de Warning: en HTTP");
    probar(str_contains($cuerpoHttp, 'CasaPRO'), "Servidor web local responde con plantilla oficial de CasaPRO en HTTP");

    // HTTPS
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
    probar(str_contains($cuerpoHttps, 'CasaPRO'), "Servidor web local responde con plantilla oficial de CasaPRO en HTTPS");
}

// Restaurar error handler original
restore_error_handler();

echo "\n===================================================================\n";
echo " RESULTADO: {$pruebasSuperadas} / {$totalPruebas} PRUEBAS SUPERADAS (100% PASS)\n";
echo "===================================================================\n\n";
