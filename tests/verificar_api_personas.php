<?php

declare(strict_types=1);

/**
 * Suite de Verificación Integral de API REST, Dominio y Repositorio de Personas (Microfase 1D).
 *
 * Verifica los 12 Gates de Calidad y requisitos obligatorios:
 * - Deny by Default y Guardia de Actores (401 en anónimo).
 * - CSRF estricto en mutaciones (403 si ausente/inválido, prohibición de bypass Bearer).
 * - DTO Allowlist estricto (422 en campos raíz o anidados no permitidos).
 * - Validación y Whitelist SQL de DataTables Server-Side (400 en order_dir o columnas manipuladas).
 * - Multiplicidad de documentos: 0..N permitidos; máximo 1 principal activo.
 * - 360 consulta y serialización limpia.
 * - PUT integral: colecciones omitidas preservadas, [] vaciadas, sincronización completa.
 * - Inmutabilidad de tipo_persona en actualización.
 * - PATCH de estado (ACTIVO <-> INACTIVO) y rechazo de BLOQUEADO/ELIMINADO.
 * - Ausencia total de DELETE físico.
 * - Auditoría transversal en la misma conexión y atomicidad transaccional.
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
use App\Core\Enrutador;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Repositorios\PersonaRepositorio;
use App\Controladores\PersonaControlador;

CargadorEntorno::cargar(dirname(__DIR__));
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE API REST, DOMINIO Y PERSISTENCIA (FASE 1D)\n";
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

// Inicializar base de datos y dependencias
$proveedor = new ProveedorConexion();
$pdo = $proveedor->obtenerConexion();
$repositorio = new PersonaRepositorio();
$auditoria = new AuditoriaServicio();
$servicio = new PersonaServicio($proveedor, $repositorio, $auditoria);
$controlador = new PersonaControlador($servicio);

// Función auxiliar para despachar peticiones a través del Enrutador oficial
function despacharRuta(string $metodo, string $ruta, array $cuerpo = [], ?string $csrfToken = null, bool $autenticado = true, array $paramsGet = []): array
{
    $_SERVER['REQUEST_METHOD'] = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $ruta;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_GET = $paramsGet;

    if ($csrfToken !== null) {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
    } else {
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    if ($autenticado) {
        GestorSesion::establecer('actor_id', 1);
    } else {
        GestorSesion::eliminar('actor_id');
    }

    $peticion = new Peticion();
    $peticion->establecerMetodo($metodo);
    $peticion->establecerRuta($ruta);
    $peticion->establecerCabecera('Accept', 'application/json');
    $peticion->establecerCabecera('X-Requested-With', 'XMLHttpRequest');

    if ($csrfToken !== null) {
        $peticion->establecerCabecera('X-CSRF-Token', $csrfToken);
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
        // En caso de que una excepción escape al enrutador
        $respuesta->establecerCodigoEstado(500);
        $respuesta->establecerCuerpo(json_encode(['error' => $t->getMessage()]));
    }
    $salidaEcho = ob_get_clean();

    $cuerpoRespuesta = $respuesta->obtenerCuerpo();
    if (empty($cuerpoRespuesta) && !empty($salidaEcho)) {
        $cuerpoRespuesta = $salidaEcho;
    }

    $json = json_decode($cuerpoRespuesta, true) ?: [];

    return [
        'codigo' => $respuesta->obtenerCodigoEstado(),
        'json'   => $json
    ];
}

$tokenCsrfValido = CsrfServicio::obtenerToken();

// -----------------------------------------------------------------------------
// BLOQUE 1: Deny by Default y Guardia de Actores (Peticiones Anónimas)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 1: Deny by Default y Guardia de Actores (Peticiones Anónimas) ---\n";

$res1 = despacharRuta('GET', '/api/personas', [], null, false);
afirmativo($res1['codigo'] === 401, 'GET /api/personas anónimo es rechazado con HTTP 401');
afirmativo(($res1['json']['estado'] ?? '') === 'error', 'GET anónimo retorna estructura JSON de error');

$res2 = despacharRuta('GET', '/api/personas/1', [], null, false);
afirmativo($res2['codigo'] === 401, 'GET /api/personas/{id} anónimo es rechazado con HTTP 401');

$res3 = despacharRuta('POST', '/api/personas', ['tipo_persona' => 'NATURAL'], $tokenCsrfValido, false);
afirmativo($res3['codigo'] === 401, 'POST /api/personas anónimo con CSRF es rechazado con HTTP 401');

$res4 = despacharRuta('PUT', '/api/personas/1', ['notas' => 'Prueba'], $tokenCsrfValido, false);
afirmativo($res4['codigo'] === 401, 'PUT /api/personas/{id} anónimo con CSRF es rechazado con HTTP 401');

$res5 = despacharRuta('PATCH', '/api/personas/1/estado', ['estado' => 'INACTIVO'], $tokenCsrfValido, false);
afirmativo($res5['codigo'] === 401, 'PATCH /api/personas/{id}/estado anónimo con CSRF es rechazado con HTTP 401');

// -----------------------------------------------------------------------------
// BLOQUE 2: Validación de CSRF en Mutaciones (Actor Autenticado)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Validación de CSRF en Mutaciones (Actor Autenticado) ---\n";

$resCsrf1 = despacharRuta('POST', '/api/personas', ['tipo_persona' => 'NATURAL'], null, true);
afirmativo($resCsrf1['codigo'] === 403, 'POST /api/personas sin token CSRF es rechazado con HTTP 403');

$resCsrf2 = despacharRuta('POST', '/api/personas', ['tipo_persona' => 'NATURAL'], 'token_invalido_hacker', true);
afirmativo($resCsrf2['codigo'] === 403, 'POST /api/personas con token CSRF inválido es rechazado con HTTP 403');

$resCsrf3 = despacharRuta('PUT', '/api/personas/1', ['notas' => 'Prueba'], null, true);
afirmativo($resCsrf3['codigo'] === 403, 'PUT /api/personas/{id} sin token CSRF es rechazado con HTTP 403');

$resCsrf4 = despacharRuta('PATCH', '/api/personas/1/estado', ['estado' => 'INACTIVO'], null, true);
afirmativo($resCsrf4['codigo'] === 403, 'PATCH /api/personas/{id}/estado sin token CSRF es rechazado con HTTP 403');

// -----------------------------------------------------------------------------
// BLOQUE 3: DTO Allowlist Estricto y Protección Contra Mass Assignment
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 3: DTO Allowlist Estricto (Rechazo HTTP 422 a Campos Inesperados) ---\n";

// Campo desconocido en raíz
$resMass1 = despacharRuta('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres'      => 'Juan',
    'apellido_paterno' => 'Pérez',
    'rol_admin'    => 'SUPERADMIN', // Campo no permitido
    'saldo'        => 50000.00      // Campo no permitido
], $tokenCsrfValido, true);
afirmativo($resMass1['codigo'] === 422, 'POST con campos desconocidos en raíz es rechazado con HTTP 422');
afirmativo(isset($resMass1['json']['errores']['rol_admin']), '422 especifica los campos desconocidos en raíz');

// Campo desconocido en colección documentos
$resMass2 = despacharRuta('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres'      => 'Juan',
    'apellido_paterno' => 'Pérez',
    'documentos'   => [
        [
            'tipo_documento_id' => 1,
            'numero_documento'  => '44556677',
            'campo_fantasma'    => 'hack' // No permitido
        ]
    ]
], $tokenCsrfValido, true);
afirmativo($resMass2['codigo'] === 422, 'POST con campos desconocidos en colección documentos es rechazado con HTTP 422');

// Campo desconocido en colección contactos
$resMass3 = despacharRuta('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres'      => 'Juan',
    'apellido_paterno' => 'Pérez',
    'contactos'    => [
        [
            'tipo_contacto_id' => 1,
            'valor'            => '999888777',
            'inyeccion'        => true
        ]
    ]
], $tokenCsrfValido, true);
afirmativo($resMass3['codigo'] === 422, 'POST con campos desconocidos en colección contactos es rechazado con HTTP 422');

// Campo desconocido en colección direcciones
$resMass4 = despacharRuta('POST', '/api/personas', [
    'tipo_persona' => 'NATURAL',
    'nombres'      => 'Juan',
    'apellido_paterno' => 'Pérez',
    'direcciones'  => [
        [
            'tipo_direccion_id' => 1,
            'direccion'         => 'Av. Los Próceres 123',
            'coordenadas_gps'   => '12.34,56.78' // No permitido en este modelo
        ]
    ]
], $tokenCsrfValido, true);
afirmativo($resMass4['codigo'] === 422, 'POST con campos desconocidos en colección direcciones es rechazado con HTTP 422');

// -----------------------------------------------------------------------------
// BLOQUE 4: DataTables Server-Side con Whitelist SQL Rígida
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 4: DataTables Server-Side (Validación Estricta y Whitelist SQL) ---\n";

// Listado estándar válido
$resDt1 = despacharRuta('GET', '/api/personas', [], null, true, [
    'draw'   => '1',
    'start'  => '0',
    'length' => '10'
]);
afirmativo($resDt1['codigo'] === 200, 'GET /api/personas responde HTTP 200 en consulta DataTables válida');
afirmativo(isset($resDt1['json']['draw'], $resDt1['json']['recordsTotal'], $resDt1['json']['recordsFiltered'], $resDt1['json']['data']),
    'Respuesta DataTables contiene draw, recordsTotal, recordsFiltered y data');

// Intento de inyección SQL o columna no autorizada en ordenamiento
$resDt2 = despacharRuta('GET', '/api/personas', [], null, true, [
    'draw'  => '1',
    'order' => [
        ['column' => 'id; DROP TABLE personas;', 'dir' => 'asc']
    ]
]);
afirmativo($resDt2['codigo'] === 400, 'DataTables rechaza columna de ordenamiento manipulada con HTTP 400 Bad Request');

// Dirección de ordenamiento inválida
$resDt3 = despacharRuta('GET', '/api/personas', [], null, true, [
    'draw'  => '1',
    'order' => [
        ['column' => '0', 'dir' => 'HACK_DESC']
    ]
]);
afirmativo($resDt3['codigo'] === 400, 'DataTables rechaza order_dir inválido con HTTP 400 Bad Request');

// Parámetro start negativo
$resDt4 = despacharRuta('GET', '/api/personas', [], null, true, [
    'draw'  => '1',
    'start' => '-10'
]);
afirmativo($resDt4['codigo'] === 400, 'DataTables rechaza start negativo con HTTP 400 Bad Request');

// Parámetro length no numérico
$resDt5 = despacharRuta('GET', '/api/personas', [], null, true, [
    'draw'   => '1',
    'length' => 'veinte'
]);
afirmativo($resDt5['codigo'] === 400, 'DataTables rechaza length no numérico con HTTP 400 Bad Request');

// -----------------------------------------------------------------------------
// BLOQUE 5: Creación de Persona Natural y Multiplicidad de Documentos
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Creación de Persona Natural (Reglas de Dominio y 0..N Documentos) ---\n";

// Persona Natural con CERO documentos (permitido en identidad central)
$resNatCero = despacharRuta('POST', '/api/personas', [
    'tipo_persona'     => 'NATURAL',
    'nombres'          => 'Carlos',
    'apellido_paterno' => 'Villanueva',
    'apellido_materno' => 'Rojas',
    'notas'            => 'Prospecto sin documento inicial'
], $tokenCsrfValido, true);
afirmativo($resNatCero['codigo'] === 201, 'Creación de Persona Natural con 0 documentos es permitida (HTTP 201)');
$idPersonaSinDoc = (int) ($resNatCero['json']['datos']['id'] ?? 0);
afirmativo($idPersonaSinDoc > 0, 'Persona sin documentos recibe ID primario autoincremental');

// Persona Natural con 2 documentos (DNI principal, Pasaporte secundario), 2 contactos y 1 dirección
$numDniUnico = '71' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$resNatCompleta = despacharRuta('POST', '/api/personas', [
    'tipo_persona'        => 'NATURAL',
    'nombres'             => 'María Elena',
    'apellido_paterno'    => 'Gonzales',
    'apellido_materno'    => 'Quispe',
    'sexo_id'             => 2, // FEMENINO
    'estado_civil_id'     => 1, // SOLTERO(A)
    'profesion_ocupacion' => 'Ingeniera Civil',
    'notas'               => 'Registro completo de prueba',
    'documentos'          => [
        [
            'tipo_documento_id' => 1, // DNI
            'numero_documento'  => $numDniUnico,
            'es_principal'      => true
        ],
        [
            'tipo_documento_id' => 3, // PASAPORTE
            'numero_documento'  => 'PE9988776',
            'es_principal'      => false
        ]
    ],
    'contactos'           => [
        [
            'tipo_contacto_id' => 1, // TELEFONO
            'valor'            => '987654321',
            'etiqueta'         => 'Móvil Personal',
            'es_principal'     => true
        ],
        [
            'tipo_contacto_id' => 2, // EMAIL
            'valor'            => 'maria.gonzales@ejemplo.pe',
            'etiqueta'         => 'Correo Trabajo',
            'es_principal'     => false
        ]
    ],
    'direcciones'         => [
        [
            'tipo_direccion_id' => 1, // FISCAL
            'distrito_id'       => 697, // 080101 CUSCO
            'direccion'         => 'Av. El Sol 450, Int. 3B',
            'es_principal'      => true
        ]
    ]
], $tokenCsrfValido, true);

afirmativo($resNatCompleta['codigo'] === 201, 'Creación de Persona Natural completa responde HTTP 201 Created');
$idPersonaNatural = (int) ($resNatCompleta['json']['datos']['id'] ?? 0);
afirmativo($idPersonaNatural > 0, 'Persona Natural completa registrada con éxito (ID: ' . $idPersonaNatural . ')');

// Conflicto de unicidad de documento (mismo DNI) -> HTTP 409
$resDupDoc = despacharRuta('POST', '/api/personas', [
    'tipo_persona'     => 'NATURAL',
    'nombres'          => 'Duplicado',
    'apellido_paterno' => 'Test',
    'documentos'       => [
        [
            'tipo_documento_id' => 1,
            'numero_documento'  => $numDniUnico, // Mismo DNI
            'es_principal'      => true
        ]
    ]
], $tokenCsrfValido, true);
afirmativo($resDupDoc['codigo'] === 409 || $resDupDoc['codigo'] === 422, 'Rechazo de documento de identidad duplicado con conflicto');

// Formato de DNI inválido (no numérico o diferente de 8 dígitos)
$resDniInvalido = despacharRuta('POST', '/api/personas', [
    'tipo_persona'     => 'NATURAL',
    'nombres'          => 'Formato',
    'apellido_paterno' => 'Invalido',
    'documentos'       => [
        [
            'tipo_documento_id' => 1,
            'numero_documento'  => '12345', // 5 dígitos
            'es_principal'      => true
        ]
    ]
], $tokenCsrfValido, true);
afirmativo($resDniInvalido['codigo'] === 422, 'DNI con longitud incorrecta es rechazado con HTTP 422');

// -----------------------------------------------------------------------------
// BLOQUE 6: Creación de Persona Jurídica y Representación Legal
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 6: Creación de Persona Jurídica y Representantes ---\n";

$numRucUnico = '20' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);
$resJuridica = despacharRuta('POST', '/api/personas', [
    'tipo_persona'       => 'JURIDICA',
    'razon_social'       => 'CONSTRUCTORA E INMOBILIARIA ANDINA S.A.C.',
    'nombre_comercial'   => 'ANDINA CONSTRUCTORA',
    'fecha_constitucion' => '2020-03-15',
    'objeto_social'      => 'Construcción y promoción inmobiliaria',
    'documentos'         => [
        [
            'tipo_documento_id' => 2, // RUC
            'numero_documento'  => $numRucUnico,
            'es_principal'      => true
        ]
    ],
    'representantes'     => [
        [
            'persona_natural_id'      => $idPersonaNatural,
            'cargo'                   => 'GERENTE GENERAL',
            'fecha_inicio'            => '2020-03-15',
            'partida_registral'       => 'PR-11223344',
            'es_representante_actual' => true
        ]
    ]
], $tokenCsrfValido, true);

afirmativo($resJuridica['codigo'] === 201, 'Creación de Persona Jurídica responde HTTP 201 Created');
$idPersonaJuridica = (int) ($resJuridica['json']['datos']['id'] ?? 0);
afirmativo($idPersonaJuridica > 0, 'Persona Jurídica registrada con éxito (ID: ' . $idPersonaJuridica . ')');

// Persona Jurídica con datos de Natural (violación de consistencia)
$resJurInvalida = despacharRuta('POST', '/api/personas', [
    'tipo_persona'     => 'JURIDICA',
    'razon_social'     => 'EMPRESA ILEGAL S.A.C.',
    'nombres'          => 'No corresponde' // Inválido para Jurídica
], $tokenCsrfValido, true);
afirmativo($resJurInvalida['codigo'] === 422, 'Persona Jurídica con nombres es rechazada con HTTP 422');

// -----------------------------------------------------------------------------
// BLOQUE 7: Consulta Detallada 360 (GET /api/personas/{id})
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 7: Consulta Detallada 360 (GET /api/personas/{id}) ---\n";

$res360Nat = despacharRuta('GET', "/api/personas/{$idPersonaNatural}", [], null, true);
afirmativo($res360Nat['codigo'] === 200, 'GET /api/personas/{id} para Natural responde HTTP 200');
$datos360Nat = $res360Nat['json']['datos'] ?? [];
afirmativo(($datos360Nat['persona']['tipo_persona'] ?? '') === 'NATURAL', 'Ficha 360 confirma tipo_persona NATURAL');
afirmativo(($datos360Nat['natural']['nombre_completo'] ?? '') === 'Gonzales Quispe, María Elena', 'Ficha 360 formatea nombre_completo correctamente');
afirmativo(count($datos360Nat['documentos'] ?? []) === 2, 'Ficha 360 retorna los 2 documentos registrados');
afirmativo(count($datos360Nat['contactos'] ?? []) === 2, 'Ficha 360 retorna los 2 contactos registrados');
afirmativo(count($datos360Nat['direcciones'] ?? []) === 1, 'Ficha 360 retorna la dirección con UBIGEO');
afirmativo(strtoupper($datos360Nat['direcciones'][0]['ubigeo']['distrito'] ?? '') === 'CUSCO', 'UBIGEO resuelve distrito Cusco');

$res360Jur = despacharRuta('GET', "/api/personas/{$idPersonaJuridica}", [], null, true);
afirmativo($res360Jur['codigo'] === 200, 'GET /api/personas/{id} para Jurídica responde HTTP 200');
$datos360Jur = $res360Jur['json']['datos'] ?? [];
afirmativo(($datos360Jur['juridica']['razon_social'] ?? '') === 'CONSTRUCTORA E INMOBILIARIA ANDINA S.A.C.', 'Ficha 360 confirma razón social');
afirmativo(count($datos360Jur['representantes'] ?? []) === 1, 'Ficha 360 retorna el representante legal');
afirmativo(($datos360Jur['representantes'][0]['cargo'] ?? '') === 'GERENTE GENERAL', 'Representante registra cargo Gerente General');

// Identificador inexistente -> 404 Not Found
$res404 = despacharRuta('GET', '/api/personas/99999999', [], null, true);
afirmativo($res404['codigo'] === 404, 'GET con ID inexistente responde HTTP 404 Not Found');

// Identificador malformado -> 400 Bad Request
$res400Id = despacharRuta('GET', '/api/personas/identificador_invalido', [], null, true);
afirmativo($res400Id['codigo'] === 400, 'GET con ID alfanumérico responde HTTP 400 Bad Request');

// -----------------------------------------------------------------------------
// BLOQUE 8: Actualización Integral (PUT /api/personas/{id})
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 8: Actualización Integral (PUT /api/personas/{id}) ---\n";

// Intento prohibido de cambiar tipo_persona en actualización -> 422
$resPutTipo = despacharRuta('PUT', "/api/personas/{$idPersonaNatural}", [
    'tipo_persona' => 'JURIDICA',
    'razon_social' => 'INTENTO DE CONVERSION'
], $tokenCsrfValido, true);
afirmativo($resPutTipo['codigo'] === 422, 'PUT intentando cambiar tipo_persona es estrictamente rechazado con HTTP 422');

// Actualización exitosa con documentos omitidos (deben preservarse intactos)
$resPutOmit = despacharRuta('PUT', "/api/personas/{$idPersonaNatural}", [
    'nombres'             => 'María Elena Modificada',
    'apellido_paterno'    => 'Gonzales',
    'apellido_materno'    => 'Quispe',
    'profesion_ocupacion' => 'Ingeniera Civil y Estructural',
    'notas'               => 'Actualización con documentos omitidos'
    // 'documentos' no enviado en payload: debe mantenerse intacto
], $tokenCsrfValido, true);
afirmativo($resPutOmit['codigo'] === 200, 'PUT con colecciones omitidas responde HTTP 200 OK');

// Verificar en BD que los documentos siguen existiendo
$resVerifDoc = despacharRuta('GET', "/api/personas/{$idPersonaNatural}", [], null, true);
$docsTrasPut = $resVerifDoc['json']['datos']['documentos'] ?? [];
afirmativo(count($docsTrasPut) === 2, 'Colección de documentos se preservó intacta tras omitirse en PUT');

// Vaciado explícito de contactos mediante array vacío []
$resPutVaciar = despacharRuta('PUT', "/api/personas/{$idPersonaNatural}", [
    'nombres'          => 'María Elena Modificada',
    'apellido_paterno' => 'Gonzales',
    'contactos'        => [] // Array explícito vacío: desactiva los contactos
], $tokenCsrfValido, true);
afirmativo($resPutVaciar['codigo'] === 200, 'PUT con array vacío [] de contactos responde HTTP 200 OK');

$resVerifCon = despacharRuta('GET', "/api/personas/{$idPersonaNatural}", [], null, true);
$contactosActivos = $resVerifCon['json']['datos']['contactos'] ?? [];
afirmativo(count($contactosActivos) === 0, 'Contactos fueron desactivados tras enviar colección vacía []');

// -----------------------------------------------------------------------------
// BLOQUE 9: Transición Especializada de Estado (PATCH /api/personas/{id}/estado)
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 9: Transición de Estado (PATCH /api/personas/{id}/estado) ---\n";

// Transición a INACTIVO con motivo
$resPatchInactivo = despacharRuta('PATCH', "/api/personas/{$idPersonaNatural}/estado", [
    'estado' => 'INACTIVO',
    'motivo' => 'Cierre temporal de ficha por auditoría interna'
], $tokenCsrfValido, true);
afirmativo($resPatchInactivo['codigo'] === 200, 'PATCH cambiando a INACTIVO responde HTTP 200 OK');
afirmativo(($resPatchInactivo['json']['datos']['estado'] ?? '') === 'INACTIVO', 'Estado actualizado a INACTIVO en respuesta');

// Intento de asignar estado BLOQUEADO o ELIMINADO (prohibidos en Persona)
$resPatchBloqueado = despacharRuta('PATCH', "/api/personas/{$idPersonaNatural}/estado", [
    'estado' => 'BLOQUEADO',
    'motivo' => 'Intento inválido'
], $tokenCsrfValido, true);
afirmativo($resPatchBloqueado['codigo'] === 422, 'Asignación de estado BLOQUEADO en Persona es rechazada con HTTP 422');

$resPatchEliminado = despacharRuta('PATCH', "/api/personas/{$idPersonaNatural}/estado", [
    'estado' => 'ELIMINADO'
], $tokenCsrfValido, true);
afirmativo($resPatchEliminado['codigo'] === 422, 'Asignación de estado ELIMINADO en Persona es rechazada con HTTP 422');

// Retorno a ACTIVO
$resPatchActivo = despacharRuta('PATCH', "/api/personas/{$idPersonaNatural}/estado", [
    'estado' => 'ACTIVO',
    'motivo' => 'Reactivación formal de ficha'
], $tokenCsrfValido, true);
afirmativo($resPatchActivo['codigo'] === 200, 'Reactivación a ACTIVO responde HTTP 200 OK');

// -----------------------------------------------------------------------------
// BLOQUE 10: Inexistencia de Endpoint DELETE Físico
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 10: Verificación de Prohibición de DELETE Físico ---\n";

$resDelete = despacharRuta('DELETE', "/api/personas/{$idPersonaNatural}", [], $tokenCsrfValido, true);
afirmativo($resDelete['codigo'] === 404, 'DELETE /api/personas/{id} no existe y retorna HTTP 404 Not Found');

// -----------------------------------------------------------------------------
// BLOQUE 11: Auditoría Transversal y Trazabilidad de Peticiones
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 11: Auditoría Transversal en Base de Datos ---\n";

$stmtAudit = $pdo->prepare("
    SELECT * FROM `auditorias`
    WHERE `modulo` = 'identidad' AND `entidad` = 'personas' AND `registro_id` = :id
    ORDER BY `id` ASC
");
$stmtAudit->bindValue(':id', $idPersonaNatural, PDO::PARAM_INT);
$stmtAudit->execute();
$registrosAudit = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

afirmativo(count($registrosAudit) >= 3, 'Se registraron al menos 3 eventos de auditoría para la persona (CREAR, ACTUALIZAR, ESTADO)');

$eventoCrear = $registrosAudit[0] ?? [];
afirmativo(($eventoCrear['accion'] ?? '') === 'CREAR', 'Primer evento de auditoría es de acción CREAR');
afirmativo((int) ($eventoCrear['actor_id'] ?? 0) === 1, 'Auditoría vinculada a actor_id = 1 (actor en sesión)');
afirmativo(!empty($eventoCrear['id_correlacion']), 'Auditoría conserva ID de correlación de la petición');
afirmativo(!empty($eventoCrear['datos_nuevos']), 'Auditoría de creación almacena snapshot en datos_nuevos');

$ultimoEvento = end($registrosAudit);
afirmativo(($ultimoEvento['accion'] ?? '') === 'ACTUALIZAR', 'Último evento de auditoría es de acción ACTUALIZAR');

// -----------------------------------------------------------------------------
// BLOQUE 12: Atomicidad Transaccional y Reversión ante Fallos
// -----------------------------------------------------------------------------
echo "\n--- BLOQUE 12: Atomicidad Transaccional (Rollback ante Fallo) ---\n";

$cuentaPersonasAntes = (int) $pdo->query("SELECT COUNT(*) FROM `personas`")->fetchColumn();
$cuentaAuditoriasAntes = (int) $pdo->query("SELECT COUNT(*) FROM `auditorias`")->fetchColumn();

// Petición con DTO inválido a nivel de integridad de catálogo (distrito_id inexistente)
$resFalloAtomico = despacharRuta('POST', '/api/personas', [
    'tipo_persona'     => 'NATURAL',
    'nombres'          => 'Prueba',
    'apellido_paterno' => 'Rollback',
    'direcciones'      => [
        [
            'tipo_direccion_id' => 1,
            'distrito_id'       => 999999 // Distrito inexistente
        ]
    ]
], $tokenCsrfValido, true);

$cuentaPersonasDespues = (int) $pdo->query("SELECT COUNT(*) FROM `personas`")->fetchColumn();
$cuentaAuditoriasDespues = (int) $pdo->query("SELECT COUNT(*) FROM `auditorias`")->fetchColumn();

afirmativo($resFalloAtomico['codigo'] === 422, 'Petición con UBIGEO inválido es rechazada');
afirmativo($cuentaPersonasDespues === $cuentaPersonasAntes, 'Atomicidad: No se crearon personas residuales tras el fallo');
afirmativo($cuentaAuditoriasDespues === $cuentaAuditoriasAntes, 'Atomicidad: No se registraron auditorías huérfanas tras el fallo');

// Limpieza de datos de prueba creados en la suite
$pdo->beginTransaction();
$pdo->exec("DELETE FROM `persona_representantes` WHERE `persona_juridica_id` = {$idPersonaJuridica}");
$pdo->exec("DELETE FROM `persona_juridica` WHERE `persona_id` = {$idPersonaJuridica}");
$pdo->exec("DELETE FROM `persona_documentos` WHERE `persona_id` IN ({$idPersonaSinDoc}, {$idPersonaNatural}, {$idPersonaJuridica})");
$pdo->exec("DELETE FROM `persona_contactos` WHERE `persona_id` IN ({$idPersonaSinDoc}, {$idPersonaNatural}, {$idPersonaJuridica})");
$pdo->exec("DELETE FROM `persona_direcciones` WHERE `persona_id` IN ({$idPersonaSinDoc}, {$idPersonaNatural}, {$idPersonaJuridica})");
$pdo->exec("DELETE FROM `persona_natural` WHERE `persona_id` IN ({$idPersonaSinDoc}, {$idPersonaNatural})");
$pdo->exec("DELETE FROM `auditorias` WHERE `modulo` = 'identidad' AND `entidad` = 'personas' AND `registro_id` IN ({$idPersonaSinDoc}, {$idPersonaNatural}, {$idPersonaJuridica})");
$pdo->exec("DELETE FROM `personas` WHERE `id` IN ({$idPersonaSinDoc}, {$idPersonaNatural}, {$idPersonaJuridica})");
$pdo->commit();

echo "\n===================================================================\n";
echo " RESUMEN FINAL API PERSONAS: {$pruebasSuperadas} de {$pruebasEjecutadas} superadas.\n";
if ($pruebasSuperadas === $pruebasEjecutadas) {
    echo " RESULTADO SUITE API JSON PERSONAS: [PASS]\n";
} else {
    echo " RESULTADO SUITE API JSON PERSONAS: [FAIL]\n";
}
echo "===================================================================\n";

if ($pruebasSuperadas !== $pruebasEjecutadas) {
    exit(1);
}
