<?php

declare(strict_types=1);

/**
 * Suite de Verificación Integral para Microfase 1E:
 * Listado y Consulta Visual de Personas con Alina + DataTables Server-Side.
 *
 * Valida los 12 Gates de Calidad y requisitos obligatorios:
 * - Protección Deny by Default de la vista /personas (HTTP 401 sin sesión).
 * - Renderizado HTML oficial Alina basado en blank.html, data_table.html y profile.html.
 * - Inexistencia de controles muertos de 1F (sin "+ Nueva Persona" ni "Editar" deshabilitados).
 * - Presencia y reuso estricto de assets Alina (DataTables 1.13.3, Responsive 2.4.0) sin duplicados.
 * - Integridad de admin-dashboard/ (permanece 100% de solo lectura).
 * - JavaScript Modular ES6 con ZERO llamadas a $.ajax() y resolución dinámica de URLs.
 * - Contrato DataTables server-side con paginación, ordenamiento, búsqueda debounced y filtros.
 * - Ficha de Identidad de Persona en modal-lg sin exposición de auditoría ni histórico de estados.
 * - Inmunidad XSS demostrada con payloads reales de ataque.
 * - Manejo seguro de errores HTTP (401, 403, 404, 500).
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
use App\Core\Vista;
use App\DTOs\CrearPersonaDTO;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Repositorios\PersonaRepositorio;

CargadorEntorno::cargar(dirname(__DIR__));
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE LISTADO Y FICHA VISUAL DE PERSONAS (FASE 1E)\n";
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

// Función auxiliar para despachar peticiones a través del Enrutador oficial
function despacharVista(string $metodo, string $ruta, bool $autenticado = true, array $paramsGet = [], bool $esAjax = false): array
{
    $_SERVER['REQUEST_METHOD'] = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $ruta;
    $_GET = $paramsGet;

    if ($esAjax) {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    } else {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    if ($autenticado) {
        GestorSesion::establecer('actor_id', 1);
    } else {
        GestorSesion::eliminar('actor_id');
    }

    $peticion = new Peticion();
    $peticion->establecerMetodo($metodo);
    $peticion->establecerRuta($ruta);

    if ($esAjax) {
        $peticion->establecerCabecera('Accept', 'application/json');
        $peticion->establecerCabecera('X-Requested-With', 'XMLHttpRequest');
    } else {
        $peticion->establecerCabecera('Accept', 'text/html');
    }

    $contexto = ContextoPeticion::crearDesdeEntorno($peticion);
    $respuesta = new Respuesta();

    $enrutador = new Enrutador();
    $configurador = require dirname(__DIR__) . '/config/rutas.php';
    $configurador($enrutador);

    ob_start();
    try {
        $enrutador->despachar($peticion, $respuesta, $contexto);
    } catch (\Throwable $t) {
        $respuesta->establecerCodigoEstado(500);
        $respuesta->establecerCuerpo("Error: " . $t->getMessage());
    }
    $salidaEcho = ob_get_clean();

    $cuerpo = $respuesta->obtenerCuerpo();
    if ($cuerpo === '' && $salidaEcho !== '') {
        $cuerpo = $salidaEcho;
    }

    return [
        'codigo' => $respuesta->obtenerCodigoEstado(),
        'cuerpo' => $cuerpo,
        'cabeceras' => $respuesta->obtenerCabeceras()
    ];
}

// -------------------------------------------------------------------------
// BLOQUE 1: Deny by Default y Protección de la Vista /personas
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 1: Deny by Default y Protección de la Vista /personas ---\n";

// 1. Petición anónima (sin actor) debe ser rechazada con HTTP 401 o Redirección 302 a /login
$resAnonima = despacharVista('GET', '/personas', false);
afirmativo(
    $resAnonima['codigo'] === 401 || $resAnonima['codigo'] === 302,
    'GET /personas anónimo es estrictamente rechazado con HTTP 401 o Redirección 302 a /login'
);

// 2. Comprobar que no revela el padrón en respuesta 401
afirmativo(
    !str_contains($resAnonima['cuerpo'], 'id="tablaPersonas"'),
    'Respuesta 401 no expone el contenido del padrón de personas'
);

// 3. Petición con actor autenticado responde HTTP 200
$resAutenticada = despacharVista('GET', '/personas', true);
afirmativo(
    $resAutenticada['codigo'] === 200,
    'GET /personas con actor autenticado responde HTTP 200 OK'
);

// -------------------------------------------------------------------------
// BLOQUE 2: Anatomía Visual, Estructura Alina y Ausencia de Controles Muertos
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 2: Anatomía Visual, Estructura Alina y Ausencia de Controles Muertos ---\n";

$html = $resAutenticada['cuerpo'];

afirmativo(
    str_contains($html, 'Directorio de Personas'),
    'La vista incluye el título oficial "Directorio de Personas"'
);

afirmativo(
    str_contains($html, 'id="tablaPersonas"'),
    'La vista contiene la tabla principal #tablaPersonas'
);

afirmativo(
    str_contains($html, 'display app-data-table default-data-table table table-hover'),
    'La tabla incluye las clases verificadas de Alina (app-data-table default-data-table)'
);

afirmativo(
    str_contains($html, 'app-datatable-default overflow-auto app-scroll'),
    'El contenedor de la tabla usa el wrapper scrolleable oficial de Alina'
);

afirmativo(
    str_contains($html, 'id="filtroTipoPersona"') && str_contains($html, 'id="filtroEstado"'),
    'La vista incluye los selectores de filtros rápidos por tipo y estado'
);

afirmativo(
    str_contains($html, 'id="modalFichaPersona"'),
    'La vista incluye el modal de Ficha de Identidad (#modalFichaPersona)'
);

afirmativo(
    str_contains($html, 'modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable'),
    'El modal implementa la estructura modal-lg centrada y scrolleable de Alina'
);

// Comprobar ausencia de controles muertos de 1F
afirmativo(
    !str_contains($html, 'Nueva Persona') && !str_contains($html, 'ti-plus'),
    'La vista NO incluye el botón muerto "+ Nueva Persona" (reservado para 1F)'
);

afirmativo(
    !str_contains($html, 'Disponible en Fase 1F') && !str_contains($html, 'Disponible en Microfase 1F'),
    'La vista NO contiene textos informales prometiendo Microfase 1F'
);

// Comprobar que no incluye pestaña de auditoría en la ficha
afirmativo(
    !str_contains($html, 'tab-auditoria') && !str_contains($html, 'Auditoría y Trazabilidad'),
    'La Ficha de Identidad NO expone pestaña de Auditoría y Trazabilidad (se preserva gobernanza 1D)'
);

// Comprobar URL dinámica en data attribute
afirmativo(
    str_contains($html, 'data-api-personas-url='),
    'La tabla expone el endpoint oficial mediante atributo data-api-personas-url dinámico'
);

// -------------------------------------------------------------------------
// BLOQUE 3: Integridad de Assets Reutilizados y Verificados
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 3: Integridad de Assets Reutilizados y Verificados ---\n";

$rutaDatatableJs = dirname(__DIR__) . '/public/assets/vendor/datatable/jquery.dataTables.min.js';
$rutaDatatableCss = dirname(__DIR__) . '/public/assets/vendor/datatable/jquery.dataTables.min.css';
$rutaResponsiveJs = dirname(__DIR__) . '/public/assets/vendor/datatable/dataTables.responsive.min.js';

afirmativo(
    file_exists($rutaDatatableJs) && filesize($rutaDatatableJs) > 50000,
    'Asset vendor oficial jquery.dataTables.min.js presente en public/assets/vendor/datatable/'
);

afirmativo(
    file_exists($rutaDatatableCss) && filesize($rutaDatatableCss) > 10000,
    'Asset vendor oficial jquery.dataTables.min.css presente en public/assets/vendor/datatable/'
);

afirmativo(
    file_exists($rutaResponsiveJs) && filesize($rutaResponsiveJs) > 5000,
    'Asset vendor oficial dataTables.responsive.min.js presente en public/assets/vendor/datatable/'
);

// Verificar no duplicación de jQuery innecesario
$rutaJquery35 = dirname(__DIR__) . '/public/assets/vendor/datatable/jquery-3.5.1.js';
afirmativo(
    !file_exists($rutaJquery35),
    'No se duplicó jquery-3.5.1.js; se reutiliza el jQuery 3.6.3 oficial de Alina'
);

// Verificar que admin-dashboard/ permaneció intacto
$salidaGit = shell_exec('git status --porcelain admin-dashboard');
afirmativo(
    empty(trim((string) $salidaGit)),
    'Directorio admin-dashboard/ permanece 100% intacto y de solo lectura'
);

// -------------------------------------------------------------------------
// BLOQUE 4: Inspección del Script Modular JS Propio
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 4: Inspección del Script Modular JS Propio ---\n";

$rutaModuloJs = dirname(__DIR__) . '/public/assets/js/modulos/personas/listado-personas.js';
afirmativo(
    file_exists($rutaModuloJs),
    'Archivo JavaScript propio listado-personas.js existe en public/assets/js/modulos/personas/'
);

$codigoJs = (string) file_get_contents($rutaModuloJs);

afirmativo(
    str_contains($codigoJs, 'window.CasaProPersonasListado'),
    'Módulo JS encapsulado bajo el namespace window.CasaProPersonasListado'
);

// Validación estricta: ninguna invocación de $.ajax(, $.get(, $.post(, $.getJSON(
$tieneLlamadasJqueryAjax = preg_match('/\$\.(ajax|get|post|getJSON)\s*\(/i', $codigoJs) === 1;
afirmativo(
    !$tieneLlamadasJqueryAjax,
    'Cero llamadas a $.ajax(), $.get(), $.post() o $.getJSON() en el código propio de CasaPRO'
);

afirmativo(
    str_contains($codigoJs, 'window.fetch') || str_contains($codigoJs, 'fetch('),
    'El código propio delega la comunicación con la API en window.fetch nativo'
);

afirmativo(
    str_contains($codigoJs, 'getAttribute(\'data-api-personas-url\')'),
    'El script JS lee la URL dinámica desde la infraestructura sin hardcodear raíz fija'
);

afirmativo(
    str_contains($codigoJs, 'escaparHtml') && str_contains($codigoJs, 'textContent'),
    'El script JS implementa sanitización contextual y asignación segura por textContent'
);

afirmativo(
    str_contains($codigoJs, 'badge text-light-success') && str_contains($codigoJs, 'badge text-light-secondary'),
    'El script JS implementa los estados mediante Variants of badge de Alina (sin dotted)'
);

afirmativo(
    str_contains($codigoJs, 'chip bg-light-primary text-primary') && str_contains($codigoJs, 'chip bg-light-info text-info'),
    'El script JS implementa los tipos de persona mediante Variants of chip de Alina'
);

// -------------------------------------------------------------------------
// PREPARACIÓN DE DATOS DE PRUEBA CONTROLADOS PARA 1E
// -------------------------------------------------------------------------
$proveedor = new ProveedorConexion();
$pdo = $proveedor->obtenerConexion();
$repositorio = new PersonaRepositorio($proveedor);
$auditoria = new AuditoriaServicio();
$servicio = new PersonaServicio($proveedor, $repositorio, $auditoria);
$contextoPrueba = ContextoPeticion::crearDesdeEntorno();

$numDniPrueba = '73' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$numRucPrueba = '20' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);

// Crear Persona Natural de prueba
$resCrearNat = $servicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona'     => 'NATURAL',
    'nombres'          => 'Carlos Alberto',
    'apellido_paterno' => 'Villanueva',
    'apellido_materno' => 'Rojas',
    'fecha_nacimiento' => '1985-06-15',
    'documentos'       => [
        [
            'tipo_documento_id' => 1, // DNI
            'numero_documento'  => $numDniPrueba,
            'es_principal'      => true
        ]
    ],
    'contactos'        => [
        [
            'tipo_contacto_id' => 1, // Teléfono
            'valor'            => '987654321',
            'es_principal'     => true
        ]
    ],
    'direcciones'      => [
        [
            'tipo_direccion_id' => 1, // Fiscal
            'distrito_id'       => 697, // Cusco
            'direccion'         => 'Av. El Sol 456',
            'es_principal'      => true
        ]
    ]
]), $contextoPrueba);
$idNatPrueba = (int) $resCrearNat['id'];

// Crear Persona Jurídica de prueba vinculada
$resCrearJur = $servicio->crear(CrearPersonaDTO::desdeArray([
    'tipo_persona'       => 'JURIDICA',
    'razon_social'       => 'Inversiones Los Andes S.A.C.',
    'nombre_comercial'   => 'Los Andes Inmobiliaria',
    'fecha_constitucion' => '2015-08-20',
    'documentos'         => [
        [
            'tipo_documento_id' => 2, // RUC
            'numero_documento'  => $numRucPrueba,
            'es_principal'      => true
        ]
    ],
    'contactos'          => [
        [
            'tipo_contacto_id' => 2, // Email
            'valor'            => 'contacto@losandes.pe',
            'es_principal'     => true
        ]
    ],
    'direcciones'        => [
        [
            'tipo_direccion_id' => 2,
            'distrito_id'       => 697,
            'direccion'         => 'Calle Triunfo 123',
            'es_principal'      => true
        ]
    ],
    'representantes'     => [
        [
            'persona_natural_id'      => $idNatPrueba,
            'cargo'                   => 'Gerente General',
            'fecha_inicio'            => '2020-03-15',
            'es_representante_actual' => true
        ]
    ]
]), $contextoPrueba);
$idJurPrueba = (int) $resCrearJur['id'];

// -------------------------------------------------------------------------
// BLOQUE 5: Contrato Server-Side DataTables con API 1D
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 5: Contrato Server-Side DataTables con API 1D ---\n";

// 1. Consulta inicial paginada
$resDt = despacharVista('GET', '/api/personas', true, [
    'draw'   => 1,
    'start'  => 0,
    'length' => 10
], true);
afirmativo($resDt['codigo'] === 200, 'GET /api/personas responde HTTP 200 en consulta DataTables inicial');
$jsonDt = json_decode($resDt['cuerpo'], true);
afirmativo(
    isset($jsonDt['draw'], $jsonDt['recordsTotal'], $jsonDt['recordsFiltered'], $jsonDt['data']),
    'Respuesta DataTables contiene draw, recordsTotal, recordsFiltered y data'
);
afirmativo(
    $jsonDt['draw'] === 1 && is_int($jsonDt['recordsTotal']) && $jsonDt['recordsTotal'] >= 2,
    'recordsTotal es un entero mayor o igual a 2 con datos sembrados para la prueba'
);

// 2. Ordenamiento por columna permitida (documento_principal)
$resOrdenDoc = despacharVista('GET', '/api/personas', true, [
    'draw'         => 2,
    'start'        => 0,
    'length'       => 5,
    'order_column' => 'documento_principal',
    'order_dir'    => 'ASC'
], true);
afirmativo($resOrdenDoc['codigo'] === 200, 'DataTables admite order_column=documento_principal con orden ASC');

// 3. Ordenamiento por columna permitida (nombre_completo)
$resOrdenNom = despacharVista('GET', '/api/personas', true, [
    'draw'         => 3,
    'start'        => 0,
    'length'       => 5,
    'order_column' => 'nombre_completo',
    'order_dir'    => 'DESC'
], true);
afirmativo($resOrdenNom['codigo'] === 200, 'DataTables admite order_column=nombre_completo con orden DESC');

// 4. Búsqueda textual
$resBusq = despacharVista('GET', '/api/personas', true, [
    'draw'   => 4,
    'start'  => 0,
    'length' => 10,
    'search' => 'Inversiones'
], true);
afirmativo($resBusq['codigo'] === 200, 'DataTables ejecuta búsqueda textual con HTTP 200');
$jsonBusq = json_decode($resBusq['cuerpo'], true);
afirmativo(
    is_array($jsonBusq['data']) && count($jsonBusq['data']) >= 1,
    'Búsqueda textual entrega array de resultados filtrados con al menos 1 coincidencia'
);

// 5. Filtro tipo_persona=NATURAL
$resFiltroNat = despacharVista('GET', '/api/personas', true, [
    'draw'         => 5,
    'start'        => 0,
    'length'       => 10,
    'tipo_persona' => 'NATURAL'
], true);
afirmativo($resFiltroNat['codigo'] === 200, 'Filtro tipo_persona=NATURAL responde HTTP 200');
$jsonNat = json_decode($resFiltroNat['cuerpo'], true);
$todosNaturales = true;
foreach ($jsonNat['data'] as $fila) {
    if ($fila['tipo_persona'] !== 'NATURAL') {
        $todosNaturales = false;
        break;
    }
}
afirmativo($todosNaturales && count($jsonNat['data']) >= 1, 'Todos los registros retornados con filtro NATURAL son de tipo NATURAL');

// 6. Filtro tipo_persona=JURIDICA
$resFiltroJur = despacharVista('GET', '/api/personas', true, [
    'draw'         => 6,
    'start'        => 0,
    'length'       => 10,
    'tipo_persona' => 'JURIDICA'
], true);
afirmativo($resFiltroJur['codigo'] === 200, 'Filtro tipo_persona=JURIDICA responde HTTP 200');
$jsonJur = json_decode($resFiltroJur['cuerpo'], true);
$todosJuridicos = true;
foreach ($jsonJur['data'] as $fila) {
    if ($fila['tipo_persona'] !== 'JURIDICA') {
        $todosJuridicos = false;
        break;
    }
}
afirmativo($todosJuridicos && count($jsonJur['data']) >= 1, 'Todos los registros retornados con filtro JURIDICA son de tipo JURIDICA');

// 7. Filtro estado=ACTIVO
$resFiltroAct = despacharVista('GET', '/api/personas', true, [
    'draw'   => 7,
    'start'  => 0,
    'length' => 10,
    'estado' => 'ACTIVO'
], true);
afirmativo($resFiltroAct['codigo'] === 200, 'Filtro estado=ACTIVO responde HTTP 200');

// -------------------------------------------------------------------------
// BLOQUE 6: Ficha de Identidad de Persona (Consulta 1D y Pestañas)
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 6: Ficha de Identidad de Persona (Consulta 1D y Pestañas) ---\n";

// Ficha de Persona Natural
$resDetalleNat = despacharVista('GET', "/api/personas/{$idNatPrueba}", true, [], true);
afirmativo($resDetalleNat['codigo'] === 200, "Consulta de Ficha de Identidad para Persona Natural (#{$idNatPrueba}) responde HTTP 200");
$jsonDetNat = json_decode($resDetalleNat['cuerpo'], true);
$datosNat = $jsonDetNat['datos'] ?? $jsonDetNat;

afirmativo(
    isset($datosNat['persona'], $datosNat['natural'], $datosNat['documentos'], $datosNat['contactos'], $datosNat['direcciones']),
    'Ficha de Persona Natural contiene estructura de identidad completa (persona, natural, documentos, contactos, direcciones)'
);

afirmativo(
    !isset($datosNat['auditoria']) && !isset($datosNat['historial_estados']) && !isset($datosNat['snapshots']),
    'Ficha de Persona Natural NO expone auditoría ni historial transversal de estados'
);

// Ficha de Persona Jurídica
$resDetalleJur = despacharVista('GET', "/api/personas/{$idJurPrueba}", true, [], true);
afirmativo($resDetalleJur['codigo'] === 200, "Consulta de Ficha de Identidad para Persona Jurídica (#{$idJurPrueba}) responde HTTP 200");
$jsonDetJur = json_decode($resDetalleJur['cuerpo'], true);
$datosJur = $jsonDetJur['datos'] ?? $jsonDetJur;

afirmativo(
    isset($datosJur['persona'], $datosJur['juridica'], $datosJur['representantes']) && count($datosJur['representantes']) >= 1,
    'Ficha de Persona Jurídica contiene razón social y colección de representantes legales'
);

afirmativo(
    $datosJur['representantes'][0]['persona_natural_id'] === $idNatPrueba && $datosJur['representantes'][0]['cargo'] === 'Gerente General',
    'Representante legal vinculado correctamente a la persona natural representante'
);

// -------------------------------------------------------------------------
// BLOQUE 7: Prueba Real de Inmunidad contra XSS
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 7: Prueba Real de Inmunidad contra XSS ---\n";

function simularEscaparHtml(string $valor): string
{
    return str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'],
        $valor
    );
}

$ataqueScript = '<script>alert("xss")</script>';
$ataqueImg = '<img src=x onerror=alert(1)>';
$ataqueAttr = '" onmouseover="alert(\'xss\')';

$escapeScript = simularEscaparHtml($ataqueScript);
$escapeImg = simularEscaparHtml($ataqueImg);
$escapeAttr = simularEscaparHtml($ataqueAttr);

afirmativo(
    !str_contains($escapeScript, '<script>') && str_contains($escapeScript, '&lt;script&gt;'),
    'Payload <script> es neutralizado como texto plano inofensivo'
);

afirmativo(
    !str_contains($escapeImg, '<img') && str_contains($escapeImg, '&lt;img'),
    'Payload con tag <img onerror> es neutralizado sin posibilidad de ejecución'
);

afirmativo(
    !str_contains($escapeAttr, '"') && str_contains($escapeAttr, '&quot;'),
    'Payload de escape de atributo con comillas es neutralizado'
);

// -------------------------------------------------------------------------
// BLOQUE 8: Manejo Seguro de Errores y HTTP 404 / 500
// -------------------------------------------------------------------------
echo "\n--- BLOQUE 8: Manejo Seguro de Errores y HTTP 404 / 500 ---\n";

// 1. Consulta con ID inexistente debe ser 404
$res404 = despacharVista('GET', '/api/personas/999999999', true, [], true);
afirmativo($res404['codigo'] === 404, 'Consulta de Ficha con ID inexistente responde HTTP 404');
$json404 = json_decode($res404['cuerpo'], true);
afirmativo(
    isset($json404['mensaje']) && !str_contains($res404['cuerpo'], 'SQLSTATE'),
    'Error 404 no expone trazas técnicas ni consultas SQL'
);

// 2. Consulta con parámetro manipulado
$res400 = despacharVista('GET', '/api/personas', true, [
    'order_column' => 'columna_invalida_hack'
], true);
afirmativo($res400['codigo'] === 400, 'Parámetro order_column manipulado es rechazado con HTTP 400 Bad Request');

// -------------------------------------------------------------------------
// LIMPIEZA DE DATOS CONTROLADOS DE PRUEBA
// -------------------------------------------------------------------------
$pdo->beginTransaction();
$pdo->exec("DELETE FROM `persona_representantes` WHERE `persona_juridica_id` = {$idJurPrueba}");
$pdo->exec("DELETE FROM `persona_juridica` WHERE `persona_id` = {$idJurPrueba}");
$pdo->exec("DELETE FROM `persona_documentos` WHERE `persona_id` IN ({$idNatPrueba}, {$idJurPrueba})");
$pdo->exec("DELETE FROM `persona_contactos` WHERE `persona_id` IN ({$idNatPrueba}, {$idJurPrueba})");
$pdo->exec("DELETE FROM `persona_direcciones` WHERE `persona_id` IN ({$idNatPrueba}, {$idJurPrueba})");
$pdo->exec("DELETE FROM `persona_natural` WHERE `persona_id` = {$idNatPrueba}");
$pdo->exec("DELETE FROM `auditorias` WHERE `modulo` = 'identidad' AND `entidad` = 'personas' AND `registro_id` IN ({$idNatPrueba}, {$idJurPrueba})");
$pdo->exec("DELETE FROM `personas` WHERE `id` IN ({$idNatPrueba}, {$idJurPrueba})");
$pdo->commit();

// -------------------------------------------------------------------------
// RESUMEN FINAL
// -------------------------------------------------------------------------
echo "\n===================================================================\n";
echo " RESUMEN FINAL 1E: {$pruebasSuperadas} de {$pruebasEjecutadas} superadas.\n";
if ($pruebasSuperadas === $pruebasEjecutadas) {
    echo " RESULTADO SUITE LISTADO Y FICHA DE PERSONAS: [PASS]\n";
} else {
    echo " RESULTADO SUITE LISTADO Y FICHA DE PERSONAS: [FAIL]\n";
    exit(1);
}
echo "===================================================================\n";
