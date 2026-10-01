<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Vista;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Servicios\ProyectoServicio;
use App\Repositorios\ProyectoRepositorio;
use App\Repositorios\ProyectoPredioMatrizRepositorio;
use App\DTOs\CrearProyectoDTO;
use App\DTOs\ActualizarProyectoDTO;
use App\DTOs\CambiarEstadoProyectoDTO;
use App\DTOs\CrearPredioMatrizDTO;
use App\DTOs\ActualizarPredioMatrizDTO;
use App\DTOs\CambiarEstadoPredioMatrizDTO;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Throwable;

/**
 * ProyectoControlador — Controlador Web y API REST para Proyectos Inmobiliarios y Predios Matrices.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class ProyectoControlador extends BaseControlador
{
    private ProyectoServicio $proyectoServicio;
    private ProyectoRepositorio $proyectoRepositorio;
    private ProyectoPredioMatrizRepositorio $predioRepositorio;

    public function __construct(
        ?ProyectoServicio $proyectoServicio = null,
        ?ProyectoRepositorio $proyectoRepositorio = null,
        ?ProyectoPredioMatrizRepositorio $predioRepositorio = null
    ) {
        $this->proyectoServicio = $proyectoServicio ?? new ProyectoServicio();
        $this->proyectoRepositorio = $proyectoRepositorio ?? new ProyectoRepositorio();
        $this->predioRepositorio = $predioRepositorio ?? new ProyectoPredioMatrizRepositorio();
    }

    /**
     * Resuelve de forma segura el ID de la empresa activa en sesión.
     */
    private function resolverEmpresaActiva(): int
    {
        GestorSesion::iniciar();
        $empresaId = GestorSesion::obtener('contexto_empresa_id');
        if ($empresaId === null || (int) $empresaId <= 0) {
            throw new ReglaNegocioExcepcion('No se ha seleccionado una empresa activa en la sesión.', 409);
        }
        return (int) $empresaId;
    }

    /**
     * GET /proyectos
     * Vista principal del catálogo de Proyectos de la empresa activa.
     */
    public function index(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $personaRepo = new \App\Repositorios\PersonaRepositorio();
        $catalogos = $personaRepo->obtenerCatalogosFormulario();

        return $this->renderizar('modulos/proyectos/index', [
            'tituloPagina'   => 'Proyectos Inmobiliarios | CasaPRO',
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Catastro y Territorio', 'url' => null],
                ['texto' => 'Proyectos', 'url' => null]
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
                'js/modulos/proyectos/gestion-proyectos.js'
            ]
        ]);
    }

    /**
     * GET /proyectos/{id}
     * Ficha 360° del Proyecto inmobiliario con predios matrices y balance de áreas.
     */
    public function ficha(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $id = (int) ($parametros['id'] ?? 0);
        $empresaId = $this->resolverEmpresaActiva();

        $proyecto = $this->proyectoRepositorio->buscarPorId($id, $empresaId);
        if (!$proyecto) {
            $respuesta->redireccionar('/proyectos');
            return '';
        }

        $predios = $this->predioRepositorio->listarPorProyecto($id);
        $conciliacion = $this->proyectoServicio->evaluarConciliacionAreas($id, $empresaId);

        $personaRepo = new \App\Repositorios\PersonaRepositorio();
        $catalogos = $personaRepo->obtenerCatalogosFormulario();

        return $this->renderizar('modulos/proyectos/ficha', [
            'tituloPagina'   => "Ficha de Proyecto: {$proyecto['nombre']} | CasaPRO",
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Catastro y Territorio', 'url' => null],
                ['texto' => 'Proyectos', 'url' => Vista::url('proyectos')],
                ['texto' => $proyecto['codigo'], 'url' => null]
            ],
            'proyecto'       => $proyecto,
            'predios'        => $predios,
            'conciliacion'   => $conciliacion,
            'catalogos'      => $catalogos,
            'tokenCsrf'      => CsrfServicio::obtenerToken(),
            'cssAdicionales' => [
                'vendor/select/select2.min.css',
                'vendor/leaflet-maps/leaflet.css'
            ],
            'jsAdicionales'  => [
                'vendor/sweetalert/sweetalert.js',
                'vendor/select/select2.min.js',
                'vendor/pristine/pristine.min.js',
                'vendor/cleavejs/cleave.min.js',
                'vendor/leaflet-maps/leaflet.js',
                'js/modulos/proyectos/ficha-proyecto.js'
            ]
        ]);
    }

    /**
     * GET /api/proyectos
     * DataTables server-side de proyectos para la empresa activa.
     */
    public function listar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $empresaId = $this->resolverEmpresaActiva();

            $criterios = [
                'draw'          => (int) $peticion->obtenerConsulta('draw', 1),
                'start'         => (int) $peticion->obtenerConsulta('start', 0),
                'length'        => (int) $peticion->obtenerConsulta('length', 10),
                'search'        => (string) $peticion->obtenerConsulta('search', ''),
                'estado'        => (string) $peticion->obtenerConsulta('estado', ''),
                'tipo_proyecto' => (string) $peticion->obtenerConsulta('tipo_proyecto', ''),
                'order_by'      => (string) $peticion->obtenerConsulta('order_column', ''),
                'order_dir'     => (string) $peticion->obtenerConsulta('order_dir', 'DESC')
            ];

            $busquedaRaw = $peticion->obtenerConsulta('search');
            if (is_array($busquedaRaw) && isset($busquedaRaw['value'])) {
                $criterios['search'] = (string) $busquedaRaw['value'];
            }

            $orderRaw = $peticion->obtenerConsulta('order');
            if (is_array($orderRaw) && isset($orderRaw[0]['column'])) {
                $criterios['order_by'] = (string) $orderRaw[0]['column'];
                $criterios['order_dir'] = (string) ($orderRaw[0]['dir'] ?? 'DESC');
            }

            $resultado = $this->proyectoRepositorio->listarDataTables($empresaId, $criterios);

            $respuesta->json([
                'draw'            => $resultado['draw'],
                'recordsTotal'    => $resultado['recordsTotal'],
                'recordsFiltered' => $resultado['recordsFiltered'],
                'data'            => $resultado['data']
            ]);
        } catch (Throwable $e) {
            $respuesta->json([
                'draw'            => (int) $peticion->obtenerConsulta('draw', 1),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/proyectos/{id}
     * Detalle JSON de un proyecto específico.
     */
    public function obtener(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $proyecto = $this->proyectoRepositorio->buscarPorId($id, $empresaId);

            if (!$proyecto) {
                $respuesta->json([
                    'estado'        => 'error',
                    'codigo'        => 404,
                    'mensaje'       => 'El proyecto solicitado no existe en la empresa activa.',
                    'id_correlacion'=> $idCorrelacion
                ], 404);
                return;
            }

            $balance = $this->predioRepositorio->calcularBalanceAreas($id);
            $proyecto['balance_predios'] = $balance;

            $respuesta->json([
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => $proyecto
            ]);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'        => 'error',
                'codigo'        => 500,
                'mensaje'       => 'Error interno al consultar proyecto: ' . $e->getMessage(),
                'id_correlacion'=> $idCorrelacion
            ], 500);
        }
    }

    /**
     * POST /api/proyectos
     * Alta de nuevo Proyecto.
     */
    public function crear(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $dto = CrearProyectoDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $creado = $this->proyectoServicio->crear($dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Proyecto inmobiliario creado exitosamente.',
                'datos'   => $creado
            ], 201);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (ReglaNegocioExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $e->getCode() ?: 409,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error inesperado al crear proyecto: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * PUT /api/proyectos/{id}
     * Actualización de proyecto existente.
     */
    public function actualizar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $dto = ActualizarProyectoDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $actualizado = $this->proyectoServicio->actualizar($id, $dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Proyecto actualizado exitosamente.',
                'datos'   => $actualizado
            ]);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $e->getCode() ?: 409,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error inesperado al actualizar proyecto: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * PATCH /api/proyectos/{id}/estado
     * Conmutación de estado del proyecto.
     */
    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $dto = CambiarEstadoProyectoDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $resultado = $this->proyectoServicio->cambiarEstado($id, $dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => "Estado del proyecto actualizado a {$dto->estado}.",
                'datos'   => $resultado
            ]);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al cambiar estado de proyecto: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * GET /api/proyectos/{id}/predios
     * Listado de Predios Matrices de un Proyecto.
     */
    public function listarPredios(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $proyectoId = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $proyecto = $this->proyectoRepositorio->buscarPorId($proyectoId, $empresaId);

            if (!$proyecto) {
                $respuesta->json([
                    'estado'         => 'error',
                    'codigo'         => 404,
                    'mensaje'        => 'El proyecto solicitado no existe en la empresa activa.',
                    'id_correlacion' => $idCorrelacion
                ], 404);
                return;
            }

            $predios = $this->predioRepositorio->listarPorProyecto($proyectoId);
            $balance = $this->predioRepositorio->calcularBalanceAreas($proyectoId);

            $respuesta->json([
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => [
                    'predios' => $predios,
                    'balance' => $balance
                ]
            ]);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al listar predios matrices: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * POST /api/proyectos/{id}/predios
     * Alta de un nuevo Predio Matriz en el Proyecto.
     */
    public function crearPredio(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $proyectoId = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $cuerpo['proyecto_id'] = $proyectoId;

            $dto = CrearPredioMatrizDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $creado = $this->proyectoServicio->crearPredioMatriz($dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Predio matriz incorporado exitosamente al proyecto.',
                'datos'   => $creado
            ], 201);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $e->getCode() ?: 409,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al crear predio matriz: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * PUT /api/predios/{id}
     * Actualización de un Predio Matriz.
     */
    public function actualizarPredio(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $dto = ActualizarPredioMatrizDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $actualizado = $this->proyectoServicio->actualizarPredioMatriz($id, $dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Predio matriz actualizado exitosamente.',
                'datos'   => $actualizado
            ]);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $e->getCode() ?: 403,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], $e->getCode() ?: 403);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al actualizar predio matriz: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * PATCH /api/predios/{id}/estado
     * Conmutación de estado de un Predio Matriz.
     */
    public function cambiarEstadoPredio(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $id = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverOperadorId();

            $cuerpo = $peticion->obtenerCuerpo();
            $dto = CambiarEstadoPredioMatrizDTO::desdeArray($cuerpo);

            $ctx = $contexto ?? new ContextoPeticion($idCorrelacion, $peticion->obtenerIp(), $peticion->obtenerAgenteUsuario(), 'WEB', $operadorId, $empresaId, 'EMPRESA');
            $resultado = $this->proyectoServicio->cambiarEstadoPredioMatriz($id, $dto, $empresaId, $operadorId, $ctx);

            $respuesta->json([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => "Estado del predio matriz actualizado a {$dto->estado}.",
                'datos'   => $resultado
            ]);
        } catch (ValidacionExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 422,
                'mensaje'        => $e->getMessage(),
                'errores'        => $e->obtenerErrores(),
                'id_correlacion' => $idCorrelacion
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $e->getCode() ?: 403,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], $e->getCode() ?: 403);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al cambiar estado de predio matriz: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    /**
     * GET /api/proyectos/{id}/conciliacion-areas
     * Consulta el semáforo y evaluación técnica de balance de áreas.
     */
    public function conciliarAreas(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);
        $proyectoId = (int) ($parametros['id'] ?? 0);

        try {
            $empresaId = $this->resolverEmpresaActiva();
            $evaluacion = $this->proyectoServicio->evaluarConciliacionAreas($proyectoId, $empresaId);

            $respuesta->json([
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => $evaluacion
            ]);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 404,
                'mensaje'        => $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 404);
        } catch (Throwable $e) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 500,
                'mensaje'        => 'Error al evaluar conciliación de áreas: ' . $e->getMessage(),
                'id_correlacion' => $idCorrelacion
            ], 500);
        }
    }

    private function resolverOperadorId(): int
    {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');
        return (int) ($auth['usuario_id'] ?? 1);
    }
}
