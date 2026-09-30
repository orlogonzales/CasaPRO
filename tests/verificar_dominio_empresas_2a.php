<?php

declare(strict_types=1);

/**
 * =============================================================================
 * CasaPRO — Suite de Pruebas Automatizadas de Dominio de Empresas (2A)
 * =============================================================================
 * 
 * Verifica el modelo de dominio de entidades corporativas (Empresas), la relación 1 : 0..1
 * con Persona Jurídica, la orquestación transaccional con PersonaServicio, la
 * semántica de estados y errores (422 vs 409), inmutabilidad, rollbacks y auditoría.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/comun/AmbientePruebas.php';
require_once __DIR__ . '/comun/FixtureAutenticacion.php';

use Tests\Comun\AmbientePruebas;
use Tests\Comun\FixtureAutenticacion;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\DTOs\CrearEmpresaDTO;
use App\DTOs\ActualizarEmpresaDTO;
use App\DTOs\CambiarEstadoEmpresaDTO;
use App\DTOs\CrearPersonaDTO;
use App\Modelos\Empresa;
use App\Modelos\Persona;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Servicios\EmpresaServicio;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
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
echo " SUITE DE PRUEBAS DE DOMINIO DE EMPRESAS (MICROFASE 2A)\n";
echo "===================================================================\n\n";

// Iniciar ambiente aislado en casapro_test
$conexion = AmbientePruebas::iniciar(true);
$contexto = new ContextoPeticion('REQ-TEST-2A', '127.0.0.1', 'CLI-PHPUnit');

try {
    // Autenticar fixture como SUPERADMIN
    $auth = FixtureAutenticacion::autenticarComoSuperadmin($conexion);
    $operadorId = (int) $auth['usuario_id'];

    $proveedor = new ProveedorConexion();
    $empresaRepo = new EmpresaRepositorio($proveedor);
    $personaRepo = new PersonaRepositorio($proveedor);
    $auditoriaServicio = new AuditoriaServicio($proveedor);
    $personaServicio = new PersonaServicio($proveedor, $personaRepo, $auditoriaServicio);
    $empresaServicio = new EmpresaServicio($proveedor, $empresaRepo, $personaServicio, $personaRepo, $auditoriaServicio);

    // =========================================================================
    // 1. ESQUEMA RELACIONAL Y PERSISTENCIA (27 TABLAS, DDL, FK, PRIVILEGIOS)
    // =========================================================================
    echo "1. Esquema Relacional, Persistencia y Privilegios RBAC\n";

    $dbActual = (string) $conexion->query('SELECT DATABASE()')->fetchColumn();
    afirmar($dbActual === 'casapro_test', "La suite se ejecuta exclusivamente en 'casapro_test'");

    $totalTablas = (int) $conexion->query("
        SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'
    ")->fetchColumn();
    afirmar($totalTablas === 27, "La base de datos contiene exactamente 27 tablas productivas");

    $existeTablaEmpresas = (int) $conexion->query("
        SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = 'casapro_test' AND table_name = 'empresas'
    ")->fetchColumn();
    afirmar($existeTablaEmpresas === 1, "La tabla 'empresas' existe en el esquema");

    // Verificar FK ON DELETE RESTRICT
    $fkRestrict = (int) $conexion->query("
        SELECT COUNT(*) 
        FROM information_schema.referential_constraints 
        WHERE constraint_schema = 'casapro_test' 
          AND table_name = 'empresas' 
          AND constraint_name = 'fk_empresas_persona' 
          AND delete_rule = 'RESTRICT'
    ")->fetchColumn();
    afirmar($fkRestrict === 1, "La FK 'fk_empresas_persona' tiene regla ON DELETE RESTRICT inviolable");

    // Verificar los 4 privilegios de empresas
    $privilegiosEmpresas = $conexion->query("
        SELECT codigo FROM `privilegios` WHERE modulo = 'empresas' ORDER BY codigo ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $esperados = ['empresas.cambiar_estado', 'empresas.crear', 'empresas.editar', 'empresas.ver'];
    afirmar($privilegiosEmpresas === $esperados, "Existen los 4 privilegios del módulo 'empresas' en el catálogo");

    // Verificar asignación a SUPERADMIN
    $privsSuperadmin = $conexion->query("
        SELECT COUNT(*)
        FROM `rol_privilegios` rp
        INNER JOIN `roles` r ON r.id = rp.rol_id
        INNER JOIN `privilegios` p ON p.id = rp.privilegio_id
        WHERE r.codigo = 'SUPERADMIN' AND p.modulo = 'empresas'
    ")->fetchColumn();
    afirmar((int) $privsSuperadmin === 4, "El rol SUPERADMIN tiene asignados los 4 privilegios de 'empresas'");

    // =========================================================================
    // 2. VALIDACIONES DE DTOs Y ALLOWLIST ESTRICTA
    // =========================================================================
    echo "\n2. Validaciones de DTOs y Allowlist Estricta\n";

    // Rechazo de código en minúsculas
    $errorCodigoMinusculas = false;
    try {
        CrearEmpresaDTO::desdeArray([
            'persona_id'   => 1,
            'codigo'       => 'empresa_minusculas',
            'nombre_corto' => 'Empresa Test'
        ]);
    } catch (ValidacionExcepcion $e) {
        $errorCodigoMinusculas = true;
    }
    afirmar($errorCodigoMinusculas, "CrearEmpresaDTO rechaza código en minúsculas (exige [A-Z0-9_])");

    // Rechazo de código con espacios o caracteres especiales
    $errorCodigoEspecial = false;
    try {
        CrearEmpresaDTO::desdeArray([
            'persona_id'   => 1,
            'codigo'       => 'CASA PRO!',
            'nombre_corto' => 'CasaPRO'
        ]);
    } catch (ValidacionExcepcion $e) {
        $errorCodigoEspecial = true;
    }
    afirmar($errorCodigoEspecial, "CrearEmpresaDTO rechaza código con espacios y caracteres especiales");

    // Rechazo de nombre corto vacío
    $errorNombreCorto = false;
    try {
        CrearEmpresaDTO::desdeArray([
            'persona_id'   => 1,
            'codigo'       => 'EMPRESA_TEST',
            'nombre_corto' => ' '
        ]);
    } catch (ValidacionExcepcion $e) {
        $errorNombreCorto = true;
    }
    afirmar($errorNombreCorto, "CrearEmpresaDTO rechaza nombre_corto vacío");

    // Rechazo de campos no permitidos (ej. ruta_logo, color_hex descartados por YAGNI)
    $errorCamposNoPermitidos = false;
    try {
        CrearEmpresaDTO::desdeArray([
            'persona_id'   => 1,
            'codigo'       => 'EMPRESA_TEST',
            'nombre_corto' => 'Test',
            'ruta_logo'    => '/logos/test.png',
            'color_hex'    => '#FF0000'
        ]);
    } catch (ValidacionExcepcion $e) {
        $errorCamposNoPermitidos = true;
    }
    afirmar($errorCamposNoPermitidos, "CrearEmpresaDTO rechaza campos descartados por YAGNI (ruta_logo, color_hex)");

    // ActualizarEmpresaDTO rechaza modificación de atributos inmutables (persona_id o codigo)
    $errorInmutable = false;
    try {
        ActualizarEmpresaDTO::desdeArray([
            'persona_id'   => 2,
            'codigo'       => 'NUEVO_CODIGO',
            'nombre_corto' => 'Nuevo Nombre'
        ]);
    } catch (ValidacionExcepcion $e) {
        $errorInmutable = true;
    }
    afirmar($errorInmutable, "ActualizarEmpresaDTO rechaza intento de modificar persona_id o codigo (inmutables)");

    // CambiarEstadoEmpresaDTO valida estados permitidos
    $errorEstadoInvalido = false;
    try {
        CambiarEstadoEmpresaDTO::desdeArray(['estado' => 'SUSPENDIDO']);
    } catch (ValidacionExcepcion $e) {
        $errorEstadoInvalido = true;
    }
    afirmar($errorEstadoInvalido, "CambiarEstadoEmpresaDTO rechaza estado no admitido (solo ACTIVO/INACTIVO)");

    // =========================================================================
    // 3. CASOS DE ÉXITO DE CREACIÓN (MODALIDAD VINCULADA Y ORQUESTADA)
    // =========================================================================
    echo "\n3. Casos de Éxito de Creación de Empresa\n";

    // 3.1 Modalidad A (Vinculada): Crear primero una Persona Jurídica activa en el padrón
    $dtoPersona1 = CrearPersonaDTO::desdeArray([
        'tipo_persona' => 'JURIDICA',
        'razon_social' => 'INVERSIONES BONIFACIO S.A.C.',
        'nombre_comercial' => 'Grupo Bonifacio',
        'documentos' => [
            [
                'tipo_documento_id' => 2, // RUC
                'numero_documento'  => '20601234567',
                'es_principal'      => 1
            ]
        ]
    ]);
    $resPersona1 = $personaServicio->crear($dtoPersona1, $contexto, $conexion);
    $personaJuridicaId1 = (int) $resPersona1['id'];
    afirmar($personaJuridicaId1 > 0, "Persona Jurídica 'INVERSIONES BONIFACIO S.A.C.' creada en el padrón (ID: {$personaJuridicaId1})");

    // Vincular Empresa a la Persona Jurídica existente
    $dtoEmpresa1 = CrearEmpresaDTO::desdeArray([
        'persona_id'   => $personaJuridicaId1,
        'codigo'       => 'MATRIZ_BONIFACIO',
        'nombre_corto' => 'Matriz Bonifacio'
    ]);
    $resEmpresa1 = $empresaServicio->crear($dtoEmpresa1, $operadorId, $contexto, $conexion);
    $empresaId1 = (int) $resEmpresa1['id'];

    afirmar($empresaId1 > 0, "Empresa 'Matriz Bonifacio' creada con éxito por vinculación directa (ID: {$empresaId1})");
    afirmar($resEmpresa1['codigo'] === 'MATRIZ_BONIFACIO', "Código corporativo persistido correctamente");
    afirmar($resEmpresa1['razon_social'] === 'INVERSIONES BONIFACIO S.A.C.', "Hereda soberanamente la razón social de la Persona Jurídica sin duplicación");
    afirmar($resEmpresa1['ruc'] === '20601234567', "Hereda soberanamente el RUC principal de la Persona Jurídica");
    afirmar($resEmpresa1['estado'] === 'ACTIVO', "Empresa se inicia en estado ACTIVO");

    // 3.2 Modalidad B (Orquestada): Creación simultánea de Persona Jurídica + Empresa en una llamada
    $dtoEmpresa2 = CrearEmpresaDTO::desdeArray([
        'datos_persona' => [
            'tipo_persona' => 'JURIDICA',
            'razon_social' => 'CASAPRO DESARROLLOS INMOBILIARIOS S.A.C.',
            'nombre_comercial' => 'CasaPRO',
            'documentos' => [
                [
                    'tipo_documento_id' => 2, // RUC
                    'numero_documento'  => '20609876543',
                    'es_principal'      => 1
                ]
            ]
        ],
        'codigo'       => 'CASAPRO_SAC',
        'nombre_corto' => 'CasaPRO'
    ]);
    $resEmpresa2 = $empresaServicio->crear($dtoEmpresa2, $operadorId, $contexto, $conexion);
    $empresaId2 = (int) $resEmpresa2['id'];
    $personaJuridicaId2 = (int) $resEmpresa2['persona_id'];

    afirmar($empresaId2 > 0 && $personaJuridicaId2 > 0, "Empresa 'CasaPRO' creada con éxito por orquestación transaccional (Empresa ID: {$empresaId2}, Persona ID: {$personaJuridicaId2})");
    afirmar($resEmpresa2['codigo'] === 'CASAPRO_SAC', "Código corporativo orquestado persistido");
    afirmar($resEmpresa2['ruc'] === '20609876543', "RUC orquestado persistido en padrón civil y consultable");

    // =========================================================================
    // 4. CASOS DE RECHAZO Y SEMÁNTICA HTTP (422 vs 409)
    // =========================================================================
    echo "\n4. Casos de Rechazo de Dominio y Semántica HTTP (422 vs 409)\n";

    // 4.1 Persona Natural intentando ser Empresa -> HTTP 422
    $dtoPersonaNatural = CrearPersonaDTO::desdeArray([
        'tipo_persona'    => 'NATURAL',
        'nombres'         => 'Juan',
        'apellido_paterno'=> 'Pérez',
        'documentos'      => [
            ['tipo_documento_id' => 1, 'numero_documento' => '40506070', 'es_principal' => 1]
        ]
    ]);
    $resNatural = $personaServicio->crear($dtoPersonaNatural, $contexto, $conexion);
    $personaNaturalId = (int) $resNatural['id'];

    $errorPersonaNatural = false;
    $codigoHttpNatural = 0;
    try {
        $empresaServicio->crear(CrearEmpresaDTO::desdeArray([
            'persona_id'   => $personaNaturalId,
            'codigo'       => 'JUAN_EMPRESA',
            'nombre_corto' => 'Juan Empresa'
        ]), $operadorId, $contexto, $conexion);
    } catch (ReglaNegocioExcepcion $e) {
        $errorPersonaNatural = true;
        $codigoHttpNatural = $e->getCode();
    }
    afirmar($errorPersonaNatural && $codigoHttpNatural === 422, "Intento de vincular Persona Natural a Empresa es rechazado con HTTP 422");

    // 4.2 Persona Jurídica INACTIVA -> HTTP 422
    $dtoPersonaInactiva = CrearPersonaDTO::desdeArray([
        'tipo_persona' => 'JURIDICA',
        'razon_social' => 'EMPRESA INACTIVA DE PRUEBA S.A.C.',
        'documentos' => [
            ['tipo_documento_id' => 2, 'numero_documento' => '20600000001', 'es_principal' => 1]
        ]
    ]);
    $resInactiva = $personaServicio->crear($dtoPersonaInactiva, $contexto, $conexion);
    $personaInactivaId = (int) $resInactiva['id'];
    // Inactivar la persona en el padrón
    $personaRepo->cambiarEstadoPersona($personaInactivaId, 'INACTIVO', $conexion);

    $errorPersonaInactiva = false;
    $codigoHttpInactiva = 0;
    try {
        $empresaServicio->crear(CrearEmpresaDTO::desdeArray([
            'persona_id'   => $personaInactivaId,
            'codigo'       => 'EMP_INACTIVA',
            'nombre_corto' => 'Inactiva Corp'
        ]), $operadorId, $contexto, $conexion);
    } catch (ReglaNegocioExcepcion $e) {
        $errorPersonaInactiva = true;
        $codigoHttpInactiva = $e->getCode();
    }
    afirmar($errorPersonaInactiva && $codigoHttpInactiva === 422, "Intento de constituir Empresa desde Persona Jurídica INACTIVA es rechazado con HTTP 422");

    // 4.3 Duplicidad Persona Jurídica 1:1 -> HTTP 409 Conflict
    $errorDuplicidadPersona = false;
    $codigoHttpDuplicidadPersona = 0;
    try {
        $empresaServicio->crear(CrearEmpresaDTO::desdeArray([
            'persona_id'   => $personaJuridicaId1, // Ya usada por MATRIZ_BONIFACIO
            'codigo'       => 'OTRA_BONIFACIO',
            'nombre_corto' => 'Otra Bonifacio'
        ]), $operadorId, $contexto, $conexion);
    } catch (ReglaNegocioExcepcion $e) {
        $errorDuplicidadPersona = true;
        $codigoHttpDuplicidadPersona = $e->getCode();
    }
    afirmar($errorDuplicidadPersona && $codigoHttpDuplicidadPersona === 409, "Intento de vincular Persona Jurídica ya convertida en Empresa es rechazado con HTTP 409 Conflict");

    // 4.4 Código Corporativo duplicado -> HTTP 409 Conflict
    $dtoPersona3 = CrearPersonaDTO::desdeArray([
        'tipo_persona' => 'JURIDICA',
        'razon_social' => 'TERCERA EMPRESA S.A.C.',
        'documentos' => [
            ['tipo_documento_id' => 2, 'numero_documento' => '20603333333', 'es_principal' => 1]
        ]
    ]);
    $resPersona3 = $personaServicio->crear($dtoPersona3, $contexto, $conexion);
    $personaJuridicaId3 = (int) $resPersona3['id'];

    $errorCodigoDuplicado = false;
    $codigoHttpCodigoDuplicado = 0;
    try {
        $empresaServicio->crear(CrearEmpresaDTO::desdeArray([
            'persona_id'   => $personaJuridicaId3,
            'codigo'       => 'MATRIZ_BONIFACIO', // Ya existe
            'nombre_corto' => 'Tercera Empresa'
        ]), $operadorId, $contexto, $conexion);
    } catch (ReglaNegocioExcepcion $e) {
        $errorCodigoDuplicado = true;
        $codigoHttpCodigoDuplicado = $e->getCode();
    }
    afirmar($errorCodigoDuplicado && $codigoHttpCodigoDuplicado === 409, "Intento de reutilizar código de empresa existente es rechazado con HTTP 409 Conflict");

    // =========================================================================
    // 5. ATOMICIDAD Y ROLLBACKS CRÍTICOS (PROPIEDAD TRANSACCIONAL)
    // =========================================================================
    echo "\n5. Atomicidad y Rollbacks Críticos (Unicidad de Propietario Transaccional)\n";

    // 5.1 Rollback 1: Crear Persona Jurídica + fallar inserción de Empresa
    // Se envía DTO orquestado con código duplicado ('MATRIZ_BONIFACIO') para forzar fallo en Empresa
    $conteoPersonasAntes = (int) $conexion->query("SELECT COUNT(*) FROM personas")->fetchColumn();
    $conteoEmpresasAntes = (int) $conexion->query("SELECT COUNT(*) FROM empresas")->fetchColumn();

    $falloEmpresa = false;
    try {
        $empresaServicio->crear(CrearEmpresaDTO::desdeArray([
            'datos_persona' => [
                'tipo_persona' => 'JURIDICA',
                'razon_social' => 'EMPRESA QUE DEBE FALLAR S.A.C.',
                'documentos' => [
                    ['tipo_documento_id' => 2, 'numero_documento' => '20609999999', 'es_principal' => 1]
                ]
            ],
            'codigo'       => 'MATRIZ_BONIFACIO', // Provocará 409 Conflict
            'nombre_corto' => 'Falla'
        ]), $operadorId, $contexto, $conexion);
    } catch (ReglaNegocioExcepcion $e) {
        $falloEmpresa = true;
    }

    $conteoPersonasDespues = (int) $conexion->query("SELECT COUNT(*) FROM personas")->fetchColumn();
    $conteoEmpresasDespues = (int) $conexion->query("SELECT COUNT(*) FROM empresas")->fetchColumn();

    afirmar($falloEmpresa, "Operación orquestada falló como se esperaba por conflicto en Empresa");
    afirmar($conteoPersonasAntes === $conteoPersonasDespues, "Rollback Total: La Persona Jurídica NO fue persistida en el padrón tras el fallo de Empresa");
    afirmar($conteoEmpresasAntes === $conteoEmpresasDespues, "Rollback Total: Ninguna Empresa fue persistida");

    // 5.2 Rollback 2: Crear Persona Jurídica + Empresa + fallar Auditoría
    // Mockeamos o forzamos fallo en el servicio de auditoría
    $auditoriaMockFalla = new class($proveedor) extends AuditoriaServicio {
        public function registrar(array $datos, ?PDO $conexion = null): int {
            throw new RuntimeException("Fallo intencional inducido en AuditoriaServicio para probar rollback atómico");
        }
    };
    $empresaServicioConFalloAuditoria = new EmpresaServicio(
        $proveedor,
        $empresaRepo,
        $personaServicio,
        $personaRepo,
        $auditoriaMockFalla
    );

    $conteoPersonasAntes2 = (int) $conexion->query("SELECT COUNT(*) FROM personas")->fetchColumn();
    $conteoEmpresasAntes2 = (int) $conexion->query("SELECT COUNT(*) FROM empresas")->fetchColumn();

    $falloAuditoria = false;
    try {
        $empresaServicioConFalloAuditoria->crear(CrearEmpresaDTO::desdeArray([
            'datos_persona' => [
                'tipo_persona' => 'JURIDICA',
                'razon_social' => 'EMPRESA FALLA AUDITORIA S.A.C.',
                'documentos' => [
                    ['tipo_documento_id' => 2, 'numero_documento' => '20608888888', 'es_principal' => 1]
                ]
            ],
            'codigo'       => 'FALLA_AUDIT',
            'nombre_corto' => 'Falla Audit'
        ]), $operadorId, $contexto, $conexion);
    } catch (RuntimeException $e) {
        $falloAuditoria = true;
    }

    $conteoPersonasDespues2 = (int) $conexion->query("SELECT COUNT(*) FROM personas")->fetchColumn();
    $conteoEmpresasDespues2 = (int) $conexion->query("SELECT COUNT(*) FROM empresas")->fetchColumn();

    afirmar($falloAuditoria, "Operación orquestada falló como se esperaba por error en Auditoría");
    afirmar($conteoPersonasAntes2 === $conteoPersonasDespues2, "Rollback Total: Ni la Persona Jurídica persiste si la Auditoría falla");
    afirmar($conteoEmpresasAntes2 === $conteoEmpresasDespues2, "Rollback Total: Ni la Empresa persiste si la Auditoría falla");

    // =========================================================================
    // 6. CICLO DE VIDA, INMUTABILIDAD Y CONSULTA DE INACTIVAS
    // =========================================================================
    echo "\n6. Ciclo de Vida, Inmutabilidad y Consulta de Inactivas\n";

    // 6.1 Actualización de nombre_corto
    $resActualizada = $empresaServicio->actualizar(
        $empresaId1,
        ActualizarEmpresaDTO::desdeArray(['nombre_corto' => 'Grupo Bonifacio Matriz']),
        $operadorId,
        $contexto,
        $conexion
    );
    afirmar($resActualizada['nombre_corto'] === 'Grupo Bonifacio Matriz', "nombre_corto modificado exitosamente");
    afirmar($resActualizada['codigo'] === 'MATRIZ_BONIFACIO', "codigo corporativo permanece inmutable");
    afirmar((int) $resActualizada['persona_id'] === $personaJuridicaId1, "persona_id permanece inmutable");

    // 6.2 Inactivación y consultabilidad histórica
    $resInactivada = $empresaServicio->cambiarEstado(
        $empresaId1,
        CambiarEstadoEmpresaDTO::desdeArray(['estado' => 'INACTIVO']),
        $operadorId,
        $contexto,
        $conexion
    );
    afirmar($resInactivada['estado'] === 'INACTIVO', "Empresa conmutada a estado INACTIVO");

    // Empresa inactiva permanece consultable históricamente
    $empresaConsultada = $empresaServicio->obtenerPorId($empresaId1);
    afirmar($empresaConsultada !== null && $empresaConsultada['estado'] === 'INACTIVO', "Empresa INACTIVA permanece consultable históricamente por ID");

    $listaInactivas = $empresaServicio->listarTodas('INACTIVO');
    afirmar(count($listaInactivas) >= 1, "Empresas INACTIVAS son recuperables mediante filtro de estado");

    // Reactivar empresa
    $resReactivada = $empresaServicio->cambiarEstado(
        $empresaId1,
        CambiarEstadoEmpresaDTO::desdeArray(['estado' => 'ACTIVO']),
        $operadorId,
        $contexto,
        $conexion
    );
    afirmar($resReactivada['estado'] === 'ACTIVO', "Empresa reactivada exitosamente a estado ACTIVO");

    // 6.3 Listado general
    $todas = $empresaServicio->listarTodas();
    afirmar(count($todas) === 2, "listarTodas() devuelve exactamente las 2 empresas creadas");

    // =========================================================================
    // 7. PROHIBICIÓN DE ELIMINACIÓN FÍSICA (DELETE INEXISTENTE)
    // =========================================================================
    echo "\n7. Prohibición de Eliminación Física (Cero métodos DELETE públicos)\n";

    $refServicio = new ReflectionClass(EmpresaServicio::class);
    $tieneMetodoEliminarServicio = $refServicio->hasMethod('eliminar') || $refServicio->hasMethod('borrar') || $refServicio->hasMethod('destroy');
    afirmar(!$tieneMetodoEliminarServicio, "EmpresaServicio no expone método público para eliminación física (DELETE)");

    $refRepositorio = new ReflectionClass(EmpresaRepositorio::class);
    $tieneMetodoEliminarRepo = $refRepositorio->hasMethod('eliminar') || $refRepositorio->hasMethod('borrar') || $refRepositorio->hasMethod('destroy');
    afirmar(!$tieneMetodoEliminarRepo, "EmpresaRepositorio no expone método público para eliminación física (DELETE)");

    // =========================================================================
    // 8. AUDITORÍA FORENSE ATÓMICA DE OPERACIONES
    // =========================================================================
    echo "\n8. Auditoría Forense Atómica de Operaciones en 'auditorias'\n";

    $conteoAuditoriasEmpresas = (int) $conexion->query("
        SELECT COUNT(*) FROM `auditorias` WHERE `entidad` = 'empresas'
    ")->fetchColumn();
    afirmar($conteoAuditoriasEmpresas >= 4, "Las operaciones de alta, edición y cambio de estado registraron eventos en 'auditorias' (total: {$conteoAuditoriasEmpresas})");

    $stmtAud = $conexion->query("
        SELECT accion, resultado FROM `auditorias` WHERE `entidad` = 'empresas' ORDER BY id ASC
    ");
    $accionesRegistradas = $stmtAud->fetchAll(PDO::FETCH_ASSOC);
    $acciones = array_column($accionesRegistradas, 'accion');
    afirmar(in_array('CREAR', $acciones, true), "Auditoría contiene evento CREAR");
    afirmar(in_array('ACTUALIZAR', $acciones, true), "Auditoría contiene evento ACTUALIZAR");
    afirmar(in_array('CAMBIAR_ESTADO', $acciones, true), "Auditoría contiene evento CAMBIAR_ESTADO");

} finally {
    // Restaurar tablas mutables al estado limpio de pruebas
    AmbientePruebas::limpiarTablasMutables($conexion);
}

echo "\n===================================================================\n";
echo " RESUMEN: {$pruebasSuperadas} de {$totalPruebas} pruebas superadas [PASS]\n";
echo " RESULTADO SUITE DOMINIO DE EMPRESAS (2A): [PASS]\n";
echo "===================================================================\n";

exit($pruebasSuperadas === $totalPruebas ? 0 : 1);
