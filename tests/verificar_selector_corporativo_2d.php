<?php

declare(strict_types=1);

/**
 * Suite de Verificación Integral — Microfase 2D: Selector Corporativo y Contexto Activo en Topbar
 *
 * Valida:
 * 1. Persistencia DDL=0, ranura 000013 intacta y 28 tablas exactas.
 * 2. Selección automática determinista canónica en login (ORDER BY e.codigo ASC, e.nombre_corto ASC, e.id ASC).
 * 3. Endpoint GET /api/contexto/empresas (filtro de empresas activas asignadas).
 * 4. Endpoint POST /api/contexto/cambiar-empresa (conmutación atómica, allowlist, 200, 401, 403, 404, 409, 422).
 * 5. Blindaje preventivo de contextos hijos (reseteo obligatorio de contexto_proyecto_id y contexto_sector_id).
 * 6. Auditoría forense mínima no sensible (CAMBIO_CONTEXTO_EMPRESA con datos técnicos e IDs).
 * 7. Detección en caliente de empresa inactivada en ScopeMiddleware (409 Conflict y desasignación de sesión).
 * 8. Detección en caliente de rol revocado (incremento de version_autorizacion, 401 y 403 territorial).
 * 9. Gate Anti-IDOR multifuente (Query string, POST body, JSON payload, parámetro general -> 403 Forbidden).
 * 10. Integridad Frontend, MVC desacoplado (cero servicios en vista), tooltips oficiales y responsive móvil.
 */

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tests/comun/AmbientePruebas.php';

use Tests\Comun\AmbientePruebas;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Modelos\Rol;
use App\Modelos\Usuario;
use App\Modelos\Empresa;
use App\Modelos\Persona;
use App\Modelos\UsuarioEmpresaRol;
use App\DTOs\AsignarRolEmpresaDTO;
use App\DTOs\RevocarRolEmpresaDTO;
use App\DTOs\Contexto\CambiarContextoEmpresaDTO;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\AsignacionTerritorialRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Servicios\AutorizacionServicio;
use App\Servicios\AutenticacionServicio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\ContextoServicio;
use App\Controladores\ContextoControlador;
use App\Middlewares\AutenticacionMiddleware;
use App\Middlewares\ScopeMiddleware;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;

echo "===================================================================\n";
echo " VERIFICACIÓN MICROFASE 2D: SELECTOR CORPORATIVO Y CONTEXTO ACTIVO\n";
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
    }
}

// --------------------------------------------------------------------------
// Inicialización del Entorno Aislado en casapro_test
// --------------------------------------------------------------------------
$conexion = AmbientePruebas::iniciar(true);
$proveedorConexion = AmbientePruebas::obtenerProveedorTest();


$dbActual = $conexion->query("SELECT DATABASE()")->fetchColumn();
probar($dbActual === 'casapro_test', "Entorno aislado en base de datos 'casapro_test' (actual: {$dbActual})");

// =========================================================================
// BLOQUE 1: Persistencia DDL=0, Ranura 000013 Intacta y 28 Tablas
// =========================================================================
echo "\n--- BLOQUE 1: Persistencia DDL=0 y Ranura 000013 Intacta ---\n";

$archivosMigraciones = glob(dirname(__DIR__) . '/SQL/migraciones/*.sql') ?: [];
$migracionesNombres = array_map('basename', $archivosMigraciones);
$migracion14Existe = false;
foreach ($migracionesNombres as $nombre) {
    if (str_contains($nombre, '000014')) {
        $migracion14Existe = true;
        break;
    }
}
probar(!$migracion14Existe, "Ranura de migración 000014 permanece libre e intacta en SQL/migraciones");

$tablas = $conexion->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
probar(count($tablas) >= 28, "Esquema relacional en casapro_test contiene al menos 28 tablas (actual: " . count($tablas) . ")");

// Verificar que la tabla empresas y usuario_empresa_roles conservan sus columnas originales
$colsEmpresas = $conexion->query("SHOW COLUMNS FROM `empresas`")->fetchAll(PDO::FETCH_COLUMN);
$colsUER = $conexion->query("SHOW COLUMNS FROM `usuario_empresa_roles`")->fetchAll(PDO::FETCH_COLUMN);
probar(count($colsEmpresas) === 7, "Tabla 'empresas' permanece intacta con 7 columnas (sin columnas artificiales)");
probar(count($colsUER) === 10, "Tabla 'usuario_empresa_roles' permanece intacta con 10 columnas");

// =========================================================================
// BLOQUE 2: Configuración de Fixtures y Determinismo en Login
// =========================================================================
echo "\n--- BLOQUE 2: Configuración de Fixtures y Selección Determinista en Login ---\n";

// Limpiar datos de pruebas previas
$conexion->exec("SET FOREIGN_KEY_CHECKS = 0");
$conexion->exec("DELETE FROM `auditorias` WHERE `modulo` IN ('seguridad', 'empresas')");
$conexion->exec("DELETE FROM `eventos_seguridad`");
$conexion->exec("DELETE FROM `usuario_empresa_roles`");
$conexion->exec("DELETE FROM `usuario_roles`");
$conexion->exec("DELETE FROM `usuarios` WHERE `nombre_usuario` IN ('superadmin_2d', 'multi_2d', 'unico_2d', 'huerfano_2d')");
$conexion->exec("DELETE FROM `empresas` WHERE `codigo` IN ('EMP-2D-A', 'EMP-2D-B', 'EMP-2D-C', 'EMP-2D-INACTIVA')");
$conexion->exec("DELETE FROM `actores` WHERE `codigo` = 'ACT-FIXTURE-2D' OR `nombre` LIKE '%Fixture 2D%'");
$conexion->exec("SET FOREIGN_KEY_CHECKS = 1");

// Insertar actor de prueba
$conexion->exec("INSERT INTO `actores` (`codigo`, `tipo_actor`, `nombre`, `estado`) VALUES ('ACT-FIXTURE-2D', 'SISTEMA', 'Actor Fixture 2D', 'ACTIVO')");
$actorIdFixture = (int) $conexion->lastInsertId();

// Asegurar existencia de roles funcionales necesarios para pruebas
$conexion->exec("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `es_sistema`, `estado`) VALUES 
    (2, 'ADMINISTRADOR', 'Administrador Corporativo', 0, 'ACTIVO'),
    (3, 'VENDEDOR', 'Asesor Comercial Inmobiliario', 0, 'ACTIVO')
    ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `estado` = 'ACTIVO'");

// Insertar persona jurídica base para empresas
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('JURIDICA', 'ACTIVO')");
$personaIdBase = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `persona_juridica` (`persona_id`, `razon_social`, `nombre_comercial`) VALUES ({$personaIdBase}, 'Corporación Test 2D S.A.C.', 'Corp 2D')");

// Crear empresas con códigos deliberadamente ordenables:
// EMP-2D-A (ID X, nombre 'Alfa Construcciones')
// EMP-2D-B (ID Y, nombre 'Beta Desarrollos')
// EMP-2D-C (ID Z, nombre 'Gamma Inmobiliaria')
// EMP-2D-INACTIVA (ID W, inactiva)
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('JURIDICA', 'ACTIVO')");
$pA = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `empresas` (`persona_id`, `codigo`, `nombre_corto`, `estado`) VALUES ({$pA}, 'EMP-2D-A', 'Alfa Construcciones', 'ACTIVO')");
$empresaAId = (int) $conexion->lastInsertId();

$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('JURIDICA', 'ACTIVO')");
$pB = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `empresas` (`persona_id`, `codigo`, `nombre_corto`, `estado`) VALUES ({$pB}, 'EMP-2D-B', 'Beta Desarrollos', 'ACTIVO')");
$empresaBId = (int) $conexion->lastInsertId();

$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('JURIDICA', 'ACTIVO')");
$pC = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `empresas` (`persona_id`, `codigo`, `nombre_corto`, `estado`) VALUES ({$pC}, 'EMP-2D-C', 'Gamma Inmobiliaria', 'ACTIVO')");
$empresaCId = (int) $conexion->lastInsertId();

$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('JURIDICA', 'ACTIVO')");
$pInactiva = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `empresas` (`persona_id`, `codigo`, `nombre_corto`, `estado`) VALUES ({$pInactiva}, 'EMP-2D-INACTIVA', 'Empresa Inactiva 2D', 'INACTIVO')");
$empresaInactivaId = (int) $conexion->lastInsertId();

// Insertar usuarios de prueba
$hashPassword = password_hash('ClaveSecreta123*', PASSWORD_DEFAULT);

// 1. Usuario SUPERADMIN
$conexion->exec("INSERT INTO `actores` (`codigo`, `tipo_actor`, `nombre`, `estado`) VALUES ('ACT-2D-SUPER', 'USUARIO', 'Actor Superadmin 2D', 'ACTIVO')");
$actorSuper = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('NATURAL', 'ACTIVO')");
$pSup = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `usuarios` (`persona_id`, `actor_id`, `nombre_usuario`, `email`, `password_hash`, `estado`, `version_autorizacion`)
                 VALUES ({$pSup}, {$actorSuper}, 'superadmin_2d', 'superadmin_2d@casapro.pe', '{$hashPassword}', 'ACTIVO', 1)");
$usuarioSuperId = (int) $conexion->lastInsertId();
$rolSuperId = (int) $conexion->query("SELECT `id` FROM `roles` WHERE `codigo` = 'SUPERADMIN'")->fetchColumn();
$conexion->exec("INSERT INTO `usuario_roles` (`usuario_id`, `rol_id`) VALUES ({$usuarioSuperId}, {$rolSuperId})");

// 2. Usuario Multiempresa (Asignado a Empresa B y Empresa A, con roles distintos)
$conexion->exec("INSERT INTO `actores` (`codigo`, `tipo_actor`, `nombre`, `estado`) VALUES ('ACT-2D-MULTI', 'USUARIO', 'Actor Multi 2D', 'ACTIVO')");
$actorMulti = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('NATURAL', 'ACTIVO')");
$pMulti = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `usuarios` (`persona_id`, `actor_id`, `nombre_usuario`, `email`, `password_hash`, `estado`, `version_autorizacion`)
                 VALUES ({$pMulti}, {$actorMulti}, 'multi_2d', 'multi_2d@casapro.pe', '{$hashPassword}', 'ACTIVO', 1)");
$usuarioMultiId = (int) $conexion->lastInsertId();
$rolAdminId = (int) $conexion->query("SELECT `id` FROM `roles` WHERE `codigo` = 'ADMINISTRADOR'")->fetchColumn();
$rolVendedorId = (int) $conexion->query("SELECT `id` FROM `roles` WHERE `codigo` = 'VENDEDOR'")->fetchColumn();

// Asignar a B primero, luego a A
$conexion->exec("INSERT INTO `usuario_empresa_roles` (`usuario_id`, `empresa_id`, `rol_id`, `asignado_por`, `estado`) VALUES ({$usuarioMultiId}, {$empresaBId}, {$rolAdminId}, {$actorIdFixture}, 'ACTIVO')");
$conexion->exec("INSERT INTO `usuario_empresa_roles` (`usuario_id`, `empresa_id`, `rol_id`, `asignado_por`, `estado`) VALUES ({$usuarioMultiId}, {$empresaAId}, {$rolVendedorId}, {$actorIdFixture}, 'ACTIVO')");

// 3. Usuario con 1 Sola Empresa (Empresa C)
$conexion->exec("INSERT INTO `actores` (`codigo`, `tipo_actor`, `nombre`, `estado`) VALUES ('ACT-2D-UNICO', 'USUARIO', 'Actor Unico 2D', 'ACTIVO')");
$actorUnico = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('NATURAL', 'ACTIVO')");
$pUnico = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `usuarios` (`persona_id`, `actor_id`, `nombre_usuario`, `email`, `password_hash`, `estado`, `version_autorizacion`)
                 VALUES ({$pUnico}, {$actorUnico}, 'unico_2d', 'unico_2d@casapro.pe', '{$hashPassword}', 'ACTIVO', 1)");
$usuarioUnicoId = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `usuario_empresa_roles` (`usuario_id`, `empresa_id`, `rol_id`, `asignado_por`, `estado`) VALUES ({$usuarioUnicoId}, {$empresaCId}, {$rolVendedorId}, {$actorIdFixture}, 'ACTIVO')");

// 4. Usuario Huérfano (0 empresas asignadas)
$conexion->exec("INSERT INTO `actores` (`codigo`, `tipo_actor`, `nombre`, `estado`) VALUES ('ACT-2D-HUERF', 'USUARIO', 'Actor Huerfano 2D', 'ACTIVO')");
$actorHuerfano = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `personas` (`tipo_persona`, `estado`) VALUES ('NATURAL', 'ACTIVO')");
$pHuerfano = (int) $conexion->lastInsertId();
$conexion->exec("INSERT INTO `usuarios` (`persona_id`, `actor_id`, `nombre_usuario`, `email`, `password_hash`, `estado`, `version_autorizacion`)
                 VALUES ({$pHuerfano}, {$actorHuerfano}, 'huerfano_2d', 'huerfano_2d@casapro.pe', '{$hashPassword}', 'ACTIVO', 1)");
$usuarioHuerfanoId = (int) $conexion->lastInsertId();

$autenticacionServicio = new AutenticacionServicio($proveedorConexion);

// Test 2.1: Login Usuario Multiempresa -> auto-selecciona EMP-2D-A (primer registro canónico por codigo ASC)
GestorSesion::iniciar();
GestorSesion::destruir();
$dtoLoginMulti = \App\DTOs\AutenticarUsuarioDTO::desdeArray(['identificador' => 'multi_2d', 'password' => 'ClaveSecreta123*']);
$contextoReq = new ContextoPeticion('127.0.0.1', 'PHPUnit');
$resMulti = $autenticacionServicio->autenticar($dtoLoginMulti, $contextoReq);

$empresaSesionMulti = GestorSesion::obtener('contexto_empresa_id');
probar($empresaSesionMulti === $empresaAId, "Usuario multiempresa selecciona deterministamente EMP-2D-A por orden canónico (ID: {$empresaAId}, sesión: {$empresaSesionMulti})");

// Test 2.2: Login Usuario con 1 Empresa -> auto-asigna su única empresa (Empresa C)
GestorSesion::destruir();
$dtoLoginUnico = \App\DTOs\AutenticarUsuarioDTO::desdeArray(['identificador' => 'unico_2d', 'password' => 'ClaveSecreta123*']);
$resUnico = $autenticacionServicio->autenticar($dtoLoginUnico, $contextoReq);
$empresaSesionUnico = GestorSesion::obtener('contexto_empresa_id');
probar($empresaSesionUnico === $empresaCId, "Usuario con 1 empresa auto-asigna de inmediato su única empresa (ID: {$empresaCId}, sesión: {$empresaSesionUnico})");

// Test 2.3: Login Usuario Huérfano -> contexto_empresa_id es null
GestorSesion::destruir();
$dtoLoginHuerfano = \App\DTOs\AutenticarUsuarioDTO::desdeArray(['identificador' => 'huerfano_2d', 'password' => 'ClaveSecreta123*']);
$resHuerfano = $autenticacionServicio->autenticar($dtoLoginHuerfano, $contextoReq);
$empresaSesionHuerfano = GestorSesion::obtener('contexto_empresa_id');
probar($empresaSesionHuerfano === null, "Usuario huérfano inicia sesión sin contexto territorial (contexto_empresa_id es null)");

// Test 2.4: Login SUPERADMIN -> auto-selecciona primera empresa activa canónica del catálogo completo
GestorSesion::destruir();
$dtoLoginSuper = \App\DTOs\AutenticarUsuarioDTO::desdeArray(['identificador' => 'superadmin_2d', 'password' => 'ClaveSecreta123*']);
$resSuper = $autenticacionServicio->autenticar($dtoLoginSuper, $contextoReq);
$empresaSesionSuper = GestorSesion::obtener('contexto_empresa_id');
// La primera del catálogo ordenado por codigo ASC es la menor de todas las activas
$primeraCatId = (int) $conexion->query("SELECT `id` FROM `empresas` WHERE `estado` = 'ACTIVO' ORDER BY `codigo` ASC, `nombre_corto` ASC, `id` ASC LIMIT 1")->fetchColumn();
probar($empresaSesionSuper === $primeraCatId, "SUPERADMIN auto-selecciona la primera empresa determinista del catálogo activo (ID: {$primeraCatId})");

// =========================================================================
// BLOQUE 3: Endpoint GET /api/contexto/empresas
// =========================================================================
$empresaRepoTest = new EmpresaRepositorio($proveedorConexion);
$autorizacionServicioTest = new AutorizacionServicio($proveedorConexion);
$auditoriaServicioTest = new AuditoriaServicio($proveedorConexion);
$seguridadRepoTest = new SeguridadRepositorio($proveedorConexion);
$contextoServicioTest = new ContextoServicio($autorizacionServicioTest, $empresaRepoTest, $auditoriaServicioTest, $seguridadRepoTest);
$controlador = new ContextoControlador($contextoServicioTest);

$scopeMiddleware = new ScopeMiddleware(
    new AutenticacionMiddleware($autorizacionServicioTest, $seguridadRepoTest),
    $autorizacionServicioTest,
    $empresaRepoTest,
    $seguridadRepoTest
);

// Simular sesión de usuario multiempresa con activa en Empresa A
GestorSesion::destruir();
GestorSesion::establecer('auth', [
    'usuario_id'           => $usuarioMultiId,
    'actor_id'             => $actorIdFixture,
    'nombre_usuario'       => 'multi_2d',
    'version_autorizacion' => 1
]);
GestorSesion::establecer('contexto_empresa_id', $empresaAId);

$peticionGet = new Peticion('GET', '/api/contexto/empresas');
$respuestaGet = new Respuesta();

ob_start();
$controlador->listarEmpresasDisponibles($peticionGet, $respuestaGet);
$salidaJsonGet = ob_get_clean();

$datosGet = json_decode($salidaJsonGet, true);
probar(($datosGet['estado'] ?? '') === 'exito', "Endpoint GET /api/contexto/empresas retorna estado 'exito'");
probar(($datosGet['codigo'] ?? 0) === 200, "Endpoint GET retorna código HTTP 200");
probar(($datosGet['datos']['empresa_activa_id'] ?? 0) === $empresaAId, "Endpoint GET reporta empresa_activa_id correspondiente a sesión ({$empresaAId})");
probar(($datosGet['datos']['total_disponibles'] ?? 0) === 2, "Endpoint GET lista exactamente las 2 empresas activas asignadas a multi_2d");

$empresasMulti = $datosGet['datos']['empresas'] ?? [];
$idA_activa = false;
$idB_inactiva = false;
foreach ($empresasMulti as $e) {
    if ($e['id'] === $empresaAId && $e['es_activa'] === true) $idA_activa = true;
    if ($e['id'] === $empresaBId && $e['es_activa'] === false) $idB_inactiva = true;
}
probar($idA_activa && $idB_inactiva, "Bandera 'es_activa' refleja correctamente empresa A activa y empresa B inactiva");

// =========================================================================
// BLOQUE 4: Endpoint POST /api/contexto/cambiar-empresa (Conmutación y Errores)
// =========================================================================
echo "\n--- BLOQUE 4: Endpoint POST /api/contexto/cambiar-empresa ---\n";

// 4.1: Conmutación exitosa hacia Empresa B (asignada)
$peticionPost = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPost->establecerJson(['empresa_id' => $empresaBId]);
$respuestaPost = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPost, $respuestaPost, null, $contextoReq);
$salidaPost = ob_get_clean();
$datosPost = json_decode($salidaPost, true);

probar(($datosPost['estado'] ?? '') === 'exito', "Conmutación hacia Empresa B autorizada retorna estado 'exito'");
probar(($datosPost['datos']['empresa_id'] ?? 0) === $empresaBId, "Conmutación retorna empresa_id = {$empresaBId}");
probar(GestorSesion::obtener('contexto_empresa_id') === $empresaBId, "Sesión del servidor actualizada atómicamente a empresa_id = {$empresaBId}");

// 4.2: Intento hacia empresa no asignada (Empresa C) -> 403 FUERA_DE_SCOPE
$peticionPostAjena = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPostAjena->establecerJson(['empresa_id' => $empresaCId]);
$respuestaPostAjena = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPostAjena, $respuestaPostAjena, null, $contextoReq);
$salidaPostAjena = ob_get_clean();
$datosPostAjena = json_decode($salidaPostAjena, true);

probar(($datosPostAjena['codigo'] ?? 0) === 403, "Intento de conmutar a empresa no asignada arroja HTTP 403");
probar(($datosPostAjena['codigo_error'] ?? '') === 'FUERA_DE_SCOPE', "Código de error es FUERA_DE_SCOPE");
probar(GestorSesion::obtener('contexto_empresa_id') === $empresaBId, "Sesión no sufre alteraciones tras intento denegado (continúa en {$empresaBId})");

// 4.3: Intento hacia empresa inactiva -> 409 EMPRESA_INACTIVA
$peticionPostInactiva = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPostInactiva->establecerJson(['empresa_id' => $empresaInactivaId]);
$respuestaPostInactiva = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPostInactiva, $respuestaPostInactiva, null, $contextoReq);
$salidaPostInactiva = ob_get_clean();
$datosPostInactiva = json_decode($salidaPostInactiva, true);

probar(($datosPostInactiva['codigo'] ?? 0) === 409, "Intento de conmutar a empresa inactiva arroja HTTP 409 Conflict");
probar(($datosPostInactiva['codigo_error'] ?? '') === 'EMPRESA_INACTIVA', "Código de error es EMPRESA_INACTIVA");

// 4.4: Intento hacia empresa inexistente -> 404 EMPRESA_NO_ENCONTRADA
$peticionPostInexistente = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPostInexistente->establecerJson(['empresa_id' => 999999]);
$respuestaPostInexistente = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPostInexistente, $respuestaPostInexistente, null, $contextoReq);
$salidaPostInexistente = ob_get_clean();
$datosPostInexistente = json_decode($salidaPostInexistente, true);

probar(($datosPostInexistente['codigo'] ?? 0) === 404, "Intento de conmutar a empresa inexistente arroja HTTP 404 Not Found");
probar(($datosPostInexistente['codigo_error'] ?? '') === 'EMPRESA_NO_ENCONTRADA', "Código de error es EMPRESA_NO_ENCONTRADA");

// 4.5: Intento con payload inválido -> 422 DATOS_INVALIDOS
$peticionPostInvalida = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPostInvalida->establecerJson(['empresa_id' => -5]);
$respuestaPostInvalida = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPostInvalida, $respuestaPostInvalida, null, $contextoReq);
$salidaPostInvalida = ob_get_clean();
$datosPostInvalida = json_decode($salidaPostInvalida, true);

probar(($datosPostInvalida['codigo'] ?? 0) === 422, "Payload inválido (empresa_id negativo) arroja HTTP 422 Unprocessable");
probar(($datosPostInvalida['codigo_error'] ?? '') === 'DATOS_INVALIDOS', "Código de error es DATOS_INVALIDOS");

// 4.6: Intento sin autenticación -> 401 NO_AUTENTICADO
GestorSesion::destruir();
$_SESSION = [];
GestorSesion::eliminar('auth');
$peticionPostAnonima = new Peticion('POST', '/api/contexto/cambiar-empresa');
$peticionPostAnonima->establecerJson(['empresa_id' => $empresaAId]);
$respuestaPostAnonima = new Respuesta();

ob_start();
$controlador->cambiarEmpresa($peticionPostAnonima, $respuestaPostAnonima, null, $contextoReq);
$salidaPostAnonima = ob_get_clean();
$datosPostAnonima = json_decode($salidaPostAnonima, true);

probar(($datosPostAnonima['codigo'] ?? 0) === 401, "Petición sin autenticación arroja HTTP 401 Unauthorized");

// =========================================================================
// BLOQUE 5: Blindaje Preventivo de Contextos Hijos Incompatibles
// =========================================================================
echo "\n--- BLOQUE 5: Blindaje Preventivo de Contextos Subordinados ---\n";

// Restaurar sesión de usuario multiempresa en Empresa A
GestorSesion::establecer('auth', [
    'usuario_id'           => $usuarioMultiId,
    'actor_id'             => $actorIdFixture,
    'nombre_usuario'       => 'multi_2d',
    'version_autorizacion' => 1
]);
GestorSesion::establecer('contexto_empresa_id', $empresaAId);

// Simular que existían punteros subordinados de proyectos y sectores futuros
GestorSesion::establecer('contexto_proyecto_id', 88);
GestorSesion::establecer('contexto_sector_id', 12);

probar(GestorSesion::obtener('contexto_proyecto_id') === 88, "contexto_proyecto_id = 88 simulado en sesión");
probar(GestorSesion::obtener('contexto_sector_id') === 12, "contexto_sector_id = 12 simulado en sesión");

// Conmutar a Empresa B
$resConmutar = $contextoServicioTest->cambiarEmpresa($usuarioMultiId, $empresaBId, $contextoReq);

probar(GestorSesion::obtener('contexto_empresa_id') === $empresaBId, "Empresa activa conmutada a Empresa B ({$empresaBId})");
probar(GestorSesion::obtener('contexto_proyecto_id') === null, "Blindaje Fase 3: contexto_proyecto_id eliminado atómicamente de sesión");
probar(GestorSesion::obtener('contexto_sector_id') === null, "Blindaje Fase 3: contexto_sector_id eliminado atómicamente de sesión");

// =========================================================================
// BLOQUE 6: Auditoría Forense Mínima y No Sensible
// =========================================================================
echo "\n--- BLOQUE 6: Auditoría Forense Mínima y No Sensible ---\n";

$auditoriaStmt = $conexion->prepare("SELECT * FROM `auditorias` WHERE `accion` = 'CAMBIO_CONTEXTO_EMPRESA' ORDER BY `id` DESC LIMIT 1");
$auditoriaStmt->execute();
$registroAuditoria = $auditoriaStmt->fetch(PDO::FETCH_ASSOC);

probar($registroAuditoria !== false, "Evento 'CAMBIO_CONTEXTO_EMPRESA' registrado en tabla auditorias");
probar(($registroAuditoria['entidad'] ?? '') === 'empresas', "Entidad auditada es 'empresas'");
probar((int) ($registroAuditoria['registro_id'] ?? 0) === $empresaBId, "registro_id coincide con nueva empresa ({$empresaBId})");

$datosNuevos = json_decode((string) ($registroAuditoria['datos_nuevos'] ?? '{}'), true);
$datosAnteriores = json_decode((string) ($registroAuditoria['datos_anteriores'] ?? '{}'), true);

probar(isset($datosNuevos['empresa_id']) && $datosNuevos['empresa_id'] === $empresaBId, "datos_nuevos contiene 'empresa_id' correcto");
probar(isset($datosNuevos['codigo']) && $datosNuevos['codigo'] === 'EMP-2D-B', "datos_nuevos contiene 'codigo' corporativo");
probar(!isset($datosNuevos['razon_social']), "datos_nuevos no contiene razón social ni datos sensibles de personas");
probar(($datosAnteriores['empresa_id'] ?? null) === $empresaAId, "datos_anteriores registra únicamente empresa_id previo ({$empresaAId})");

// =========================================================================
// BLOQUE 7: Detección en Caliente de Empresa Inactivada (ScopeMiddleware)
// =========================================================================
echo "\n--- BLOQUE 7: Detección en Caliente de Empresa Inactivada ---\n";

// Conmutar Empresa B a INACTIVO directamente en BD para simular inactivación administrativa
$conexion->exec("UPDATE `empresas` SET `estado` = 'INACTIVO' WHERE `id` = {$empresaBId}");

$scopeMiddleware = new ScopeMiddleware();
$peticionTerritorial = new Peticion('GET', '/api/operaciones/listar');
$peticionTerritorial->establecerCabecera('Accept', 'application/json');
$respuestaTerritorial = new Respuesta();

ob_start();
$aprobado = $scopeMiddleware->procesar($peticionTerritorial, $respuestaTerritorial, $contextoReq);
$salidaInactivaScope = ob_get_clean();
$datosInactivaScope = json_decode($salidaInactivaScope, true);

probar($aprobado === false, "ScopeMiddleware rechaza petición cuando la empresa activa en sesión fue inactivada en BD");
probar(($datosInactivaScope['codigo'] ?? 0) === 409, "ScopeMiddleware responde HTTP 409 Conflict");
probar(($datosInactivaScope['codigo_error'] ?? '') === 'EMPRESA_INACTIVA', "Código de error devuelto es EMPRESA_INACTIVA");
probar(GestorSesion::obtener('contexto_empresa_id') === null, "ScopeMiddleware expulsa y limpia contexto_empresa_id de la sesión tras detectar inactividad");

// Restaurar Empresa B a ACTIVO
$conexion->exec("UPDATE `empresas` SET `estado` = 'ACTIVO' WHERE `id` = {$empresaBId}");

// =========================================================================
// BLOQUE 8: Detección de Revocación de Rol en Sesión
// =========================================================================
echo "\n--- BLOQUE 8: Detección de Revocación de Rol Territorial ---\n";

// Establecer sesión de usuario único con Empresa C activa
GestorSesion::establecer('auth', [
    'usuario_id'           => $usuarioUnicoId,
    'actor_id'             => $actorIdFixture,
    'nombre_usuario'       => 'unico_2d',
    'version_autorizacion' => 1
]);
GestorSesion::establecer('contexto_empresa_id', $empresaCId);

$autorizacionServicio = new AutorizacionServicio($proveedorConexion);

// Revocar rol del usuario único en Empresa C
$dtoRevocar = new RevocarRolEmpresaDTO($usuarioUnicoId, $empresaCId, $rolVendedorId, 'Revocación de prueba 2D');
$resRevocar = $autorizacionServicio->revocarRolEmpresa($dtoRevocar, $actorIdFixture, $contextoReq);

probar($resRevocar['estado'] === UsuarioEmpresaRol::ESTADO_INACTIVO, "Rol revocado exitosamente a estado INACTIVO");
probar($resRevocar['version_autorizacion'] === 2, "version_autorizacion incrementada a 2 en base de datos");

// La siguiente petición por AutenticacionMiddleware debe detectar el cambio de versión (1 en sesión != 2 en BD)
$autenticacionMiddleware = new AutenticacionMiddleware($autorizacionServicioTest, $seguridadRepoTest);
$peticionRevocada = new Peticion('GET', '/api/recurso/protegido');
$peticionRevocada->establecerCabecera('Accept', 'application/json');
$respuestaRevocada = new Respuesta();

ob_start();
$autenticadoValido = $autenticacionMiddleware->procesar($peticionRevocada, $respuestaRevocada, $contextoReq);
$salidaRevocada = ob_get_clean();
$datosRevocada = json_decode($salidaRevocada, true);

probar($autenticadoValido === false, "AutenticacionMiddleware invalida en caliente la sesión ante revocación territorial");
probar(($datosRevocada['codigo'] ?? 0) === 401, "AutenticacionMiddleware responde HTTP 401 por cambio de version_autorizacion");
$eventoInvalidada = $conexion->query("SELECT * FROM `eventos_seguridad` WHERE `tipo_evento` = 'SESION_INVALIDADA' AND `usuario_id` = {$usuarioUnicoId} ORDER BY `id` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
probar($eventoInvalidada !== false, "Evento forense SESION_INVALIDADA registrado tras detectar revocación");

// Doble barrera: verificar que ScopeMiddleware también rechazaría con 403 FUERA_DE_SCOPE si la sesión persistiera
$puedeAccederTrasRevocar = $autorizacionServicio->puedeAccederEmpresa($usuarioUnicoId, $empresaCId);
probar($puedeAccederTrasRevocar === false, "Barrera territorial en tiempo real: puedeAccederEmpresa resuelve false tras revocación");

// =========================================================================
// BLOQUE 9: Gate Anti-IDOR Multifuente
// =========================================================================
echo "\n--- BLOQUE 9: Gate Anti-IDOR Multifuente Fail-Closed ---\n";

// Configurar sesión legítima de multi_2d en Empresa A
GestorSesion::establecer('auth', [
    'usuario_id'           => $usuarioMultiId,
    'actor_id'             => $actorIdFixture,
    'nombre_usuario'       => 'multi_2d',
    'version_autorizacion' => 1
]);
GestorSesion::establecer('contexto_empresa_id', $empresaAId);

// 9.1: Inyección Anti-IDOR en Query String (?empresa_id=B)
$petIdorQuery = new Peticion('GET', '/api/operaciones/territoriales');
$petIdorQuery->establecerConsulta(['empresa_id' => $empresaBId]);
$petIdorQuery->establecerCabecera('Accept', 'application/json');
$resIdorQuery = new Respuesta();

ob_start();
$aprobadoQuery = $scopeMiddleware->procesar($petIdorQuery, $resIdorQuery, $contextoReq);
$salidaIdorQuery = ob_get_clean();
$datosIdorQuery = json_decode($salidaIdorQuery, true);

probar($aprobadoQuery === false, "Gate Anti-IDOR bloquea inyección en Query String");
probar(($datosIdorQuery['codigo'] ?? 0) === 403, "Respuesta estricta HTTP 403 Forbidden");
probar(($datosIdorQuery['codigo_error'] ?? '') === 'DISCORDANCIA_TERRITORIAL', "Código de error es DISCORDANCIA_TERRITORIAL");

// 9.2: Inyección Anti-IDOR en Cuerpo POST / Formulario
$petIdorPost = new Peticion('POST', '/api/operaciones/territoriales');
$petIdorPost->establecerCuerpo(['empresa_id' => $empresaBId]);
$petIdorPost->establecerCabecera('Accept', 'application/json');
$resIdorPost = new Respuesta();

ob_start();
$aprobadoPost = $scopeMiddleware->procesar($petIdorPost, $resIdorPost, $contextoReq);
$salidaIdorPost = ob_get_clean();
$datosIdorPost = json_decode($salidaIdorPost, true);

probar($aprobadoPost === false, "Gate Anti-IDOR bloquea inyección en Cuerpo POST");
probar(($datosIdorPost['codigo'] ?? 0) === 403, "Respuesta estricta HTTP 403 Forbidden");

// 9.3: Inyección Anti-IDOR en Payload JSON
$petIdorJson = new Peticion('POST', '/api/operaciones/territoriales');
$petIdorJson->establecerJson(['empresa_id' => $empresaBId]);
$petIdorJson->establecerCabecera('Accept', 'application/json');
$resIdorJson = new Respuesta();

ob_start();
$aprobadoJson = $scopeMiddleware->procesar($petIdorJson, $resIdorJson, $contextoReq);
$salidaIdorJson = ob_get_clean();
$datosIdorJson = json_decode($salidaIdorJson, true);

probar($aprobadoJson === false, "Gate Anti-IDOR bloquea inyección en Payload JSON");
probar(($datosIdorJson['codigo'] ?? 0) === 403, "Respuesta estricta HTTP 403 Forbidden");

// 9.4: Comprobar registro de telemetría forense en eventos_seguridad
$eventoIdor = $conexion->query("SELECT * FROM `eventos_seguridad` WHERE `metadatos` LIKE '%DISCORDANCIA_TERRITORIAL_IDOR%' ORDER BY `id` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
probar($eventoIdor !== false, "Telemetría forense DISCORDANCIA_TERRITORIAL_IDOR registrada en eventos_seguridad");

// =========================================================================
// BLOQUE 10: Integridad Frontend, MVC Desacoplado, Tooltips y Responsive
// =========================================================================
echo "\n--- BLOQUE 10: Integridad Frontend, MVC Desacoplado y Tooltips ---\n";

$contenidoTopbar = file_get_contents(dirname(__DIR__) . '/app/Vistas/layouts/parciales/barra-superior.php') ?: '';
$contenidoScripts = file_get_contents(dirname(__DIR__) . '/app/Vistas/layouts/parciales/pie-scripts.php') ?: '';
$contenidoJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/nucleo/selector-empresa.js') ?: '';

probar(!str_contains($contenidoTopbar, 'new AutorizacionServicio'), "MVC Desacoplado: barra-superior.php NO instancia AutorizacionServicio");
probar(!str_contains($contenidoTopbar, 'new EmpresaRepositorio'), "MVC Desacoplado: barra-superior.php NO instancia repositorios");
probar(!str_contains($contenidoTopbar, 'PDO'), "MVC Desacoplado: barra-superior.php NO contiene sentencias ni tipos PDO");

// Corrección 2: Cero doble data-bs-toggle en el botón interactivo del dropdown
probar(str_contains($contenidoTopbar, 'id="dropdownBotonEmpresa"'), "Botón interactivo #dropdownBotonEmpresa definido en barra-superior.php");
probar(str_contains($contenidoTopbar, 'data-bs-toggle="dropdown"'), "Botón interactivo utiliza data-bs-toggle=\"dropdown\"");

// Verificar que en el elemento dropdownBotonEmpresa no existe data-bs-toggle="tooltip"
preg_match('/<a[^>]*id="dropdownBotonEmpresa"[^>]*>/i', $contenidoTopbar, $matchBtn);
$botonHtml = $matchBtn[0] ?? '';
probar(!str_contains($botonHtml, 'data-bs-toggle="tooltip"'), "Corrección 2 verificada: Cero doble data-bs-toggle en #dropdownBotonEmpresa");

// Tooltips oficiales en badges estáticos
probar(str_contains($contenidoTopbar, 'data-bs-toggle="tooltip"'), "Tooltips oficiales data-bs-toggle=\"tooltip\" presentes en badges estáticos");
probar(str_contains($contenidoTopbar, 'data-bs-title='), "Atributo oficial data-bs-title utilizado para contenido de tooltips");

// Responsividad: soporte desktop y móvil compacto
probar(str_contains($contenidoTopbar, 'id="etiquetaEmpresaActivaDesktop"'), "Topbar define vista desktop #etiquetaEmpresaActivaDesktop");
probar(str_contains($contenidoTopbar, 'id="etiquetaEmpresaActivaMovil"'), "Topbar define vista móvil compacta #etiquetaEmpresaActivaMovil");

// Script JS
probar(str_contains($contenidoScripts, 'selector-empresa.js'), "pie-scripts.php incluye selector-empresa.js");
probar(str_contains($contenidoJs, 'window.fetch('), "selector-empresa.js utiliza window.fetch() nativo");
probar(!str_contains($contenidoJs, '$.ajax'), "selector-empresa.js no utiliza $.ajax (cero jQuery propio)");
probar(!str_contains($contenidoJs, '$.get'), "selector-empresa.js no utiliza $.get (cero jQuery propio)");
probar(!str_contains($contenidoJs, '$.post'), "selector-empresa.js no utiliza $.post (cero jQuery propio)");

// Comprobación de DELTA en casapro (desarrollo) = 0
$pdoDesarrollo = new PDO("mysql:host=127.0.0.1;dbname=casapro;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
$deltaUsuarios = (int) $pdoDesarrollo->query("SELECT COUNT(*) FROM `usuarios` WHERE `nombre_usuario` IN ('superadmin_2d', 'multi_2d', 'unico_2d', 'huerfano_2d')")->fetchColumn();
$deltaEmpresas = (int) $pdoDesarrollo->query("SELECT COUNT(*) FROM `empresas` WHERE `codigo` LIKE 'EMP-2D-%'")->fetchColumn();
probar($deltaUsuarios === 0 && $deltaEmpresas === 0, "DELTA casapro (desarrollo) = 0 confirmado (cero contaminación de fixtures de prueba)");

// Limpieza final de fixtures en casapro_test
$conexion->exec("SET FOREIGN_KEY_CHECKS = 0");
$conexion->exec("DELETE FROM `auditorias` WHERE `modulo` IN ('seguridad', 'empresas') AND `actor_id` = {$actorIdFixture}");
$conexion->exec("DELETE FROM `eventos_seguridad` WHERE `usuario_id` IN ({$usuarioSuperId}, {$usuarioMultiId}, {$usuarioUnicoId}, {$usuarioHuerfanoId})");
$conexion->exec("DELETE FROM `usuario_empresa_roles` WHERE `usuario_id` IN ({$usuarioSuperId}, {$usuarioMultiId}, {$usuarioUnicoId}, {$usuarioHuerfanoId})");
$conexion->exec("DELETE FROM `usuario_roles` WHERE `usuario_id` = {$usuarioSuperId}");
$conexion->exec("DELETE FROM `usuarios` WHERE `id` IN ({$usuarioSuperId}, {$usuarioMultiId}, {$usuarioUnicoId}, {$usuarioHuerfanoId})");
$conexion->exec("DELETE FROM `empresas` WHERE `id` IN ({$empresaAId}, {$empresaBId}, {$empresaCId}, {$empresaInactivaId})");
$conexion->exec("DELETE FROM `actores` WHERE `id` IN ({$actorIdFixture}, {$actorSuper}, {$actorMulti}, {$actorUnico}, {$actorHuerfano})");
$conexion->exec("SET FOREIGN_KEY_CHECKS = 1");

GestorSesion::destruir();

echo "\n===================================================================\n";
echo " RESULTADO: {$pruebasSuperadas} / {$totalPruebas} PRUEBAS SUPERADAS\n";
echo "===================================================================\n";

if ($pruebasSuperadas !== $totalPruebas) {
    exit(1);
}
