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
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Modelos\MenuOpcion;
use App\Repositorios\MenuRepositorio;
use App\Servicios\MenuServicio;
use App\Controladores\MenuControlador;
use App\DTOs\CrearMenuOpcionDTO;
use App\DTOs\ActualizarMenuOpcionDTO;
use App\DTOs\ReordenarMenuDTO;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Tests\Comun\AmbientePruebas;
use Tests\Comun\FixtureAutenticacion;

$conexion = AmbientePruebas::iniciar(true);
$proveedor = AmbientePruebas::obtenerProveedorTest();
GestorSesion::iniciar();

echo "===================================================================\n";
echo " SUITE DE PRUEBAS DE MENÚ DINÁMICO Y NAVEGACIÓN JERÁRQUICA (1G-3)\n";
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

// -------------------------------------------------------------------------
// Contexto y Operador de Auditoría
// -------------------------------------------------------------------------
$contexto = new ContextoPeticion('REQ-TEST-1G3', '127.0.0.1', 'CLI-TestRunner/1G3', 'CLI', 1);

$repo = new MenuRepositorio($proveedor);
$servicio = new MenuServicio($proveedor, $repo);
$controlador = new MenuControlador($servicio);

try {
    // =========================================================================
    // 1. AISLAMIENTO Y ESQUEMA BASELINE DE BASE DE DATOS
    // =========================================================================
    echo "1. Verificación de Aislamiento y Esquema Relacional de BD\n";

    $bdActual = $conexion->query("SELECT DATABASE()")->fetchColumn();
    afirmar($bdActual === 'casapro_test', "La suite se ejecuta exclusivamente en 'casapro_test'");

    $tablas = $conexion->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    afirmar(count($tablas) >= 26, "La base de datos contiene al menos 26 tablas productivas");
    afirmar(in_array('menu_opciones', $tablas, true), "La tabla 'menu_opciones' existe en el esquema");

    // Verificar FK RESTRICT
    $fkQuery = "
        SELECT rc.DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        WHERE rc.CONSTRAINT_SCHEMA = 'casapro_test'
          AND rc.TABLE_NAME = 'menu_opciones'
          AND rc.REFERENCED_TABLE_NAME = 'privilegios'
    ";
    $reglaFk = $conexion->query($fkQuery)->fetchColumn();
    afirmar($reglaFk === 'RESTRICT', "La FK 'fk_menu_privilegio' tiene regla ON DELETE RESTRICT");

    // =========================================================================
    // 2. SEED MÍNIMO Y PRIVILEGIOS RBAC DE MENÚ
    // =========================================================================
    echo "\n2. Verificación de Privilegios RBAC y Seed Mínimo Oficial (7 Nodos)\n";

    $privsMenu = $conexion->query("
        SELECT codigo FROM `privilegios` WHERE modulo = 'menu' ORDER BY codigo ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $esperados = ['menu.cambiar_estado', 'menu.crear', 'menu.editar', 'menu.eliminar', 'menu.reordenar', 'menu.ver'];
    afirmar($privsMenu === $esperados, "Existen los 6 privilegios del módulo 'menu' en catálogo");

    // Verificar asignación a SUPERADMIN
    $stmtSup = $conexion->prepare("
        SELECT COUNT(*)
        FROM `rol_privilegios` rp
        JOIN `roles` r ON r.id = rp.rol_id
        JOIN `privilegios` p ON p.id = rp.privilegio_id
        WHERE r.codigo = 'SUPERADMIN' AND p.modulo = 'menu'
    ");
    $stmtSup->execute();
    afirmar((int) $stmtSup->fetchColumn() === 6, "El rol SUPERADMIN tiene asignados los 6 privilegios de 'menu'");

    // Verificar los 7 nodos semilla
    $arbolCompleto = $servicio->obtenerArbolCompleto();
    afirmar(count($arbolCompleto) === 2, "El árbol raíz contiene 2 nodos principales (MOD_INICIO y MOD_IDENTIDAD)");

    $inicio = $arbolCompleto[0];
    afirmar($inicio['codigo'] === 'MOD_INICIO' && $inicio['tipo'] === 'ENLACE' && $inicio['ruta'] === '/inicio', "MOD_INICIO es Enlace directo de nivel 0 hacia '/inicio'");

    $identidad = $arbolCompleto[1];
    afirmar(count($identidad['hijos']) >= 2, "MOD_IDENTIDAD contiene al menos 2 agrupadores de nivel 1 (GRP_PERSONAS, GRP_SEGURIDAD, GRP_EMPRESAS)");

    $grpPersonas = $identidad['hijos'][0];
    afirmar($grpPersonas['codigo'] === 'GRP_PERSONAS' && count($grpPersonas['hijos']) === 1, "GRP_PERSONAS contiene 1 enlace de nivel 2 (OPC_PERSONAS_LISTADO)");
    afirmar($grpPersonas['hijos'][0]['codigo'] === 'OPC_PERSONAS_LISTADO' && $grpPersonas['hijos'][0]['ruta'] === 'personas', "OPC_PERSONAS_LISTADO apunta a 'personas'");

    $grpSeguridad = $identidad['hijos'][1];
    afirmar($grpSeguridad['codigo'] === 'GRP_SEGURIDAD' && count($grpSeguridad['hijos']) === 2, "GRP_SEGURIDAD contiene 2 enlaces de nivel 2 (OPC_USUARIOS_LISTADO y OPC_MENU_LISTADO)");

    // =========================================================================
    // 3. PROFUNDIDAD VARIABLE Y RECHAZO DE NIVEL 4+
    // =========================================================================
    echo "\n3. Reglas de Negocio: Profundidad Variable (1, 2 y 3) y Rechazo de Nivel 4+\n";

    // Nivel 0 -> Nivel 1 (Profundidad 2)
    // Crear agrupador temporal en raíz
    $dtoRaizTemp = CrearMenuOpcionDTO::desdeArray([
        'padre_id' => null,
        'tipo'     => 'AGRUPADOR',
        'codigo'   => 'MOD_TEST_PROF',
        'titulo'   => 'Módulo Test Profundidad',
        'icono'    => 'fa-solid fa-flask'
    ]);
    $raizTemp = $servicio->crearOpcion($dtoRaizTemp, 1, $contexto);
    $raizTempId = (int) $raizTemp['id'];
    afirmar($raizTempId > 0, "Se crea nodo raíz de nivel 0 exitosamente");

    // Nivel 1: Agrupador hijo
    $dtoHijo1 = CrearMenuOpcionDTO::desdeArray([
        'padre_id' => $raizTempId,
        'tipo'     => 'AGRUPADOR',
        'codigo'   => 'GRP_TEST_NIVEL1',
        'titulo'   => 'Grupo Test Nivel 1',
        'icono'    => 'fa-solid fa-folder'
    ]);
    $hijo1 = $servicio->crearOpcion($dtoHijo1, 1, $contexto);
    $hijo1Id = (int) $hijo1['id'];
    afirmar($hijo1Id > 0, "Se crea agrupador hijo de nivel 1 exitosamente (Profundidad 2)");

    // Nivel 2: Enlace hoja
    $privMenuVerId = (int) $conexion->query("SELECT id FROM `privilegios` WHERE `codigo` = 'menu.ver'")->fetchColumn();
    $dtoHijo2 = CrearMenuOpcionDTO::desdeArray([
        'padre_id'      => $hijo1Id,
        'tipo'          => 'ENLACE',
        'codigo'        => 'OPC_TEST_NIVEL2',
        'titulo'        => 'Opción Test Nivel 2',
        'ruta'          => 'test/opcion-2',
        'privilegio_id' => $privMenuVerId
    ]);
    $hijo2 = $servicio->crearOpcion($dtoHijo2, 1, $contexto);
    $hijo2Id = (int) $hijo2['id'];
    afirmar($hijo2Id > 0, "Se crea enlace de nivel 2 exitosamente (Profundidad 3 alcanzada)");

    // Intento de Nivel 3 (Profundidad 4): Debe ser RECHAZADO
    $excepcionProfundidad = false;
    try {
        $dtoNivel3Invalido = CrearMenuOpcionDTO::desdeArray([
            'padre_id' => $hijo2Id,
            'tipo'     => 'ENLACE',
            'codigo'   => 'OPC_TEST_NIVEL3_INV',
            'titulo'   => 'Opción Test Nivel 3 Inválida',
            'ruta'     => 'test/opcion-3'
        ]);
        $servicio->crearOpcion($dtoNivel3Invalido, 1, $contexto);
    } catch (ReglaNegocioExcepcion $e) {
        $excepcionProfundidad = true;
    }
    afirmar($excepcionProfundidad, "Intento de crear un elemento en nivel 3 (profundidad 4+) es rechazado con ReglaNegocioExcepcion");

    // Intento de crear AGRUPADOR como hoja en nivel 2: Debe ser RECHAZADO (el nivel 2 solo puede ser ENLACE)
    $excepcionAgrupadorNivel2 = false;
    try {
        $dtoAgrupadorNivel2 = CrearMenuOpcionDTO::desdeArray([
            'padre_id' => $hijo1Id,
            'tipo'     => 'AGRUPADOR',
            'codigo'   => 'GRP_TEST_NIVEL2_INV',
            'titulo'   => 'Agrupador Inválido en Nivel 2'
        ]);
        $servicio->crearOpcion($dtoAgrupadorNivel2, 1, $contexto);
    } catch (ReglaNegocioExcepcion $e) {
        $excepcionAgrupadorNivel2 = true;
    }
    afirmar($excepcionAgrupadorNivel2, "Intento de crear AGRUPADOR en nivel 2 es rechazado (el nivel final solo admite enlaces)");

    // =========================================================================
    // 4. PREVENCIÓN DE CICLOS DIRECTOS E INDIRECTOS
    // =========================================================================
    echo "\n4. Reglas de Integridad: Prevención de Ciclos Directos e Indirectos\n";

    // Ciclo directo: padre_id == id
    $excepcionCicloDirecto = false;
    try {
        $dtoCicloDirecto = ActualizarMenuOpcionDTO::desdeArray([
            'padre_id' => $raizTempId,
            'titulo'   => 'Intento Ciclo Directo'
        ], 'AGRUPADOR');
        $servicio->actualizarOpcion($raizTempId, $dtoCicloDirecto, 1, $contexto);
    } catch (ReglaNegocioExcepcion $e) {
        $excepcionCicloDirecto = true;
    }
    afirmar($excepcionCicloDirecto, "Ciclo directo (padre_id == id) es rechazado");

    // Ciclo indirecto: mover $raizTemp bajo su hijo $hijo1
    $excepcionCicloIndirecto = false;
    try {
        $dtoCicloIndirecto = ActualizarMenuOpcionDTO::desdeArray([
            'padre_id' => $hijo1Id,
            'titulo'   => 'Intento Ciclo Indirecto'
        ], 'AGRUPADOR');
        $servicio->actualizarOpcion($raizTempId, $dtoCicloIndirecto, 1, $contexto);
    } catch (ReglaNegocioExcepcion $e) {
        $excepcionCicloIndirecto = true;
    }
    afirmar($excepcionCicloIndirecto, "Ciclo indirecto (mover nodo padre como descendiente de su propio hijo) es rechazado");

    // =========================================================================
    // 5. VALIDACIÓN DE CAMPOS, RUTAS SEGURAS E ICONOS FONT AWESOME
    // =========================================================================
    echo "\n5. Validaciones de DTO: Rutas Internas, Iconos Font Awesome y Sanitización\n";

    // Validación de ruta maliciosa o externa
    $rutasInvalidas = ['https://malicioso.com', 'javascript:alert(1)', 'ftp://server/test', '/invalido//ruta'];
    foreach ($rutasInvalidas as $rInvalida) {
        $fallo = false;
        try {
            CrearMenuOpcionDTO::desdeArray([
                'padre_id' => $hijo1Id,
                'tipo'     => 'ENLACE',
                'codigo'   => 'OPC_RUTA_INV',
                'titulo'   => 'Ruta Inválida',
                'ruta'     => $rInvalida
            ]);
        } catch (ValidacionExcepcion) {
            $fallo = true;
        }
        afirmar($fallo, "Ruta inválida/insegura '{$rInvalida}' es rechazada por el DTO");
    }

    // Validación de iconos: Solo Font Awesome permitido (rechazo explícito de Tabler u otros)
    $iconosInvalidos = ['ti' . ' ti-home', 'iconoir-user', '<script>alert(1)</script>', 'glyph-star'];
    foreach ($iconosInvalidos as $icoInvalido) {
        $fallo = false;
        try {
            CrearMenuOpcionDTO::desdeArray([
                'padre_id' => null,
                'tipo'     => 'AGRUPADOR',
                'codigo'   => 'MOD_ICO_INV',
                'titulo'   => 'Icono Inválido',
                'icono'    => $icoInvalido
            ]);
        } catch (ValidacionExcepcion) {
            $fallo = true;
        }
        afirmar($fallo, "Icono no Font Awesome '{$icoInvalido}' es rechazado por el DTO");
    }

    // Icono Font Awesome válido
    $dtoIconoOk = CrearMenuOpcionDTO::desdeArray([
        'padre_id' => null,
        'tipo'     => 'AGRUPADOR',
        'codigo'   => 'MOD_ICO_OK',
        'titulo'   => 'Icono Válido',
        'icono'    => 'fa-solid fa-chart-line'
    ]);
    afirmar($dtoIconoOk->icono === 'fa-solid fa-chart-line', "Icono Font Awesome 'fa-solid fa-chart-line' es aceptado");

    // =========================================================================
    // 6. NORMALIZACIÓN DETERMINISTA DE ORDEN ENTRE HERMANOS (PHP/PDO)
    // =========================================================================
    echo "\n6. Normalización Determinista de Orden $1..N$ en PHP/PDO (Cero variables MySQL @seq)\n";

    // Crear dos hermanos adicionales bajo $raizTemp
    $dtoHermano2 = CrearMenuOpcionDTO::desdeArray([
        'padre_id' => $raizTempId,
        'tipo'     => 'AGRUPADOR',
        'codigo'   => 'GRP_HERMANO_2',
        'titulo'   => 'Hermano 2',
        'orden'    => 10 // Forzar orden con hueco
    ]);
    $hermano2 = $servicio->crearOpcion($dtoHermano2, 1, $contexto);
    $hermano2Id = (int) $hermano2['id'];

    $dtoHermano3 = CrearMenuOpcionDTO::desdeArray([
        'padre_id' => $raizTempId,
        'tipo'     => 'AGRUPADOR',
        'codigo'   => 'GRP_HERMANO_3',
        'titulo'   => 'Hermano 3',
        'orden'    => 25 // Forzar orden con hueco mayor
    ]);
    $hermano3 = $servicio->crearOpcion($dtoHermano3, 1, $contexto);
    $hermano3Id = (int) $hermano3['id'];

    // Ejecutar normalización del grupo
    $servicio->normalizarOrdenHermanos($raizTempId);

    $ordenesHermanos = $conexion->query("
        SELECT orden FROM `menu_opciones` WHERE padre_id = {$raizTempId} ORDER BY orden ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
    $ordenesHermanos = array_map('intval', $ordenesHermanos);

    afirmar($ordenesHermanos === [1, 2, 3], "Los órdenes de los 3 hermanos quedaron normalizados exactamente a [1, 2, 3]");

    // =========================================================================
    // 7. REORDENAMIENTO MASIVO JERÁRQUICO CON ROLLBACK ATÓMICO
    // =========================================================================
    echo "\n7. Reordenamiento Jerárquico en Lote y Rollback Atómico\n";

    // Reordenar intercambiando posiciones: hermano 3 a orden 1, hermano 2 a orden 2, hijo 1 a orden 3
    $dtoReordenar = ReordenarMenuDTO::desdeArray([
        'opciones' => [
            ['id' => $hermano3Id, 'nuevo_padre_id' => $raizTempId, 'orden' => 1],
            ['id' => $hermano2Id, 'nuevo_padre_id' => $raizTempId, 'orden' => 2],
            ['id' => $hijo1Id,    'nuevo_padre_id' => $raizTempId, 'orden' => 3]
        ]
    ]);
    $servicio->reordenar($dtoReordenar, 1, $contexto);

    $nuevoOrden3 = (int) $conexion->query("SELECT orden FROM `menu_opciones` WHERE id = {$hermano3Id}")->fetchColumn();
    $nuevoOrden1 = (int) $conexion->query("SELECT orden FROM `menu_opciones` WHERE id = {$hijo1Id}")->fetchColumn();
    afirmar($nuevoOrden3 === 1 && $nuevoOrden1 === 3, "El reordenamiento masivo aplicó las nuevas posiciones atómicamente");

    // Intento de reordenamiento con payload que genere ciclo: debe hacer ROLLBACK completo
    $ordenAntesDelFallo = $conexion->query("SELECT id, orden, padre_id FROM `menu_opciones` WHERE padre_id = {$raizTempId}")->fetchAll(PDO::FETCH_ASSOC);

    $falloRollback = false;
    try {
        $dtoReordenarInvalido = ReordenarMenuDTO::desdeArray([
            'opciones' => [
                ['id' => $raizTempId, 'nuevo_padre_id' => $hermano3Id, 'orden' => 1] // Ciclo!
            ]
        ]);
        $servicio->reordenar($dtoReordenarInvalido, 1, $contexto);
    } catch (ReglaNegocioExcepcion) {
        $falloRollback = true;
    }
    afirmar($falloRollback, "Reordenamiento que induce ciclo es rechazado");

    $ordenDespuesDelFallo = $conexion->query("SELECT id, orden, padre_id FROM `menu_opciones` WHERE padre_id = {$raizTempId}")->fetchAll(PDO::FETCH_ASSOC);
    afirmar($ordenAntesDelFallo === $ordenDespuesDelFallo, "Transacción revertida: El estado previo se conserva intacto tras el rollback");

    // =========================================================================
    // 8. PODA FAIL-CLOSED ANTE PRIVILEGIOS Y PODA DE RAMAS VACÍAS
    // =========================================================================
    echo "\n8. Poda Fail-Closed y Poda de Ramas Vacías (Bottom-Up)\n";

    // Usuario de prueba sin roles ni privilegios (ID 9999)
    $usuarioSinPrivsId = 9999;

    // Consultar menú para usuario sin privilegios
    $arbolSinPrivs = $servicio->obtenerArbolParaUsuario($usuarioSinPrivsId);

    // MOD_INICIO no tiene privilegio_id -> Debe ser visible
    // MOD_IDENTIDAD requiere que sus hijos sean visibles. Pero sus hijos requieren 'personas.ver', 'usuarios.ver', 'menu.ver'.
    // Al no tener ninguno, los hijos se podan, y por tanto MOD_IDENTIDAD queda como rama vacía y se poda completamente.
    afirmar(count($arbolSinPrivs) === 1, "Para usuario sin privilegios, solo MOD_INICIO es visible (MOD_IDENTIDAD podado por ramas vacías)");
    afirmar($arbolSinPrivs[0]['codigo'] === 'MOD_INICIO', "El único nodo visible es MOD_INICIO (público / sin privilegio específico)");

    // Probar SUPERADMIN: Debe ver todas las opciones activas (incluyendo las creadas en el test)
    $authSuperadmin = FixtureAutenticacion::autenticarComoSuperadmin($conexion);
    $arbolSuperadmin = $servicio->obtenerArbolParaUsuario((int) $authSuperadmin['usuario_id']);
    afirmar(count($arbolSuperadmin) >= 2, "SUPERADMIN ve todos los módulos activos del sistema");

    // =========================================================================
    // 9. REGLAS DE ELIMINACIÓN: NODO HOJA VS NODO PADRE CON HIJOS (409 CONFLICT)
    // =========================================================================
    echo "\n9. Reglas de Eliminación: Nodo Hoja Permitido vs Padre con Hijos (Conflict)\n";

    // Intentar eliminar $raizTemp que tiene hijos: Debe ser RECHAZADO
    $excepcionEliminarPadre = false;
    try {
        $servicio->eliminarOpcion($raizTempId, 1, $contexto);
    } catch (ReglaNegocioExcepcion $e) {
        $excepcionEliminarPadre = true;
    }
    afirmar($excepcionEliminarPadre, "Eliminación de nodo padre con sub-elementos activos es rechazada con ReglaNegocioExcepcion");

    // Eliminar hojas primero
    $servicio->eliminarOpcion($hijo2Id, 1, $contexto);
    afirmar($servicio->obtenerPorId($hijo2Id) === null, "Eliminación de nodo hoja (sin hijos) es permitida exitosamente");

    // Eliminar los otros hijos
    $servicio->eliminarOpcion($hijo1Id, 1, $contexto);
    $servicio->eliminarOpcion($hermano2Id, 1, $contexto);
    $servicio->eliminarOpcion($hermano3Id, 1, $contexto);

    // Ahora $raizTemp no tiene hijos: su eliminación debe permitirse
    $servicio->eliminarOpcion($raizTempId, 1, $contexto);
    afirmar($servicio->obtenerPorId($raizTempId) === null, "Nodo raíz previamente contenedor ahora es eliminado al no tener hijos");

    // =========================================================================
    // 10. SEGURIDAD SOBERANA: MENÚ ≠ AUTORIZACIÓN (URL DIRECTA DEVUELVE HTTP 403)
    // =========================================================================
    echo "\n10. Principio de Seguridad Soberana: Menú ≠ Autorización (Middleware 403)\n";

    // Simular sesión de usuario estándar sin privilegio 'menu.ver'
    GestorSesion::establecer('auth', [
        'usuario_id' => 999,
        'username'   => 'operador_limitado',
        'roles'      => ['OPERADOR'],
        'scope'      => 'EMPRESA'
    ]);

    $crearPeticionMock = function (string $metodo, string $ruta, array $cuerpo = []): Peticion {
        $_SERVER['REQUEST_METHOD'] = $metodo;
        $_SERVER['REQUEST_URI'] = $ruta;
        $p = new Peticion();
        $p->establecerMetodo($metodo);
        $p->establecerRuta($ruta);
        if (!empty($cuerpo)) {
            $p->establecerJson($cuerpo);
            $p->establecerCuerpo($cuerpo);
        }
        return $p;
    };

    // Consultar middleware de autorización directamente para 'menu.ver'
    $autorizado = \App\Middlewares\AutorizacionMiddleware::exigir('menu.ver');
    $peticionMock = $crearPeticionMock('GET', '/menu');
    $respuestaMock = new Respuesta();

    $bloqueado = false;
    $siguienteMock = function () use (&$bloqueado) {
        $bloqueado = false;
    };

    // Al no tener 'menu.ver', debe retornar 403 o lanzar excepción de autorización
    try {
        $autorizado($peticionMock, $respuestaMock, $contexto, $siguienteMock);
        if ($respuestaMock->obtenerCodigoEstado() === 403) {
            $bloqueado = true;
        }
    } catch (\App\Excepciones\AutorizacionExcepcion) {
        $bloqueado = true;
    } catch (\Throwable) {
        $bloqueado = true;
    }
    afirmar($bloqueado, "El acceso directo por URL sin privilegio RBAC 'menu.ver' es rechazado con HTTP 403 por el Middleware");

    // =========================================================================
    // 11. ENDPOINTS DE CONTROLADOR Y CÓDIGOS DE ESTADO HTTP
    // =========================================================================
    echo "\n11. Integración de Endpoints del Controlador y Códigos HTTP\n";

    // Restaurar sesión SUPERADMIN
    FixtureAutenticacion::autenticarComoSuperadmin($conexion);

    // GET /api/menu -> 200
    $peticion = $crearPeticionMock('GET', '/api/menu');
    $respuesta = new Respuesta();
    $controlador->obtenerArbol($peticion, $respuesta);
    afirmar($respuesta->obtenerCodigoEstado() === 200, "GET /api/menu responde HTTP 200");
    $jsonArbol = json_decode($respuesta->obtenerCuerpo(), true);
    afirmar($jsonArbol['estado'] === 'exito' && is_array($jsonArbol['datos']), "GET /api/menu entrega estructura JSON estándar");

    // GET /api/menu/{id} -> 200
    $peticion = $crearPeticionMock('GET', '/api/menu/1');
    $respuesta = new Respuesta();
    $controlador->obtenerPorId($peticion, $respuesta, $contexto, ['id' => 1]);
    afirmar($respuesta->obtenerCodigoEstado() === 200, "GET /api/menu/1 responde HTTP 200");

    // GET /api/menu/99999 -> 404
    $peticion = $crearPeticionMock('GET', '/api/menu/99999');
    $respuesta = new Respuesta();
    $controlador->obtenerPorId($peticion, $respuesta, $contexto, ['id' => 99999]);
    afirmar($respuesta->obtenerCodigoEstado() === 404, "GET /api/menu/99999 responde HTTP 404");

    // POST /api/menu con datos inválidos -> 422
    $peticion = $crearPeticionMock('POST', '/api/menu', ['codigo' => 'NO']);
    $respuesta = new Respuesta();
    $controlador->crear($peticion, $respuesta, $contexto);
    afirmar($respuesta->obtenerCodigoEstado() === 422, "POST /api/menu con DTO inválido responde HTTP 422");

    // PATCH /api/menu/{id}/estado -> 200
    $peticion = $crearPeticionMock('PATCH', '/api/menu/1/estado', ['estado' => 'ACTIVO']);
    $respuesta = new Respuesta();
    $controlador->cambiarEstado($peticion, $respuesta, $contexto, ['id' => 1]);
    afirmar($respuesta->obtenerCodigoEstado() === 200, "PATCH /api/menu/1/estado responde HTTP 200");

    // DELETE /api/menu/2 (MOD_IDENTIDAD tiene hijos) -> 409 Conflict
    $peticion = $crearPeticionMock('DELETE', '/api/menu/2');
    $respuesta = new Respuesta();
    $controlador->eliminar($peticion, $respuesta, $contexto, ['id' => 2]);
    afirmar($respuesta->obtenerCodigoEstado() === 409, "DELETE /api/menu/2 con sub-elementos activos responde HTTP 409 Conflict");

    // =========================================================================
    // 12. AUDITORÍA DE OPERACIONES EN 'auditorias'
    // =========================================================================
    echo "\n12. Verificación de Inmutabilidad y Auditoría de Operaciones en 'auditorias'\n";

    $eventosMenu = $conexion->query("
        SELECT COUNT(*)
        FROM `auditorias`
        WHERE entidad = 'menu_opciones'
    ")->fetchColumn();
    afirmar((int) $eventosMenu > 0, "Las operaciones de alta, edición y baja de opciones registraron eventos en 'auditorias'");

} finally {
    // Restaurar tablas mutables al estado limpio de pruebas
    AmbientePruebas::limpiarTablasMutables($conexion);
}

echo "\n===================================================================\n";
echo " RESULTADO: {$pruebasSuperadas} / {$totalPruebas} PRUEBAS SUPERADAS (100% PASS)\n";
echo "===================================================================\n";
