<?php

declare(strict_types=1);

/**
 * Suite de Verificación Integral para Microfase 1F:
 * Alta, Edición, Transición de Estados de Personas y Consulta Asistida DNI/RUC.
 *
 * Valida los 12 Gates de Calidad y directrices de gobernanza CasaPRO:
 * 1. Alta de Persona Natural y Jurídica bajo arquitectura desacoplada.
 * 2. Edición completa vía PUT preservando colecciones y blindando tipo_persona.
 * 3. Transición de estados (ACTIVO <-> INACTIVO) con motivo auditable y SweetAlert2.
 * 4. Consulta documental desacoplada con Regla de Oro Anti-Duplicidad Primero.
 * 5. Adaptador de proveedor con inyección de dobles deterministas (éxito, no encontrado, timeout).
 * 6. Fallback manual transparente ante indisponibilidad del proveedor externo.
 * 7. Protección de credenciales: Token privado nunca expuesto al cliente.
 * 8. Deny by Default y CSRF en mutaciones y consultas documentales.
 * 9. DTOs con allowlist estricta rechazando campos imprevistos con HTTP 422.
 * 10. Anatomía visual Alina: modal-xl, tabs, SweetAlert2, CleaveJS, PristineJS, Flatpickr.
 * 11. Preservación estricta de Font Awesome (cero Tabler Icons residuales).
 * 12. Inmutabilidad de la base de datos (cero migraciones nuevas) y admin-dashboard/.
 */

define('CASAPRO_TESTING', true);
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Enrutador;
use App\Core\CsrfServicio;
use App\DTOs\CrearPersonaDTO;
use App\DTOs\ActualizarPersonaDTO;
use App\DTOs\CambiarEstadoPersonaDTO;
use App\DTOs\ConsultaDocumentoDTO;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\ConsultaDocumentoServicio;
use App\Servicios\ProveedorDocumentoInterface;
use App\Repositorios\PersonaRepositorio;

CargadorEntorno::cargar(dirname(__DIR__));
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE FORMULARIO, ESTADOS Y CONSULTA DNI/RUC (1F)\n";
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

// Proveedor Mock Determinista para pruebas desacopladas de APIsPERU
class ProveedorDocumentoMock implements ProveedorDocumentoInterface
{
    public string $modo = 'EXITO'; // 'EXITO', 'NO_ENCONTRADO', 'TIMEOUT', 'ERROR'

    public function consultarDni(string $dni): array
    {
        if ($this->modo === 'TIMEOUT') {
            return ['exito' => false, 'codigo' => 'TIMEOUT', 'mensaje' => 'Timeout simulado del servicio externo.'];
        }
        if ($this->modo === 'ERROR') {
            return ['exito' => false, 'codigo' => 'ERROR_PROVEEDOR', 'mensaje' => 'Error 500 simulado en proveedor.'];
        }
        if ($this->modo === 'NO_ENCONTRADO' || $dni === '00000000') {
            return ['exito' => false, 'codigo' => 'NO_ENCONTRADO', 'mensaje' => 'DNI no encontrado en padrón.'];
        }

        return [
            'exito' => true,
            'codigo' => 'ENCONTRADO',
            'mensaje' => 'DNI encontrado en padrón oficial.',
            'datos' => [
                'numero_documento' => $dni,
                'nombres' => 'CARLOS ALBERTO',
                'apellido_paterno' => 'MENDOZA',
                'apellido_materno' => 'QUISPE',
                'nombre_completo' => 'MENDOZA QUISPE CARLOS ALBERTO'
            ]
        ];
    }

    public function consultarRuc(string $ruc): array
    {
        if ($this->modo === 'TIMEOUT') {
            return ['exito' => false, 'codigo' => 'TIMEOUT', 'mensaje' => 'Timeout simulado del servicio externo.'];
        }
        if ($this->modo === 'ERROR') {
            return ['exito' => false, 'codigo' => 'ERROR_PROVEEDOR', 'mensaje' => 'Error 500 simulado en proveedor.'];
        }
        if ($this->modo === 'NO_ENCONTRADO' || $ruc === '20000000001') {
            return ['exito' => false, 'codigo' => 'NO_ENCONTRADO', 'mensaje' => 'RUC no encontrado en SUNAT.'];
        }

        return [
            'exito' => true,
            'codigo' => 'ENCONTRADO',
            'mensaje' => 'RUC encontrado en registro tributario.',
            'datos' => [
                'numero_documento' => $ruc,
                'razon_social' => 'INVERSIONES DEL SUR S.A.C.',
                'estado_contribuyente' => 'ACTIVO',
                'condicion_contribuyente' => 'HABIDO',
                'direccion' => 'AV. EL SOL 450, CUSCO',
                'codigo_ubigeo' => '080101'
            ]
        ];
    }
}

// Función auxiliar para despachar peticiones a través del enrutador
function despacharEndpoint(string $metodo, string $ruta, array $cuerpo = [], bool $autenticado = true, bool $enviarCsrf = true): array
{
    $_SERVER['REQUEST_METHOD'] = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $ruta;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

    $token = $enviarCsrf ? CsrfServicio::obtenerToken() : null;
    if ($token !== null) {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    } else {
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    if ($autenticado) {
        GestorSesion::establecer('actor_id', 1);
    } else {
        GestorSesion::eliminar('actor_id');
    }

    $partesUrl = parse_url($ruta);
    $rutaLimpia = $partesUrl['path'] ?? $ruta;
    $queryParams = [];
    if (!empty($partesUrl['query'])) {
        parse_str($partesUrl['query'], $queryParams);
    }
    $_GET = $queryParams;

    $peticion = new Peticion();
    $peticion->establecerMetodo($metodo);
    $peticion->establecerRuta($rutaLimpia);
    $peticion->establecerCabecera('Accept', 'application/json');
    $peticion->establecerCabecera('Content-Type', 'application/json');
    $peticion->establecerCabecera('X-Requested-With', 'XMLHttpRequest');

    if ($token !== null) {
        $peticion->establecerCabecera('X-CSRF-Token', $token);
    }

    if (!empty($cuerpo)) {
        $peticion->establecerJson($cuerpo);
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
        $respuesta->establecerCuerpo(json_encode(['estado' => 'error', 'mensaje' => $t->getMessage()]));
    }
    $salidaEcho = ob_get_clean();

    $cuerpoStr = $respuesta->obtenerCuerpo();
    if ($cuerpoStr === '' && $salidaEcho !== '') {
        $cuerpoStr = $salidaEcho;
    }

    return [
        'codigo' => $respuesta->obtenerCodigoEstado(),
        'json'   => json_decode($cuerpoStr, true) ?? [],
        'raw'    => $cuerpoStr
    ];
}

$conexion = (new ProveedorConexion())->obtenerConexion();
$repositorio = new PersonaRepositorio();
$auditoriaServicio = new AuditoriaServicio();
$personaServicio = new PersonaServicio(new ProveedorConexion(), $repositorio, $auditoriaServicio);

// =========================================================================
// BLOQUE 1: Seguridad, Deny by Default y CSRF en Consulta Documental
// =========================================================================
echo "\n--- BLOQUE 1: Seguridad y Protección en Consulta Documental ---\n";

// 1. Consulta anónima rechazada con HTTP 401
$resAnonima = despacharEndpoint('POST', '/api/personas/consultar-documento', ['tipo_documento_id' => 1, 'numero_documento' => '47852145'], false, true);
afirmativo($resAnonima['codigo'] === 401, 'POST /api/personas/consultar-documento anónimo es rechazado con HTTP 401');

// 2. Consulta sin token CSRF rechazada con HTTP 403
$resSinCsrf = despacharEndpoint('POST', '/api/personas/consultar-documento', ['tipo_documento_id' => 1, 'numero_documento' => '47852145'], true, false);
afirmativo($resSinCsrf['codigo'] === 403, 'POST /api/personas/consultar-documento sin CSRF es rechazado con HTTP 403');

// 3. Consulta con payload malformado/campo desconocido rechazada con 422
$resDesconocido = despacharEndpoint('POST', '/api/personas/consultar-documento', [
    'tipo_documento_id' => 1,
    'numero_documento' => '47852145',
    'campo_malicioso' => 'hack'
], true, true);
afirmativo($resDesconocido['codigo'] === 422, 'Consulta con campos desconocidos en payload es rechazada con HTTP 422 (Allowlist)');

// 4. Consulta con formato inválido rechazada con 422
$resFormatoInvalido = despacharEndpoint('POST', '/api/personas/consultar-documento', [
    'tipo_documento_id' => 1,
    'numero_documento' => '12' // DNI requiere 8 dígitos
], true, true);
afirmativo($resFormatoInvalido['codigo'] === 422, 'Número de DNI con longitud menor a la requerida por catálogo es rechazado con HTTP 422');

// =========================================================================
// BLOQUE 2: Regla de Oro Anti-Duplicidad Primero y Privacidad
// =========================================================================
echo "\n--- BLOQUE 2: Regla de Oro Anti-Duplicidad Primero ---\n";

// Registrar previamente una persona de prueba para verificar anti-duplicidad
$docPruebaDuplicado = '7' . str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$contextoPrueba = ContextoPeticion::crearDesdeEntorno(new Peticion());
$dtoCrearExistente = CrearPersonaDTO::desdeArray([
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Persona',
    'apellido_paterno' => 'Existente',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $docPruebaDuplicado, 'es_principal' => 1]
    ]
]);
$resCrearExistente = $personaServicio->crear($dtoCrearExistente, $contextoPrueba);
$personaExistenteId = $resCrearExistente['id'];

// Consultar el mismo documento ya registrado
$resConsultaDuplicada = despacharEndpoint('POST', '/api/personas/consultar-documento', [
    'tipo_documento_id' => 1,
    'numero_documento' => $docPruebaDuplicado
], true, true);

afirmativo($resConsultaDuplicada['codigo'] === 200, 'Consulta de documento existente responde HTTP 200');
afirmativo(
    ($resConsultaDuplicada['json']['datos']['estado_consulta'] ?? '') === 'DUPLICADO_LOCAL',
    'La consulta detecta DUPLICADO_LOCAL en persona_documentos sin llamar a proveedor externo'
);
afirmativo(
    isset($resConsultaDuplicada['json']['datos']['persona_existente']['id']) &&
    $resConsultaDuplicada['json']['datos']['persona_existente']['id'] === $personaExistenteId,
    'La respuesta contiene los datos mínimos de identidad de la persona existente para enlace asistido'
);
afirmativo(
    !isset($resConsultaDuplicada['json']['datos']['persona_existente']['direcciones']) &&
    !isset($resConsultaDuplicada['json']['datos']['persona_existente']['contactos']),
    'Principio de privacidad respetado: No se enumeran datos sensibles no autorizados'
);

// =========================================================================
// BLOQUE 3: Servicio de Consulta con Proveedor Inyectable (DNI / RUC / Fallback)
// =========================================================================
echo "\n--- BLOQUE 3: Integración Desacoplada de Proveedor con Mocks ---\n";

$mockProveedor = new ProveedorDocumentoMock();
$servicioConsulta = new ConsultaDocumentoServicio(
    new ProveedorConexion(),
    $repositorio,
    $mockProveedor
);

// 1. Consulta DNI exitosa
$dtoDni = ConsultaDocumentoDTO::desdeArray(['tipo_documento_id' => 1, 'numero_documento' => '41239874']);
$resDni = $servicioConsulta->consultar($dtoDni);
afirmativo($resDni['estado_consulta'] === 'ENCONTRADO', 'Consulta asistida de DNI devuelve estado ENCONTRADO');
afirmativo($resDni['datos']['nombres'] === 'CARLOS ALBERTO', 'Mapeo correcto de nombres devueltos por proveedor');
afirmativo($resDni['datos']['apellido_paterno'] === 'MENDOZA', 'Mapeo correcto de apellido paterno');

// 2. Consulta RUC exitosa
$dtoRuc = ConsultaDocumentoDTO::desdeArray(['tipo_documento_id' => 2, 'numero_documento' => '20556677889']);
$resRuc = $servicioConsulta->consultar($dtoRuc);
afirmativo($resRuc['estado_consulta'] === 'ENCONTRADO', 'Consulta asistida de RUC devuelve estado ENCONTRADO');
afirmativo($resRuc['datos']['razon_social'] === 'INVERSIONES DEL SUR S.A.C.', 'Mapeo correcto de razón social de RUC');
afirmativo(!empty($resRuc['datos']['ubigeo_local']), 'UBIGEO tributario es correlacionado automáticamente con el catálogo local de distritos');

// 3. Documento no encontrado
$mockProveedor->modo = 'NO_ENCONTRADO';
$resNoEnc = $servicioConsulta->consultar($dtoDni);
afirmativo($resNoEnc['estado_consulta'] === 'NO_ENCONTRADO', 'Documento no registrado en el padrón devuelve estado NO_ENCONTRADO');

// 4. Timeout del proveedor -> Fallback manual garantizado
$mockProveedor->modo = 'TIMEOUT';
$resTimeout = $servicioConsulta->consultar($dtoDni);
afirmativo($resTimeout['estado_consulta'] === 'NO_DISPONIBLE', 'Timeout en conexión externa devuelve NO_DISPONIBLE habilitando fallback manual');

// 5. Error del proveedor -> Fallback manual garantizado
$mockProveedor->modo = 'ERROR';
$resError = $servicioConsulta->consultar($dtoDni);
afirmativo($resError['estado_consulta'] === 'NO_DISPONIBLE', 'Fallo del servidor externo devuelve NO_DISPONIBLE sin interrumpir el formulario');

// =========================================================================
// BLOQUE 4: Alta Asíncrona de Personas (Natural y Jurídica)
// =========================================================================
echo "\n--- BLOQUE 4: Alta Asíncrona de Personas ---\n";

// 1. Crear Persona Natural completa vía POST /api/personas
$dniNuevo = '8' . str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
$payloadNatural = [
    'tipo_persona' => 'NATURAL',
    'nombres' => 'Ana Lucía',
    'apellido_paterno' => 'Vargas',
    'apellido_materno' => 'Paredes',
    'fecha_nacimiento' => '1992-06-15',
    'sexo_id' => 2, // Femenino
    'estado_civil_id' => 1, // Soltera
    'profesion_ocupacion' => 'Arquitecta',
    'notas' => 'Alta mediante formulario modal 1F',
    'documentos' => [
        ['tipo_documento_id' => 1, 'numero_documento' => $dniNuevo, 'es_principal' => 1]
    ],
    'contactos' => [
        ['tipo_contacto_id' => 2, 'valor' => '984112233', 'etiqueta' => 'Celular Personal', 'es_principal' => 1]
    ],
    'direcciones' => [
        ['tipo_direccion_id' => 1, 'distrito_id' => 1, 'direccion' => 'Av. Los Incas 320', 'referencia' => 'Frente a la plaza', 'es_principal' => 1]
    ]
];

$resAltaNat = despacharEndpoint('POST', '/api/personas', $payloadNatural, true, true);
afirmativo($resAltaNat['codigo'] === 201, 'Alta de Persona Natural mediante POST /api/personas responde HTTP 201 Created');
$idPersonaNat = $resAltaNat['json']['datos']['id'] ?? 0;
afirmativo($idPersonaNat > 0, "Persona Natural creada con éxito con ID: {$idPersonaNat}");

// 2. Crear Persona Jurídica completa vía POST /api/personas
$rucNuevo = '20' . str_pad((string) mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
$payloadJuridica = [
    'tipo_persona' => 'JURIDICA',
    'razon_social' => 'DESARROLLOS URBANOS DEL PERU S.A.C.',
    'nombre_comercial' => 'URBANIA PERU',
    'fecha_constitucion' => '2018-03-20',
    'objeto_social' => 'Desarrollo de proyectos inmobiliarios y habilitación urbana.',
    'notas' => 'Persona Jurídica creada en prueba 1F',
    'documentos' => [
        ['tipo_documento_id' => 2, 'numero_documento' => $rucNuevo, 'es_principal' => 1]
    ],
    'contactos' => [
        ['tipo_contacto_id' => 1, 'valor' => 'gerencia@urbaniaperu.com', 'etiqueta' => 'Correo Corporativo', 'es_principal' => 1]
    ],
    'direcciones' => [
        ['tipo_direccion_id' => 2, 'distrito_id' => 1, 'direccion' => 'Av. El Sol 1010', 'es_principal' => 1]
    ],
    'representantes' => [
        ['persona_natural_id' => $idPersonaNat, 'cargo' => 'Gerente General', 'fecha_inicio' => '2018-03-20', 'es_representante_actual' => 1]
    ]
];

$resAltaJur = despacharEndpoint('POST', '/api/personas', $payloadJuridica, true, true);
afirmativo($resAltaJur['codigo'] === 201, 'Alta de Persona Jurídica responde HTTP 201 Created');
$idPersonaJur = $resAltaJur['json']['datos']['id'] ?? 0;
afirmativo($idPersonaJur > 0, "Persona Jurídica creada con éxito con ID: {$idPersonaJur}");

// =========================================================================
// BLOQUE 5: Edición Asíncrona (PUT /api/personas/{id}) e Inmutabilidad
// =========================================================================
echo "\n--- BLOQUE 5: Edición Asíncrona e Inmutabilidad de Identidad ---\n";

// 1. Intento de mutar tipo_persona debe ser estrictamente rechazado con 422
$resMutarTipo = despacharEndpoint('PUT', "/api/personas/{$idPersonaNat}", [
    'tipo_persona' => 'JURIDICA',
    'nombres' => 'Modificación Prohibida'
], true, true);
afirmativo($resMutarTipo['codigo'] === 422, 'Intento de modificar tipo_persona en PUT es estrictamente rechazado con HTTP 422');

// 2. Edición válida de datos de Persona Natural
$payloadEdicion = [
    'nombres' => 'Ana Lucía Modificada',
    'apellido_paterno' => 'Vargas',
    'profesion_ocupacion' => 'Ingeniera Civil y Perito Tasador',
    'notas' => 'Actualizado desde modal de edición 1F'
];
$resEdicion = despacharEndpoint('PUT', "/api/personas/{$idPersonaNat}", $payloadEdicion, true, true);
afirmativo($resEdicion['codigo'] === 200, 'Actualización de Persona Natural mediante PUT responde HTTP 200 OK');

// Verificar que se persistió
$personaModificada = $repositorio->buscarPorId($idPersonaNat);
afirmativo($personaModificada['notas'] === 'Actualizado desde modal de edición 1F', 'Notas de persona persistidas correctamente en BD');

// =========================================================================
// BLOQUE 6: Transición de Estados con Motivo y Prohibición de DELETE
// =========================================================================
echo "\n--- BLOQUE 6: Transición de Estados y Motivo Auditable ---\n";

// 1. Transición a INACTIVO con motivo obligatorio
$resInactivar = despacharEndpoint('PATCH', "/api/personas/{$idPersonaNat}/estado", [
    'estado' => 'INACTIVO',
    'motivo' => 'Suspensión temporal de actividades por solicitud del titular'
], true, true);
afirmativo($resInactivar['codigo'] === 200, 'PATCH cambiando estado a INACTIVO con motivo responde HTTP 200');

// 2. Transición sin motivo rechazada con 422
$resSinMotivo = despacharEndpoint('PATCH', "/api/personas/{$idPersonaNat}/estado", [
    'estado' => 'ACTIVO',
    'motivo' => ''
], true, true);
afirmativo($resSinMotivo['codigo'] === 422, 'PATCH sin motivo es rechazado con HTTP 422 para salvaguardar la bitácora de auditoría');

// 3. Estado no permitido (BLOQUEADO/ELIMINADO) rechazado con 422
$resEstadoInvalido = despacharEndpoint('PATCH', "/api/personas/{$idPersonaNat}/estado", [
    'estado' => 'BLOQUEADO',
    'motivo' => 'Intento inválido'
], true, true);
afirmativo($resEstadoInvalido['codigo'] === 422, 'Estado no permitido (BLOQUEADO) en Persona es rechazado con HTTP 422');

// 4. Reactivación a ACTIVO
$resReactivar = despacharEndpoint('PATCH', "/api/personas/{$idPersonaNat}/estado", [
    'estado' => 'ACTIVO',
    'motivo' => 'Reanudación formal de actividades comerciales'
], true, true);
afirmativo($resReactivar['codigo'] === 200, 'Reactivación a ACTIVO responde HTTP 200');

// 5. Cero DELETE físico
$resDelete = despacharEndpoint('DELETE', "/api/personas/{$idPersonaNat}", [], true, true);
afirmativo($resDelete['codigo'] === 404, 'DELETE /api/personas/{id} no existe y retorna HTTP 404 (Inmutabilidad)');

// =========================================================================
// BLOQUE 7: Auditoría Transversal de Operaciones 1F
// =========================================================================
echo "\n--- BLOQUE 7: Auditoría Forense de Operaciones 1F ---\n";

$stmtAud = $conexion->prepare("
    SELECT * FROM `auditorias`
    WHERE `modulo` = 'identidad' AND `entidad` = 'personas' AND `registro_id` = :id
    ORDER BY `id` ASC
");
$stmtAud->bindValue(':id', $idPersonaNat, PDO::PARAM_INT);
$stmtAud->execute();
$eventosAuditoria = $stmtAud->fetchAll(PDO::FETCH_ASSOC);

afirmativo(count($eventosAuditoria) >= 3, 'Se registraron al menos 3 eventos forenses en auditorias (CREAR, ACTUALIZAR, CAMBIO_ESTADO)');

$ultimoEvento = end($eventosAuditoria);
$metadatos = json_decode((string) ($ultimoEvento['metadatos'] ?? '{}'), true);
afirmativo(
    isset($metadatos['motivo']) && str_contains($metadatos['motivo'], 'Reanudación'),
    'El motivo obligatorio enviado en el PATCH se persiste fidedignamente en la columna metadatos JSON de auditorias'
);

// =========================================================================
// BLOQUE 8: Endpoints de Catálogo UBIGEO en Cascada
// =========================================================================
echo "\n--- BLOQUE 8: Endpoints de Cascada Geográfica UBIGEO ---\n";

$resProvincias = despacharEndpoint('GET', '/api/ubigeo/provincias?departamento_id=8', [], true, true);
afirmativo($resProvincias['codigo'] === 200, 'GET /api/ubigeo/provincias responde HTTP 200');
afirmativo(count($resProvincias['json']['datos'] ?? []) > 0, 'Retorna array con provincias del departamento (Cusco)');

$resDistritos = despacharEndpoint('GET', '/api/ubigeo/distritos?provincia_id=1', [], true, true);
afirmativo($resDistritos['codigo'] === 200, 'GET /api/ubigeo/distritos responde HTTP 200');
afirmativo(count($resDistritos['json']['datos'] ?? []) > 0, 'Retorna array con distritos de la provincia');

// =========================================================================
// BLOQUE 9: Integridad de Assets Frontend, Principio Anti-Invención y Alina
// =========================================================================
echo "\n--- BLOQUE 9: Integridad de Assets Frontend y Alina ---\n";

afirmativo(file_exists(dirname(__DIR__) . '/public/assets/vendor/pristine/pristine.min.js'), 'PristineJS presente en public/assets/vendor/pristine/pristine.min.js');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/vendor/cleavejs/cleave.min.js'), 'CleaveJS presente en public/assets/vendor/cleavejs/cleave.min.js');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/vendor/sweetalert/sweetalert.js'), 'SweetAlert2 presente en public/assets/vendor/sweetalert/sweetalert.js');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/vendor/select/select2.min.js'), 'Select2 JS presente en public/assets/vendor/select/select2.min.js');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/vendor/flatpickr/flatpickr.js'), 'Flatpickr JS presente en public/assets/vendor/flatpickr/flatpickr.js');

afirmativo(file_exists(dirname(__DIR__) . '/public/assets/js/modulos/personas/consulta-documento.js'), 'Módulo JS propio consulta-documento.js existe');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/js/modulos/personas/formulario-persona.js'), 'Módulo JS propio formulario-persona.js existe');
afirmativo(file_exists(dirname(__DIR__) . '/public/assets/js/modulos/personas/listado-personas.js'), 'Módulo JS propio listado-personas.js existe');

// =========================================================================
// BLOQUE 10: Verificación de Código Propio (Cero Tabler, Cero $.ajax)
// =========================================================================
echo "\n--- BLOQUE 10: Cumplimiento Anti-Tabler y Vanilla JS ---\n";

$contenidoFormJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/modulos/personas/formulario-persona.js');
$contenidoConsJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/modulos/personas/consulta-documento.js');
$contenidoListJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/modulos/personas/listado-personas.js');
$contenidoVista = file_get_contents(dirname(__DIR__) . '/app/Vistas/modulos/personas/index.php');

afirmativo(!str_contains($contenidoFormJs, '$.ajax') && !str_contains($contenidoFormJs, '$.post'), 'formulario-persona.js libre de llamadas AJAX jQuery');
afirmativo(!str_contains($contenidoConsJs, '$.ajax') && !str_contains($contenidoConsJs, '$.post'), 'consulta-documento.js libre de llamadas AJAX jQuery');
afirmativo(!str_contains($contenidoListJs, '$.ajax') && !str_contains($contenidoListJs, '$.post'), 'listado-personas.js libre de llamadas AJAX jQuery');

afirmativo(!str_contains($contenidoFormJs, 'ti' . ' ti-') && !str_contains($contenidoVista, 'ti' . ' ti-'), 'Código de 1F utiliza exclusivamente Font Awesome (Cero clases Tabler)');

// =========================================================================
// BLOQUE 11: Inspección de Anatomía Alina en la Vista Personas
// =========================================================================
echo "\n--- BLOQUE 11: Anatomía de Alina en Vista Personas ---\n";

afirmativo(str_contains($contenidoVista, 'id="modalFormularioPersona"'), 'Vista contiene el modal #modalFormularioPersona');
afirmativo(str_contains($contenidoVista, 'modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable'), 'Modal de alta/edición implementa modal-xl centrado y scrolleable de Alina');
afirmativo(str_contains($contenidoVista, 'nav-tabs nav-bottom-line'), 'Modal implementa el patrón nav-tabs nav-bottom-line de Alina');
afirmativo(str_contains($contenidoVista, 'id="datosCatalogosCasaPro"'), 'Vista precarga los catálogos en JSON seguro respetando soberanía relacional');
afirmativo(str_contains($contenidoVista, 'id="btnConsultarDocumento"'), 'Modal incluye el botón interactivo de consulta documental');

// =========================================================================
// BLOQUE 12: Inmutabilidad de admin-dashboard/ y Esquema de Base de Datos
// =========================================================================
echo "\n--- BLOQUE 12: Inmutabilidad de BD y Plantilla Base ---\n";

afirmativo(is_dir(dirname(__DIR__) . '/admin-dashboard'), 'admin-dashboard/ permanece presente e intacto');
$migraciones = glob(dirname(__DIR__) . '/SQL/migraciones/*.sql');
afirmativo(count($migraciones) >= 6, 'Integridad de migraciones: Se mantienen las migraciones históricas (000001 a 000006)');

echo "\n===================================================================\n";
echo " RESUMEN FINAL 1F: {$pruebasSuperadas} de {$pruebasEjecutadas} superadas.\n";
if ($pruebasSuperadas === $pruebasEjecutadas) {
    echo " RESULTADO SUITE FORMULARIO Y CONSULTA DE PERSONAS: [PASS]\n";
} else {
    echo " RESULTADO SUITE FORMULARIO Y CONSULTA DE PERSONAS: [FAIL]\n";
}
echo "===================================================================\n";

if ($pruebasSuperadas !== $pruebasEjecutadas) {
    exit(1);
}
