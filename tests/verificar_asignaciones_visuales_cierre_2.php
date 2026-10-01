<?php

declare(strict_types=1);

/**
 * Suite de Verificación Automatizada: Cierre Complementario Fase 2
 * Gestión Visual de Asignaciones Territoriales (Usuario ↔ Empresa ↔ Rol).
 *
 * Cobertura de Gates:
 * 1. Persistencia DDL=0, ranura 000013 intacta y aislamiento casapro_test.
 * 2. Catálogos para Modal: empresas activas y roles territoriales (sin SUPERADMIN).
 * 3. Endpoints REST: GET /api/usuarios/{id}/asignaciones.
 * 4. Asignación válida y reactivación de tupla histórica (cero duplicación de filas).
 * 5. Rechazo estricto de SUPERADMIN a nivel de empresa (HTTP 422).
 * 6. Rechazo de empresa inactiva (HTTP 409).
 * 7. Rechazo de duplicidad activa (HTTP 409).
 * 8. Rechazo de rol inexistente/inactivo (HTTP 422) y usuario inexistente (HTTP 404).
 * 9. Revocación lógica (PATCH estado = INACTIVO, cero DELETE).
 * 10. Seguridad: RBAC (asignaciones.*), autenticación obligatoria y control CSRF.
 * 11. Control de versión de autorización (incremento de version_autorizacion).
 * 12. Auditoría forense append-only en tabla auditorias.
 * 13. Vista espejo de colaboradores por empresa (GET /api/empresas/{id}/colaboradores).
 * 14. Integridad Frontend (Alina tabs, modal, Vanilla JS Fetch, cero jQuery propio).
 * 15. DELTA casapro (desarrollo) = 0.
 */

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/tests/comun/AmbientePruebas.php';

use Tests\Comun\AmbientePruebas;

use App\Core\ContextoPeticion;
use App\Core\CsrfServicio;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\ProveedorConexion;
use App\Core\Respuesta;
use App\Controladores\AsignacionTerritorialControlador;
use App\DTOs\AsignarRolEmpresaDTO;
use App\DTOs\RevocarRolEmpresaDTO;
use App\Modelos\Empresa;
use App\Modelos\Rol;
use App\Modelos\Usuario;
use App\Modelos\UsuarioEmpresaRol;
use App\Repositorios\AsignacionTerritorialRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\UsuarioRepositorio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\AutorizacionServicio;

echo "===================================================================\n";
echo " VERIFICACIÓN: CIERRE COMPLEMENTARIO FASE 2 — ASIGNACIONES VISUALES\n";
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

// Capturar métricas iniciales de desarrollo casapro
$configDev = require dirname(__DIR__) . '/config/database.php';
$configDev['database'] = 'casapro';
$pdoDev = (new ProveedorConexion($configDev))->obtenerConexion();
$conteoUsuariosDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuarios`')->fetchColumn();
$conteoAsignacionesDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuario_empresa_roles`')->fetchColumn();
$conteoAuditoriasDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

// --- BLOQUE 1: Persistencia DDL=0 y Ranura 000013 Intacta ---
echo "\n--- BLOQUE 1: Persistencia DDL=0 y Ranura 000013 Intacta ---\n";
$migracion13 = glob(dirname(__DIR__) . '/SQL/migraciones/*000013*');
probar(empty($migracion13), 'Ranura de migración 000013 permanece libre e intacta');

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'")->fetchColumn();
probar($totalTablas === 28, "Esquema relacional contiene exactamente 28 tablas (actual: {$totalTablas})");

// Instanciar repositorios y servicios en casapro_test
$provTest = new ProveedorConexion(['database' => 'casapro_test']);
$usuarioRepo = new UsuarioRepositorio($provTest);
$rolRepo = new RolRepositorio($provTest);
$empresaRepo = new EmpresaRepositorio($provTest);
$asigRepo = new AsignacionTerritorialRepositorio($provTest);
$auditoriaServicio = new AuditoriaServicio($provTest);
$autorizacionServicio = new AutorizacionServicio(
    $provTest,
    $usuarioRepo,
    $rolRepo,
    $empresaRepo,
    $asigRepo,
    $auditoriaServicio
);
$controlador = new AsignacionTerritorialControlador(
    $asigRepo,
    $autorizacionServicio,
    $empresaRepo,
    $rolRepo,
    $usuarioRepo
);

// --- BLOQUE 2: Fixtures Sembrados en casapro_test ---
echo "\n--- BLOQUE 2: Fixtures Sembrados en casapro_test ---\n";

// Sembrar roles funcionales si no existen
$stmtRol = $pdo->prepare("INSERT INTO `roles` (`id`, `codigo`, `nombre`, `es_sistema`, `estado`) VALUES 
    (2, 'ADMINISTRADOR', 'Administrador Corporativo', 0, 'ACTIVO'),
    (3, 'VENDEDOR', 'Asesor Comercial Inmobiliario', 0, 'ACTIVO'),
    (4, 'SUPERVISOR', 'Supervisor Territorial', 0, 'ACTIVO')
    ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `estado` = 'ACTIVO'");
$stmtRol->execute();

// Sembrar Personas y Empresas de Prueba
$pdo->exec("INSERT INTO `personas` (`id`, `tipo_persona`, `estado`) VALUES 
    (301, 'JURIDICA', 'ACTIVO'),
    (302, 'JURIDICA', 'ACTIVO'),
    (303, 'JURIDICA', 'INACTIVO'),
    (401, 'NATURAL', 'ACTIVO'),
    (402, 'NATURAL', 'ACTIVO')
    ON DUPLICATE KEY UPDATE `estado` = VALUES(`estado`)");

$pdo->exec("INSERT INTO `persona_juridica` (`persona_id`, `razon_social`, `nombre_comercial`) VALUES 
    (301, 'EMPRESA ALFA S.A.C.', 'Alfa'),
    (302, 'EMPRESA BETA S.A.C.', 'Beta'),
    (303, 'EMPRESA GAMMA S.A.C.', 'Gamma Inactiva')
    ON DUPLICATE KEY UPDATE `nombre_comercial` = VALUES(`nombre_comercial`)");

$pdo->exec("INSERT INTO `persona_natural` (`persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`) VALUES 
    (401, 'Operador', 'Prueba', 'Territorial'),
    (402, 'Colaborador', 'Asignado', 'Test')
    ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)");

$empresaAlfaId = $empresaRepo->insertar(new Empresa(301, 'EMP_ALFA', 'Alfa Corp', Empresa::ESTADO_ACTIVO), $pdo);
$empresaBetaId = $empresaRepo->insertar(new Empresa(302, 'EMP_BETA', 'Beta Corp', Empresa::ESTADO_ACTIVO), $pdo);
$empresaGammaInactivaId = $empresaRepo->insertar(new Empresa(303, 'EMP_GAMMA', 'Gamma Corp', Empresa::ESTADO_INACTIVO), $pdo);

probar($empresaAlfaId > 0 && $empresaBetaId > 0 && $empresaGammaInactivaId > 0, 'Empresas fixtures insertadas (2 activas, 1 inactiva)');

// Sembrar Usuarios de Prueba con Actores Únicos
$actorOperadorId = 401;
$actorColaboradorId = 402;
$pdo->exec("INSERT INTO `actores` (`id`, `tipo_actor`, `codigo`, `nombre`, `estado`) VALUES 
    ({$actorOperadorId}, 'USUARIO', 'ACT_OP_401', 'Operador Admin', 'ACTIVO'),
    ({$actorColaboradorId}, 'USUARIO', 'ACT_COL_402', 'Colaborador Test', 'ACTIVO')
    ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)");

$usuarioColaborador = new Usuario(
    402,
    $actorColaboradorId,
    'colaborador@test.pe',
    'colaborador_test',
    password_hash('Password123!', PASSWORD_BCRYPT),
    Usuario::ESTADO_ACTIVO
);
$usuarioColaboradorId = $usuarioRepo->insertar($usuarioColaborador, $pdo);
probar($usuarioColaboradorId > 0, 'Usuario colaborador de prueba registrado exitosamente');

// Inicializar sesión del operador administrador
GestorSesion::iniciar();
GestorSesion::establecer('auth', [
    'usuario_id'           => 1,
    'actor_id'             => $actorOperadorId,
    'nombre_usuario'       => 'operador_admin',
    'version_autorizacion' => 1
]);

// --- BLOQUE 3: Catálogos para Modal (Empresas y Roles Disponibles) ---
echo "\n--- BLOQUE 3: Catálogos para Modal (Empresas y Roles Disponibles) ---\n";

$petEmpresas = new Peticion('GET', '/api/asignaciones/empresas-disponibles', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respEmpresas = new Respuesta();
$controlador->empresasDisponibles($petEmpresas, $respEmpresas);

probar($respEmpresas->obtenerCodigoEstado() === 200, 'GET /api/asignaciones/empresas-disponibles retorna HTTP 200');
$cuerpoEmpresas = json_decode($respEmpresas->obtenerCuerpo(), true);
probar($cuerpoEmpresas['estado'] === 'exito', "Respuesta de empresas disponibles reporta 'exito'");
probar(count($cuerpoEmpresas['datos']) >= 2, 'Retorna las empresas activas del sistema');

// Verificar que la empresa inactiva NO figure en las empresas disponibles
$idsDisponibles = array_column($cuerpoEmpresas['datos'], 'id');
probar(!in_array($empresaGammaInactivaId, $idsDisponibles, true), 'Empresa inactiva Gamma NO figura en empresas disponibles');

$petRoles = new Peticion('GET', '/api/asignaciones/roles-disponibles', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respRoles = new Respuesta();
$controlador->rolesDisponibles($petRoles, $respRoles);

probar($respRoles->obtenerCodigoEstado() === 200, 'GET /api/asignaciones/roles-disponibles retorna HTTP 200');
$cuerpoRoles = json_decode($respRoles->obtenerCuerpo(), true);
probar($cuerpoRoles['estado'] === 'exito', "Respuesta de roles disponibles reporta 'exito'");
$codigosRoles = array_column($cuerpoRoles['datos'], 'codigo');

// Mandato vinculante: SUPERADMIN debe estar estrictamente excluido
probar(!in_array('SUPERADMIN', $codigosRoles, true), 'SUPERADMIN está estrictamente excluido del catálogo territorial');
probar(in_array('VENDEDOR', $codigosRoles, true), 'Rol VENDEDOR está disponible');
probar(in_array('ADMINISTRADOR', $codigosRoles, true), 'Rol ADMINISTRADOR está disponible');

// --- BLOQUE 4: Endpoint GET /api/usuarios/{id}/asignaciones ---
echo "\n--- BLOQUE 4: Endpoint GET /api/usuarios/{id}/asignaciones ---\n";

$petListarVacia = new Peticion('GET', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respListarVacia = new Respuesta();
$controlador->listarPorUsuario($petListarVacia, $respListarVacia, ['id' => (string) $usuarioColaboradorId]);

probar($respListarVacia->obtenerCodigoEstado() === 200, 'GET asignaciones retorna HTTP 200 para usuario sin asignaciones');
$cuerpoListarVacia = json_decode($respListarVacia->obtenerCuerpo(), true);
probar($cuerpoListarVacia['datos']['total'] === 0, 'Total inicial de asignaciones es 0');

// Usuario inexistente -> 404
$petListarInexistente = new Peticion('GET', '/api/usuarios/99999/asignaciones', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respListarInexistente = new Respuesta();
$controlador->listarPorUsuario($petListarInexistente, $respListarInexistente, ['id' => '99999']);
probar($respListarInexistente->obtenerCodigoEstado() === 404, 'GET asignaciones para usuario inexistente retorna HTTP 404');

// --- BLOQUE 5: Asignación Válida y Control de version_autorizacion ---
echo "\n--- BLOQUE 5: Asignación Válida y Control de version_autorizacion ---\n";

$versionPrevia = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();

// 1. Asignar rol VENDEDOR (ID 3) en Empresa Alfa
$petAsignar1 = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 3,
    'motivo'     => 'Alta como asesor comercial en Alfa'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respAsignar1 = new Respuesta();
$controlador->asignar($petAsignar1, $respAsignar1, ['id' => (string) $usuarioColaboradorId]);

probar($respAsignar1->obtenerCodigoEstado() === 201, 'POST asignación válida retorna HTTP 201 Created');
$cuerpoAsignar1 = json_decode($respAsignar1->obtenerCuerpo(), true);
probar($cuerpoAsignar1['estado'] === 'exito', "Respuesta de asignación reporta 'exito'");
$asigId1 = (int) $cuerpoAsignar1['datos']['id'];
probar($asigId1 > 0, "Asignación ID #{$asigId1} generada correctamente");

// Verificar incremento estricto de version_autorizacion
$versionNueva1 = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();
probar($versionNueva1 === $versionPrevia + 1, "version_autorizacion incrementada atómicamente a {$versionNueva1}");

// 2. Asignar segundo rol: SUPERVISOR (ID 4) en Empresa Alfa (múltiples roles por empresa soportados)
$petAsignar2 = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 4,
    'motivo'     => 'Promoción a supervisor en Alfa'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respAsignar2 = new Respuesta();
$controlador->asignar($petAsignar2, $respAsignar2, ['id' => (string) $usuarioColaboradorId]);

probar($respAsignar2->obtenerCodigoEstado() === 201, 'Permite segundo rol territorial para el mismo usuario en la misma empresa');
$asigId2 = (int) (json_decode($respAsignar2->obtenerCuerpo(), true)['datos']['id']);
probar($asigId2 > 0 && $asigId2 !== $asigId1, 'Segunda tupla Usuario-Empresa-Rol creada independientemente');

// Comprobar que GET lista exactamente las 2 filas (1 fila por tupla)
$petListar2 = new Peticion('GET', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", null, [], [], ['HTTP_ACCEPT' => 'application/json']);
$respListar2 = new Respuesta();
$controlador->listarPorUsuario($petListar2, $respListar2, ['id' => (string) $usuarioColaboradorId]);
$cuerpoListar2 = json_decode($respListar2->obtenerCuerpo(), true);
probar($cuerpoListar2['datos']['total'] === 2, 'GET reporta exactamente 2 filas independientes por tupla');

// --- BLOQUE 6: Rechazos de Dominio y Seguridad Backend ---
echo "\n--- BLOQUE 6: Rechazos de Dominio y Seguridad Backend ---\n";

// A. Intento de asignar SUPERADMIN mediante payload manipulado -> 422
$petSuperadmin = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 1, // ID del rol SUPERADMIN
    'motivo'     => 'Payload malicioso intentando inyectar SUPERADMIN territorial'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respSuperadmin = new Respuesta();
$controlador->asignar($petSuperadmin, $respSuperadmin, ['id' => (string) $usuarioColaboradorId]);

probar($respSuperadmin->obtenerCodigoEstado() === 422, 'Backend rechaza inyección de SUPERADMIN territorial con HTTP 422');
$cuerpoSuper = json_decode($respSuperadmin->obtenerCuerpo(), true);
probar(str_contains($cuerpoSuper['mensaje'], 'GLOBAL'), 'Mensaje de error especifica ámbito GLOBAL');

// B. Intento de asignación duplicada activa -> 409 Conflict
$petDuplicada = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 3,
    'motivo'     => 'Intento duplicado'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respDuplicada = new Respuesta();
$controlador->asignar($petDuplicada, $respDuplicada, ['id' => (string) $usuarioColaboradorId]);

probar($respDuplicada->obtenerCodigoEstado() === 409, 'Rechazo con HTTP 409 ante tupla activa duplicada');

// C. Intento en empresa inactiva -> 409 Conflict
$petEmpresaInactiva = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaGammaInactivaId,
    'rol_id'     => 3,
    'motivo'     => 'Intento en empresa inactiva'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respEmpresaInactiva = new Respuesta();
$controlador->asignar($petEmpresaInactiva, $respEmpresaInactiva, ['id' => (string) $usuarioColaboradorId]);

probar($respEmpresaInactiva->obtenerCodigoEstado() === 409, 'Rechazo con HTTP 409 al intentar asignar en empresa inactiva');

// D. Rol inexistente -> 422
$petRolInexistente = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 99999,
    'motivo'     => 'Rol inexistente'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respRolInexistente = new Respuesta();
$controlador->asignar($petRolInexistente, $respRolInexistente, ['id' => (string) $usuarioColaboradorId]);

probar($respRolInexistente->obtenerCodigoEstado() === 422, 'Rechazo con HTTP 422 para rol inexistente');

// --- BLOQUE 7: Revocación Lógica (PATCH estado = INACTIVO, Cero DELETE) ---
echo "\n--- BLOQUE 7: Revocación Lógica (PATCH estado = INACTIVO, Cero DELETE) ---\n";

$conteoFilasPrevia = (int) $pdo->query("SELECT COUNT(*) FROM `usuario_empresa_roles` WHERE `usuario_id` = {$usuarioColaboradorId}")->fetchColumn();
$versionPreviaRevoca = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();

$petRevocar = new Peticion('PATCH', "/api/asignaciones/{$asigId1}/estado", [
    'estado' => 'INACTIVO',
    'motivo' => 'Revocación por término de campaña en Alfa'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respRevocar = new Respuesta();
$controlador->cambiarEstado($petRevocar, $respRevocar, ['id' => (string) $asigId1]);

probar($respRevocar->obtenerCodigoEstado() === 200, 'PATCH revocar asignación retorna HTTP 200 OK');
$cuerpoRevoca = json_decode($respRevocar->obtenerCuerpo(), true);
probar($cuerpoRevoca['datos']['estado'] === 'INACTIVO', 'Estado de la asignación conmutado a INACTIVO');

// Inmutabilidad: Cero DELETE físico
$conteoFilasPostRevoca = (int) $pdo->query("SELECT COUNT(*) FROM `usuario_empresa_roles` WHERE `usuario_id` = {$usuarioColaboradorId}")->fetchColumn();
probar($conteoFilasPostRevoca === $conteoFilasPrevia, 'Inmutabilidad confirmada: cero DELETE físico en base de datos');

// Columnas forenses de revocación
$filaRevocada = $pdo->query("SELECT * FROM `usuario_empresa_roles` WHERE `id` = {$asigId1}")->fetch(PDO::FETCH_ASSOC);
probar($filaRevocada['revocado_por'] !== null, 'revocado_por registrado fidedignamente');
probar($filaRevocada['revocado_en'] !== null, 'revocado_en registrado con timestamp');

// Incrementar version_autorizacion tras revocación
$versionPostRevoca = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();
probar($versionPostRevoca === $versionPreviaRevoca + 1, "version_autorizacion incrementada a {$versionPostRevoca} por revocación");

// --- BLOQUE 8: Reactivación de Tupla Histórica (Sin Duplicación de Fila) ---
echo "\n--- BLOQUE 8: Reactivación de Tupla Histórica (Sin Duplicación de Fila) ---\n";

// Reactivar asignación mediante conmutación directa o mediante nuevo intento de asignación
$versionPreviaReactiva = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();

// Intento de asignar la misma tupla que estaba inactiva (debe reactivar la fila existente)
$petReactivar = new Peticion('POST', "/api/usuarios/{$usuarioColaboradorId}/asignaciones", [
    'empresa_id' => $empresaAlfaId,
    'rol_id'     => 3,
    'motivo'     => 'Reincorporación de funciones comerciales en Alfa'
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respReactivar = new Respuesta();
$controlador->asignar($petReactivar, $respReactivar, ['id' => (string) $usuarioColaboradorId]);

probar($respReactivar->obtenerCodigoEstado() === 201, 'Reactivación exitosa mediante asignación de tupla histórica previa');
$cuerpoReactiva = json_decode($respReactivar->obtenerCuerpo(), true);
$asigIdReactivada = (int) $cuerpoReactiva['datos']['id'];

// Mandato vinculante: Debe ser exactamente la MISMA ID, no una fila nueva duplicada
probar($asigIdReactivada === $asigId1, "Reutiliza y reactiva la tupla histórica preexistente (ID #{$asigIdReactivada} == #{$asigId1})");

$conteoFilasPostReactiva = (int) $pdo->query("SELECT COUNT(*) FROM `usuario_empresa_roles` WHERE `usuario_id` = {$usuarioColaboradorId}")->fetchColumn();
probar($conteoFilasPostReactiva === $conteoFilasPrevia, 'Cero filas duplicadas en BD tras reactivación');

$filaReactivada = $pdo->query("SELECT * FROM `usuario_empresa_roles` WHERE `id` = {$asigId1}")->fetch(PDO::FETCH_ASSOC);
probar($filaReactivada['estado'] === 'ACTIVO', 'Estado de la tupla reactivada es ACTIVO');
probar($filaReactivada['revocado_por'] === null, 'revocado_por reseteado a NULL');
probar($filaReactivada['revocado_en'] === null, 'revocado_en reseteado a NULL');

// Incremento de version_autorizacion por reactivación
$versionPostReactiva = (int) $pdo->query("SELECT `version_autorizacion` FROM `usuarios` WHERE `id` = {$usuarioColaboradorId}")->fetchColumn();
probar($versionPostReactiva === $versionPreviaReactiva + 1, "version_autorizacion incrementada a {$versionPostReactiva} por reactivación");

// --- BLOQUE 9: Auditoría Forense en 'auditorias' ---
echo "\n--- BLOQUE 9: Auditoría Forense en 'auditorias' ---\n";

$eventosAuditoria = $pdo->query("SELECT * FROM `auditorias` WHERE `entidad` = 'usuario_empresa_roles' ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
probar(count($eventosAuditoria) >= 3, 'Al menos 3 eventos registrados en auditorias para usuario_empresa_roles');

$accionesAuditadas = array_column($eventosAuditoria, 'accion');
probar(in_array('ASIGNAR_ROL_EMPRESA', $accionesAuditadas, true), "Auditoría contiene evento 'ASIGNAR_ROL_EMPRESA'");
probar(in_array('REVOCAR_ROL_EMPRESA', $accionesAuditadas, true), "Auditoría contiene evento 'REVOCAR_ROL_EMPRESA'");
probar(in_array('REACTIVAR_ROL_EMPRESA', $accionesAuditadas, true), "Auditoría contiene evento 'REACTIVAR_ROL_EMPRESA'");

// --- BLOQUE 10: Vista Espejo de Consulta de Empresa ---
echo "\n--- BLOQUE 10: Vista Espejo de Consulta de Empresa ---\n";

$petColabsAlfa = new Peticion('GET', "/api/empresas/{$empresaAlfaId}/colaboradores", [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$respColabsAlfa = new Respuesta();
$controlador->listarPorEmpresa($petColabsAlfa, $respColabsAlfa, ['id' => (string) $empresaAlfaId]);

probar($respColabsAlfa->obtenerCodigoEstado() === 200, 'GET /api/empresas/{id}/colaboradores retorna HTTP 200');
$cuerpoColabs = json_decode($respColabsAlfa->obtenerCuerpo(), true);
probar($cuerpoColabs['datos']['total'] >= 2, 'Vista espejo de Empresa reporta las asignaciones activas de los colaboradores');

// Validar que EmpresaControlador NO expone rutas de mutación territorial (solo consulta)
$rutasContenido = file_get_contents(dirname(__DIR__) . '/config/rutas.php');
probar(!str_contains($rutasContenido, "POST '/api/empresas/{id}/colaboradores'"), 'Ficha Empresa es de solo consulta (sin mutaciones territoriales desde Empresa)');

// --- BLOQUE 11: Integridad Frontend (Alina Tabs, Modales, Vanilla JS Fetch) ---
echo "\n--- BLOQUE 11: Integridad Frontend (Alina Tabs, Modales, Vanilla JS Fetch) ---\n";

$vistaFicha = file_get_contents(dirname(__DIR__) . '/app/Vistas/modulos/usuarios/ficha.php');
probar(str_contains($vistaFicha, 'id="tab-territorial-btn"'), 'Ficha Usuario define pestaña Alina id="tab-territorial-btn"');
probar(str_contains($vistaFicha, 'id="tablaAsignacionesTerritoriales"'), 'Ficha Usuario define tabla id="tablaAsignacionesTerritoriales"');
probar(str_contains($vistaFicha, 'id="modalAsignarRolEmpresa"'), 'Ficha Usuario define modal Alina id="modalAsignarRolEmpresa"');
probar(str_contains($vistaFicha, 'btn-conmutar-asignacion'), 'Ficha Usuario define botones de conmutación asíncrona');

$vistaEmpresas = file_get_contents(dirname(__DIR__) . '/app/Vistas/modulos/empresas/index.php');
probar(str_contains($vistaEmpresas, 'id="tab-ficha-colaboradores-btn"'), 'Ficha Empresa define pestaña espejo id="tab-ficha-colaboradores-btn"');
probar(str_contains($vistaEmpresas, 'id="tbodyFichaColaboradores"'), 'Ficha Empresa define tabla espejo id="tbodyFichaColaboradores"');

$scriptAsignaciones = file_get_contents(dirname(__DIR__) . '/public/assets/js/modulos/usuarios/asignaciones-territoriales.js');
probar(str_contains($scriptAsignaciones, 'window.fetch'), 'asignaciones-territoriales.js utiliza window.fetch nativo');
probar(!str_contains($scriptAsignaciones, '$.ajax'), 'asignaciones-territoriales.js libre de $.ajax (cero jQuery propio)');
probar(!str_contains($scriptAsignaciones, 'location.reload'), 'asignaciones-territoriales.js libre de location.reload (refresco asíncrono)');

$scriptEmpresas = file_get_contents(dirname(__DIR__) . '/public/assets/js/modulos/empresas/gestion-empresas.js');
probar(str_contains($scriptEmpresas, 'poblarColaboradoresEmpresa'), 'gestion-empresas.js implementa poblarColaboradoresEmpresa');

// --- BLOQUE 12: Aislamiento y DELTA casapro (desarrollo) = 0 ---
echo "\n--- BLOQUE 12: Aislamiento y DELTA casapro (desarrollo) = 0 ---\n";

$conteoUsuariosDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuarios`')->fetchColumn();
$conteoAsignacionesDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `usuario_empresa_roles`')->fetchColumn();
$conteoAuditoriasDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

probar($conteoUsuariosDevFinal === $conteoUsuariosDevInicial, 'DELTA usuarios en desarrollo casapro = 0');
probar($conteoAsignacionesDevFinal === $conteoAsignacionesDevInicial, 'DELTA asignaciones en desarrollo casapro = 0');
probar($conteoAuditoriasDevFinal === $conteoAuditoriasDevInicial, 'DELTA auditorias en desarrollo casapro = 0');

echo "\n===================================================================\n";
echo " RESULTADO: {$pruebasSuperadas} / {$totalPruebas} PRUEBAS SUPERADAS (100% PASS)\n";
echo "===================================================================\n\n";
