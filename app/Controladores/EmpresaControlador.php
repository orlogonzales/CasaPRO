<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Vista;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Servicios\EmpresaServicio;
use App\DTOs\CrearEmpresaDTO;
use App\DTOs\ActualizarEmpresaDTO;
use App\DTOs\CambiarEstadoEmpresaDTO;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Throwable;

/**
 * EmpresaControlador — Controlador web y API para la gestión integral de Empresas corporativas.
 *
 * Microfase 2C — CasaPRO Inmobiliario.
 * Reglas:
 * - Cero DELETE físico.
 * - Respuestas JSON estandarizadas (estado, codigo, mensaje, datos, meta).
 * - Protección CSRF y autorización RBAC empresas.*.
 */
class EmpresaControlador extends BaseControlador
{
    private EmpresaServicio $empresaServicio;

    public function __construct(?EmpresaServicio $empresaServicio = null)
    {
        $this->empresaServicio = $empresaServicio ?? new EmpresaServicio();
    }

    /**
     * GET /empresas
     * Renderiza la vista principal del catálogo de empresas corporativas.
     */
    public function index(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $personaRepo = new \App\Repositorios\PersonaRepositorio();
        $catalogos = $personaRepo->obtenerCatalogosFormulario();

        return $this->renderizar('modulos/empresas/index', [
            'tituloPagina'   => 'Catálogo de Empresas | CasaPRO',
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Estructura Corporativa', 'url' => null],
                ['texto' => 'Empresas', 'url' => null]
            ],
            'catalogos'      => $catalogos,
            'tokenCsrf'      => CsrfServicio::obtenerToken(),
            'cssAdicionales' => [
                'vendor/datatable/jquery.dataTables.min.css',
                'vendor/select/select2.min.css'
            ],
            'jsAdicionales'  => [
                'vendor/datatable/jquery.dataTables.min.js',
                'vendor/datatable/dataTables.responsive.min.js',
                'vendor/sweetalert/sweetalert.js',
                'vendor/select/select2.min.js',
                'vendor/pristine/pristine.min.js',
                'vendor/cleavejs/cleave.min.js',
                'js/modulos/personas/consulta-documento.js',
                'js/modulos/empresas/gestion-empresas.js'
            ]
        ]);
    }

    /**
     * GET /api/empresas
     * Endpoint DataTables server-side con paginación, filtros y ordenamiento seguro.
     */
    public function listar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $criterios = [
                'draw'      => (int) $peticion->obtenerConsulta('draw', 1),
                'start'     => (int) $peticion->obtenerConsulta('start', 0),
                'length'    => (int) $peticion->obtenerConsulta('length', 10),
                'search'    => (string) $peticion->obtenerConsulta('search', ''),
                'estado'    => (string) $peticion->obtenerConsulta('estado', ''),
                'order_by'  => (string) $peticion->obtenerConsulta('order_column', ''),
                'order_dir' => (string) $peticion->obtenerConsulta('order_dir', 'DESC')
            ];

            // Si DataTables envía search como array ['value' => '...']
            $busquedaRaw = $peticion->obtenerConsulta('search');
            if (is_array($busquedaRaw) && isset($busquedaRaw['value'])) {
                $criterios['search'] = (string) $busquedaRaw['value'];
            }

            // Si DataTables envía order como array [['column' => X, 'dir' => 'asc']]
            $ordenRaw = $peticion->obtenerConsulta('order');
            if (is_array($ordenRaw) && isset($ordenRaw[0]['column'])) {
                $mapaColumnas = [
                    0 => 'codigo',
                    1 => 'nombre_corto',
                    2 => 'razon_social',
                    3 => 'ruc',
                    4 => 'estado',
                    5 => 'creado_en'
                ];
                $colIdx = (int) $ordenRaw[0]['column'];
                if (isset($mapaColumnas[$colIdx])) {
                    $criterios['order_by'] = $mapaColumnas[$colIdx];
                    $criterios['order_dir'] = strtoupper((string) ($ordenRaw[0]['dir'] ?? 'DESC'));
                }
            }

            $resultado = $this->empresaServicio->obtenerListadoDataTables($criterios);
            $respuesta->json($resultado);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/empresas/personas-juridicas-disponibles
     * Endpoint para Select2: recupera Personas Jurídicas activas no vinculadas a ninguna empresa.
     */
    public function personasJuridicasDisponibles(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $termino = (string) ($peticion->obtenerConsulta('q') ?? $peticion->obtenerConsulta('termino') ?? '');
            $limite = (int) ($peticion->obtenerConsulta('limit', 20));

            $personas = $this->empresaServicio->obtenerPersonasJuridicasDisponibles($termino, $limite);
            $this->responderExito($respuesta, 200, 'Personas jurídicas disponibles recuperadas.', $personas, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/empresas/{id}
     * Recupera la Ficha 360 integral de una empresa y su persona jurídica vinculada.
     */
    public function detalle(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        if ($id <= 0) {
            $this->responderError($respuesta, 400, 'Identificador de empresa inválido.', null, $idCorrelacion);
            return;
        }

        try {
            $detalle = $this->empresaServicio->obtenerDetalleCompleto($id);

            if ($detalle === null) {
                $this->responderError($respuesta, 404, "La empresa solicitada (ID {$id}) no existe.", null, $idCorrelacion);
                return;
            }

            $this->responderExito($respuesta, 200, 'Ficha detallada de empresa recuperada.', $detalle, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/empresas
     * Alta de empresa corporativa (soporta modalidad vinculada u orquestada).
     */
    public function crear(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();

        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CrearEmpresaDTO::desdeArray($datos);

            GestorSesion::iniciar();
            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 1);

            $resultado = $this->empresaServicio->crear($dto, $operadorId, $contexto);
            $this->responderExito($respuesta, 201, 'Empresa creada exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $ve) {
            $this->responderError($respuesta, 422, $ve->getMessage(), $ve->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $rne) {
            $this->responderError($respuesta, $rne->getCode() ?: 422, $rne->getMessage(), null, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PUT /api/empresas/{id}
     * Edición del nombre corto operativo de una empresa. persona_id y codigo son inmutables.
     */
    public function actualizar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();
        $id = (int) ($parametros['id'] ?? 0);

        if ($id <= 0) {
            $this->responderError($respuesta, 400, 'Identificador de empresa inválido.', null, $idCorrelacion);
            return;
        }

        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = ActualizarEmpresaDTO::desdeArray($datos);

            GestorSesion::iniciar();
            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 1);

            $resultado = $this->empresaServicio->actualizar($id, $dto, $operadorId, $contexto);
            $this->responderExito($respuesta, 200, 'Empresa actualizada exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $ve) {
            $this->responderError($respuesta, 422, $ve->getMessage(), $ve->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $rne) {
            $this->responderError($respuesta, $rne->getCode() ?: 422, $rne->getMessage(), null, $idCorrelacion);
        } catch (RecursoNoEncontradoExcepcion $rne2) {
            $this->responderError($respuesta, 404, $rne2->getMessage(), null, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PATCH /api/empresas/{id}/estado
     * Conmuta el estado operativo de la empresa (ACTIVO <-> INACTIVO).
     */
    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();
        $id = (int) ($parametros['id'] ?? 0);

        if ($id <= 0) {
            $this->responderError($respuesta, 400, 'Identificador de empresa inválido.', null, $idCorrelacion);
            return;
        }

        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CambiarEstadoEmpresaDTO::desdeArray($datos);

            GestorSesion::iniciar();
            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 1);

            $resultado = $this->empresaServicio->cambiarEstado($id, $dto, $operadorId, $contexto);
            $this->responderExito($respuesta, 200, 'Estado de la empresa actualizado exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $ve) {
            $this->responderError($respuesta, 422, $ve->getMessage(), $ve->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $rne) {
            $this->responderError($respuesta, $rne->getCode() ?: 422, $rne->getMessage(), null, $idCorrelacion);
        } catch (RecursoNoEncontradoExcepcion $rne2) {
            $this->responderError($respuesta, 404, $rne2->getMessage(), null, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    private function obtenerDatosEntrada(Peticion $peticion): array
    {
        $datos = $peticion->obtenerJson();
        if (empty($datos)) {
            $cuerpo = $peticion->obtenerCuerpo();
            if (!empty($cuerpo)) {
                return $cuerpo;
            }
        }
        return $datos ?: [];
    }

    private function responderExito(Respuesta $respuesta, int $codigo, string $mensaje, mixed $datos, string $idCorrelacion): void
    {
        $respuesta->json([
            'estado'  => 'exito',
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
            'datos'   => $datos,
            'meta'    => [
                'timestamp'      => date('c'),
                'id_correlacion' => $idCorrelacion
            ]
        ], $codigo);
    }

    private function responderError(Respuesta $respuesta, int $codigo, string $mensaje, ?array $errores, string $idCorrelacion): void
    {
        $cuerpo = [
            'estado'  => 'error',
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
            'meta'    => [
                'timestamp'      => date('c'),
                'id_correlacion' => $idCorrelacion
            ]
        ];

        if ($errores !== null) {
            $cuerpo['errores'] = $errores;
        }

        $respuesta->json($cuerpo, $codigo);
    }

    private function manejarErrorInterno(Throwable $t, Respuesta $respuesta, string $idCorrelacion): void
    {
        error_log("[CasaPRO Empresas][{$idCorrelacion}] Error no controlado: " . $t->getMessage() . " en " . $t->getFile() . ":" . $t->getLine());

        $this->responderError(
            $respuesta,
            500,
            'Ha ocurrido un error interno al procesar la solicitud.',
            ['detalle' => 'Consulte el log del sistema con el identificador de correlación.'],
            $idCorrelacion
        );
    }

    private function resolverIdCorrelacion(?ContextoPeticion $contexto): string
    {
        return $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));
    }
}
