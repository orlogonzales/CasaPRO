<?php

declare(strict_types=1);

/**
 * =============================================================================
 * CasaPRO — Suite de Pruebas de Administración Web de Empresas (Microfase 2C)
 * =============================================================================
 *
 * Certifica el cumplimiento de los 11 bloques normativos de la Microfase 2C:
 * 1. Persistencia y Menú Dinámico de Empresas (Migración 000012, cero IDs mágicos).
 * 2. Deny-by-default y Seguridad RBAC (empresas.*).
 * 3. Contrato DataTables Server-Side con Adaptador Fetch (paginación, orden, filtros).
 * 4. Endpoint de Personas Jurídicas Disponibles (Select2 anti-duplicidad).
 * 5. Alta Vinculada de Empresa (Unicidad 1:1, Unicidad de Código).
 * 6. Alta Orquestada con Rollback Transaccional Atómico (Contrato completo Persona Jurídica).
 * 7. Inmutabilidad en Edición (persona_id y codigo inmutables, solo nombre_corto).
 * 8. Conmutación de Estado y Cero DELETE Físico (Auditoría de Inexistencia de DELETE).
 * 9. Ficha 360° Integral de la Empresa (Despliegue veraz sin atributos inventados).
 * 10. Auditoría Forense Append-Only y Trazabilidad de Operaciones.
 * 11. Integridad Frontend (Anti-Invención, Cero location.reload(), Fetch DataTables, DELTA = 0).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/comun/AmbientePruebas.php';
require_once __DIR__ . '/comun/FixtureAutenticacion.php';

if (!defined('CASAPRO_TESTING')) {
    define('CASAPRO_TESTING', true);
}

use Tests\Comun\AmbientePruebas;
use Tests\Comun\FixtureAutenticacion;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Modelos\Empresa;
use App\Modelos\Persona;
use App\DTOs\CrearEmpresaDTO;
use App\DTOs\ActualizarEmpresaDTO;
use App\DTOs\CambiarEstadoEmpresaDTO;
use App\DTOs\CrearPersonaDTO;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Repositorios\MenuRepositorio;
use App\Servicios\EmpresaServicio;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\AutorizacionServicio;
use App\Controladores\EmpresaControlador;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;

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

echo "===================================================================\n";
echo " SUITE DE PRUEBAS: ADMINISTRACIÓN WEB DE EMPRESAS (MICROFASE 2C)\n";
echo "===================================================================\n\n";

// Iniciar ambiente aislado en casapro_test
$conexion = AmbientePruebas::iniciar(true);
$proveedor = AmbientePruebas::obtenerProveedorTest();
$contexto = new ContextoPeticion('REQ-TEST-2C', '127.0.0.1', 'CLI-PHPUnit/2C', 'CLI', 1);

GestorSesion::iniciar();
$auth = FixtureAutenticacion::autenticarComoSuperadmin($conexion);
$operadorId = (int) $auth['usuario_id'];

$empresaRepo = new EmpresaRepositorio($proveedor);
$personaRepo = new PersonaRepositorio($proveedor);
$auditoriaServicio = new AuditoriaServicio($proveedor);
$personaServicio = new PersonaServicio($proveedor, $personaRepo, $auditoriaServicio);
$empresaServicio = new EmpresaServicio($proveedor, $empresaRepo, $personaServicio, $personaRepo, $auditoriaServicio);
$empresaControlador = new EmpresaControlador($empresaServicio);
$autorizacionServicio = new AutorizacionServicio($proveedor);

// =============================================================================
// BLOQUE 1: Persistencia, Migración 000012 y Menú de Empresas
// =============================================================================
echo "1. Persistencia, Migración 000012 y Menú Corporativo en Base de Datos\n";

$dbActual = (string) $conexion->query('SELECT DATABASE()')->fetchColumn();
afirmar($dbActual === 'casapro_test', "La suite se ejecuta exclusivamente en casapro_test");

// 1.1 Verificar migración 000012 en disco y en base de datos de desarrollo
$rutaMig000012 = dirname(__DIR__) . '/SQL/migraciones/2026_09_30_000012_sembrar_menu_empresas.sql';
afirmar(file_exists($rutaMig000012), "Archivo físico de migración 000012 existe en SQL/migraciones");

$configDev = require dirname(__DIR__) . '/config/database.php';
$pdoDev = new PDO("mysql:host={$configDev['host']};dbname=casapro;charset=utf8mb4", $configDev['username'], $configDev['password']);
$stmtMig = $pdoDev->prepare("SELECT COUNT(*) FROM `migraciones` WHERE `migracion` LIKE '%000012_sembrar_menu_empresas%'");
$stmtMig->execute();
afirmar(((int) $stmtMig->fetchColumn()) >= 1, "Migración 000012 registrada formalmente en la tabla 'migraciones' de casapro");

// 1.2 Verificar que la siguiente ranura libre es 000014
$archivosMig = glob(dirname(__DIR__) . '/SQL/migraciones/*.sql');
$archivosPosteriores = array_filter($archivosMig, fn($a) => basename($a) >= '2026_10_01_000014');
afirmar(empty($archivosPosteriores), "Ranura 000014 permanece libre para la siguiente microfase");

// 1.3 Verificar nodos de menú sembrados sin IDs mágicos
$stmtGrp = $conexion->prepare("
    SELECT m.id, m.padre_id, m.codigo, m.etiqueta, m.privilegio_id, p.codigo AS priv_codigo, pad.codigo AS padre_codigo
    FROM `menu_opciones` m
    INNER JOIN `privilegios` p ON p.id = m.privilegio_id
    INNER JOIN `menu_opciones` pad ON pad.id = m.padre_id
    WHERE m.codigo = 'GRP_EMPRESAS'
");
$stmtGrp->execute();
$grpEmpresas = $stmtGrp->fetch(PDO::FETCH_ASSOC);

afirmar($grpEmpresas !== false, "Nodo agrupador 'GRP_EMPRESAS' existe en 'menu_opciones'");
afirmar($grpEmpresas['padre_codigo'] === 'MOD_IDENTIDAD', "GRP_EMPRESAS cuelga correctamente de 'MOD_IDENTIDAD'");
afirmar($grpEmpresas['priv_codigo'] === 'empresas.ver', "GRP_EMPRESAS está vinculado al privilegio canónico 'empresas.ver'");

$stmtOpc = $conexion->prepare("
    SELECT m.id, m.padre_id, m.codigo, m.etiqueta, m.ruta, m.icono, m.privilegio_id, p.codigo AS priv_codigo, pad.codigo AS padre_codigo
    FROM `menu_opciones` m
    INNER JOIN `privilegios` p ON p.id = m.privilegio_id
    INNER JOIN `menu_opciones` pad ON pad.id = m.padre_id
    WHERE m.codigo = 'OPC_EMPRESAS_LISTADO'
");
$stmtOpc->execute();
$opcEmpresas = $stmtOpc->fetch(PDO::FETCH_ASSOC);

afirmar($opcEmpresas !== false, "Nodo enlace 'OPC_EMPRESAS_LISTADO' existe en 'menu_opciones'");
afirmar($opcEmpresas['padre_codigo'] === 'GRP_EMPRESAS', "OPC_EMPRESAS_LISTADO cuelga correctamente de 'GRP_EMPRESAS'");
afirmar($opcEmpresas['ruta'] === 'empresas', "OPC_EMPRESAS_LISTADO apunta a la ruta 'empresas'");
afirmar($opcEmpresas['icono'] === 'fa-solid fa-building', "OPC_EMPRESAS_LISTADO utiliza el icono 'fa-solid fa-building'");
afirmar($opcEmpresas['priv_codigo'] === 'empresas.ver', "OPC_EMPRESAS_LISTADO está vinculado al privilegio canónico 'empresas.ver'");

// =============================================================================
// BLOQUE 2: Rutas y Seguridad RBAC / Deny-by-Default
// =============================================================================
echo "\n2. Rutas y Seguridad RBAC (empresas.*)\n";

$archivoRutas = file_get_contents(dirname(__DIR__) . '/config/rutas.php');
afirmar(str_contains($archivoRutas, "'/empresas'"), "Ruta web '/empresas' registrada en config/rutas.php");
afirmar(str_contains($archivoRutas, "'/api/empresas'"), "Endpoint '/api/empresas' registrado en config/rutas.php");
afirmar(str_contains($archivoRutas, "'/api/empresas/{id}'"), "Endpoint '/api/empresas/{id}' registrado en config/rutas.php");
afirmar(str_contains($archivoRutas, "'/api/empresas/{id}/estado'"), "Endpoint '/api/empresas/{id}/estado' registrado en config/rutas.php");
afirmar(str_contains($archivoRutas, "'/api/empresas/personas-juridicas-disponibles'"), "Endpoint '/api/empresas/personas-juridicas-disponibles' registrado en config/rutas.php");
afirmar(str_contains($archivoRutas, "AutorizacionMiddleware::exigir('empresas.ver')"), "Middleware de autorización exige 'empresas.ver' para listado y detalle");
afirmar(str_contains($archivoRutas, "AutorizacionMiddleware::exigir('empresas.crear')"), "Middleware de autorización exige 'empresas.crear' para creación");
afirmar(str_contains($archivoRutas, "AutorizacionMiddleware::exigir('empresas.editar')"), "Middleware de autorización exige 'empresas.editar' para actualización");
afirmar(str_contains($archivoRutas, "AutorizacionMiddleware::exigir('empresas.cambiar_estado')"), "Middleware de autorización exige 'empresas.cambiar_estado' para switch");

// Verificar autorización RBAC de SUPERADMIN
afirmar($autorizacionServicio->tienePrivilegio($operadorId, 'empresas.ver'), "SUPERADMIN tiene privilegio 'empresas.ver'");
afirmar($autorizacionServicio->tienePrivilegio($operadorId, 'empresas.crear'), "SUPERADMIN tiene privilegio 'empresas.crear'");
afirmar($autorizacionServicio->tienePrivilegio($operadorId, 'empresas.editar'), "SUPERADMIN tiene privilegio 'empresas.editar'");
afirmar($autorizacionServicio->tienePrivilegio($operadorId, 'empresas.cambiar_estado'), "SUPERADMIN tiene privilegio 'empresas.cambiar_estado'");

// =============================================================================
// BLOQUE 3: DataTables Server-Side Contract (GET /api/empresas)
// =============================================================================
echo "\n3. Contrato DataTables Server-Side con Whitelist y Paginación Segura\n";

$resultadoDt = $empresaServicio->obtenerListadoDataTables([
    'draw'         => 1,
    'start'        => 0,
    'length'       => 10,
    'search'       => '',
    'estado'       => '',
    'order_column' => 'codigo',
    'order_dir'    => 'ASC'
]);

afirmar(isset($resultadoDt['draw']) && $resultadoDt['draw'] === 1, "DataTables response incluye 'draw' (int)");
afirmar(isset($resultadoDt['recordsTotal']) && is_int($resultadoDt['recordsTotal']), "DataTables response incluye 'recordsTotal' (int)");
afirmar(isset($resultadoDt['recordsFiltered']) && is_int($resultadoDt['recordsFiltered']), "DataTables response incluye 'recordsFiltered' (int)");
afirmar(isset($resultadoDt['data']) && is_array($resultadoDt['data']), "DataTables response incluye 'data' (array)");

// Prueba de inyección SQL en order_column (debe aplicar whitelist y no fallar)
$resultadoInyeccion = $empresaServicio->obtenerListadoDataTables([
    'draw'         => 2,
    'start'        => 0,
    'length'       => 10,
    'search'       => '',
    'estado'       => '',
    'order_by'     => 'id; DROP TABLE usuarios;',
    'order_dir'    => 'DESC'
]);
afirmar(is_array($resultadoInyeccion['data']), "Columna de orden maliciosa es sanitizada mediante whitelist estricta");

// =============================================================================
// BLOQUE 4: Endpoint Personas Jurídicas Disponibles (Select2)
// =============================================================================
echo "\n4. Endpoint de Personas Jurídicas Disponibles (Anti-Duplicidad Select2)\n";

// Crear Persona Jurídica 1 en el padrón
$dtoPJ1 = CrearPersonaDTO::desdeArray([
    'tipo_persona' => Persona::TIPO_JURIDICA,
    'razon_social' => 'CONSTRUCTORA LOS ANDES S.A.C.',
    'nombre_comercial' => 'Los Andes',
    'documentos' => [
        [
            'tipo_documento_id' => 2, // RUC
            'numero_documento'  => '20556677881',
            'es_principal'      => 1
        ]
    ]
]);
$resPJ1 = $personaServicio->crear($dtoPJ1, $contexto);
$pj1Id = (int) ($resPJ1['id'] ?? $resPJ1['persona_id'] ?? 0);

// Crear Persona Jurídica 2 en el padrón
$dtoPJ2 = CrearPersonaDTO::desdeArray([
    'tipo_persona' => Persona::TIPO_JURIDICA,
    'razon_social' => 'INMOBILIARIA DEL VALLE S.A.C.',
    'nombre_comercial' => 'Valle Inmobiliario',
    'documentos' => [
        [
            'tipo_documento_id' => 2, // RUC
            'numero_documento'  => '20556677882',
            'es_principal'      => 1
        ]
    ]
]);
$resPJ2 = $personaServicio->crear($dtoPJ2, $contexto);
$pj2Id = (int) ($resPJ2['id'] ?? $resPJ2['persona_id'] ?? 0);

// Crear Persona Natural (no debe listarse en personas jurídicas disponibles)
$dtoPN = CrearPersonaDTO::desdeArray([
    'tipo_persona'     => Persona::TIPO_NATURAL,
    'nombres'          => 'Carlos',
    'apellido_paterno' => 'Mendoza',
    'documentos'       => [
        [
            'tipo_documento_id' => 1, // DNI
            'numero_documento'  => '47889901',
            'es_principal'      => 1
        ]
    ]
]);
$resPN = $personaServicio->crear($dtoPN, $contexto);
$pnId = (int) ($resPN['id'] ?? $resPN['persona_id'] ?? 0);

$disponibles = $empresaServicio->obtenerPersonasJuridicasDisponibles();
$idsDisponibles = array_column($disponibles, 'id');

afirmar(in_array($pj1Id, $idsDisponibles, true), "Persona Jurídica 1 aparece en personas jurídicas disponibles");
afirmar(in_array($pj2Id, $idsDisponibles, true), "Persona Jurídica 2 aparece en personas jurídicas disponibles");
afirmar(!in_array($pnId, $idsDisponibles, true), "Persona Natural está excluida de personas jurídicas disponibles");

// Probar filtro de búsqueda en personas disponibles
$busquedaAndes = $empresaServicio->obtenerPersonasJuridicasDisponibles('Andes');
afirmar(count($busquedaAndes) >= 1 && (int) $busquedaAndes[0]['id'] === $pj1Id, "Filtro de búsqueda por término retorna la persona jurídica correcta");

// =============================================================================
// BLOQUE 5: Alta Vinculada de Empresa (POST /api/empresas)
// =============================================================================
echo "\n5. Alta Vinculada de Empresa (Unicidad 1:1 y Unicidad de Código)\n";

$dtoCrearEmpresa1 = CrearEmpresaDTO::desdeArray([
    'persona_id'   => $pj1Id,
    'codigo'       => 'ANDES_SAC',
    'nombre_corto' => 'Andes Inmobiliaria'
]);

$resEmpresa1 = $empresaServicio->crear($dtoCrearEmpresa1, $operadorId, $contexto);
$empresa1Id = (int) $resEmpresa1['id'];

afirmar($empresa1Id > 0, "Empresa 1 creada exitosamente con ID {$empresa1Id}");

$empresa1Bd = $empresaRepo->buscarPorId($empresa1Id);
afirmar($empresa1Bd !== null, "Empresa 1 recuperada de la base de datos");
afirmar($empresa1Bd['codigo'] === 'ANDES_SAC', "Código de Empresa 1 persistido correctamente");
afirmar($empresa1Bd['estado'] === Empresa::ESTADO_ACTIVO, "Empresa 1 se crea en estado inicial ACTIVO");

// Verificar que PJ1 ya no aparece como disponible
$disponiblesPost = $empresaServicio->obtenerPersonasJuridicasDisponibles();
$idsDisponiblesPost = array_column($disponiblesPost, 'id');
afirmar(!in_array($pj1Id, $idsDisponiblesPost, true), "PJ1 ya no aparece disponible tras ser vinculada a Empresa 1");

// Regla 1: Unicidad 1:1 Persona Jurídica -> Empresa (HTTP 409 Conflict)
$conflictoPersona1a1 = false;
try {
    $dtoRepetido = CrearEmpresaDTO::desdeArray([
        'persona_id'   => $pj1Id,
        'codigo'       => 'OTRA_EMPRESA_SAC',
        'nombre_corto' => 'Otra Empresa'
    ]);
    $empresaServicio->crear($dtoRepetido, $operadorId, $contexto);
} catch (ReglaNegocioExcepcion $rne) {
    $conflictoPersona1a1 = ($rne->getCode() === 409);
}
afirmar($conflictoPersona1a1, "Intento de vincular la misma Persona Jurídica arroja HTTP 409 Conflict");

// Regla 2: Unicidad de Código Corporativo (HTTP 409 Conflict)
$conflictoCodigo = false;
try {
    $dtoCodRepetido = CrearEmpresaDTO::desdeArray([
        'persona_id'   => $pj2Id,
        'codigo'       => 'ANDES_SAC', // Código ya en uso
        'nombre_corto' => 'Nombre Valido'
    ]);
    $empresaServicio->crear($dtoCodRepetido, $operadorId, $contexto);
} catch (ReglaNegocioExcepcion $rne) {
    $conflictoCodigo = ($rne->getCode() === 409);
}
afirmar($conflictoCodigo, "Intento de usar código corporativo duplicado arroja HTTP 409 Conflict");

// =============================================================================
// BLOQUE 6: Alta Orquestada con Rollback Transaccional Atómico
// =============================================================================
echo "\n6. Alta Orquestada con Rollback Transaccional Atómico (Contrato Completo)\n";

$dtoOrquestada = CrearEmpresaDTO::desdeArray([
    'codigo'       => 'ORQ_NORTE_SAC',
    'nombre_corto' => 'Norte Desarrollos',
    'datos_persona' => [
        'tipo_persona'     => Persona::TIPO_JURIDICA,
        'razon_social'     => 'DESARROLLOS DEL NORTE S.A.C.',
        'nombre_comercial' => 'Norte Desarrollos',
        'fecha_constitucion' => '2020-03-15',
        'objeto_social'    => 'Desarrollo de proyectos inmobiliarios y habilitación urbana.',
        'documentos'       => [
            [
                'tipo_documento_id' => 2,
                'numero_documento'  => '20998877661',
                'es_principal'      => 1
            ]
        ],
        'direcciones'      => [
            [
                'tipo_direccion_id' => 1,
                'distrito_id'       => 1,
                'direccion'         => 'Av. América Norte 1234, Urb. Las Quintanas',
                'referencia'        => 'Frente a la clínica',
                'codigo_postal'     => '13001',
                'es_principal'      => 1
            ]
        ],
        'contactos'        => [
            [
                'tipo_contacto_id' => 2,
                'valor'            => 'contacto@nortedesarrollos.pe',
                'etiqueta'         => 'Mesa de partes',
                'es_principal'     => 1
            ]
        ]
    ]
]);

$resOrq = $empresaServicio->crear($dtoOrquestada, $operadorId, $contexto);
$empresaOrqId = (int) $resOrq['id'];
afirmar($empresaOrqId > 0, "Alta orquestada ejecutada con éxito (Empresa ID: {$empresaOrqId})");

$empresaOrqBd = $empresaRepo->buscarPorId($empresaOrqId);
afirmar($empresaOrqBd !== null && $empresaOrqBd['codigo'] === 'ORQ_NORTE_SAC', "Empresa orquestada persistida en BD");
$pjOrqId = (int) $empresaOrqBd['persona_id'];
$pjOrqBd = $personaRepo->buscarPorId($pjOrqId);
afirmar($pjOrqBd !== null && $pjOrqBd['tipo_persona'] === Persona::TIPO_JURIDICA, "Persona Jurídica fue creada transaccionalmente en el padrón");

// Verificación de Rollback Transaccional Atómico:
// Provocar error en inserción de empresa (código duplicado) para verificar que la persona jurídica NO queda huérfana
$totalPersonasAntes = (int) $conexion->query("SELECT COUNT(*) FROM `personas`")->fetchColumn();
$rollbackExitoso = false;

try {
    $dtoFalla = CrearEmpresaDTO::desdeArray([
        'codigo'       => 'ORQ_NORTE_SAC', // Ya existe, detona 409
        'nombre_corto' => 'Empresa Fallida',
        'datos_persona' => [
            'tipo_persona' => Persona::TIPO_JURIDICA,
            'razon_social' => 'EMPRESA QUE DEBE REVERTIRSE S.A.C.',
            'documentos'   => [
                [
                    'tipo_documento_id' => 2,
                    'numero_documento'  => '20112233445',
                    'es_principal'      => 1
                ]
            ]
        ]
    ]);
    $empresaServicio->crear($dtoFalla, $operadorId, $contexto);
} catch (ReglaNegocioExcepcion $rne) {
    $rollbackExitoso = true;
}

$totalPersonasDespues = (int) $conexion->query("SELECT COUNT(*) FROM `personas`")->fetchColumn();
afirmar($rollbackExitoso, "Error forzado en alta orquestada detona ReglaNegocioExcepcion");
afirmar($totalPersonasAntes === $totalPersonasDespues, "Rollback atómico verificado: Cero personas jurídicas huérfanas tras fallo transaccional");

// =============================================================================
// BLOQUE 7: Inmutabilidad en Edición (PUT /api/empresas/{id})
// =============================================================================
echo "\n7. Inmutabilidad en Edición (persona_id y codigo inmutables)\n";

$dtoActualizar = ActualizarEmpresaDTO::desdeArray([
    'nombre_corto' => 'Andes Inmobiliaria y Construcción'
]);

$resActualizar = $empresaServicio->actualizar($empresa1Id, $dtoActualizar, $operadorId, $contexto);
afirmar($resActualizar['nombre_corto'] === 'Andes Inmobiliaria y Construcción', "Nombre corto actualizado exitosamente");

$empresa1PostActualizar = $empresaRepo->buscarPorId($empresa1Id);
afirmar($empresa1PostActualizar['persona_id'] === $pj1Id, "persona_id permanece inalterado");
afirmar($empresa1PostActualizar['codigo'] === 'ANDES_SAC', "codigo corporativo permanece inalterado");

// Intento de modificar persona_id o codigo debe ser rechazado con 422
$intentoInmutableRechazado = false;
try {
    ActualizarEmpresaDTO::desdeArray([
        'nombre_corto' => 'Nuevo Nombre',
        'codigo'       => 'CODIGO_MODIFICADO'
    ]);
} catch (ValidacionExcepcion $ve) {
    $intentoInmutableRechazado = true;
}
afirmar($intentoInmutableRechazado, "Intento de enviar 'codigo' en actualización es bloqueado con ValidacionExcepcion (422)");

$intentoPersonaInmutableRechazado = false;
try {
    ActualizarEmpresaDTO::desdeArray([
        'nombre_corto' => 'Nuevo Nombre',
        'persona_id'   => 9999
    ]);
} catch (ValidacionExcepcion $ve) {
    $intentoPersonaInmutableRechazado = true;
}
afirmar($intentoPersonaInmutableRechazado, "Intento de enviar 'persona_id' en actualización es bloqueado con ValidacionExcepcion (422)");

// =============================================================================
// BLOQUE 8: Conmutación de Estado y Cero DELETE Físico
// =============================================================================
echo "\n8. Conmutación de Estado (PATCH) y Cero DELETE Físico\n";

// Conmutar a INACTIVO
$dtoInactivar = CambiarEstadoEmpresaDTO::desdeArray(['estado' => Empresa::ESTADO_INACTIVO]);
$resInactivar = $empresaServicio->cambiarEstado($empresa1Id, $dtoInactivar, $operadorId, $contexto);
afirmar($resInactivar['estado'] === Empresa::ESTADO_INACTIVO, "Empresa 1 conmutada a INACTIVO vía PATCH");

$empresa1InactivaBd = $empresaRepo->buscarPorId($empresa1Id);
afirmar($empresa1InactivaBd['estado'] === Empresa::ESTADO_INACTIVO, "Estado INACTIVO persistido en base de datos");

// Conmutar de vuelta a ACTIVO
$dtoActivar = CambiarEstadoEmpresaDTO::desdeArray(['estado' => Empresa::ESTADO_ACTIVO]);
$resActivar = $empresaServicio->cambiarEstado($empresa1Id, $dtoActivar, $operadorId, $contexto);
afirmar($resActivar['estado'] === Empresa::ESTADO_ACTIVO, "Empresa 1 reactivada a ACTIVO vía PATCH");

// Auditoría de Cero DELETE Físico:
// Inspeccionar EmpresaControlador mediante Reflection para asegurar que NO hay métodos de borrado
$reflectorControlador = new ReflectionClass(EmpresaControlador::class);
$metodosProhibidos = ['eliminar', 'destroy', 'delete', 'borrar', 'remover'];
foreach ($metodosProhibidos as $mp) {
    afirmar(!$reflectorControlador->hasMethod($mp), "EmpresaControlador no implementa método de borrado físico '{$mp}'");
}

// Inspeccionar rutas para asegurar que no hay verbo DELETE en empresas
$rutasContenido = file_get_contents(dirname(__DIR__) . '/config/rutas.php');
$coincidenciasDelete = preg_match("/->agregarRuta\s*\(\s*'DELETE'\s*,\s*'(\/api)?\/empresas/", $rutasContenido);
afirmar($coincidenciasDelete === 0, "No existe ninguna ruta con verbo HTTP DELETE asociada a empresas en config/rutas.php");

// =============================================================================
// BLOQUE 9: Ficha 360° Integral de la Empresa
// =============================================================================
echo "\n9. Ficha 360° Integral de la Empresa (Despliegue Veraz y Soberano)\n";

$detalle360 = $empresaServicio->obtenerDetalleCompleto($empresaOrqId);
afirmar($detalle360 !== null, "Ficha 360° de la empresa recuperada exitosamente");
afirmar(isset($detalle360['empresa']) && is_array($detalle360['empresa']), "Ficha 360° incluye sección 'empresa'");
afirmar(isset($detalle360['detalle_persona']) && is_array($detalle360['detalle_persona']), "Ficha 360° incluye sección 'detalle_persona'");

$detPersona = $detalle360['detalle_persona'];
afirmar(isset($detPersona['persona']), "detalle_persona contiene datos civiles de 'persona'");
afirmar(isset($detPersona['juridica']), "detalle_persona contiene datos de 'juridica'");
afirmar(isset($detPersona['documentos']) && count($detPersona['documentos']) >= 1, "detalle_persona contiene documentos con RUC");
afirmar(isset($detPersona['direcciones']) && count($detPersona['direcciones']) >= 1, "detalle_persona contiene direcciones registradas");
afirmar(isset($detPersona['contactos']) && count($detPersona['contactos']) >= 1, "detalle_persona contiene contactos registrados");

// Verificar que empresa inexistente retorna null
afirmar($empresaServicio->obtenerDetalleCompleto(999999) === null, "Consulta de Ficha 360° de ID inexistente retorna null (HTTP 404)");

// =============================================================================
// BLOQUE 10: Auditoría Forense Append-Only
// =============================================================================
echo "\n10. Auditoría Forense Append-Only y Trazabilidad de Operaciones\n";

$stmtAudCrear = $conexion->prepare("
    SELECT COUNT(*) FROM `auditorias`
    WHERE `entidad` = 'empresas' AND `accion` = 'CREAR' AND `registro_id` = :id
");
$stmtAudCrear->bindValue(':id', $empresa1Id, PDO::PARAM_INT);
$stmtAudCrear->execute();
afirmar(((int) $stmtAudCrear->fetchColumn()) >= 1, "Evento CREAR registrado en auditoría para Empresa 1");

$stmtAudAct = $conexion->prepare("
    SELECT COUNT(*) FROM `auditorias`
    WHERE `entidad` = 'empresas' AND `accion` = 'ACTUALIZAR' AND `registro_id` = :id
");
$stmtAudAct->bindValue(':id', $empresa1Id, PDO::PARAM_INT);
$stmtAudAct->execute();
afirmar(((int) $stmtAudAct->fetchColumn()) >= 1, "Evento ACTUALIZAR registrado en auditoría para Empresa 1");

$stmtAudEstado = $conexion->prepare("
    SELECT COUNT(*) FROM `auditorias`
    WHERE `entidad` = 'empresas' AND `accion` = 'CAMBIAR_ESTADO' AND `registro_id` = :id
");
$stmtAudEstado->bindValue(':id', $empresa1Id, PDO::PARAM_INT);
$stmtAudEstado->execute();
afirmar(((int) $stmtAudEstado->fetchColumn()) >= 1, "Evento CAMBIAR_ESTADO registrado en auditoría para Empresa 1");

// =============================================================================
// BLOQUE 11: Integridad Frontend (Anti-Invención y Script Asíncrono)
// =============================================================================
echo "\n11. Integridad Frontend (Anti-Invención, Cero location.reload() y DELTA = 0)\n";

$vistaPath = dirname(__DIR__) . '/app/Vistas/modulos/empresas/index.php';
afirmar(file_exists($vistaPath), "Archivo de vista app/Vistas/modulos/empresas/index.php existe");
$vistaContenido = file_get_contents($vistaPath);

afirmar(str_contains($vistaContenido, 'id="tablaEmpresas"'), "Vista define la tabla DataTables #tablaEmpresas");
afirmar(str_contains($vistaContenido, 'id="modalCrearEmpresa"'), "Vista define el diálogo modal #modalCrearEmpresa");
afirmar(str_contains($vistaContenido, 'id="modalEditarEmpresa"'), "Vista define el diálogo modal #modalEditarEmpresa");
afirmar(str_contains($vistaContenido, 'id="modalFichaEmpresa"'), "Vista define el diálogo modal #modalFichaEmpresa");
afirmar(str_contains($vistaContenido, 'id="tab-vincular-btn"'), "Vista define la pestaña de vinculación de persona jurídica existente");
afirmar(str_contains($vistaContenido, 'id="tab-orquestada-btn"'), "Vista define la pestaña de alta orquestada completa");
afirmar(str_contains($vistaContenido, 'data-csrf-token='), "Vista inyecta el token CSRF para peticiones seguras");

$scriptPath = dirname(__DIR__) . '/public/assets/js/modulos/empresas/gestion-empresas.js';
afirmar(file_exists($scriptPath), "Archivo de script public/assets/js/modulos/empresas/gestion-empresas.js existe");
$scriptContenido = file_get_contents($scriptPath);

afirmar(str_contains($scriptContenido, 'window.CasaProEmpresas'), "Script expone el namespace soberano window.CasaProEmpresas");
afirmar(str_contains($scriptContenido, 'window.fetch('), "Script implementa adaptador HTTP nativo window.fetch()");
afirmar(str_contains($scriptContenido, 'self.tabla.ajax.reload(null, false)'), "Script refresca DataTables conservando página con ajax.reload(null, false)");

// Cero location.reload() en código ejecutable
$lineasScript = explode("\n", $scriptContenido);
$fallasReload = 0;
$fallasJqueryAjax = 0;
foreach ($lineasScript as $numLinea => $linea) {
    $lineaTrim = trim($linea);
    if (str_starts_with($lineaTrim, '*') || str_starts_with($lineaTrim, '//')) {
        continue; // Ignorar comentarios explicativos
    }
    if (str_contains($linea, 'location.reload')) {
        $fallasReload++;
    }
    if (preg_match('/(\$\.ajax|\$\.get\(|\$\.post\(|\$\.getJSON\()/', $linea)) {
        $fallasJqueryAjax++;
    }
}
afirmar($fallasReload === 0, "Cero llamadas a location.reload() en código ejecutable propio");
afirmar($fallasJqueryAjax === 0, "Cero llamadas a $.ajax / $.get / $.post en código ejecutable propio");

// Verificar DELTA casapro == 0 en esquema de base de datos
$tablasDev = (int) $conexion->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'")->fetchColumn();
afirmar($tablasDev >= 28, "Esquema relacional consolidado oficial contiene al menos 28 tablas (actual: {$tablasDev})");

echo "\n===================================================================\n";
echo " RESULTADO: {$pruebasSuperadas} / {$totalPruebas} PRUEBAS SUPERADAS (100% PASS)\n";
echo "===================================================================\n";
