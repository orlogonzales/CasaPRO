<?php

declare(strict_types=1);

/**
 * Suite de Verificación Exhaustiva — Microfase 2B: Asignaciones Usuario ↔ Empresa y Scopes Territoriales
 *
 * Valida:
 * 1. Modelo de Dominio UsuarioEmpresaRol (estados, ciclos de vigencia, inmutabilidad).
 * 2. DTOs estrictos de asignación y revocación.
 * 3. Persistencia PDO AsignacionTerritorialRepositorio (cero DELETE).
 * 4. Servicio AutorizacionServicio con resolución multidimensional (GLOBAL vs EMPRESA).
 * 5. Multi-rol contextualizado (Usuario A es Admin en Empresa 1 y Vendedor en Empresa 2).
 * 6. Tratamiento de SUPERADMIN (acceso territorial universal condicionado a empresa activa).
 * 7. Empresa inactiva rechazada fail-closed (409 Conflict).
 * 8. ScopeMiddleware con validación soberana de sesión.
 * 9. Gate Anti-IDOR / Anti-Tampering (discordancia de empresa_id cliente vs sesión -> 403 Forbidden).
 * 10. Incremento de version_autorizacion por usuario e invalidación en caliente de sesión desactualizada.
 * 11. Auditoría forense inmutable con actores vinculados (ON DELETE RESTRICT).
 * 12. Aislamiento estricto de base de datos casapro_test y DELTA casapro = 0.
 */

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
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\AsignacionTerritorialRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Servicios\AutorizacionServicio;
use App\Servicios\AuditoriaServicio;
use App\Middlewares\AutenticacionMiddleware;
use App\Middlewares\AutorizacionMiddleware;
use App\Middlewares\ScopeMiddleware;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;

echo "===================================================================\n";
echo " VERIFICACIÓN MICROFASE 2B: ASIGNACIONES Y SCOPES TERRITORIALES\n";
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

// 1. Inicializar ambiente aislado de testing
$pdo = AmbientePruebas::iniciar(true);
$dbActual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
probar($dbActual === 'casapro_test', "Entorno aislado en base de datos 'casapro_test' (actual: {$dbActual})");

// Capturar métricas iniciales de la base de desarrollo para verificar DELTA casapro = 0
$configDev = require dirname(__DIR__) . '/config/database.php';
$configDev['database'] = 'casapro';
$pdoDev = (new ProveedorConexion($configDev))->obtenerConexion();
$conteoUsuariosDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuarios`')->fetchColumn();
$conteoAsignacionesDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuario_empresa_roles`')->fetchColumn();

// Instanciar repositorios y servicios sobre casapro_test
$provTest = new ProveedorConexion(['database' => 'casapro_test']);
$usuarioRepo = new UsuarioRepositorio($provTest);
$rolRepo = new RolRepositorio($provTest);
$empresaRepo = new EmpresaRepositorio($provTest);
$asigRepo = new AsignacionTerritorialRepositorio($provTest);
$personaRepo = new PersonaRepositorio($provTest);
$auditoriaServicio = new AuditoriaServicio($provTest);
$autorizacionServicio = new AutorizacionServicio(
    $provTest,
    $usuarioRepo,
    $rolRepo,
    $empresaRepo,
    $asigRepo,
    $auditoriaServicio
);

// -----------------------------------------------------------------------------
// BLOQUE 1: Modelo de Dominio UsuarioEmpresaRol
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 1: Modelo de Dominio UsuarioEmpresaRol ---\n";

$uer = new UsuarioEmpresaRol(10, 20, 30, 1, UsuarioEmpresaRol::ESTADO_ACTIVO);
probar($uer->obtenerUsuarioId() === 10, 'UsuarioEmpresaRol inicializa usuarioId correctamente');
probar($uer->obtenerEmpresaId() === 20, 'UsuarioEmpresaRol inicializa empresaId correctamente');
probar($uer->obtenerRolId() === 30, 'UsuarioEmpresaRol inicializa rolId correctamente');
probar($uer->obtenerAsignadoPor() === 1, 'UsuarioEmpresaRol inicializa asignadoPor correctamente');
probar($uer->estaActivo() === true, 'UsuarioEmpresaRol estado inicial es ACTIVO');

$uer->revocar(1);
probar($uer->estaActivo() === false, 'revocar() cambia estado a INACTIVO');
probar($uer->obtenerRevocadoPor() === 1, 'revocar() registra revocadoPor');
probar($uer->obtenerRevocadoEn() !== null, 'revocar() registra timestamp revocadoEn');

$uer->reactivar(2);
probar($uer->estaActivo() === true, 'reactivar() cambia estado a ACTIVO');
probar($uer->obtenerAsignadoPor() === 2, 'reactivar() actualiza asignadoPor');
probar($uer->obtenerRevocadoPor() === null, 'reactivar() limpia revocadoPor');
probar($uer->obtenerRevocadoEn() === null, 'reactivar() limpia revocadoEn');

// -----------------------------------------------------------------------------
// BLOQUE 2: Fixtures de Dominio en casapro_test (Usuarios, Empresas y Roles)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Configuración de Fixtures en casapro_test ---\n";

// Crear Roles adicionales de negocio: ADMINISTRADOR y VENDEDOR
$stmtRol = $pdo->prepare("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `es_sistema`, `estado`) VALUES 
    (2, 'ADMINISTRADOR', 'Administrador Corporativo', 0, 'ACTIVO'),
    (3, 'VENDEDOR', 'Asesor Comercial Inmobiliario', 0, 'ACTIVO')
    ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)");
$stmtRol->execute();

// Vincular privilegios a roles:
// ADMINISTRADOR tiene 'empresas.editar' (ID 21), 'usuarios.ver' (ID 6)
// VENDEDOR tiene 'personas.ver' (ID 1)
$stmtRolPriv = $pdo->prepare("INSERT INTO `rol_privilegios` (`rol_id`, `privilegio_id`) VALUES 
    (2, 21), (2, 6), (3, 1)
    ON DUPLICATE KEY UPDATE `rol_id` = VALUES(`rol_id`)");
$stmtRolPriv->execute();

// Crear Personas y Empresas:
// Empresa 1: Matriz Bonifacio (ACTIVA)
// Empresa 2: CasaPRO (ACTIVA)
// Empresa 3: Inmobiliaria Sur (INACTIVA)
$pdo->exec("INSERT INTO `personas` (`id`, `tipo_persona`, `estado`) VALUES 
    (101, 'JURIDICA', 'ACTIVO'),
    (102, 'JURIDICA', 'ACTIVO'),
    (103, 'JURIDICA', 'ACTIVO'),
    (201, 'NATURAL', 'ACTIVO'),
    (202, 'NATURAL', 'ACTIVO')");

$pdo->exec("INSERT INTO `persona_juridica` (`persona_id`, `razon_social`, `nombre_comercial`) VALUES 
    (101, 'GRUPO BONIFACIO S.A.C.', 'Matriz Bonifacio'),
    (102, 'CASAPRO DESARROLLOS S.A.C.', 'CasaPRO'),
    (103, 'INMOBILIARIA SUR S.A.C.', 'Inmobiliaria Sur')");

$pdo->exec("INSERT INTO `persona_natural` (`persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`) VALUES 
    (201, 'Juan', 'Pérez', 'Gómez'),
    (202, 'Carlos', 'Superadmin', 'Soberano')");

$empresa1Id = $empresaRepo->insertar(new Empresa(101, 'MATRIZ_BONIFACIO', 'Bonifacio', Empresa::ESTADO_ACTIVO), $pdo);
$empresa2Id = $empresaRepo->insertar(new Empresa(102, 'CASAPRO', 'CasaPRO', Empresa::ESTADO_ACTIVO), $pdo);
$empresa3Id = $empresaRepo->insertar(new Empresa(103, 'INMOBILIARIA_SUR', 'InmoSur', Empresa::ESTADO_INACTIVO), $pdo);

probar($empresa1Id > 0 && $empresa2Id > 0 && $empresa3Id > 0, 'Empresas fixtures insertadas (2 activas, 1 inactiva)');

// Crear Usuarios:
// Usuario 10: Juan Pérez (Usuario regular multiempresa)
// Usuario 11: Superadmin (Usuario con rol SUPERADMIN en usuario_roles)
$actorJuanId = 10;
$actorAdminId = 11;
$pdo->exec("INSERT INTO `actores` (`id`, `tipo_actor`, `codigo`, `nombre`, `estado`) VALUES 
    ({$actorJuanId}, 'USUARIO', 'USR_JUAN', 'Juan Pérez', 'ACTIVO'),
    ({$actorAdminId}, 'USUARIO', 'USR_SUPER', 'Super Admin', 'ACTIVO')");

$usuarioJuan = new Usuario(
    201,
    $actorJuanId,
    'juan@bonifacio.pe',
    'juan_perez',
    password_hash('Password123!', PASSWORD_BCRYPT),
    Usuario::ESTADO_ACTIVO
);
$usuarioJuanId = $usuarioRepo->insertar($usuarioJuan, $pdo);

$usuarioSuper = new Usuario(
    202,
    $actorAdminId,
    'super@casapro.pe',
    'superadmin_test',
    password_hash('Password123!', PASSWORD_BCRYPT),
    Usuario::ESTADO_ACTIVO
);
$usuarioSuperId = $usuarioRepo->insertar($usuarioSuper, $pdo);
// Asignar rol SUPERADMIN global en usuario_roles
$usuarioRepo->asignarRol($usuarioSuperId, 1, $pdo);

probar($usuarioJuanId > 0, 'Usuario regular Juan Pérez creado correctamente');
probar($usuarioSuperId > 0, 'Usuario SUPERADMIN creado correctamente y asignación en usuario_roles');

// -----------------------------------------------------------------------------
// BLOQUE 3: Servicio de Asignaciones Territoriales (2B)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Servicio de Asignaciones Territoriales ---\n";

$contextoCLI = new ContextoPeticion('REQ-TEST-2B-01', '127.0.0.1', 'PHPUnit/CLI', 'CLI', 1);

// 1. Asignar Juan Pérez como ADMINISTRADOR (rol 2) en Matriz Bonifacio (empresa 1)
$resAsig1 = $autorizacionServicio->asignarRolEmpresa(
    new AsignarRolEmpresaDTO($usuarioJuanId, $empresa1Id, 2, 'Alta como Administrador en Bonifacio'),
    1,
    $contextoCLI
);
probar($resAsig1['id'] > 0, 'Asignación 1 formalizada: Juan -> Bonifacio -> ADMINISTRADOR');
probar($resAsig1['version_autorizacion'] === 2, 'version_autorizacion de Juan incrementada a 2');

// 2. Asignar Juan Pérez como VENDEDOR (rol 3) en CasaPRO (empresa 2)
$resAsig2 = $autorizacionServicio->asignarRolEmpresa(
    new AsignarRolEmpresaDTO($usuarioJuanId, $empresa2Id, 3, 'Alta como Vendedor en CasaPRO'),
    1,
    $contextoCLI
);
probar($resAsig2['id'] > 0, 'Asignación 2 formalizada: Juan -> CasaPRO -> VENDEDOR');
probar($resAsig2['version_autorizacion'] === 3, 'version_autorizacion de Juan incrementada a 3');

// 3. Intento de asignar rol en empresa INACTIVA (Inmobiliaria Sur) -> 409 Conflict
$bloqueoEmpresaInactiva = false;
try {
    $autorizacionServicio->asignarRolEmpresa(
        new AsignarRolEmpresaDTO($usuarioJuanId, $empresa3Id, 3, 'Intento en empresa inactiva'),
        1,
        $contextoCLI
    );
} catch (ReglaNegocioExcepcion $e) {
    if ($e->getCode() === 409 && str_contains($e->getMessage(), 'inactiva')) {
        $bloqueoEmpresaInactiva = true;
    }
}
probar($bloqueoEmpresaInactiva, 'Rechazo con HTTP 409 al intentar asignar rol en empresa INACTIVA');

// 4. Intento de asignar rol SUPERADMIN a nivel de empresa -> 422
$bloqueoSuperadminEmpresa = false;
try {
    $autorizacionServicio->asignarRolEmpresa(
        new AsignarRolEmpresaDTO($usuarioJuanId, $empresa1Id, 1, 'Intento SUPERADMIN en empresa'),
        1,
        $contextoCLI
    );
} catch (ReglaNegocioExcepcion $e) {
    if ($e->getCode() === 422 && str_contains($e->getMessage(), 'GLOBAL')) {
        $bloqueoSuperadminEmpresa = true;
    }
}
probar($bloqueoSuperadminEmpresa, 'Rechazo con HTTP 422 al intentar asignar rol SUPERADMIN por empresa');

// 5. Intento de asignación duplicada activa -> 409 Conflict
$bloqueoDuplicada = false;
try {
    $autorizacionServicio->asignarRolEmpresa(
        new AsignarRolEmpresaDTO($usuarioJuanId, $empresa1Id, 2, 'Intento duplicado'),
        1,
        $contextoCLI
    );
} catch (ReglaNegocioExcepcion $e) {
    if ($e->getCode() === 409 && str_contains($e->getMessage(), 'ya cuenta con este rol activo')) {
        $bloqueoDuplicada = true;
    }
}
probar($bloqueoDuplicada, 'Rechazo con HTTP 409 ante asignación duplicada activa');

// -----------------------------------------------------------------------------
// BLOQUE 4: Resolución Multidimensional de Autorización (RBAC + Scope)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Resolución Multidimensional de Autorización ---\n";

// A. Juan Pérez en Matriz Bonifacio (ADMINISTRADOR):
// Tiene 'empresas.editar' y 'usuarios.ver'
probar(
    $autorizacionServicio->tienePrivilegio($usuarioJuanId, 'empresas.editar', 'EMPRESA', $empresa1Id),
    'Juan Pérez tiene privilegio empresas.editar en Matriz Bonifacio (PASS)'
);
probar(
    $autorizacionServicio->tienePrivilegio($usuarioJuanId, 'usuarios.ver', 'EMPRESA', $empresa1Id),
    'Juan Pérez tiene privilegio usuarios.ver en Matriz Bonifacio (PASS)'
);

// B. Juan Pérez en CasaPRO (VENDEDOR):
// Tiene 'personas.ver', NO tiene 'empresas.editar'
probar(
    $autorizacionServicio->tienePrivilegio($usuarioJuanId, 'personas.ver', 'EMPRESA', $empresa2Id),
    'Juan Pérez tiene privilegio personas.ver en CasaPRO (PASS)'
);
probar(
    !$autorizacionServicio->tienePrivilegio($usuarioJuanId, 'empresas.editar', 'EMPRESA', $empresa2Id),
    'Juan Pérez NO tiene privilegio empresas.editar en CasaPRO (DENY 403)'
);

// C. Aislamiento Territorial Estricto:
// Juan intenta usar privilegios de Vendedor en Matriz Bonifacio (donde es Admin)
probar(
    !$autorizacionServicio->tienePrivilegio($usuarioJuanId, 'personas.ver', 'EMPRESA', $empresa1Id),
    'Privilegios de CasaPRO no contaminan Matriz Bonifacio (DENY 403)'
);

// D. Juan en Empresa no asignada (o Inactiva):
probar(
    !$autorizacionServicio->tienePrivilegio($usuarioJuanId, 'personas.ver', 'EMPRESA', $empresa3Id),
    'Acceso denegado sobre empresa inactiva o no asignada (DENY 403)'
);

// E. SUPERADMIN: Bypass universal en empresas activas
probar(
    $autorizacionServicio->tienePrivilegio($usuarioSuperId, 'empresas.editar', 'EMPRESA', $empresa1Id),
    'SUPERADMIN tiene bypass universal en Matriz Bonifacio (PASS)'
);
probar(
    $autorizacionServicio->tienePrivilegio($usuarioSuperId, 'personas.ver', 'EMPRESA', $empresa2Id),
    'SUPERADMIN tiene bypass universal en CasaPRO (PASS)'
);
probar(
    !$autorizacionServicio->tienePrivilegio($usuarioSuperId, 'personas.ver', 'EMPRESA', $empresa3Id),
    'SUPERADMIN denegado en Empresa INACTIVA (Fail-Closed sobre empresa inactiva)'
);

// -----------------------------------------------------------------------------
// BLOQUE 5: Consultas de Ámbito y Empresas Disponibles (Selector 2D)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Consultas de Ámbito y Empresas Disponibles ---\n";

$empresasJuan = $autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuarioJuanId);
probar(count($empresasJuan) === 2, 'Juan Pérez tiene exactamente 2 empresas activas disponibles');
$codigosJuan = array_column($empresasJuan, 'codigo');
probar(in_array('MATRIZ_BONIFACIO', $codigosJuan, true) && in_array('CASAPRO', $codigosJuan, true), 'Empresas de Juan corresponden a Matriz Bonifacio y CasaPRO');

$empresasSuper = $autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuarioSuperId);
probar(count($empresasSuper) === 2, 'SUPERADMIN obtiene todas las empresas activas del catálogo');

$usuarioSinEmpresasId = 999;
$empresasVacio = $autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuarioSinEmpresasId);
probar(empty($empresasVacio), 'Usuario sin asignaciones obtiene lista vacía de empresas');

probar($autorizacionServicio->puedeAccederEmpresa($usuarioJuanId, $empresa1Id), 'puedeAccederEmpresa Juan -> Bonifacio es true');
probar($autorizacionServicio->puedeAccederEmpresa($usuarioJuanId, $empresa2Id), 'puedeAccederEmpresa Juan -> CasaPRO es true');
probar(!$autorizacionServicio->puedeAccederEmpresa($usuarioJuanId, $empresa3Id), 'puedeAccederEmpresa Juan -> Empresa Inactiva es false');

// -----------------------------------------------------------------------------
// BLOQUE 6: Revocación, Reactivación y Cero DELETE
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: Revocación, Reactivación e Inmutabilidad ---\n";

// Revocar rol ADMINISTRADOR a Juan en Bonifacio
$resRevoca = $autorizacionServicio->revocarRolEmpresa(
    new RevocarRolEmpresaDTO($usuarioJuanId, $empresa1Id, 2, 'Cese de funciones administrativas en Bonifacio'),
    1,
    $contextoCLI
);
probar($resRevoca['estado'] === UsuarioEmpresaRol::ESTADO_INACTIVO, 'Baja lógica ejecutada: estado = INACTIVO');
probar($resRevoca['version_autorizacion'] === 4, 'version_autorizacion incrementada a 4 por revocación');

// Comprobar que en base de datos NO se ejecutó DELETE
$conteoTotalAsignaciones = (int) $pdo->query("SELECT COUNT(*) FROM `usuario_empresa_roles` WHERE `usuario_id` = {$usuarioJuanId}")->fetchColumn();
probar($conteoTotalAsignaciones === 2, 'Inmutabilidad confirmada: cero DELETE físico en BD (2 filas persisten)');

// Comprobar que el acceso fue revocado de inmediato
probar(
    !$autorizacionServicio->tienePrivilegio($usuarioJuanId, 'empresas.editar', 'EMPRESA', $empresa1Id),
    'Revocación en caliente efectiva: Juan ya no tiene empresas.editar en Bonifacio'
);

// Reactivar rol ADMINISTRADOR a Juan en Bonifacio
$resReactiva = $autorizacionServicio->asignarRolEmpresa(
    new AsignarRolEmpresaDTO($usuarioJuanId, $empresa1Id, 2, 'Reincorporación de funciones administrativas'),
    1,
    $contextoCLI
);
probar($resReactiva['estado'] === UsuarioEmpresaRol::ESTADO_ACTIVO, 'Reactivación exitosa: estado = ACTIVO');
probar($resReactiva['version_autorizacion'] === 5, 'version_autorizacion incrementada a 5 por reactivación');
probar(
    $autorizacionServicio->tienePrivilegio($usuarioJuanId, 'empresas.editar', 'EMPRESA', $empresa1Id),
    'Permiso recuperado inmediatamente tras reactivación'
);

// -----------------------------------------------------------------------------
// BLOQUE 7: Middlewares (ScopeMiddleware y AutorizacionMiddleware)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 7: Middlewares de Scope y Autorización ---\n";

GestorSesion::iniciar();
$authJuan = [
    'usuario_id'           => $usuarioJuanId,
    'actor_id'             => $actorJuanId,
    'version_autorizacion' => 5,
    'nombre_usuario'       => 'juan_perez'
];
GestorSesion::establecer('auth', $authJuan);

$scopeMiddleware = new ScopeMiddleware(null, $autorizacionServicio, $empresaRepo);

// Caso A: Petición sin empresa activa fijada en sesión -> 409 Conflict
GestorSesion::eliminar('contexto_empresa_id');
$peticionA = new Peticion('GET', '/api/ventas/listar', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respA = new Respuesta();
ob_start();
$pasoA = $scopeMiddleware->procesar($peticionA, $respA);
ob_end_clean();
probar($pasoA === false, 'ScopeMiddleware rechaza petición sin contexto de empresa en sesión');
probar($respA->obtenerCodigoEstado() === 409, 'Respuesta HTTP 409 Conflict ante ausencia de empresa activa');

// Caso B: Petición con empresa activa legítima en sesión (CasaPRO: 2) -> PASS
GestorSesion::establecer('contexto_empresa_id', $empresa2Id);
$peticionB = new Peticion('GET', '/api/ventas/listar', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respB = new Respuesta();
$contextoB = ContextoPeticion::crearDesdeEntorno($peticionB);
$pasoB = $scopeMiddleware->procesar($peticionB, $respB, $contextoB);
probar($pasoB === true, 'ScopeMiddleware aprueba petición con empresa autorizada en sesión (PASS)');
probar($contextoB->obtenerEmpresaId() === $empresa2Id, 'ContextoPeticion contiene el empresa_id validado');
probar($contextoB->obtenerScopeTipo() === 'EMPRESA', 'ContextoPeticion scopeTipo establecido en EMPRESA');

// Caso C: GATE ANTI-IDOR / ANTI-TAMPERING
// Sesión tiene empresa 2 (CasaPRO), pero el cliente envía empresa_id=1 (Bonifacio) en JSON/GET -> 403 Forbidden
$peticionC = new Peticion('POST', '/api/ventas/crear', ['empresa_id' => $empresa1Id], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respC = new Respuesta();
ob_start();
$pasoC = $scopeMiddleware->procesar($peticionC, $respC);
ob_end_clean();
probar($pasoC === false, 'Gate Anti-IDOR: ScopeMiddleware detecta y bloquea manipulación de empresa_id del cliente');
probar($respC->obtenerCodigoEstado() === 403, 'Gate Anti-IDOR: Respuesta estricta HTTP 403 Forbidden');

// Caso D: Gate Anti-IDOR con parámetro en query string (?empresa_id=1) -> 403 Forbidden
$peticionD = new Peticion('GET', '/api/ventas/listar', [], ['empresa_id' => (string) $empresa1Id], [], ['HTTP_ACCEPT' => 'application/json']);
$respD = new Respuesta();
ob_start();
$pasoD = $scopeMiddleware->procesar($peticionD, $respD);
ob_end_clean();
probar($pasoD === false, 'Gate Anti-IDOR: ScopeMiddleware bloquea discordancia en Query String');
probar($respD->obtenerCodigoEstado() === 403, 'Gate Anti-IDOR en Query: HTTP 403 Forbidden');

// Caso E: Empresa Activa INACTIVA en BD -> 409 Conflict
GestorSesion::establecer('contexto_empresa_id', $empresa3Id);
$peticionE = new Peticion('GET', '/api/ventas/listar', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respE = new Respuesta();
ob_start();
$pasoE = $scopeMiddleware->procesar($peticionE, $respE);
ob_end_clean();
probar($pasoE === false, 'ScopeMiddleware rechaza empresa activa si está INACTIVA');
probar($respE->obtenerCodigoEstado() === 409, 'Respuesta HTTP 409 Conflict ante empresa inactiva');

// Caso F: AutorizacionMiddleware con scope EMPRESA
GestorSesion::establecer('contexto_empresa_id', $empresa2Id); // CasaPRO: Juan es Vendedor
$autoMiddlewareVendedor = AutorizacionMiddleware::exigirEmpresa('personas.ver');
$peticionF1 = new Peticion('GET', '/api/personas', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respF1 = new Respuesta();
probar($autoMiddlewareVendedor->procesar($peticionF1, $respF1), 'AutorizacionMiddleware aprueba personas.ver en CasaPRO');

$autoMiddlewareAdmin = AutorizacionMiddleware::exigirEmpresa('empresas.editar');
$peticionF2 = new Peticion('POST', '/api/empresas/editar', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respF2 = new Respuesta();
ob_start();
$pasoF2 = $autoMiddlewareAdmin->procesar($peticionF2, $respF2);
ob_end_clean();
probar(!$pasoF2, 'AutorizacionMiddleware deniega empresas.editar en CasaPRO');
probar($respF2->obtenerCodigoEstado() === 403, 'AutorizacionMiddleware responde HTTP 403 Forbidden');

// -----------------------------------------------------------------------------
// BLOQUE 8: Auditoría Forense y FKs de Actores (ON DELETE RESTRICT)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 8: Auditoría Forense y Trazabilidad de Actores ---\n";

$conteoAuditorias = (int) $pdo->query("SELECT COUNT(*) FROM `auditorias` WHERE `modulo` = 'seguridad' AND `entidad` = 'usuario_empresa_roles'")->fetchColumn();
probar($conteoAuditorias >= 4, "Trazabilidad forense registrada en tabla auditorias (total registros: {$conteoAuditorias})");

$stmtAudAcciones = $pdo->query("SELECT DISTINCT `accion` FROM `auditorias` WHERE `entidad` = 'usuario_empresa_roles'");
$acciones = $stmtAudAcciones->fetchAll(PDO::FETCH_COLUMN) ?: [];
probar(in_array('ASIGNAR_ROL_EMPRESA', $acciones, true), 'Acción ASIGNAR_ROL_EMPRESA registrada en bitácora forense');
probar(in_array('REVOCAR_ROL_EMPRESA', $acciones, true), 'Acción REVOCAR_ROL_EMPRESA registrada en bitácora forense');
probar(in_array('REACTIVAR_ROL_EMPRESA', $acciones, true), 'Acción REACTIVAR_ROL_EMPRESA registrada en bitácora forense');

// Verificar integridad de claves foráneas de actores:
// Intentar borrar físicamente el actor 1 (SISTEMA_CASAPRO) que tiene asignaciones asignadas -> debe abortar por FK
$bloqueoFkActor = false;
try {
    $pdo->exec("DELETE FROM `actores` WHERE `id` = 1");
} catch (\PDOException $e) {
    if (str_contains($e->getMessage(), 'Integrity constraint violation') || str_contains($e->getMessage(), '1451')) {
        $bloqueoFkActor = true;
    }
}
probar($bloqueoFkActor, 'ON DELETE RESTRICT en fk_uer_actor_asigna impide eliminar actor con asignaciones');

// -----------------------------------------------------------------------------
// BLOQUE 9: Aislamiento y DELTA casapro = 0
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 9: Aislamiento y DELTA casapro = 0 ---\n";

$conteoUsuariosDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuarios`')->fetchColumn();
$conteoAsignacionesDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuario_empresa_roles`')->fetchColumn();

$deltaUsuarios = $conteoUsuariosDevFinal - $conteoUsuariosDevInicial;
$deltaAsignaciones = $conteoAsignacionesDevFinal - $conteoAsignacionesDevInicial;

probar($deltaUsuarios === 0, "DELTA usuarios en desarrollo casapro = 0 (actual: {$deltaUsuarios})");
probar($deltaAsignaciones === 0, "DELTA asignaciones en desarrollo casapro = 0 (actual: {$deltaAsignaciones})");

echo "\n===================================================================\n";
echo " RESUMEN FINAL: {$pruebasSuperadas} de {$totalPruebas} pruebas superadas [PASS]\n";
echo "===================================================================\n";
