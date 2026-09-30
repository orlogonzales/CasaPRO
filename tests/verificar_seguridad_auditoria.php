<?php

declare(strict_types=1);

/**
 * Suite de Verificación de Seguridad, CSRF, Actores y Auditoría Transversal (Microfase 1C).
 */

define('CASAPRO_TESTING', true);
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Middlewares\CsrfMiddleware;
use App\Servicios\AuditoriaServicio;
use App\Modelos\Actor;
use App\Modelos\AuditoriaRegistro;

CargadorEntorno::cargar(dirname(__DIR__));
GestorSesion::iniciar();

echo "===============================================================\n";
echo " PRUEBAS DE SEGURIDAD, CSRF, ACTORES Y AUDITORÍA (FASE 1C)\n";
echo "===============================================================\n";

$pruebasEjecutadas = 0;
$pruebasSuperadas = 0;

function afirmativo(bool $condicion, string $descripcion): void
{
    global $pruebasEjecutadas, $pruebasSuperadas;
    $pruebasEjecutadas++;
    if ($condicion) {
        $pruebasSuperadas++;
        echo "  [PASS] {$descripcion}\n";
    } else {
        echo "  [FAIL] {$descripcion}\n";
    }
}

// -----------------------------------------------------------------------------
// BLOQUE 1: Gestor de Sesiones y Configuración de Seguridad
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 1: Gestor de Sesiones y Configuración Estricta ---\n";
afirmativo(GestorSesion::estaIniciada(), 'GestorSesion inicia sesión correctamente');
afirmativo(ini_get('session.use_strict_mode') === '1', 'session.use_strict_mode activado en 1');
afirmativo(ini_get('session.use_only_cookies') === '1', 'session.use_only_cookies activado en 1');

GestorSesion::establecer('clave_prueba', 'valor_seguro');
afirmativo(GestorSesion::obtener('clave_prueba') === 'valor_seguro', 'GestorSesion almacena y recupera valores');
GestorSesion::eliminar('clave_prueba');
afirmativo(!GestorSesion::tiene('clave_prueba'), 'GestorSesion elimina valores correctamente');

// -----------------------------------------------------------------------------
// BLOQUE 2: Contexto de Petición y Trazabilidad (ID de Correlación)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Contexto de Petición y Correlación ---\n";

$contexto = new ContextoPeticion('REQ-CUSTOM-123456', '192.168.1.50', 'TestAgent/1.0', 'WEB', 1);
afirmativo($contexto->obtenerIdCorrelacion() === 'REQ-CUSTOM-123456', 'Contexto conserva ID de correlación válido');
afirmativo($contexto->obtenerIp() === '192.168.1.50', 'Contexto valida y retorna IP del cliente');
afirmativo($contexto->obtenerUserAgent() === 'TestAgent/1.0', 'Contexto retorna User-Agent');
afirmativo($contexto->obtenerOrigen() === 'WEB', 'Contexto valida origen WEB');
afirmativo($contexto->obtenerActorId() === 1, 'Contexto asigna actorId por defecto (1: SISTEMA)');

$contextoAutogenerado = new ContextoPeticion();
afirmativo(str_starts_with($contextoAutogenerado->obtenerIdCorrelacion(), 'REQ-'), 'Contexto autogenera ID criptográfico con prefijo REQ-');

// -----------------------------------------------------------------------------
// BLOQUE 3: Servicio de Tokens CSRF
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Criptografía y Ciclo de Vida de Tokens CSRF ---\n";

$tokenOriginal = CsrfServicio::obtenerToken();
afirmativo(strlen($tokenOriginal) === 64, 'Token CSRF generado tiene 64 caracteres hexadecimales (32 bytes)');
afirmativo(CsrfServicio::validarToken($tokenOriginal), 'Token CSRF original valida exitosamente con hash_equals');
afirmativo(!CsrfServicio::validarToken('token_falso_invalido_123'), 'Token CSRF arbitrario es rechazado');
afirmativo(!CsrfServicio::validarToken(null), 'Token CSRF nulo es rechazado');
afirmativo(!CsrfServicio::validarToken(''), 'Token CSRF vacío es rechazado');

$tokenRotado = CsrfServicio::regenerarToken();
afirmativo($tokenRotado !== $tokenOriginal, 'CsrfServicio regenera un nuevo token diferente');
afirmativo(CsrfServicio::validarToken($tokenRotado), 'Nuevo token CSRF es validado correctamente');
afirmativo(!CsrfServicio::validarToken($tokenOriginal), 'Token CSRF anterior queda invalidado tras la rotación');

// -----------------------------------------------------------------------------
// BLOQUE 4: Middleware CSRF (Pruebas Positivas y Negativas)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Middleware CSRF y Prohibición de Bypass Bearer ---\n";

$middlewareCsrf = new CsrfMiddleware();

// 4.1. Métodos de lectura (GET/HEAD) no requieren CSRF
$_SERVER['REQUEST_METHOD'] = 'GET';
$peticionGet = new Peticion();
$respuestaGet = new Respuesta();
afirmativo($middlewareCsrf->procesar($peticionGet, $respuestaGet, $contexto), 'Peticiones GET pasan sin requerir CSRF');

// 4.2. Mutación POST con token en cabecera HTTP X-CSRF-Token
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = $tokenRotado;
$_POST = [];
$peticionPostHeader = new Peticion();
$respuestaPostHeader = new Respuesta();
afirmativo($middlewareCsrf->procesar($peticionPostHeader, $respuestaPostHeader, $contexto), 'Petición POST con X-CSRF-Token válido en cabecera es admitida');

// 4.3. Mutación POST con token en cuerpo _csrf_token
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$_POST['_csrf_token'] = $tokenRotado;
$peticionPostBody = new Peticion();
$respuestaPostBody = new Respuesta();
afirmativo($middlewareCsrf->procesar($peticionPostBody, $respuestaPostBody, $contexto), 'Petición POST con _csrf_token válido en cuerpo es admitida');

// 4.4. Mutación POST con token manipulado / inválido
$_POST['_csrf_token'] = 'token_manipulado_ataque';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$peticionInvalida = new Peticion();
$respuestaInvalida = new Respuesta();
ob_start();
$resultado4 = $middlewareCsrf->procesar($peticionInvalida, $respuestaInvalida, $contexto);
ob_end_clean();
afirmativo($resultado4 === false, 'Petición POST con token CSRF inválido es rechazada con código 403');

// 4.5. Mutación POST con cabecera Authorization: Bearer pero token CSRF ausente
// OBLIGATORIO: Estrictamente PROHIBIDO bypass genérico por Bearer
$_POST = [];
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.tokenFalso';
$peticionBearer = new Peticion();
$respuestaBearer = new Respuesta();
ob_start();
$resultado5 = $middlewareCsrf->procesar($peticionBearer, $respuestaBearer, $contexto);
ob_end_clean();
afirmativo($resultado5 === false, 'Prohibición estricta de Bypass Bearer: Mutación con Bearer sin CSRF es bloqueada (403)');
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_ACCEPT'], $_POST['_csrf_token']);

// -----------------------------------------------------------------------------
// BLOQUE 5: Semilla y Modelo de Actores
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Modelo y Catálogo de Actores ---\n";

$proveedor = new ProveedorConexion();
$pdo = $proveedor->obtenerConexion();

$stmtActor = $pdo->prepare("SELECT * FROM `actores` WHERE `codigo` = 'SISTEMA_CASAPRO'");
$stmtActor->execute();
$filaActor = $stmtActor->fetch(PDO::FETCH_ASSOC);

afirmativo(!empty($filaActor), 'Actor raíz SISTEMA_CASAPRO existe en la base de datos');
afirmativo((int) $filaActor['id'] === 1, 'Actor SISTEMA_CASAPRO posee ID primario 1');
afirmativo($filaActor['tipo_actor'] === 'SISTEMA', 'Actor SISTEMA_CASAPRO es de tipo SISTEMA');
afirmativo($filaActor['estado'] === 'ACTIVO', 'Actor SISTEMA_CASAPRO se encuentra ACTIVO');

$actorModelo = Actor::desdeArray($filaActor);
afirmativo($actorModelo->obtenerCodigo() === 'SISTEMA_CASAPRO', 'Modelo Actor hidrata correctamente desde array asociativo');

// -----------------------------------------------------------------------------
// BLOQUE 6: AuditoriaServicio (Sanitización y Minimización de Snapshots)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: Minimización de Snapshots y Sanitización Recursiva ---\n";

$auditoriaServicio = new AuditoriaServicio($proveedor, $contexto);

// Prueba de sanitización recursiva de datos confidenciales
$datosConClavesSensibles = [
    'usuario' => 'operador_demo',
    'password' => 'Secreto123!',
    'credenciales' => [
        'api_key' => 'live_sk_98374982374982374',
        'token_acceso' => 'jwt.token.secreto',
        'pin_seguridad' => '9988'
    ],
    'datos_publicos' => [
        'nombres' => 'Juan Pérez',
        'cvv' => '123'
    ]
];

$sanitizado = $auditoriaServicio->sanitizarRecursivo($datosConClavesSensibles);
afirmativo($sanitizado['password'] === '[PROTEGIDO]', 'Sanitización oculta password como [PROTEGIDO]');
afirmativo($sanitizado['credenciales']['api_key'] === '[PROTEGIDO]', 'Sanitización recursiva oculta api_key como [PROTEGIDO]');
afirmativo($sanitizado['credenciales']['token_acceso'] === '[PROTEGIDO]', 'Sanitización recursiva oculta token_acceso como [PROTEGIDO]');
afirmativo($sanitizado['credenciales']['pin_seguridad'] === '[PROTEGIDO]', 'Sanitización recursiva oculta pin_seguridad como [PROTEGIDO]');
afirmativo($sanitizado['datos_publicos']['cvv'] === '[PROTEGIDO]', 'Sanitización recursiva oculta cvv como [PROTEGIDO]');
afirmativo($sanitizado['datos_publicos']['nombres'] === 'Juan Pérez', 'Sanitización preserva datos no confidenciales intactos');

// Inmutabilidad a nivel de aplicación en AuditoriaServicio
$metodosReflexion = (new ReflectionClass(AuditoriaServicio::class))->getMethods();
$nombresMetodos = array_map(fn($m) => $m->getName(), $metodosReflexion);
$metodosProhibidos = ['actualizar', 'modificar', 'eliminar', 'borrar', 'purgar', 'vaciar', 'delete', 'update'];
$tieneProhibidos = false;
foreach ($metodosProhibidos as $prohibido) {
    if (in_array($prohibido, $nombresMetodos, true)) {
        $tieneProhibidos = true;
        break;
    }
}
afirmativo(!$tieneProhibidos, 'AuditoriaServicio garantiza inmutabilidad: no expone métodos de modificación o eliminación (Append-Only)');

// -----------------------------------------------------------------------------
// BLOQUE 7: Registro Atómico y Transacción Compartida con Dominio
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 7: Atomicidad Transaccional (Rollback Conjunto de Dominio y Auditoría) ---\n";

// 7.1. Registro exitoso en transacción compartida
$pdo->beginTransaction();
$idRegistroAuditado = $auditoriaServicio->registrar([
    'modulo' => 'identidad',
    'entidad' => 'personas',
    'registro_id' => 99901,
    'accion' => 'CREAR',
    'resultado' => 'EXITO',
    'datos_anteriores' => null,
    'datos_nuevos' => ['nombres' => 'Prueba Transaccional', 'password' => 'P@ssword!'],
    'metadatos' => ['prueba' => 'fase_1c_exito']
], $pdo);
$pdo->commit();

afirmativo($idRegistroAuditado > 0, "Evento de auditoría registrado exitosamente con ID {$idRegistroAuditado}");

$stmtVerificacion = $pdo->prepare("SELECT * FROM `auditorias` WHERE `id` = :id");
$stmtVerificacion->execute([':id' => $idRegistroAuditado]);
$filaAuditada = $stmtVerificacion->fetch(PDO::FETCH_ASSOC);

afirmativo(!empty($filaAuditada), 'Fila de auditoría recuperada de la base de datos');
afirmativo($filaAuditada['id_correlacion'] === $contexto->obtenerIdCorrelacion(), 'Fila de auditoría vinculada al ID de correlación de la petición');
$datosNuevosBD = json_decode($filaAuditada['datos_nuevos'], true);
afirmativo($datosNuevosBD['password'] === '[PROTEGIDO]', 'Fila persistida en BD tiene el campo password protegido');

// 7.2. Minimización de snapshots en actualización
$idAuditActualizar = $auditoriaServicio->registrar([
    'modulo' => 'identidad',
    'entidad' => 'personas',
    'registro_id' => 99901,
    'accion' => 'ACTUALIZAR',
    'datos_anteriores' => ['telefono' => '999111222', 'email' => 'anterior@correo.com', 'pais' => 'PE'],
    'datos_nuevos' => ['telefono' => '999333444', 'email' => 'anterior@correo.com', 'pais' => 'PE']
]);

$stmtVerifAct = $pdo->prepare("SELECT * FROM `auditorias` WHERE `id` = :id");
$stmtVerifAct->execute([':id' => $idAuditActualizar]);
$filaAct = $stmtVerifAct->fetch(PDO::FETCH_ASSOC);
$antMin = json_decode($filaAct['datos_anteriores'], true);
$nueMin = json_decode($filaAct['datos_nuevos'], true);

afirmativo(isset($antMin['telefono']) && isset($nueMin['telefono']), 'Minimización almacena el campo mutado (telefono)');
afirmativo(!isset($antMin['email']) && !isset($antMin['pais']), 'Minimización descarta campos idénticos sin cambio (email, pais)');

// 7.3. Rollback Atómico: Si ocurre fallo en la transacción, ni el dominio ni la auditoría persisten
$pdo->beginTransaction();
$idAuditoriaNoPersistida = $auditoriaServicio->registrar([
    'modulo' => 'identidad',
    'entidad' => 'personas',
    'registro_id' => 99902,
    'accion' => 'CREAR',
    'datos_nuevos' => ['nombres' => 'Registro Fantasma que debe cancelarse']
], $pdo);

// Simular falla de negocio que detona rollback
$pdo->rollBack();

$stmtFantasma = $pdo->prepare("SELECT * FROM `auditorias` WHERE `id` = :id");
$stmtFantasma->execute([':id' => $idAuditoriaNoPersistida]);
$filaFantasma = $stmtFantasma->fetch(PDO::FETCH_ASSOC);

afirmativo(empty($filaFantasma), 'Atomicidad garantizada: Rollback de la transacción descartó el registro de auditoría');

// Limpieza de datos de prueba
$pdo->exec("DELETE FROM `auditorias` WHERE `registro_id` IN (99901, 99902)");

echo "\n===============================================================\n";
echo " RESUMEN FINAL: {$pruebasSuperadas} de {$pruebasEjecutadas} pruebas superadas.\n";
if ($pruebasSuperadas === $pruebasEjecutadas) {
    echo " RESULTADO SUITE SEGURIDAD Y AUDITORÍA: [PASS]\n";
} else {
    echo " RESULTADO SUITE SEGURIDAD Y AUDITORÍA: [FAIL]\n";
}
echo "===============================================================\n";

exit($pruebasSuperadas === $pruebasEjecutadas ? 0 : 1);
