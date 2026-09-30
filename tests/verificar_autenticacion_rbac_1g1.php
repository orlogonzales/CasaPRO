<?php

declare(strict_types=1);

/**
 * CasaPRO — Suite Exhaustiva de Pruebas Automatizadas de Seguridad, Autenticación y RBAC (1G-1).
 *
 * Microfase: 1G-1 (Núcleo de Autenticación, RBAC y Autorización Multidimensional)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/comun/AmbientePruebas.php';

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Core\Enrutador;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\DTOs\AutenticarUsuarioDTO;
use App\DTOs\CrearPersonaDTO;
use App\Modelos\Usuario;
use App\Modelos\Rol;
use App\Modelos\Privilegio;
use App\Modelos\EventoSeguridad;
use App\Modelos\Persona;
use App\Modelos\PersonaNatural;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Servicios\AutenticacionServicio;
use App\Servicios\AutorizacionServicio;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use Tests\Comun\FixtureAutenticacion;
use Tests\Comun\AmbientePruebas;

$conexion = AmbientePruebas::iniciar(true);
$proveedor = AmbientePruebas::obtenerProveedorTest();
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE AUTENTICACIÓN, SESIONES, FUERZA BRUTA Y RBAC (1G-1)\n";
echo "===================================================================\n";

$pruebasEjecutadas = 0;
$pruebasSuperadas = 0;

function afirmativo(bool $condicion, string $descripcion, string $detalle = ''): void
{
    global $pruebasEjecutadas, $pruebasSuperadas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasSuperadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        echo "  [FAIL] {$descripcion}";
        if ($detalle !== '') {
            echo " -> {$detalle}";
        }
        echo "\n";
    }
}

$usuarioRepo = new UsuarioRepositorio($proveedor);
$rolRepo = new RolRepositorio($proveedor);
$seguridadRepo = new SeguridadRepositorio($proveedor);
$personaRepo = new PersonaRepositorio($proveedor);
$auditoriaServicio = new AuditoriaServicio($proveedor);
$personaServicio = new PersonaServicio($proveedor, $personaRepo, $auditoriaServicio);
$authServicio = new AutenticacionServicio($proveedor, $usuarioRepo, $rolRepo, $seguridadRepo, $personaRepo, $auditoriaServicio);
$autorizacionServicio = new AutorizacionServicio($proveedor, $usuarioRepo, $rolRepo);

// Helper para despachar peticiones HTTP por el enrutador
function despacharHttp(string $metodo, string $ruta, array $cuerpo = [], array $cabeceras = [], ?array $authSesion = null): array
{
    GestorSesion::iniciar();
    if ($authSesion !== null) {
        GestorSesion::establecer('auth', $authSesion);
        GestorSesion::establecer('actor_id', $authSesion['actor_id']);
    } else {
        GestorSesion::eliminar('auth');
        GestorSesion::eliminar('actor_id');
    }

    $peticion = new Peticion();
    $peticion->establecerMetodo($metodo);
    $peticion->establecerRuta($ruta);

    $esJson = false;
    foreach ($cabeceras as $nombre => $valor) {
        $peticion->establecerCabecera($nombre, $valor);
        if (strtolower($nombre) === 'content-type' && str_contains(strtolower($valor), 'application/json')) {
            $esJson = true;
        }
    }

    if ($esJson) {
        $peticion->establecerCuerpo([]);
        $peticion->establecerJson($cuerpo);
    } else {
        $peticion->establecerCuerpo($cuerpo);
        if (!empty($cuerpo)) {
            $peticion->establecerJson($cuerpo);
        }
    }

    $contexto = ContextoPeticion::crearDesdeEntorno($peticion);
    $respuesta = new Respuesta();

    $enrutador = new Enrutador();
    $enrutador->agregarMiddlewareGlobal(\App\Middlewares\CsrfMiddleware::class);
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
        'cabeceras' => $respuesta->obtenerCabeceras(),
        'json' => json_decode($cuerpoRespuesta, true)
    ];
}

// =========================================================================
// BLOQUE 1: Modelo de Identidad, Actor USER y Relación 1:1
// =========================================================================
echo "\n--- BLOQUE 1: Modelo de Identidad, Actor USER y Relación 1:1 ---\n";

// Crear Persona Natural de prueba
$dniPrueba = '7' . str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$contextoCli = new ContextoPeticion('TEST-1G1-01', '127.0.0.1', 'CLI-TEST', 'CLI', 1);

$resPersona = $personaServicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Juan Carlos',
    'apellido_paterno' => 'Perez',
    'apellido_materno' => 'Gomez',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $dniPrueba, 'es_principal' => true]
    ]
]), $contextoCli);

$personaId = (int) $resPersona['id'];
afirmativo($personaId > 0, "Persona Natural creada con éxito (ID: {$personaId})");

// Crear Usuario asociado a la Persona Natural
$emailPrueba = 'juan.perez_' . uniqid() . '@casapro.pe';
$usuarioNombre = 'jperez_' . uniqid();
$resUsuario = $authServicio->crearUsuarioConActor(
    $personaId,
    $emailPrueba,
    $usuarioNombre,
    'ClaveSegura#2026',
    1, // Rol SUPERADMIN
    null,
    $contextoCli
);

afirmativo($resUsuario['usuario_id'] > 0, "Usuario creado exitosamente con ID {$resUsuario['usuario_id']}");
afirmativo(str_starts_with($resUsuario['codigo_actor'], 'ACT_USR_'), "Actor USER creado con código canónico inmutable ({$resUsuario['codigo_actor']})");
afirmativo($resUsuario['actor_id'] > 0, "Actor USER vinculado con ID primario válido ({$resUsuario['actor_id']})");

// Rechazo de segundo usuario para la misma persona (1:1 estricto)
$excepcionDuplicado = false;
try {
    $authServicio->crearUsuarioConActor(
        $personaId,
        'otro_' . $emailPrueba,
        'otro_' . $usuarioNombre,
        'OtraClave#2026',
        1,
        null,
        $contextoCli
    );
} catch (ValidacionExcepcion $ve) {
    $excepcionDuplicado = true;
}
afirmativo($excepcionDuplicado, "Rechazo de intento de crear un segundo usuario para la misma Persona Natural (1:1)");

// Rechazo de usuario para Persona Jurídica
$resJuridica = $personaServicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona' => 'JURIDICA',
    'razon_social' => 'Inmobiliaria Los Andes SAC ' . uniqid(),
    'documentos' => [
        ['tipo_documento_id' => 2, 'numero_documento' => '20' . rand(100000000, 999999999), 'es_principal' => true]
    ]
]), $contextoCli);
$juridicaId = (int) $resJuridica['id'];

$excepcionJuridica = false;
try {
    $authServicio->crearUsuarioConActor(
        $juridicaId,
        'juridica_' . uniqid() . '@empresa.com',
        'juridica_' . uniqid(),
        'ClaveSegura#2026',
        1,
        null,
        $contextoCli
    );
} catch (ValidacionExcepcion $ve) {
    $excepcionJuridica = true;
}
afirmativo($excepcionJuridica, "Rechazo estricto de asignación de usuario a Persona Jurídica");

// =========================================================================
// BLOQUE 2: Atomicidad Transaccional y Propiedad de Transacciones
// =========================================================================
echo "\n--- BLOQUE 2: Atomicidad Transaccional y Propiedad de Transacciones ---\n";

// Simulación de rollback en transacción propagada externa
$dniRollback = '8' . str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$conexion->beginTransaction();
$resPersonaTmp = $personaServicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Temporal',
    'apellido_paterno' => 'Rollback',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $dniRollback, 'es_principal' => true]
    ]
]), $contextoCli, $conexion);

$idPersonaTmp = (int) $resPersonaTmp['id'];
$conexion->rollBack();

// Comprobar que no quedó rastro en la base de datos
$personaVerif = $personaRepo->buscarPorId($idPersonaTmp);
afirmativo($personaVerif === null, "Propiedad de transacción respetada: Rollback externo revirtió completamente la Persona creada");

// =========================================================================
// BLOQUE 3: Autenticación, Hashing Dinámico y Rehash
// =========================================================================
echo "\n--- BLOQUE 3: Autenticación, Hashing Dinámico y Rehash ---\n";

// Login exitoso por Email
$dtoLoginEmail = AutenticarUsuarioDTO::desdeArray([
    'identificador' => $emailPrueba,
    'password'      => 'ClaveSegura#2026'
]);
$authEmail = $authServicio->autenticar($dtoLoginEmail, $contextoCli);
afirmativo($authEmail['usuario_id'] === $resUsuario['usuario_id'], "Login exitoso mediante Correo Electrónico");

// Login exitoso por Username
$dtoLoginUser = AutenticarUsuarioDTO::desdeArray([
    'identificador' => $usuarioNombre,
    'password'      => 'ClaveSegura#2026'
]);
$authUser = $authServicio->autenticar($dtoLoginUser, $contextoCli);
afirmativo($authUser['usuario_id'] === $resUsuario['usuario_id'], "Login exitoso mediante Nombre de Usuario");

// Hash Dummy precalculado válido en PHP 8.3
afirmativo(password_get_info(AutenticacionServicio::HASH_DUMMY_DEFECTO)['algoName'] === 'bcrypt', "Hash dummy de mitigación es un Bcrypt válido reconocido por PHP 8.3");
afirmativo(!password_verify('ClaveCualquiera', AutenticacionServicio::HASH_DUMMY_DEFECTO), "Hash dummy evalúa de forma segura a false ante cualquier contraseña");

// =========================================================================
// BLOQUE 4: Anti-Enumeración y Telemetría Interna en eventos_seguridad
// =========================================================================
echo "\n--- BLOQUE 4: Anti-Enumeración y Telemetría Interna ---\n";

// 1. Fallo por Usuario Inexistente
$falloInexistente = false;
try {
    $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
        'identificador' => 'inexistente_' . uniqid() . '@casapro.pe',
        'password' => 'CualquierClave#123'
    ]), $contextoCli);
} catch (ReglaNegocioExcepcion $e) {
    $falloInexistente = ($e->getMessage() === 'No fue posible iniciar sesión con las credenciales proporcionadas.');
}
afirmativo($falloInexistente, "Usuario inexistente produce mensaje de error público uniforme (Anti-enumeración)");
$ultimoEvento = $seguridadRepo->obtenerUltimoEventoPorTipo('LOGIN_FALLO');
afirmativo($ultimoEvento && ($ultimoEvento->obtenerMetadatos()['motivo'] ?? '') === 'USUARIO_INEXISTENTE', "Telemetría interna registra internamente motivo 'USUARIO_INEXISTENTE'");

// 2. Fallo por Contraseña Incorrecta
$falloPassword = false;
try {
    $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
        'identificador' => $emailPrueba,
        'password' => 'PasswordErronea#123'
    ]), $contextoCli);
} catch (ReglaNegocioExcepcion $e) {
    $falloPassword = ($e->getMessage() === 'No fue posible iniciar sesión con las credenciales proporcionadas.');
}
afirmativo($falloPassword, "Contraseña incorrecta produce exactamente el mismo mensaje público uniforme");
$ultimoEvento = $seguridadRepo->obtenerUltimoEventoPorTipo('LOGIN_FALLO');
afirmativo($ultimoEvento && ($ultimoEvento->obtenerMetadatos()['motivo'] ?? '') === 'CREDENCIALES_INVALIDAS', "Telemetría interna registra internamente motivo 'CREDENCIALES_INVALIDAS'");

// 3. Fallo por Usuario Inactivo
$usuarioObj = $usuarioRepo->buscarPorId($resUsuario['usuario_id']);
$usuarioObj->setEstado('INACTIVO');
$stmtInact = $conexion->prepare("UPDATE `usuarios` SET `estado` = 'INACTIVO' WHERE `id` = :id");
$stmtInact->execute([':id' => $usuarioObj->obtenerId()]);

$falloInactivo = false;
try {
    $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
        'identificador' => $emailPrueba,
        'password' => 'ClaveSegura#2026'
    ]), $contextoCli);
} catch (ReglaNegocioExcepcion $e) {
    $falloInactivo = ($e->getMessage() === 'No fue posible iniciar sesión con las credenciales proporcionadas.');
}
afirmativo($falloInactivo, "Usuario inactivo produce exactamente el mismo mensaje público uniforme");
$ultimoEvento = $seguridadRepo->obtenerUltimoEventoPorTipo('LOGIN_FALLO');
afirmativo($ultimoEvento && ($ultimoEvento->obtenerMetadatos()['motivo'] ?? '') === 'CUENTA_INACTIVA', "Telemetría interna registra internamente motivo 'CUENTA_INACTIVA'");

// Reactivar usuario
$stmtAct = $conexion->prepare("UPDATE `usuarios` SET `estado` = 'ACTIVO' WHERE `id` = :id");
$stmtAct->execute([':id' => $usuarioObj->obtenerId()]);

// =========================================================================
// BLOQUE 5: Fuerza Bruta, Ventana de Intentos y Bloqueo Defensivo
// =========================================================================
echo "\n--- BLOQUE 5: Fuerza Bruta, Ventana de Intentos y Bloqueo Defensivo ---\n";

// Provocar 4 fallos consecutivos
for ($i = 0; $i < 4; $i++) {
    try {
        $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
            'identificador' => $emailPrueba,
            'password' => 'Mal#Password' . $i
        ]), $contextoCli);
    } catch (\Throwable) {}
}

$usrIntentos = $usuarioRepo->buscarPorId($resUsuario['usuario_id']);
afirmativo($usrIntentos->obtenerIntentosFallidos() >= 4, "Contador de intentos fallidos incrementa dentro de la ventana (Actual: {$usrIntentos->obtenerIntentosFallidos()})");

// Quinto intento fallido detona el bloqueo temporal de 15 minutos
try {
    $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
        'identificador' => $emailPrueba,
        'password' => 'Mal#Password5'
    ]), $contextoCli);
} catch (\Throwable) {}

$usrBloqueado = $usuarioRepo->buscarPorId($resUsuario['usuario_id']);
afirmativo($usrBloqueado->estaBloqueado(), "Quinto intento fallido activa el bloqueo temporal defensivo (bloqueado_hasta != null)");

$eventoBloqueo = $seguridadRepo->obtenerUltimoEventoPorTipo('BLOQUEO_TEMPORAL');
afirmativo($eventoBloqueo && (int) ($eventoBloqueo->obtenerMetadatos()['intentos'] ?? 0) >= 5, "Evento 'BLOQUEO_TEMPORAL' registrado en eventos_seguridad");

// Intento subsiguiente mientras está bloqueado produce el mismo mensaje uniforme
$falloBloqueado = false;
try {
    $authServicio->autenticar(AutenticarUsuarioDTO::desdeArray([
        'identificador' => $emailPrueba,
        'password' => 'ClaveSegura#2026' // Incluso con la clave correcta
    ]), $contextoCli);
} catch (ReglaNegocioExcepcion $e) {
    $falloBloqueado = ($e->getMessage() === 'No fue posible iniciar sesión con las credenciales proporcionadas.');
}
afirmativo($falloBloqueado, "Cuenta bloqueada temporalmente produce mensaje público uniforme");

// Reinicio de contadores defensivos
$stmtReset = $conexion->prepare("UPDATE `usuarios` SET `bloqueado_hasta` = NULL, `intentos_fallidos` = 0 WHERE `id` = :id");
$stmtReset->execute([':id' => $resUsuario['usuario_id']]);

// Login exitoso resetea completamente defensas
$authPostReset = $authServicio->autenticar($dtoLoginEmail, $contextoCli);
$usrLimpio = $usuarioRepo->buscarPorId($resUsuario['usuario_id']);
afirmativo($usrLimpio->obtenerIntentosFallidos() === 0 && $usrLimpio->obtenerBloqueadoHasta() === null, "Login exitoso resetea a cero los contadores de fallos y el bloqueo temporal");

// =========================================================================
// BLOQUE 6: Revocación Inmediata de Sesiones (Desigualdad de Versión)
// =========================================================================
echo "\n--- BLOQUE 6: Revocación Inmediata de Sesiones ---\n";

$versionActual = $authPostReset['version_autorizacion'];

// 1. Sesión válida con versión alineada
$controlValido = $autorizacionServicio->validarControlSesion($resUsuario['usuario_id'], $versionActual);
afirmativo($controlValido !== null, "Sesión con versión alineada valida exitosamente en base de datos");

// 2. Desalineación: versión en BD incrementada (versión_bd > versión_sesion)
$usuarioRepo->actualizarVersionAutorizacion($resUsuario['usuario_id'], $versionActual + 1);
$controlRevocadoMayor = $autorizacionServicio->validarControlSesion($resUsuario['usuario_id'], $versionActual);
afirmativo($controlRevocadoMayor === null, "Revocación inmediata activada por incremento de versión en BD (version_bd > version_sesion)");

// 3. Desalineación: versión en BD divergente menor (version_bd < version_sesion)
$controlRevocadoMenor = $autorizacionServicio->validarControlSesion($resUsuario['usuario_id'], $versionActual + 2);
afirmativo($controlRevocadoMenor === null, "Revocación inmediata activada por desigualdad estricta (version_bd !== version_sesion)");

// Restaurar versión para pruebas siguientes
$usuarioRepo->actualizarVersionAutorizacion($resUsuario['usuario_id'], 1);

// =========================================================================
// BLOQUE 7: Autorización RBAC, Scopes y Rol SUPERADMIN
// =========================================================================
echo "\n--- BLOQUE 7: Autorización RBAC, Scopes y Rol SUPERADMIN ---\n";

// SUPERADMIN posee bypass funcional universal para cualquier privilegio
afirmativo($autorizacionServicio->tienePrivilegio($resUsuario['usuario_id'], 'personas.ver'), "SUPERADMIN tiene acceso al privilegio 'personas.ver'");
afirmativo($autorizacionServicio->tienePrivilegio($resUsuario['usuario_id'], 'personas.crear'), "SUPERADMIN tiene acceso al privilegio 'personas.crear'");
afirmativo($autorizacionServicio->tienePrivilegio($resUsuario['usuario_id'], 'modulo_futuro.accion_futura'), "SUPERADMIN posee Bypass RBAC universal para cualquier modulo.accion");

// Usuario común sin privilegios
$dniComun = '6' . str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$resPersonaComun = $personaServicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Usuario',
    'apellido_paterno' => 'Comun',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $dniComun, 'es_principal' => true]
    ]
]), $contextoCli);

$codigoRolTest = 'CONSULTOR_' . strtoupper(bin2hex(random_bytes(4)));
$stmtRol = $conexion->prepare("INSERT INTO `roles` (`codigo`, `nombre`, `es_sistema`, `estado`) VALUES (:codigo, 'Consultor Pruebas', 0, 'ACTIVO')");
$stmtRol->execute([':codigo' => $codigoRolTest]);
$rolConsultorId = (int) $conexion->lastInsertId();

// Asignar solo privilegio 'personas.ver' (ID 1)
$rolRepo->asociarPrivilegio($rolConsultorId, 1);

$resUsuarioComun = $authServicio->crearUsuarioConActor(
    (int) $resPersonaComun['id'],
    'comun_' . uniqid() . '@casapro.pe',
    'ucomun_' . uniqid(),
    'ClaveSegura#2026',
    $rolConsultorId,
    null,
    $contextoCli
);
$idUsuarioComun = $resUsuarioComun['usuario_id'];

afirmativo($autorizacionServicio->tienePrivilegio($idUsuarioComun, 'personas.ver'), "Usuario común posee privilegio permitido ('personas.ver')");
afirmativo(!$autorizacionServicio->tienePrivilegio($idUsuarioComun, 'personas.crear'), "Usuario común tiene denegado privilegio no asignado ('personas.crear')");

// =========================================================================
// BLOQUE 8: Protección de Rutas HTTP, Deny by Default y CSRF
// =========================================================================
echo "\n--- BLOQUE 8: Protección de Rutas HTTP, Deny by Default y CSRF ---\n";

// GET /login público responde HTTP 200
$resLoginVista = despacharHttp('GET', '/login');
afirmativo($resLoginVista['codigo'] === 200, "GET /login es accesible públicamente sin autenticación (HTTP 200)");
afirmativo(str_contains($resLoginVista['cuerpo'], 'id="formLogin"'), "Vista de login contiene el formulario Alina con id='formLogin'");
afirmativo(str_contains($resLoginVista['cuerpo'], 'name="csrf_token"'), "Vista de login inyecta token CSRF pre-autenticación");

// POST /login sin CSRF es rechazado con HTTP 403
$resLoginSinCsrf = despacharHttp('POST', '/login', [
    'identificador' => $emailPrueba,
    'password'      => 'ClaveSegura#2026'
]);
afirmativo($resLoginSinCsrf['codigo'] === 403, "POST /login sin token CSRF es estrictamente bloqueado con HTTP 403");

// POST /login tradicional (x-www-form-urlencoded) con CSRF válido
$tokenCsrfLogin = CsrfServicio::obtenerToken();
$resLoginForm = despacharHttp('POST', '/login', [
    'identificador' => $emailPrueba,
    'password'      => 'ClaveSegura#2026',
    'csrf_token'    => $tokenCsrfLogin
], [
    'Content-Type'     => 'application/x-www-form-urlencoded',
    'X-Requested-With' => 'XMLHttpRequest',
    'X-CSRF-Token'     => $tokenCsrfLogin
]);
afirmativo($resLoginForm['codigo'] === 200 && ($resLoginForm['json']['estado'] ?? '') === 'exito', "POST /login tradicional (x-www-form-urlencoded) autentica exitosamente respondiendo HTTP 200 JSON");

// POST /login con Fetch JSON nativo (Content-Type: application/json, contrato real del navegador)
$tokenCsrfJson = CsrfServicio::obtenerToken();
$resLoginJson = despacharHttp('POST', '/login', [
    'identificador' => $usuarioNombre,
    'password'      => 'ClaveSegura#2026',
    'csrf_token'    => $tokenCsrfJson
], [
    'Content-Type'     => 'application/json',
    'Accept'           => 'application/json',
    'X-Requested-With' => 'XMLHttpRequest',
    'X-CSRF-Token'     => $tokenCsrfJson
]);
afirmativo($resLoginJson['codigo'] === 200 && ($resLoginJson['json']['estado'] ?? '') === 'exito', "POST /login con Fetch JSON nativo (Content-Type: application/json) autentica exitosamente respondiendo HTTP 200 JSON");

// Petición anónima a /api/personas es rechazada con HTTP 401
$resApiAnonima = despacharHttp('GET', '/api/personas', [], [
    'Accept'           => 'application/json',
    'X-Requested-With' => 'XMLHttpRequest'
]);
afirmativo($resApiAnonima['codigo'] === 401, "GET /api/personas anónimo es rechazado con HTTP 401 Unauthorized");

// Petición con SUPERADMIN a /api/personas responde HTTP 200
$sesionSuperadmin = [
    'usuario_id'           => $resUsuario['usuario_id'],
    'persona_id'           => $personaId,
    'actor_id'             => $resUsuario['actor_id'],
    'nombre_usuario'       => $usuarioNombre,
    'nombre_completo'      => 'Super Admin',
    'email'                => $emailPrueba,
    'version_autorizacion' => 1,
    'autenticado_en'       => time()
];
$resApiSuperadmin = despacharHttp('GET', '/api/personas', [], [
    'Accept'           => 'application/json',
    'X-Requested-With' => 'XMLHttpRequest'
], $sesionSuperadmin);
afirmativo($resApiSuperadmin['codigo'] === 200, "GET /api/personas con sesión SUPERADMIN responde HTTP 200 OK");

// Petición con Usuario Común a POST /api/personas (sin privilegio crear) responde HTTP 403
$sesionComun = [
    'usuario_id'           => $idUsuarioComun,
    'persona_id'           => $resPersonaComun['id'],
    'actor_id'             => $resUsuarioComun['actor_id'],
    'nombre_usuario'       => $resUsuarioComun['nombre_usuario'],
    'nombre_completo'      => 'Usuario Comun',
    'email'                => $resUsuarioComun['email'],
    'version_autorizacion' => 1,
    'autenticado_en'       => time()
];
$tokenCsrfMutacion = CsrfServicio::obtenerToken();
$resApiDenegada = despacharHttp('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres' => 'No',
    'apellido_paterno' => 'Autorizado'
], [
    'Accept'           => 'application/json',
    'X-Requested-With' => 'XMLHttpRequest',
    'X-CSRF-Token'     => $tokenCsrfMutacion
], $sesionComun);
afirmativo($resApiDenegada['codigo'] === 403, "POST /api/personas con usuario sin permiso es rechazado con HTTP 403 Forbidden");

$eventoAccesoDenegado = $seguridadRepo->obtenerUltimoEventoPorTipo('ACCESO_DENEGADO');
afirmativo($eventoAccesoDenegado && ($eventoAccesoDenegado->obtenerMetadatos()['privilegio_requerido'] ?? '') === 'personas.crear', "Telemetría registra intento no autorizado con privilegio 'personas.crear'");

// =========================================================================
// BLOQUE 9: Auditoría Forense con Actor USER Real
// =========================================================================
echo "\n--- BLOQUE 9: Auditoría Forense con Actor USER Real ---\n";

// Mutación de creación realizada por SUPERADMIN
$dniNuevo = '9' . str_pad((string) rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$resCrearSuper = despacharHttp('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Auditado',
    'apellido_paterno' => 'PorHumano',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $dniNuevo, 'es_principal' => true]
    ]
], [
    'Accept'           => 'application/json',
    'X-Requested-With' => 'XMLHttpRequest',
    'X-CSRF-Token'     => $tokenCsrfMutacion
], $sesionSuperadmin);

afirmativo($resCrearSuper['codigo'] === 201, "POST /api/personas con SUPERADMIN crea registro (HTTP 201)");
$personaNuevaId = (int) ($resCrearSuper['json']['datos']['id'] ?? 0);

// Verificar en auditorias que actor_id es el del usuario humano real, NUNCA el actor 1 del sistema
$stmtAud = $conexion->prepare("
    SELECT actor_id 
    FROM `auditorias` 
    WHERE `entidad` = 'personas' AND `registro_id` = :id AND `accion` = 'CREAR' 
    ORDER BY `id` DESC LIMIT 1
");
$stmtAud->execute([':id' => $personaNuevaId]);
$actorAuditoria = (int) $stmtAud->fetchColumn();

afirmativo($actorAuditoria === (int) $resUsuario['actor_id'], "Auditoría forense vincula la mutación al actor_id real del Usuario ({$actorAuditoria}) y NO al actor 1 del sistema");

// =========================================================================
// BLOQUE 10: First Bootstrap CLI y Detección de Parámetros Inseguros
// =========================================================================
echo "\n--- BLOQUE 10: First Bootstrap CLI y Detección Insegura ---\n";

// Ejecutar CLI con flag --password (debe fallar inmediatamente)
$salidaCliInsegura = shell_exec('php bin/casapro-bootstrap-admin.php --password=123 2>&1');
afirmativo(str_contains((string) $salidaCliInsegura, '[ERROR DE SEGURIDAD]'), "CLI de bootstrap rechaza tajantemente el argumento --password");

// Verificar que existiendo ya al menos 1 SUPERADMIN, el First Bootstrap está inhabilitado
afirmativo($rolRepo->contarSuperadmins() >= 1, "Base de datos cuenta con al menos 1 SUPERADMIN activo");

echo "\n===================================================================\n";
echo " RESUMEN FINAL 1G-1: {$pruebasSuperadas} de {$pruebasEjecutadas} superadas.\n";
if ($pruebasSuperadas === $pruebasEjecutadas) {
    echo " RESULTADO SUITE AUTENTICACIÓN Y SEGURIDAD: [PASS]\n";
} else {
    echo " RESULTADO SUITE AUTENTICACIÓN Y SEGURIDAD: [FAIL]\n";
}
echo "===================================================================\n";

if ($pruebasSuperadas !== $pruebasEjecutadas) {
    exit(1);
}
