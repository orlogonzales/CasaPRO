<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Vista;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\ProveedorConexion;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\Repositorios\PersonaRepositorio;
use App\DTOs\CrearPersonaDTO;
use App\DTOs\ActualizarPersonaDTO;
use App\DTOs\CambiarEstadoPersonaDTO;
use App\DTOs\ConsultaDataTablesDTO;
use App\DTOs\ConsultaDocumentoDTO;
use App\Servicios\ConsultaDocumentoServicio;
use App\Excepciones\PeticionIncorrectaExcepcion;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Throwable;

/**
 * PersonaControlador — Controlador para la gestión integral de identidad (Personas).
 *
 * Orquesta la recepción de solicitudes HTTP para la vista interactiva Alina y la API REST,
 * delegación en PersonaServicio y emisión de respuestas normalizadas según docs/08-API-Y-CONTRATOS.md.
 */
class PersonaControlador extends BaseControlador
{
    private PersonaServicio $personaServicio;
    private PersonaRepositorio $personaRepositorio;
    private ConsultaDocumentoServicio $consultaDocumentoServicio;

    public function __construct(
        ?PersonaServicio $personaServicio = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?ConsultaDocumentoServicio $consultaDocumentoServicio = null
    ) {
        $conexion = new ProveedorConexion();
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio($conexion);
        $this->personaServicio = $personaServicio ?? new PersonaServicio(
            $conexion,
            $this->personaRepositorio,
            new AuditoriaServicio()
        );
        $this->consultaDocumentoServicio = $consultaDocumentoServicio ?? new ConsultaDocumentoServicio(
            $conexion,
            $this->personaRepositorio
        );
    }

    /**
     * GET /personas
     * Renderiza la vista principal del directorio de personas con DataTables server-side.
     */
    public function index(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $catalogos = $this->personaRepositorio->obtenerCatalogosFormulario();

        return $this->renderizar('modulos/personas/index', [
            'tituloPagina' => 'Directorio de Personas | CasaPRO Inmobiliario',
            'migaPan' => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Identidad y Personas', 'url' => null],
                ['texto' => 'Directorio de Personas', 'url' => null]
            ],
            'catalogos' => $catalogos,
            'cssAdicionales' => [
                'vendor/datatable/jquery.dataTables.min.css',
                'vendor/select/select2.min.css',
                'vendor/flatpickr/flatpickr.min.css'
            ],
            'jsAdicionales' => [
                'vendor/datatable/jquery.dataTables.min.js',
                'vendor/datatable/dataTables.responsive.min.js',
                'vendor/sweetalert/sweetalert.js',
                'vendor/cleavejs/cleave.min.js',
                'vendor/select/select2.min.js',
                'vendor/flatpickr/flatpickr.js',
                'vendor/pristine/pristine.min.js',
                'js/modulos/personas/consulta-documento.js',
                'js/modulos/personas/formulario-persona.js',
                'js/modulos/personas/listado-personas.js'
            ]
        ]);
    }

    /**
     * GET /api/personas
     * Consulta paginada server-side compatible con el protocolo DataTables.
     */
    public function listar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $parametrosConsulta = $_GET;
            $dto = ConsultaDataTablesDTO::desdeArray($parametrosConsulta);
            $resultado = $this->personaServicio->listarDataTables($dto);

            $respuesta->json($resultado, 200);
        } catch (PeticionIncorrectaExcepcion $e) {
            $this->responderError($respuesta, 400, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/personas/{id}
     * Obtiene el detalle compuesto 360 de una persona (datos civiles, satélites, colecciones).
     */
    public function obtener(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = $this->validarId($parametros['id'] ?? null);
            $datos = $this->personaServicio->obtenerPorId($id);

            $this->responderExito($respuesta, 200, 'Ficha 360 de identidad recuperada exitosamente.', $datos, $idCorrelacion);
        } catch (PeticionIncorrectaExcepcion $e) {
            $this->responderError($respuesta, 400, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->responderError($respuesta, 404, $e->getMessage(), null, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/personas
     * Registra de forma atómica una nueva Persona (Natural o Jurídica) con sus datos iniciales.
     */
    public function crear(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();

        try {
            $cuerpo = $this->extraerDatosCuerpo($peticion);
            $dto = CrearPersonaDTO::desdeArray($cuerpo);
            $resultado = $this->personaServicio->crear($dto, $contexto);

            $this->responderExito(
                $respuesta,
                201,
                'Persona registrada exitosamente en el sistema de identidad.',
                $resultado,
                $idCorrelacion
            );
        } catch (PeticionIncorrectaExcepcion $e) {
            $this->responderError($respuesta, 400, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $codigo = ($e->getCode() >= 400 && $e->getCode() <= 499) ? (int) $e->getCode() : 422;
            $this->responderError($respuesta, $codigo, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PUT /api/personas/{id}
     * Actualiza integralmente los datos editables de la ficha de una persona.
     */
    public function actualizar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();

        try {
            $id = $this->validarId($parametros['id'] ?? null);
            $cuerpo = $this->extraerDatosCuerpo($peticion);
            $dto = ActualizarPersonaDTO::desdeArray($cuerpo);
            $resultado = $this->personaServicio->actualizar($id, $dto, $contexto);

            $this->responderExito(
                $respuesta,
                200,
                'Ficha de persona actualizada exitosamente.',
                $resultado,
                $idCorrelacion
            );
        } catch (PeticionIncorrectaExcepcion $e) {
            $this->responderError($respuesta, 400, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->responderError($respuesta, 404, $e->getMessage(), null, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $codigo = ($e->getCode() >= 400 && $e->getCode() <= 499) ? (int) $e->getCode() : 422;
            $this->responderError($respuesta, $codigo, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PATCH /api/personas/{id}/estado
     * Transición controlada de estado (ACTIVO <-> INACTIVO) con motivo auditable.
     */
    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();

        try {
            $id = $this->validarId($parametros['id'] ?? null);
            $cuerpo = $this->extraerDatosCuerpo($peticion);
            $dto = CambiarEstadoPersonaDTO::desdeArray($cuerpo);
            $resultado = $this->personaServicio->cambiarEstado($id, $dto, $contexto);

            $this->responderExito(
                $respuesta,
                200,
                $resultado['mensaje'],
                $resultado,
                $idCorrelacion
            );
        } catch (PeticionIncorrectaExcepcion $e) {
            $this->responderError($respuesta, 400, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->responderError($respuesta, 404, $e->getMessage(), null, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $codigo = ($e->getCode() >= 400 && $e->getCode() <= 499) ? (int) $e->getCode() : 422;
            $this->responderError($respuesta, $codigo, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/personas/consultar-documento
     * Consulta asistida de identidad (DNI/RUC) con anti-duplicidad local previa y fallback manual.
     */
    public function consultarDocumento(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $datos = $this->extraerDatosCuerpo($peticion);
            $dto = ConsultaDocumentoDTO::desdeArray($datos);
            $resultado = $this->consultaDocumentoServicio->consultar($dto);

            $this->responderExito(
                $respuesta,
                200,
                $resultado['mensaje'],
                $resultado,
                $idCorrelacion
            );
        } catch (ValidacionExcepcion $ve) {
            $this->responderError($respuesta, 422, $ve->getMessage(), $ve->obtenerErrores(), $idCorrelacion);
        } catch (PeticionIncorrectaExcepcion $pie) {
            $this->responderError($respuesta, 400, $pie->getMessage(), null, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/ubigeo/provincias
     * Retorna provincias pertenecientes a un departamento.
     */
    public function obtenerProvincias(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $departamentoId = isset($_GET['departamento_id']) ? (int) $_GET['departamento_id'] : 0;
            if ($departamentoId <= 0) {
                $this->responderError($respuesta, 422, 'El parámetro departamento_id es obligatorio.', ['departamento_id' => ['Valor requerido.']], $idCorrelacion);
                return;
            }

            $provincias = $this->personaRepositorio->obtenerProvinciasPorDepartamento($departamentoId);
            $this->responderExito($respuesta, 200, 'Provincias obtenidas correctamente.', $provincias, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/ubigeo/distritos
     * Retorna distritos pertenecientes a una provincia.
     */
    public function obtenerDistritos(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $provinciaId = isset($_GET['provincia_id']) ? (int) $_GET['provincia_id'] : 0;
            if ($provinciaId <= 0) {
                $this->responderError($respuesta, 422, 'El parámetro provincia_id es obligatorio.', ['provincia_id' => ['Valor requerido.']], $idCorrelacion);
                return;
            }

            $distritos = $this->personaRepositorio->obtenerDistritosPorProvincia($provinciaId);
            $this->responderExito($respuesta, 200, 'Distritos obtenidos correctamente.', $distritos, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    // -------------------------------------------------------------------------
    // UTILIDADES PRIVADAS
    // -------------------------------------------------------------------------

    private function validarId(mixed $idCandidato): int
    {
        if ($idCandidato === null || !is_numeric($idCandidato)) {
            throw new PeticionIncorrectaExcepcion("El identificador de persona especificado debe ser un número entero positivo mayor a cero.", 400);
        }

        $idInt = (int) $idCandidato;
        if ($idInt <= 0 || (string) $idInt !== (string) $idCandidato) {
            throw new PeticionIncorrectaExcepcion("El identificador de persona especificado ('{$idCandidato}') es inválido.", 400);
        }

        return $idInt;
    }

    private function extraerDatosCuerpo(Peticion $peticion): array
    {
        $cuerpoCrudo = file_get_contents('php://input');
        if (!empty($cuerpoCrudo)) {
            $decodificado = json_decode($cuerpoCrudo, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $tipoContenido = $peticion->obtenerCabecera('Content-Type', '');
                if (str_contains($tipoContenido, 'application/x-www-form-urlencoded')) {
                    parse_str($cuerpoCrudo, $datosParsed);
                    if (is_array($datosParsed)) {
                        return $datosParsed;
                    }
                }
                throw new PeticionIncorrectaExcepcion('El cuerpo de la petición no contiene una estructura JSON válida: ' . json_last_error_msg(), 400);
            }
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        $datos = $peticion->obtenerJson();
        if (empty($datos)) {
            $todos = $peticion->obtenerTodosLosParametros();
            if (!empty($todos)) {
                return $todos;
            }
        }

        return $datos;
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
        $respuesta->json([
            'estado'         => 'error',
            'codigo'         => $codigo,
            'mensaje'        => $mensaje,
            'errores'        => $errores,
            'datos'          => null,
            'id_correlacion' => $idCorrelacion
        ], $codigo);
    }

    private function manejarErrorInterno(Throwable $t, Respuesta $respuesta, string $idCorrelacion): void
    {
        error_log(sprintf(
            "[%s] [%s] ERROR 500 en PersonaControlador: %s en %s:%d\nTraza:\n%s",
            date('Y-m-d H:i:s'),
            $idCorrelacion,
            $t->getMessage(),
            $t->getFile(),
            $t->getLine(),
            $t->getTraceAsString()
        ));

        $this->responderError(
            $respuesta,
            500,
            'Ocurrió un error inesperado al procesar la solicitud de persona en el servidor.',
            null,
            $idCorrelacion
        );
    }

    private function resolverIdCorrelacion(?ContextoPeticion $contexto): string
    {
        return $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(8))));
    }
}
