<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/comun/AmbientePruebas.php';
require_once __DIR__ . '/comun/FixtureAutenticacion.php';

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Modelos\Usuario;
use App\Modelos\Rol;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Servicios\UsuarioServicio;
use App\Servicios\AutenticacionServicio;
use App\Servicios\AutorizacionServicio;
use App\Servicios\PoliticaContrasenaServicio;
use App\DTOs\CrearUsuarioDTO;
use App\DTOs\ActualizarUsuarioDTO;
use App\DTOs\CambiarEstadoUsuarioDTO;
use App\DTOs\DesbloquearUsuarioDTO;
use App\DTOs\SincronizarRolesDTO;
use App\DTOs\ResetearPasswordDTO;
use App\DTOs\CambiarPasswordPersonalDTO;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use Tests\Comun\AmbientePruebas;

$conexion = AmbientePruebas::iniciar(true);
$proveedor = AmbientePruebas::obtenerProveedorTest();
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE ADMINISTRACIÓN DE USUARIOS Y ACCESOS (1G-2)\n";
echo "===================================================================\n\n";

$totalPruebas = 0;
$pruebasSuperadas = 0;

function afirmar(bool $condicion, string $mensaje): void
{
    global $totalPruebas, $pruebasSuperadas;
    $totalPruebas++;
    if ($condicion) {
        $pruebasSuperadas++;
        echo "  [PASS] {$mensaje}\n";
    } else {
        echo "  [FAIL] {$mensaje}\n";
        throw new RuntimeException("Fallo en la aserción: {$mensaje}");
    }
}
$usuarioRepo = new UsuarioRepositorio($proveedor);
$rolRepo = new RolRepositorio($proveedor);
$personaRepo = new PersonaRepositorio($proveedor);
$seguridadRepo = new SeguridadRepositorio($proveedor);
$usuarioServicio = new UsuarioServicio($proveedor);
$authServicio = new AutenticacionServicio($proveedor);
$autorizacionServicio = new AutorizacionServicio($proveedor);

$contexto = new ContextoPeticion('REQ-TEST-1G2', '127.0.0.1', 'CLI-TestRunner/1G2', 'CLI', 1);

// -------------------------------------------------------------------------
// BLOQUE 1: Esquema de Base de Datos y Privilegios 1G-2
// -------------------------------------------------------------------------
echo "--- BLOQUE 1: Esquema de Base de Datos y Privilegios 1G-2 ---\n";

// 1.1 Columna debe_cambiar_password
$stmtCol = $conexion->query("SHOW COLUMNS FROM `usuarios` LIKE 'debe_cambiar_password'");
$col = $stmtCol->fetch(PDO::FETCH_ASSOC);
afirmar($col !== false, "Columna 'debe_cambiar_password' existe en la tabla 'usuarios'");
afirmar(str_contains(strtolower($col['Type']), 'tinyint'), "Columna 'debe_cambiar_password' es de tipo TINYINT");
afirmar($col['Null'] === 'NO', "Columna 'debe_cambiar_password' es NOT NULL");
afirmar($col['Default'] === '0', "Columna 'debe_cambiar_password' tiene DEFAULT 0");

// 1.2 Catálogo de 7 nuevos privilegios
$privilegiosEsperados = [
    'usuarios.ver',
    'usuarios.crear',
    'usuarios.editar',
    'usuarios.cambiar_estado',
    'usuarios.desbloquear',
    'usuarios.asignar_roles',
    'usuarios.resetear_password'
];

foreach ($privilegiosEsperados as $codPriv) {
    $stmtPriv = $conexion->prepare("SELECT COUNT(*) FROM `privilegios` WHERE `codigo` = :cod");
    $stmtPriv->bindValue(':cod', $codPriv, PDO::PARAM_STR);
    $stmtPriv->execute();
    afirmar(((int) $stmtPriv->fetchColumn()) === 1, "Privilegio '{$codPriv}' registrado en catálogo");
}

// 1.3 Asignación de los 7 privilegios al rol SUPERADMIN
$stmtSup = $conexion->prepare("
    SELECT COUNT(*) 
    FROM `rol_privilegios` rp
    INNER JOIN `roles` r ON r.id = rp.rol_id
    INNER JOIN `privilegios` p ON p.id = rp.privilegio_id
    WHERE r.codigo = 'SUPERADMIN' AND p.codigo = :cod
");
foreach ($privilegiosEsperados as $codPriv) {
    $stmtSup->bindValue(':cod', $codPriv, PDO::PARAM_STR);
    $stmtSup->execute();
    afirmar(((int) $stmtSup->fetchColumn()) === 1, "SUPERADMIN posee asignado el privilegio '{$codPriv}'");
}

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 2: Servicio Soberano de Política de Contraseñas
// -------------------------------------------------------------------------
echo "--- BLOQUE 2: Servicio Soberano de Política de Contraseñas ---\n";

afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('corta')), "Rechaza contraseña menor a 8 caracteres");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('todominuscularsinmayusculas123!')), "Rechaza contraseña sin letras mayúsculas");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('TODOMAYUSCULAS1234567!')), "Rechaza contraseña sin letras minúsculas");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('SinNumerosConMayuscula!')), "Rechaza contraseña sin números");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('SinSimbolosConMayuscula123')), "Rechaza contraseña sin símbolos");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores(' ClaveConEspacioInicio123!')), "Rechaza contraseña con espacio inicial");
afirmar(!empty(PoliticaContrasenaServicio::obtenerErrores('ClaveConEspacioFinal123! ')), "Rechaza contraseña con espacio final");

afirmar(empty(PoliticaContrasenaServicio::obtenerErrores('ClaveValida#2026!')), "Acepta contraseña que cumple los 5 requisitos");

// Verificar que validar() lanza ValidacionExcepcion ante clave insegura
try {
    PoliticaContrasenaServicio::validar('corta');
    afirmar(false, "validar() debe lanzar ValidacionExcepcion");
} catch (ValidacionExcepcion $e) {
    afirmar(true, "validar() lanza ValidacionExcepcion ante incumplimiento");
}

$passGen = PoliticaContrasenaServicio::generarTemporal(16);
afirmar(strlen($passGen) === 16, "Generador de contraseñas temporales respeta longitud solicitada (16)");
afirmar(empty(PoliticaContrasenaServicio::obtenerErrores($passGen)), "Contraseña temporal generada pasa el 100% de los requisitos");


echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 3: Alta de Usuario, Actor USER y Jerarquía SUPERADMIN
// -------------------------------------------------------------------------
echo "--- BLOQUE 3: Alta de Usuario, Actor USER y Jerarquía SUPERADMIN ---\n";

// Crear Persona Natural de prueba
$sufijo = bin2hex(random_bytes(4));
$stmtPN = $conexion->prepare("INSERT INTO `personas` (`tipo_persona`, `estado`, `creado_en`) VALUES ('NATURAL', 'ACTIVO', NOW())");
$stmtPN->execute();
$personaId = (int) $conexion->lastInsertId();

$stmtPNat = $conexion->prepare("INSERT INTO `persona_natural` (`persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`, `creado_en`) VALUES (:pid, 'Mario', 'Benedetti', 'Facio', NOW())");
$stmtPNat->bindValue(':pid', $personaId, PDO::PARAM_INT);
$stmtPNat->execute();

$rolAdmin = $rolRepo->buscarPorCodigo('SUPERADMIN');
$rolAdminId = $rolAdmin->obtenerId();

// Obtener ID del primer SUPERADMIN activo para actuar como operador
$stmtOp = $conexion->query("
    SELECT u.id 
    FROM `usuarios` u
    INNER JOIN `usuario_roles` ur ON ur.usuario_id = u.id
    INNER JOIN `roles` r ON r.id = ur.rol_id
    WHERE r.codigo = 'SUPERADMIN' AND u.estado = 'ACTIVO' LIMIT 1
");
$operadorSuperadminId = (int) $stmtOp->fetchColumn();
if ($operadorSuperadminId === 0) {
    $authOp = \Tests\Comun\FixtureAutenticacion::autenticarComoSuperadmin($conexion);
    $operadorSuperadminId = (int) $authOp['usuario_id'];
}

// Crear usuario con UsuarioServicio
$dtoCrear = new CrearUsuarioDTO([
    'persona_id'     => $personaId,
    'nombre_usuario' => 'mario_' . $sufijo,
    'email'          => 'mario_' . $sufijo . '@casapro.pe',
    'password'       => 'Temporal#2026Pass!',
    'roles'          => [$rolAdminId]
]);

$resCrear = $usuarioServicio->crearUsuario($dtoCrear, $operadorSuperadminId, $contexto);
$nuevoUsuarioId = (int) $resCrear['id'];

afirmar($nuevoUsuarioId > 0, "Usuario creado exitosamente con ID {$nuevoUsuarioId}");
afirmar($resCrear['debe_cambiar_password'] === true, "Alta de usuario establece debe_cambiar_password = true");
afirmar($resCrear['actor_id'] > 0, "Actor USER generado y vinculado (ID: {$resCrear['actor_id']})");

// Verificar en BD que debe_cambiar_password es 1
$controlBD = $usuarioRepo->obtenerControlSesion($nuevoUsuarioId);
afirmar((int) $controlBD['debe_cambiar_password'] === 1, "Base de datos persiste soberanamente debe_cambiar_password = 1");

// Verificar que contraseña NO se expuso en auditoría
$stmtAud = $conexion->prepare("
    SELECT * FROM `auditorias` 
    WHERE `entidad` = 'usuarios' AND `registro_id` = :id AND `accion` = 'CREAR'
    ORDER BY `id` DESC LIMIT 1
");
$stmtAud->bindValue(':id', $nuevoUsuarioId, PDO::PARAM_INT);
$stmtAud->execute();
$auditoria = $stmtAud->fetch(PDO::FETCH_ASSOC);
afirmar($auditoria !== false, "Auditoría de creación registrada en base de datos");
afirmar(!str_contains((string) $auditoria['datos_nuevos'], 'Temporal#2026Pass!'), "Auditoría no expone contraseña temporal en texto claro");

// Verificar evento de seguridad
$stmtEv = $conexion->prepare("SELECT * FROM `eventos_seguridad` WHERE `usuario_id` = :id AND `tipo_evento` = 'CREACION_USUARIO'");
$stmtEv->bindValue(':id', $nuevoUsuarioId, PDO::PARAM_INT);
$stmtEv->execute();
afirmar($stmtEv->fetch() !== false, "Evento 'CREACION_USUARIO' registrado en telemetría");

// Rechazo de segundo usuario para la misma persona (1:1 estricto)
try {
    $dtoDuplicado = new CrearUsuarioDTO([
        'persona_id'     => $personaId,
        'nombre_usuario' => 'otro_' . $sufijo,
        'email'          => 'otro_' . $sufijo . '@casapro.pe',
        'password'       => 'Temporal#2026Pass!',
        'roles'          => [$rolAdminId]
    ]);
    $usuarioServicio->crearUsuario($dtoDuplicado, $operadorSuperadminId, $contexto);
    afirmar(false, "Debe rechazar segundo usuario para la misma Persona Natural");
} catch (ValidacionExcepcion $e) {
    afirmar(true, "Rechazo estricto de duplicidad 1:1 en creación de cuenta de usuario");
}

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 4: Actualización de Perfil y Detección de Conflictos
// -------------------------------------------------------------------------
echo "--- BLOQUE 4: Actualización de Perfil y Detección de Conflictos ---\n";

$dtoActualizar = new ActualizarUsuarioDTO([
    'nombre_usuario' => 'mario_mod_' . $sufijo,
    'email'          => 'mario_mod_' . $sufijo . '@casapro.pe'
]);

$resActualizar = $usuarioServicio->actualizarUsuario($nuevoUsuarioId, $dtoActualizar, $operadorSuperadminId, $contexto);
afirmar($resActualizar['nombre_usuario'] === 'mario_mod_' . $sufijo, "Nombre de usuario actualizado correctamente");
afirmar($resActualizar['email'] === 'mario_mod_' . $sufijo . '@casapro.pe', "Correo electrónico actualizado correctamente");

// Verificar auditoría de actualización
$stmtAudAct = $conexion->prepare("
    SELECT * FROM `auditorias` 
    WHERE `entidad` = 'usuarios' AND `registro_id` = :id AND `accion` = 'ACTUALIZAR'
    ORDER BY `id` DESC LIMIT 1
");
$stmtAudAct->bindValue(':id', $nuevoUsuarioId, PDO::PARAM_INT);
$stmtAudAct->execute();
afirmar($stmtAudAct->fetch() !== false, "Auditoría de actualización registrada");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 5: Desbloqueo Administrativo Independiente
// -------------------------------------------------------------------------
echo "--- BLOQUE 5: Desbloqueo Administrativo Independiente ---\n";

// Simular bloqueo temporal de seguridad
$bloqueoHasta = date('Y-m-d H:i:s', time() + 900);
$stmtBloq = $conexion->prepare("
    UPDATE `usuarios` 
    SET `intentos_fallidos` = 5, `ultimo_intento_fallido` = NOW(), `bloqueado_hasta` = :bloq 
    WHERE `id` = :id
");
$stmtBloq->bindValue(':bloq', $bloqueoHasta, PDO::PARAM_STR);
$stmtBloq->bindValue(':id', $nuevoUsuarioId, PDO::PARAM_INT);
$stmtBloq->execute();

$usrAntes = $usuarioRepo->buscarPorId($nuevoUsuarioId);
afirmar($usrAntes->obtenerIntentosFallidos() === 5, "Simulación de cuenta bloqueada confirmada (intentos_fallidos: 5)");

// Desbloquear con UsuarioServicio
$dtoDesbloquear = new DesbloquearUsuarioDTO(['motivo' => 'Desbloqueo autorizado por mesa de ayuda']);
$resDesbloqueo = $usuarioServicio->desbloquear($nuevoUsuarioId, $dtoDesbloquear, $operadorSuperadminId, $contexto);

afirmar($resDesbloqueo['intentos_fallidos'] === 0, "Desbloqueo resetea intentos_fallidos a 0");
afirmar($resDesbloqueo['bloqueado_hasta'] === null, "Desbloqueo limpia bloqueado_hasta a NULL");

$usrDespues = $usuarioRepo->buscarPorId($nuevoUsuarioId);
afirmar($usrDespues->obtenerEstado() === 'ACTIVO', "Desbloqueo no altera el estado administrativo ('ACTIVO')");

// Verificar evento de seguridad DESBLOQUEO_ADMINISTRATIVO
$stmtEvDesb = $conexion->prepare("
    SELECT * FROM `eventos_seguridad` 
    WHERE `usuario_id` = :id AND `tipo_evento` = 'DESBLOQUEO_ADMINISTRATIVO'
");
$stmtEvDesb->bindValue(':id', $nuevoUsuarioId, PDO::PARAM_INT);
$stmtEvDesb->execute();
afirmar($stmtEvDesb->fetch() !== false, "Evento 'DESBLOQUEO_ADMINISTRATIVO' registrado en eventos_seguridad");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 6: Reseteo Administrativo de Contraseña
// -------------------------------------------------------------------------
echo "--- BLOQUE 6: Reseteo Administrativo de Contraseña ---\n";

$versionPreReset = $usrDespues->obtenerVersionAutorizacion();
$claveTemporalReset = PoliticaContrasenaServicio::generarTemporal(14);

$dtoReset = new ResetearPasswordDTO([
    'password_temporal' => $claveTemporalReset,
    'motivo'            => 'Olvido de clave reportado por el colaborador'
]);

$resReset = $usuarioServicio->resetearPassword($nuevoUsuarioId, $dtoReset, $operadorSuperadminId, $contexto);
afirmar($resReset['debe_cambiar_password'] === true, "Reset administrativo activa debe_cambiar_password = true");

$usrPostReset = $usuarioRepo->buscarPorId($nuevoUsuarioId);
afirmar($usrPostReset->obtenerVersionAutorizacion() === $versionPreReset + 1, "Reset administrativo incrementa version_autorizacion para revocar sesiones");
afirmar(password_verify($claveTemporalReset, $usrPostReset->obtenerPasswordHash()), "Hash verificado contra la clave temporal generada");

// Prohibición de auto-reset administrativo
try {
    $dtoAutoReset = new ResetearPasswordDTO(['password_temporal' => $claveTemporalReset, 'motivo' => 'Intento propio']);
    $usuarioServicio->resetearPassword($operadorSuperadminId, $dtoAutoReset, $operadorSuperadminId, $contexto);
    afirmar(false, "Debe rechazar auto-reset administrativo");
} catch (ReglaNegocioExcepcion $e) {
    afirmar(true, "Prohibición estricta de auto-reset administrativo de contraseña");
}

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 7: Cambio Personal de Contraseña y Renovación de Sesión Activa
// -------------------------------------------------------------------------
echo "--- BLOQUE 7: Cambio Personal de Contraseña y Renovación de Sesión Activa ---\n";

// Iniciar sesión simulada
GestorSesion::iniciar();
GestorSesion::establecer('auth', [
    'usuario_id'            => $nuevoUsuarioId,
    'nombre_usuario'        => $usrPostReset->obtenerNombreUsuario(),
    'email'                 => $usrPostReset->obtenerEmail(),
    'actor_id'              => $usrPostReset->obtenerActorId(),
    'version_autorizacion'  => $usrPostReset->obtenerVersionAutorizacion(),
    'debe_cambiar_password' => true
]);

$nuevaClavePersonal = 'Definitiva#CasaPRO2026!';

// 7.1 Fallo por clave actual errónea
try {
    $dtoPassErr = new CambiarPasswordPersonalDTO([
        'password_actual'    => 'ClaveIncorrecta#999',
        'nuevo_password'     => $nuevaClavePersonal,
        'confirmar_password' => $nuevaClavePersonal
    ]);
    $usuarioServicio->cambiarPasswordPersonal($nuevoUsuarioId, $dtoPassErr, $contexto);
    afirmar(false, "Debe fallar ante contraseña actual incorrecta");
} catch (ReglaNegocioExcepcion $e) {
    afirmar(true, "Rechazo estricto de contraseña actual incorrecta (sin bypass de clave actual)");
}

// 7.2 Cambio exitoso
$dtoPassOk = new CambiarPasswordPersonalDTO([
    'password_actual'    => $claveTemporalReset,
    'nuevo_password'     => $nuevaClavePersonal,
    'confirmar_password' => $nuevaClavePersonal
]);

$resCambioPersonal = $usuarioServicio->cambiarPasswordPersonal($nuevoUsuarioId, $dtoPassOk, $contexto);
afirmar($resCambioPersonal['debe_cambiar_password'] === false, "Cambio personal desactiva debe_cambiar_password = false");

$usrDef = $usuarioRepo->buscarPorId($nuevoUsuarioId);
afirmar($usrDef->debeCambiarPassword() === false, "Base de datos confirma debe_cambiar_password = 0");
afirmar(password_verify($nuevaClavePersonal, $usrDef->obtenerPasswordHash()), "Nueva clave personal verificada criptográficamente");

// 7.3 Verificación de no auto-expulsión: la sesión activa se actualizó con la nueva versión
$authSesion = GestorSesion::obtener('auth');
afirmar($authSesion['debe_cambiar_password'] === false, "Sesión activa actualizada: debe_cambiar_password es false");
afirmar($authSesion['version_autorizacion'] === $usrDef->obtenerVersionAutorizacion(), "Sesión activa renovada con la versión de BD (sin auto-expulsión)");

// 7.4 Verificación de revocación remota: una sesión concurrente con la versión anterior es invalidada
$controlRemoto = $autorizacionServicio->validarControlSesion($nuevoUsuarioId, $versionPreReset);
afirmar($controlRemoto === null, "Sesión remota concurrente con versión anterior es inmediatamente invalidada");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 8: Aislamiento Soberano en AutenticacionMiddleware
// -------------------------------------------------------------------------
echo "--- BLOQUE 8: Aislamiento Soberano en AutenticacionMiddleware ---\n";

// Simular usuario con debe_cambiar_password = 1 en BD
$conexion->exec("UPDATE `usuarios` SET `debe_cambiar_password` = 1 WHERE `id` = {$nuevoUsuarioId}");
GestorSesion::establecer('auth', [
    'usuario_id'            => $nuevoUsuarioId,
    'nombre_usuario'        => $usrDef->obtenerNombreUsuario(),
    'email'                 => $usrDef->obtenerEmail(),
    'actor_id'              => $usrDef->obtenerActorId(),
    'version_autorizacion'  => $usrDef->obtenerVersionAutorizacion(),
    'debe_cambiar_password' => true
]);

// Ejecutar middleware en petición a ruta de negocio /personas
$peticionPersonas = new \App\Core\Peticion();
$peticionPersonas->establecerMetodo('GET');
$peticionPersonas->establecerRuta('/personas');
$respuesta = new \App\Core\Respuesta();
$middlewareAuth = new \App\Middlewares\AutenticacionMiddleware();

$pasoPersonas = $middlewareAuth->procesar($peticionPersonas, $respuesta, $contexto);
afirmar($pasoPersonas === false, "Acceso a /personas es interceptado por cambio obligatorio de contraseña");
afirmar($respuesta->obtenerCodigoEstado() === 302, "Respuesta a /personas redirige con HTTP 302");
$cabeceras = $respuesta->obtenerCabeceras();
afirmar(($cabeceras['Location'] ?? '') === '/cambiar-password-obligatorio', "Redirección apunta hacia /cambiar-password-obligatorio");

// Petición AJAX debe recibir 403 PASSWORD_CHANGE_REQUIRED
$peticionAjax = new \App\Core\Peticion();
$peticionAjax->establecerMetodo('GET');
$peticionAjax->establecerRuta('/api/personas');
$peticionAjax->establecerCabecera('X-Requested-With', 'XMLHttpRequest');
$respuestaAjax = new \App\Core\Respuesta();

ob_start();
$pasoAjax = $middlewareAuth->procesar($peticionAjax, $respuestaAjax, $contexto);
ob_end_clean();

afirmar($pasoAjax === false, "Petición AJAX a /api/personas es interceptada");
afirmar($respuestaAjax->obtenerCodigoEstado() === 403, "Petición AJAX recibe HTTP 403 ante cambio obligatorio de contraseña");

// Petición a /cambiar-password-obligatorio debe ser permitida
$peticionCambio = new \App\Core\Peticion();
$peticionCambio->establecerMetodo('GET');
$peticionCambio->establecerRuta('/cambiar-password-obligatorio');
$respuestaCambio = new \App\Core\Respuesta();
$pasoCambio = $middlewareAuth->procesar($peticionCambio, $respuestaCambio, $contexto);
afirmar($pasoCambio === true, "Ruta /cambiar-password-obligatorio es accesible con debe_cambiar_password = 1");



// Restaurar en BD
$conexion->exec("UPDATE `usuarios` SET `debe_cambiar_password` = 0 WHERE `id` = {$nuevoUsuarioId}");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 9: Sincronización de Roles, Jerarquía y Anti-Orfandad
// -------------------------------------------------------------------------
echo "--- BLOQUE 9: Sincronización de Roles, Jerarquía y Anti-Orfandad ---\n";

// Crear un rol secundario para pruebas si no existe
$stmtRolSec = $conexion->query("SELECT id FROM `roles` WHERE `codigo` != 'SUPERADMIN' AND `estado` = 'ACTIVO' LIMIT 1");
$rolSecId = (int) $stmtRolSec->fetchColumn();
if ($rolSecId === 0) {
    $conexion->exec("INSERT INTO `roles` (`codigo`, `nombre`, `descripcion`, `es_sistema`, `estado`) VALUES ('OPERADOR_TEST', 'Operador de Pruebas', 'Rol secundario para pruebas', 0, 'ACTIVO')");
    $rolSecId = (int) $conexion->lastInsertId();
}

// Sincronizar roles agregando rol secundario
$dtoSyncRoles = new SincronizarRolesDTO(['roles' => [$rolAdminId, $rolSecId]]);
$resSync = $usuarioServicio->sincronizarRoles($nuevoUsuarioId, $dtoSyncRoles, $operadorSuperadminId, $contexto);
afirmar(count($resSync['roles']) === 2, "Roles sincronizados con éxito (total: 2)");

// Prohibición de revocar SUPERADMIN al único SUPERADMIN activo (Anti-orfandad)
// Ver cuántos superadmins activos hay
$totalSuperadmins = $rolRepo->contarUsuariosActivosConRolCodigo('SUPERADMIN');
if ($totalSuperadmins === 1) {
    try {
        $dtoRevocar = new SincronizarRolesDTO(['roles' => [$rolSecId]]);
        $usuarioServicio->sincronizarRoles($operadorSuperadminId, $dtoRevocar, $operadorSuperadminId, $contexto);
        afirmar(false, "Debe rechazar revocar SUPERADMIN al único SUPERADMIN activo");
    } catch (ReglaNegocioExcepcion $e) {
        afirmar(true, "Anti-orfandad activada: No se puede revocar SUPERADMIN al único activo");
    }
} else {
    afirmar(true, "Anti-orfandad: Sistema cuenta con {$totalSuperadmins} SUPERADMINs activos");
}

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 10: Ciclo de Vida Administrativo y Prohibición de Auto-Desactivación
// -------------------------------------------------------------------------
echo "--- BLOQUE 10: Ciclo de Vida Administrativo y Prohibición de Auto-Desactivación ---\n";

// Prohibición de auto-desactivación de SUPERADMIN
try {
    $dtoDesactivar = new CambiarEstadoUsuarioDTO(['nuevo_estado' => 'INACTIVO', 'motivo' => 'Auto-desactivación prueba']);
    $usuarioServicio->cambiarEstado($operadorSuperadminId, $dtoDesactivar, $operadorSuperadminId, $contexto);
    afirmar(false, "Debe rechazar la auto-desactivación de un SUPERADMIN");
} catch (ReglaNegocioExcepcion $e) {
    afirmar(true, "Prohibición estricta de auto-desactivación de SUPERADMIN");
}

// Desactivar el usuario de prueba
$dtoDesactPrueba = new CambiarEstadoUsuarioDTO(['nuevo_estado' => 'INACTIVO', 'motivo' => 'Baja administrativa de prueba']);
$resDesact = $usuarioServicio->cambiarEstado($nuevoUsuarioId, $dtoDesactPrueba, $operadorSuperadminId, $contexto);
afirmar($resDesact['estado'] === 'INACTIVO', "Usuario de prueba desactivado correctamente");

$usrInactivo = $usuarioRepo->buscarPorId($nuevoUsuarioId);
afirmar($usrInactivo->estaActivo() === false, "Base de datos confirma estado INACTIVO");

// Reactivar
$dtoReactPrueba = new CambiarEstadoUsuarioDTO(['nuevo_estado' => 'ACTIVO', 'motivo' => 'Reactivación de prueba']);
$resReact = $usuarioServicio->cambiarEstado($nuevoUsuarioId, $dtoReactPrueba, $operadorSuperadminId, $contexto);
afirmar($resReact['estado'] === 'ACTIVO', "Usuario reactivado a ACTIVO exitosamente");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 11: Endpoints HTTP, Control de Rutas y DataTables
// -------------------------------------------------------------------------
echo "--- BLOQUE 11: Endpoints HTTP, Control de Rutas y DataTables ---\n";

// 11.1 DataTables server-side
$dtData = $usuarioServicio->obtenerDataTables([
    'draw'   => 1,
    'start'  => 0,
    'length' => 10,
    'search' => ''
]);

afirmar(isset($dtData['draw']) && $dtData['draw'] === 1, "DataTables retorna 'draw' válido");
afirmar(isset($dtData['recordsTotal']) && $dtData['recordsTotal'] >= 1, "DataTables retorna recordsTotal >= 1");
afirmar(isset($dtData['data']) && is_array($dtData['data']), "DataTables retorna array 'data'");

// 11.2 Verificar que IP NO está expuesta en las columnas principales de la grilla
$primeraFila = $dtData['data'][0] ?? [];
afirmar(!isset($primeraFila['ultimo_login_ip']), "DataTables NO expone 'ultimo_login_ip' en la grilla principal (reservado para Ficha)");

// 11.3 Ficha de Seguridad del usuario
$ficha = $usuarioServicio->obtenerFichaSeguridad($nuevoUsuarioId);
afirmar($ficha !== null, "Ficha de Seguridad recuperada exitosamente");
afirmar(isset($ficha['roles']) && is_array($ficha['roles']), "Ficha contiene lista de roles asignados");
afirmar(isset($ficha['eventos_recientes']) && is_array($ficha['eventos_recientes']), "Ficha contiene timeline de eventos recientes");
afirmar(array_key_exists('ultimo_login_ip', $ficha), "Ficha de Seguridad sí provee trazabilidad de IP");

echo "\n";

// -------------------------------------------------------------------------
// BLOQUE 12: Integridad de Vistas Alina y Frontend Vanilla JS
// -------------------------------------------------------------------------
echo "--- BLOQUE 12: Integridad de Vistas Alina y Frontend Vanilla JS ---\n";

// 12.1 Vistas físicas
afirmar(file_exists(__DIR__ . '/../app/Vistas/modulos/usuarios/index.php'), "Vista app/Vistas/modulos/usuarios/index.php existe");
afirmar(file_exists(__DIR__ . '/../app/Vistas/modulos/usuarios/ficha.php'), "Vista app/Vistas/modulos/usuarios/ficha.php existe");
afirmar(file_exists(__DIR__ . '/../app/Vistas/modulos/autenticacion/cambiar-password-obligatorio.php'), "Vista app/Vistas/modulos/autenticacion/cambiar-password-obligatorio.php existe");

// 12.2 Scripts JS Vanilla
afirmar(file_exists(__DIR__ . '/../public/assets/js/modulos/usuarios/listado-usuarios.js'), "Script public/assets/js/modulos/usuarios/listado-usuarios.js existe");
afirmar(file_exists(__DIR__ . '/../public/assets/js/modulos/usuarios/ficha-usuario.js'), "Script public/assets/js/modulos/usuarios/ficha-usuario.js existe");
afirmar(file_exists(__DIR__ . '/../public/assets/js/modulos/usuarios/cambiar-password-obligatorio.js'), "Script public/assets/js/modulos/usuarios/cambiar-password-obligatorio.js existe");

// 12.3 Prohibición de jQuery AJAX en scripts propios
$jsListado = file_get_contents(__DIR__ . '/../public/assets/js/modulos/usuarios/listado-usuarios.js');
$jsFicha = file_get_contents(__DIR__ . '/../public/assets/js/modulos/usuarios/ficha-usuario.js');
$jsCambio = file_get_contents(__DIR__ . '/../public/assets/js/modulos/usuarios/cambiar-password-obligatorio.js');

afirmar(!str_contains($jsListado, '$.ajax') && !str_contains($jsListado, '$.post') && !str_contains($jsListado, '$.get('), "listado-usuarios.js libre de llamadas AJAX jQuery");
afirmar(!str_contains($jsFicha, '$.ajax') && !str_contains($jsFicha, '$.post'), "ficha-usuario.js libre de llamadas AJAX jQuery");
afirmar(!str_contains($jsCambio, '$.ajax') && !str_contains($jsCambio, '$.post'), "cambiar-password-obligatorio.js libre de llamadas AJAX jQuery");

// 12.4 Cero clases Tabler en vistas y scripts 1G-2
$contenidoTotal1G2 = $jsListado . $jsFicha . $jsCambio . 
                     file_get_contents(__DIR__ . '/../app/Vistas/modulos/usuarios/index.php') .
                     file_get_contents(__DIR__ . '/../app/Vistas/modulos/usuarios/ficha.php') .
                     file_get_contents(__DIR__ . '/../app/Vistas/modulos/autenticacion/cambiar-password-obligatorio.php');

afirmar(!preg_match('/' . 'ti\s+' . 'ti-/', $contenidoTotal1G2), "Cero clases Tabler en archivos de 1G-2 (100% Font Awesome)");

// 12.5 Enlace en menú de navegación
$navHtml = file_get_contents(__DIR__ . '/../app/Vistas/layouts/parciales/navegacion-lateral.php');
afirmar(str_contains($navHtml, "Vista::url('usuarios')"), "Navegación lateral incluye enlace activo a Vista::url('usuarios')");

// 12.6 Inmutabilidad de admin-dashboard/
afirmar(is_dir(__DIR__ . '/../admin-dashboard/alina'), "admin-dashboard/alina existe e intacto");

echo "\n===================================================================\n";
echo " RESUMEN FINAL 1G-2: {$pruebasSuperadas} de {$totalPruebas} superadas.\n";
echo " RESULTADO SUITE ADMINISTRACIÓN DE USUARIOS: [PASS]\n";
echo "===================================================================\n";
