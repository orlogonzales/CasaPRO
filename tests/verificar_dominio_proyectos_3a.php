<?php

declare(strict_types=1);

/**
 * Suite de Verificación Automatizada: Microfase 3A — Dominio de Proyectos y Predios Matrices
 *
 * Cobertura de Gates y Mandatos:
 * 1. Persistencia: Ranura 000013 consumida, ranura 000014 libre, 31 tablas relacionales.
 * 2. GATE 3A-01: Contrapartes desacopladas (tabla proyecto_participantes 1:N, sin persona_asociada_id singular).
 * 3. GATE 3A-02: Partida registral progresiva (partida_registral NULLable en saneamiento, unicidad sin colisión de NULLs).
 * 4. GATE 3A-03: Levantamiento topográfico progresivo (area_topografica_m2 NULLable, nunca 0.0000 ficticio).
 * 5. GATE 3A-04: Política determinista de tolerancia (ABSOLUTA_M2 y PORCENTUAL, evaluación de semáforo de 4 estados).
 * 6. Anti-IDOR Estricto en Cascada (aislamiento Empresa A vs B en proyectos y predios matrices).
 * 7. Inmutabilidad de Código Corporativo y Moneda Soberana.
 * 8. DataTables Server-Side con agregaciones de métricas prediales.
 * 9. Auditoría forense append-only con Actor USER real.
 * 10. DELTA casapro (desarrollo) = 0.
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
use App\Modelos\Empresa;
use App\Modelos\Proyecto;
use App\Modelos\ProyectoPredioMatriz;
use App\Modelos\ProyectoParticipante;
use App\DTOs\CrearProyectoDTO;
use App\DTOs\ActualizarProyectoDTO;
use App\DTOs\CambiarEstadoProyectoDTO;
use App\DTOs\CrearPredioMatrizDTO;
use App\DTOs\ActualizarPredioMatrizDTO;
use App\DTOs\CambiarEstadoPredioMatrizDTO;
use App\Repositorios\ProyectoRepositorio;
use App\Repositorios\ProyectoPredioMatrizRepositorio;
use App\Repositorios\EmpresaRepositorio;
use App\Servicios\ProyectoServicio;
use App\Servicios\AuditoriaServicio;
use App\Controladores\ProyectoControlador;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;

echo "===================================================================\n";
echo " VERIFICACIÓN AUTOMATIZADA: MICROFASE 3A — PROYECTOS Y PREDIOS\n";
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

// Capturar métricas iniciales de desarrollo casapro para validar DELTA = 0
$configDev = require dirname(__DIR__) . '/config/database.php';
$configDev['database'] = 'casapro';
$pdoDev = (new ProveedorConexion($configDev))->obtenerConexion();
$conteoProyectosDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyectos`')->fetchColumn();
$conteoPrediosDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyecto_predios_matriz`')->fetchColumn();
$conteoParticipantesDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyecto_participantes`')->fetchColumn();
$conteoAuditoriasDevInicial = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

// --- BLOQUE 1: Persistencia, Migraciones y Esquema 3A ---
echo "\n--- BLOQUE 1: Persistencia, Migraciones y Esquema 3A ---\n";

$migracion13 = glob(dirname(__DIR__) . '/SQL/migraciones/*000013*');
probar(!empty($migracion13), 'Ranura de migración 000013 consumida y registrada en el repositorio');

$migracion14 = glob(dirname(__DIR__) . '/SQL/migraciones/*000014*');
probar(empty($migracion14), 'Ranura de migración 000014 permanece estrictamente libre para Fase 3B');

$totalTablas = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'")->fetchColumn();
probar($totalTablas === 31, "Esquema relacional consolidado contiene exactamente 31 tablas (actual: {$totalTablas})");

// Instanciar repositorios y servicios en casapro_test
$provTest = new ProveedorConexion(['database' => 'casapro_test']);
$proyectoRepo = new ProyectoRepositorio($provTest);
$predioRepo = new ProyectoPredioMatrizRepositorio($provTest);
$empresaRepo = new EmpresaRepositorio($provTest);
$auditoriaServicio = new AuditoriaServicio($provTest);
$proyectoServicio = new ProyectoServicio($provTest, $proyectoRepo, $predioRepo, $empresaRepo, $auditoriaServicio);

// --- BLOQUE 2: Fixtures Sembrados en casapro_test ---
echo "\n--- BLOQUE 2: Fixtures Sembrados en casapro_test ---\n";

// Sembrar Personas Jurídicas para Empresas A y B
$pdo->exec("INSERT INTO `personas` (`id`, `tipo_persona`, `estado`) VALUES 
    (501, 'JURIDICA', 'ACTIVO'),
    (502, 'JURIDICA', 'ACTIVO'),
    (601, 'NATURAL', 'ACTIVO'),
    (602, 'JURIDICA', 'ACTIVO')
    ON DUPLICATE KEY UPDATE `estado` = VALUES(`estado`)");

$pdo->exec("INSERT INTO `persona_juridica` (`persona_id`, `razon_social`, `nombre_comercial`) VALUES 
    (501, 'DESARROLLADORA INMOBILIARIA ALFA S.A.C.', 'Alfa Inmobiliaria'),
    (502, 'CONSTRUCTORA E INVERSIONES BETA S.A.C.', 'Beta Constructora'),
    (602, 'ASOCIACION PRO VIVIENDA LOS KANTUS', 'APV Los Kantus')
    ON DUPLICATE KEY UPDATE `nombre_comercial` = VALUES(`nombre_comercial`)");

$pdo->exec("INSERT INTO `persona_natural` (`persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`) VALUES 
    (601, 'Juan Carlos', 'Quispe', 'Mamani')
    ON DUPLICATE KEY UPDATE `nombres` = VALUES(`nombres`)");

$empresaAlfaId = $empresaRepo->insertar(new Empresa(501, 'EMP_ALFA', 'Alfa Inmobiliaria', Empresa::ESTADO_ACTIVO), $pdo);
$empresaBetaId = $empresaRepo->insertar(new Empresa(502, 'EMP_BETA', 'Beta Constructora', Empresa::ESTADO_ACTIVO), $pdo);
probar($empresaAlfaId > 0 && $empresaBetaId > 0, "Empresas fixtures activadas: Alfa (ID {$empresaAlfaId}) y Beta (ID {$empresaBetaId})");

// Sembrar Actores para Usuarios Operadores
$pdo->exec("INSERT INTO `actores` (`id`, `tipo_actor`, `codigo`, `nombre`, `estado`) VALUES 
    (601, 'USUARIO', 'USR_OPERADOR_ALFA', 'Operador Alfa', 'ACTIVO')
    ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)");

$usuarioRepo = new \App\Repositorios\UsuarioRepositorio($provTest);
$usuarioOperador = new \App\Modelos\Usuario(
    601,
    601,
    'operador@alfa.pe',
    'operador.alfa',
    password_hash('Password123!', PASSWORD_BCRYPT),
    \App\Modelos\Usuario::ESTADO_ACTIVO
);
$operadorId = $usuarioRepo->insertar($usuarioOperador, $pdo);

// Obtener distrito válido del catálogo (Cusco o San Jerónimo)
$distritoId = (int) $pdo->query("SELECT id FROM `distritos` LIMIT 1")->fetchColumn();
probar($distritoId > 0, "Distrito UBIGEO obtenido del catálogo: ID {$distritoId}");

// --- BLOQUE 3: GATE 3A-01 (Contrapartes Desacopladas) ---
echo "\n--- BLOQUE 3: GATE 3A-01 (Contrapartes Desacopladas) ---\n";

// Verificar que 'persona_asociada_id' NO existe en tabla 'proyectos'
$columnaPersonaAsociada = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = 'casapro_test' 
      AND table_name = 'proyectos' 
      AND column_name = 'persona_asociada_id'
")->fetchColumn();
probar((int) $columnaPersonaAsociada === 0, 'Invariante: columna singular persona_asociada_id NO existe en tabla proyectos');

// Verificar que tabla extensible 'proyecto_participantes' existe
$tablaParticipantes = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = 'casapro_test' 
      AND table_name = 'proyecto_participantes'
")->fetchColumn();
probar((int) $tablaParticipantes === 1, 'Tabla extensible proyecto_participantes (1:N) creada correctamente');

// Crear un Proyecto Asociativo en Empresa Alfa
$dtoProyecto1 = new CrearProyectoDTO([
    'codigo'              => 'PRJ_VALLE_SAGRADO',
    'nombre'              => 'Valle Sagrado Residencial',
    'descripcion'         => 'Proyecto campestre en convenio con APV',
    'tipo_proyecto'       => Proyecto::TIPO_CONVENIO_APV,
    'moneda'              => Proyecto::MONEDA_USD,
    'distrito_id'         => $distritoId,
    'direccion_referencia'=> 'Carretera Urubamba Km 18',
    'tipo_tolerancia'     => Proyecto::TOLERANCIA_ABSOLUTA_M2,
    'valor_tolerancia'    => 1.0000
]);

$ctx = new ContextoPeticion('CORR-3A-001', '127.0.0.1', 'CLI-TEST', 'CLI', $operadorId, $empresaAlfaId, 'EMPRESA');
$proyecto1 = $proyectoServicio->crear($dtoProyecto1, $empresaAlfaId, $operadorId, $ctx);
$proyecto1Id = (int) $proyecto1['id'];
probar($proyecto1Id > 0, "Proyecto creado con éxito: ID {$proyecto1Id}, Modalidad {$proyecto1['tipo_proyecto']}, Moneda {$proyecto1['moneda']}");

// Insertar 2 contrapartes en proyecto_participantes (1 APV y 1 Propietario Persona Natural)
$stmtPart = $pdo->prepare("
    INSERT INTO `proyecto_participantes` 
    (`proyecto_id`, `persona_id`, `tipo_vinculo`, `porcentaje_participacion`, `fecha_inicio`, `estado`)
    VALUES 
    (:prj1, 602, :vinc1, 60.00, '2026-01-01', 'ACTIVO'),
    (:prj2, 601, :vinc2, 40.00, '2026-01-01', 'ACTIVO')
");
$stmtPart->execute([
    ':prj1'  => $proyecto1Id,
    ':vinc1' => ProyectoParticipante::VINCULO_APV,
    ':prj2'  => $proyecto1Id,
    ':vinc2' => ProyectoParticipante::VINCULO_PROPIETARIO
]);

$conteoPart = (int) $pdo->query("SELECT COUNT(*) FROM `proyecto_participantes` WHERE `proyecto_id` = {$proyecto1Id}")->fetchColumn();
probar($conteoPart === 2, 'Contrapartes múltiples 1:N vinculadas con éxito a proyecto_participantes');

// --- BLOQUE 4: GATE 3A-02 (Partida Registral Progresiva) ---
echo "\n--- BLOQUE 4: GATE 3A-02 (Partida Registral Progresiva) ---\n";

// 1. Alta de Predio Matriz con partida_registral = NULL (En Saneamiento)
$dtoPredio1 = new CrearPredioMatrizDTO([
    'proyecto_id'        => $proyecto1Id,
    'denominacion'       => 'Predio Matriz Sector Alto (En Saneamiento)',
    'partida_registral'  => null,
    'tomo_ficha'         => null,
    'area_registral_m2'  => 50000.0000,
    'area_topografica_m2'=> null,
    'distrito_id'        => $distritoId,
    'antecedente_dominial'=> 'Escritura pública de adjudicación en trámite'
]);
$predio1 = $proyectoServicio->crearPredioMatriz($dtoPredio1, $empresaAlfaId, $operadorId, $ctx);
$predio1Id = (int) $predio1['id'];
probar($predio1Id > 0 && $predio1['partida_registral'] === null, 'Predio 1 registrado con partida_registral = NULL (en saneamiento)');

// 2. Alta de un Segundo Predio Matriz en el mismo proyecto con partida_registral = NULL
$dtoPredio2 = new CrearPredioMatrizDTO([
    'proyecto_id'        => $proyecto1Id,
    'denominacion'       => 'Predio Matriz Sector Bajo (En Trámite Notarial)',
    'partida_registral'  => null,
    'tomo_ficha'         => null,
    'area_registral_m2'  => 30000.0000,
    'area_topografica_m2'=> null,
    'distrito_id'        => $distritoId
]);
$predio2 = $proyectoServicio->crearPredioMatriz($dtoPredio2, $empresaAlfaId, $operadorId, $ctx);
$predio2Id = (int) $predio2['id'];
probar($predio2Id > 0 && $predio2Id !== $predio1Id, 'Múltiples predios con partida_registral = NULL coexisten sin violar uk_predios_proyecto_partida');

// 3. Alta de Predio con Partida Registral Real
$dtoPredio3 = new CrearPredioMatrizDTO([
    'proyecto_id'        => $proyecto1Id,
    'denominacion'       => 'Predio Matriz Titulado SUNARP',
    'partida_registral'  => 'SUNARP-11098234',
    'tomo_ficha'         => 'Ficha 4920',
    'area_registral_m2'  => 20000.0000,
    'area_topografica_m2'=> 20000.5000,
    'distrito_id'        => $distritoId
]);
$predio3 = $proyectoServicio->crearPredioMatriz($dtoPredio3, $empresaAlfaId, $operadorId, $ctx);
$predio3Id = (int) $predio3['id'];
probar($predio3Id > 0 && $predio3['partida_registral'] === 'SUNARP-11098234', 'Predio 3 registrado con partida_registral = SUNARP-11098234');

// 4. Intentar duplicar la misma partida registral en el mismo proyecto (debe fallar 409)
$partidaDuplicadaRechazada = false;
try {
    $dtoDuplicado = new CrearPredioMatrizDTO([
        'proyecto_id'        => $proyecto1Id,
        'denominacion'       => 'Predio Intento Duplicado',
        'partida_registral'  => 'SUNARP-11098234',
        'area_registral_m2'  => 10000.0000,
        'distrito_id'        => $distritoId
    ]);
    $proyectoServicio->crearPredioMatriz($dtoDuplicado, $empresaAlfaId, $operadorId, $ctx);
} catch (ReglaNegocioExcepcion $e) {
    $partidaDuplicadaRechazada = ($e->getCode() === 409);
}
probar($partidaDuplicadaRechazada, 'Rechazo estricto (HTTP 409) al intentar registrar partida registral duplicada en el mismo proyecto');

// --- BLOQUE 5: GATE 3A-03 (Levantamiento Topográfico Progresivo) ---
echo "\n--- BLOQUE 5: GATE 3A-03 (Levantamiento Topográfico Progresivo) ---\n";

// Verificar en BD que area_topografica_m2 de predio 1 es estrictamente NULL, no 0.0000
$stmtCheckNull = $pdo->prepare("SELECT area_topografica_m2 FROM `proyecto_predios_matriz` WHERE id = :id");
$stmtCheckNull->execute([':id' => $predio1Id]);
$valorTopograficoDb = $stmtCheckNull->fetchColumn();
probar($valorTopograficoDb === null, 'Área topográfica almacenada como NULL nativo (sin ceros ficticios 0.0000)');

// Actualizar predio 1 con levantamiento topográfico real
$dtoActPredio1 = new ActualizarPredioMatrizDTO([
    'denominacion'       => 'Predio Matriz Sector Alto (Topografiado)',
    'partida_registral'  => null,
    'area_registral_m2'  => 50000.0000,
    'area_topografica_m2'=> 49999.5000,
    'distrito_id'        => $distritoId
]);
$predio1Actualizado = $proyectoServicio->actualizarPredioMatriz($predio1Id, $dtoActPredio1, $empresaAlfaId, $operadorId, $ctx);
probar((float) $predio1Actualizado['area_topografica_m2'] === 49999.5000, 'Predio actualizado progresivamente con levantamiento topográfico (49999.5000 m²)');

// --- BLOQUE 6: GATE 3A-04 (Política Determinista de Tolerancia) ---
echo "\n--- BLOQUE 6: GATE 3A-04 (Política Determinista de Tolerancia) ---\n";

// Crear Proyecto de prueba exclusivo para balance técnico
$dtoTolAbs = new CrearProyectoDTO([
    'codigo'          => 'PRJ_BALANCE_TEST',
    'nombre'          => 'Proyecto Test Balance Tolerancia',
    'tipo_proyecto'   => Proyecto::TIPO_PROPIO,
    'moneda'          => Proyecto::MONEDA_PEN,
    'distrito_id'     => $distritoId,
    'tipo_tolerancia' => Proyecto::TOLERANCIA_ABSOLUTA_M2,
    'valor_tolerancia'=> 1.0000 // 1 m² de tolerancia absoluta
]);
$prjTol = $proyectoServicio->crear($dtoTolAbs, $empresaAlfaId, $operadorId, $ctx);
$prjTolId = (int) $prjTol['id'];

// Escenario 1: Sin predios -> semáforo SIN_PREDIOS
$eval1 = $proyectoServicio->evaluarConciliacionAreas($prjTolId, $empresaAlfaId);
probar($eval1['semaforo'] === 'SIN_PREDIOS', 'Escenario 1: Proyecto sin predios reporta semáforo SIN_PREDIOS');

// Escenario 2: Con predio sin levantamiento -> semáforo PENDIENTE_TOPOGRAFIA
$dtoPM1 = new CrearPredioMatrizDTO([
    'proyecto_id'        => $prjTolId,
    'denominacion'       => 'Matriz A',
    'area_registral_m2'  => 10000.0000,
    'area_topografica_m2'=> null,
    'distrito_id'        => $distritoId
]);
$pmTest = $proyectoServicio->crearPredioMatriz($dtoPM1, $empresaAlfaId, $operadorId, $ctx);
$pmTestId = (int) $pmTest['id'];

$eval2 = $proyectoServicio->evaluarConciliacionAreas($prjTolId, $empresaAlfaId);
probar($eval2['semaforo'] === 'PENDIENTE_TOPOGRAFIA', 'Escenario 2: Predio sin levantamiento reporta PENDIENTE_TOPOGRAFIA');

// Escenario 3: Levantamiento dentro de la tolerancia (10000 vs 10000.6000, diff 0.6000 <= 1.0000) -> CONCILIADO
$dtoPM1Conciliado = new ActualizarPredioMatrizDTO([
    'denominacion'       => 'Matriz A',
    'area_registral_m2'  => 10000.0000,
    'area_topografica_m2'=> 10000.6000,
    'distrito_id'        => $distritoId
]);
$proyectoServicio->actualizarPredioMatriz($pmTestId, $dtoPM1Conciliado, $empresaAlfaId, $operadorId, $ctx);
$eval3 = $proyectoServicio->evaluarConciliacionAreas($prjTolId, $empresaAlfaId);
probar($eval3['semaforo'] === 'CONCILIADO' && $eval3['dentro_tolerancia'] === true, 'Escenario 3: Discrepancia 0.6000 m² <= 1.0000 m² reporta CONCILIADO');

// Escenario 4: Levantamiento fuera de tolerancia (10000 vs 10003.5000, diff 3.5000 > 1.0000) -> DISCREPANCIA_FUERA_TOLERANCIA
$dtoPM1Desviado = new ActualizarPredioMatrizDTO([
    'denominacion'       => 'Matriz A',
    'area_registral_m2'  => 10000.0000,
    'area_topografica_m2'=> 10003.5000,
    'distrito_id'        => $distritoId
]);
$proyectoServicio->actualizarPredioMatriz($pmTestId, $dtoPM1Desviado, $empresaAlfaId, $operadorId, $ctx);
$eval4 = $proyectoServicio->evaluarConciliacionAreas($prjTolId, $empresaAlfaId);
probar($eval4['semaforo'] === 'DISCREPANCIA_FUERA_TOLERANCIA' && $eval4['dentro_tolerancia'] === false, 'Escenario 4: Discrepancia 3.5000 m² > 1.0000 m² reporta DISCREPANCIA_FUERA_TOLERANCIA');

// Escenario 5: Cambiar política a PORCENTUAL 0.1% (Tolerancia = 10 m² en 10000 m²)
// 10003.5000 tiene discrepancia 3.5 m² = 0.035% <= 0.1% -> debe cambiar a CONCILIADO
$dtoCambioTol = new ActualizarProyectoDTO([
    'nombre'          => 'Proyecto Test Balance Tolerancia',
    'tipo_proyecto'   => Proyecto::TIPO_PROPIO,
    'distrito_id'     => $distritoId,
    'tipo_tolerancia' => Proyecto::TOLERANCIA_PORCENTUAL,
    'valor_tolerancia'=> 0.1000 // 0.1%
]);
$proyectoServicio->actualizar($prjTolId, $dtoCambioTol, $empresaAlfaId, $operadorId, $ctx);
$eval5 = $proyectoServicio->evaluarConciliacionAreas($prjTolId, $empresaAlfaId);
probar($eval5['semaforo'] === 'CONCILIADO' && $eval5['politica_aplicada']['tipo'] === 'PORCENTUAL', 'Escenario 5: Política PORCENTUAL evaluada con precisión decimal (0.035% <= 0.1%)');

// --- BLOQUE 7: Anti-IDOR Estricto en Cascada (Fail-Closed) ---
echo "\n--- BLOQUE 7: Anti-IDOR Estricto en Cascada (Fail-Closed) ---\n";

// Proyecto 1 pertenece a Empresa Alfa ($empresaAlfaId).
// Empresa Beta ($empresaBetaId) intenta interactuar con Proyecto 1 o sus Predios Matrices.

// 1. Consulta Anti-IDOR de Proyecto
$consultaIdorBloqueada = false;
try {
    $proyectoServicio->obtenerPorId($proyecto1Id, $empresaBetaId);
} catch (RecursoNoEncontradoExcepcion $e) {
    $consultaIdorBloqueada = ($e->getCode() === 404);
}
probar($consultaIdorBloqueada, 'Anti-IDOR: Consulta de proyecto de Empresa A desde Empresa B rechazada con 404');

// 2. Actualización Anti-IDOR de Proyecto
$updateIdorBloqueado = false;
try {
    $dtoUpdateIdor = new ActualizarProyectoDTO([
        'nombre'          => 'Hackeado por Beta',
        'tipo_proyecto'   => Proyecto::TIPO_PROPIO,
        'distrito_id'     => $distritoId,
        'tipo_tolerancia' => Proyecto::TOLERANCIA_ABSOLUTA_M2,
        'valor_tolerancia'=> 0.5
    ]);
    $proyectoServicio->actualizar($proyecto1Id, $dtoUpdateIdor, $empresaBetaId, $operadorId, $ctx);
} catch (RecursoNoEncontradoExcepcion $e) {
    $updateIdorBloqueado = ($e->getCode() === 404);
}
probar($updateIdorBloqueado, 'Anti-IDOR: Actualización de proyecto de Empresa A desde Empresa B rechazada con 404');

// 3. Conmutación de Estado Anti-IDOR de Proyecto
$estadoIdorBloqueado = false;
try {
    $dtoEstadoIdor = new CambiarEstadoProyectoDTO(['estado' => Proyecto::ESTADO_INACTIVO]);
    $proyectoServicio->cambiarEstado($proyecto1Id, $dtoEstadoIdor, $empresaBetaId, $operadorId, $ctx);
} catch (RecursoNoEncontradoExcepcion $e) {
    $estadoIdorBloqueado = ($e->getCode() === 404);
}
probar($estadoIdorBloqueado, 'Anti-IDOR: Conmutación de estado de proyecto ajeno rechazada con 404');

// 4. Inserción Anti-IDOR de Predio en Proyecto Ajeno
$crearPredioIdorBloqueado = false;
try {
    $dtoPredioIdor = new CrearPredioMatrizDTO([
        'proyecto_id'       => $proyecto1Id,
        'denominacion'      => 'Predio Intruso de Beta',
        'area_registral_m2' => 5000.0,
        'distrito_id'       => $distritoId
    ]);
    $proyectoServicio->crearPredioMatriz($dtoPredioIdor, $empresaBetaId, $operadorId, $ctx);
} catch (RecursoNoEncontradoExcepcion $e) {
    $crearPredioIdorBloqueado = ($e->getCode() === 404);
}
probar($crearPredioIdorBloqueado, 'Anti-IDOR: Creación de predio en proyecto de Empresa A desde Empresa B rechazada con 404');

// 5. Actualización Anti-IDOR de Predio Matriz (Verificación en Cascada)
$updatePredioIdorBloqueado = false;
try {
    $dtoActPredioIdor = new ActualizarPredioMatrizDTO([
        'denominacion'      => 'Predio Hackeado',
        'area_registral_m2' => 5000.0,
        'distrito_id'       => $distritoId
    ]);
    $proyectoServicio->actualizarPredioMatriz($predio1Id, $dtoActPredioIdor, $empresaBetaId, $operadorId, $ctx);
} catch (ReglaNegocioExcepcion | RecursoNoEncontradoExcepcion $e) {
    $updatePredioIdorBloqueado = in_array($e->getCode(), [403, 404], true);
}
probar($updatePredioIdorBloqueado, 'Anti-IDOR Cascada: Modificación de predio de Empresa A desde Empresa B rechazada con 403/404');

// --- BLOQUE 8: Inmutabilidad y Protección DTO ---
echo "\n--- BLOQUE 8: Inmutabilidad y Protección DTO ---\n";

// 1. Rechazo de campos desconocidos en DTOs (fail-closed contra polución de atributos)
$polucionRechazada = false;
try {
    new CrearProyectoDTO([
        'codigo'          => 'PRJ_HACK',
        'nombre'          => 'Proyecto Inyección',
        'tipo_proyecto'   => Proyecto::TIPO_PROPIO,
        'distrito_id'     => $distritoId,
        'campo_inventado' => 'malicioso'
    ]);
} catch (ValidacionExcepcion $e) {
    $polucionRechazada = ($e->getCode() === 422);
}
probar($polucionRechazada, 'DTO anti-polución: campos no reconocidos son rechazados con HTTP 422');

// 2. Inmutabilidad de 'codigo' y 'moneda' tras la creación
$dtoIntentoMutacion = new ActualizarProyectoDTO([
    'nombre'          => 'Valle Sagrado Nombre Nuevo',
    'tipo_proyecto'   => Proyecto::TIPO_CONVENIO_APV,
    'distrito_id'     => $distritoId,
    'tipo_tolerancia' => Proyecto::TOLERANCIA_ABSOLUTA_M2,
    'valor_tolerancia'=> 1.0
]);
$proyectoServicio->actualizar($proyecto1Id, $dtoIntentoMutacion, $empresaAlfaId, $operadorId, $ctx);
$proyDespues = $proyectoServicio->obtenerPorId($proyecto1Id, $empresaAlfaId);
probar($proyDespues['codigo'] === 'PRJ_VALLE_SAGRADO' && $proyDespues['moneda'] === Proyecto::MONEDA_USD, 'Invariante: código y moneda permanecen inmutables tras actualización');

// --- BLOQUE 9: DataTables Server-Side y Agregaciones ---
echo "\n--- BLOQUE 9: DataTables Server-Side y Agregaciones ---\n";

$dtResultado = $proyectoRepo->listarDataTables($empresaAlfaId, [
    'draw'         => 1,
    'start'        => 0,
    'length'       => 10,
    'search'       => 'Valle Sagrado',
    'order_column' => 'codigo',
    'order_dir'    => 'ASC'
]);

probar($dtResultado['recordsTotal'] >= 2, "DataTables recordsTotal correcto ({$dtResultado['recordsTotal']})");
probar($dtResultado['recordsFiltered'] >= 1, "DataTables recordsFiltered con filtro de texto correcto ({$dtResultado['recordsFiltered']})");

$filaDt = $dtResultado['data'][0];
probar(isset($filaDt['total_predios']) && (int) $filaDt['total_predios'] >= 2, 'DataTables agrega métrica total_predios');
probar(isset($filaDt['area_registral_total_m2']) && (float) $filaDt['area_registral_total_m2'] > 0, 'DataTables agrega métrica area_registral_total_m2');

// --- BLOQUE 10: Auditoría Forense Append-Only ---
echo "\n--- BLOQUE 10: Auditoría Forense Append-Only ---\n";

$stmtAud = $pdo->prepare("
    SELECT COUNT(*) 
    FROM `auditorias` 
    WHERE `modulo` = 'MOD_CATASTRO' 
      AND `actor_id` = :actor_id
");
$stmtAud->execute([':actor_id' => $operadorId]);
$conteoAuditoriasCatastro = (int) $stmtAud->fetchColumn();
probar($conteoAuditoriasCatastro >= 5, "Trazabilidad forense registrada en auditorias (conteo: {$conteoAuditoriasCatastro} eventos con actor USER {$operadorId})");

// --- BLOQUE 11: Idempotencia y DELTA = 0 en Desarrollo ---
echo "\n--- BLOQUE 11: Idempotencia y DELTA = 0 en Desarrollo ---\n";

$conteoProyectosDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyectos`')->fetchColumn();
$conteoPrediosDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyecto_predios_matriz`')->fetchColumn();
$conteoParticipantesDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `proyecto_participantes`')->fetchColumn();
$conteoAuditoriasDevFinal = (int) $pdoDev->query('SELECT COUNT(*) FROM `auditorias`')->fetchColumn();

$deltaProyectos = $conteoProyectosDevFinal - $conteoProyectosDevInicial;
$deltaPredios = $conteoPrediosDevFinal - $conteoPrediosDevInicial;
$deltaParticipantes = $conteoParticipantesDevFinal - $conteoParticipantesDevInicial;
$deltaAuditorias = $conteoAuditoriasDevFinal - $conteoAuditoriasDevInicial;

probar($deltaProyectos === 0, "DELTA proyectos en casapro (desarrollo) = 0 (inicial: {$conteoProyectosDevInicial}, final: {$conteoProyectosDevFinal})");
probar($deltaPredios === 0, "DELTA predios en casapro (desarrollo) = 0 (inicial: {$conteoPrediosDevInicial}, final: {$conteoPrediosDevFinal})");
probar($deltaParticipantes === 0, "DELTA participantes en casapro (desarrollo) = 0 (inicial: {$conteoParticipantesDevInicial}, final: {$conteoParticipantesDevFinal})");
probar($deltaAuditorias === 0, "DELTA auditorias en casapro (desarrollo) = 0 (inicial: {$conteoAuditoriasDevInicial}, final: {$conteoAuditoriasDevFinal})");

echo "\n===================================================================\n";
echo " RESUMEN: {$pruebasSuperadas} de {$totalPruebas} pruebas superadas (100% PASS)\n";
echo " MICROFASE 3A CERTIFICADA CON ÉXITO\n";
echo "===================================================================\n";
