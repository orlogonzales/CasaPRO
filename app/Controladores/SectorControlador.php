<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\DTOs\CrearSectorDTO;
use App\DTOs\ActualizarSectorDTO;
use App\DTOs\CambiarEstadoSectorDTO;
use App\DTOs\AjustarPrecioSectorDTO;
use App\Servicios\SectorServicio;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Throwable;

/**
 * SectorControlador — Controlador API REST para Sectores Urbanísticos, Histórico de Precios y Balance.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class SectorControlador extends BaseControlador
{
    private SectorServicio $sectorServicio;

    public function __construct(?SectorServicio $sectorServicio = null)
    {
        $this->sectorServicio = $sectorServicio ?? new SectorServicio();
    }

    private function resolverEmpresaActiva(): int
    {
        GestorSesion::iniciar();
        $empresaId = (int) (GestorSesion::obtener('contexto_empresa_id') ?? 0);
        if ($empresaId <= 0) {
            throw new ReglaNegocioExcepcion('No existe una empresa corporativa activa en la sesión.', 403);
        }
        return $empresaId;
    }

    private function resolverActorOperador(): int
    {
        GestorSesion::iniciar();
        $actorId = (int) (GestorSesion::obtener('actor_id') ?? 0);
        if ($actorId <= 0) {
            $auth = GestorSesion::obtener('auth') ?? [];
            $actorId = (int) ($auth['actor_id'] ?? 0);
        }
        return $actorId > 0 ? $actorId : 1;
    }

    /**
     * GET /api/proyectos/{id}/sectores
     */
    public function listarPorProyecto(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $proyectoId = (int) ($parametros['id'] ?? 0);
            if ($proyectoId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de proyecto inválido.', 422);
            }

            $sectores = $this->sectorServicio->listarPorProyecto($proyectoId, $empresaId);
            $balance = $this->sectorServicio->evaluarBalanceAreas($proyectoId);

            $this->json($respuesta, [
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => [
                    'sectores' => $sectores,
                    'balance'  => $balance,
                    'total'    => count($sectores),
                ]
            ], 200);

        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 400,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al consultar sectores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/proyectos/{id}/balance-areas
     */
    public function balanceAreas(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $proyectoId = (int) ($parametros['id'] ?? 0);
            if ($proyectoId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de proyecto inválido.', 422);
            }

            // Validar que el proyecto pertenezca a la empresa activa
            $this->sectorServicio->listarPorProyecto($proyectoId, $empresaId);
            $balance = $this->sectorServicio->evaluarBalanceAreas($proyectoId);

            $this->json($respuesta, [
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => $balance
            ], 200);

        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 400,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al evaluar balance de áreas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/proyectos/{id}/sectores
     */
    public function crear(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverActorOperador();
            $proyectoId = (int) ($parametros['id'] ?? 0);
            if ($proyectoId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de proyecto inválido en la ruta.', 422);
            }

            $datos = $peticion->obtenerJson() ?: $peticion->obtenerCuerpo();
            $datos['proyecto_id'] = $proyectoId;

            $dto = CrearSectorDTO::desdeArray($datos);
            $ctx = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);

            $resultado = $this->sectorServicio->crear($dto, $empresaId, $operadorId, $ctx);

            $this->json($respuesta, [
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Sector urbanístico incorporado exitosamente con precio base inicial.',
                'datos'   => $resultado
            ], 201);

        } catch (ValidacionExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 409,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al crear sector: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/sectores/{id}
     */
    public function obtener(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $sectorId = (int) ($parametros['id'] ?? 0);
            if ($sectorId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de sector inválido.', 422);
            }

            $sector = $this->sectorServicio->obtenerPorId($sectorId, $empresaId);

            $this->json($respuesta, [
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => $sector
            ], 200);

        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 400,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al consultar sector: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/sectores/{id}
     */
    public function actualizar(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverActorOperador();
            $sectorId = (int) ($parametros['id'] ?? 0);
            if ($sectorId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de sector inválido.', 422);
            }

            $datos = $peticion->obtenerJson() ?: $peticion->obtenerCuerpo();
            $dto = ActualizarSectorDTO::desdeArray($datos);
            $ctx = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);

            $resultado = $this->sectorServicio->actualizar($sectorId, $dto, $empresaId, $operadorId, $ctx);

            $this->json($respuesta, [
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Sector urbanístico actualizado exitosamente.',
                'datos'   => $resultado
            ], 200);

        } catch (ValidacionExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 409,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al actualizar sector: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/sectores/{id}/estado
     */
    public function cambiarEstado(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverActorOperador();
            $sectorId = (int) ($parametros['id'] ?? 0);
            if ($sectorId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de sector inválido.', 422);
            }

            $datos = $peticion->obtenerJson() ?: $peticion->obtenerCuerpo();
            $dto = CambiarEstadoSectorDTO::desdeArray($datos);
            $ctx = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);

            $resultado = $this->sectorServicio->cambiarEstado($sectorId, $dto, $empresaId, $operadorId, $ctx);

            $this->json($respuesta, [
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => "Estado del sector actualizado a '{$resultado['estado_nuevo']}'.",
                'datos'   => $resultado
            ], 200);

        } catch (ValidacionExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 409,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al cambiar estado de sector: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/sectores/{id}/precios
     */
    public function listarPrecios(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $sectorId = (int) ($parametros['id'] ?? 0);
            if ($sectorId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de sector inválido.', 422);
            }

            $sector = $this->sectorServicio->obtenerPorId($sectorId, $empresaId);
            $precios = $this->sectorServicio->listarHistorialPrecios($sectorId, $empresaId);

            $this->json($respuesta, [
                'estado' => 'exito',
                'codigo' => 200,
                'datos'  => [
                    'sector'  => [
                        'id'              => $sector['id'],
                        'codigo'          => $sector['codigo'],
                        'nombre'          => $sector['nombre'],
                        'proyecto_moneda' => $sector['proyecto_moneda'],
                    ],
                    'precios' => $precios,
                    'total'   => count($precios),
                ]
            ], 200);

        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 400,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 400);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al listar precios del sector: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/sectores/{id}/precios
     */
    public function ajustarPrecio(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        try {
            $empresaId = $this->resolverEmpresaActiva();
            $operadorId = $this->resolverActorOperador();
            $sectorId = (int) ($parametros['id'] ?? 0);
            if ($sectorId <= 0) {
                throw new ReglaNegocioExcepcion('Identificador de sector inválido.', 422);
            }

            $datos = $peticion->obtenerJson() ?: $peticion->obtenerCuerpo();
            $dto = AjustarPrecioSectorDTO::desdeArray($datos);
            $ctx = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);

            $resultado = $this->sectorServicio->ajustarPrecio($sectorId, $dto, $empresaId, $operadorId, $ctx);

            $this->json($respuesta, [
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Nuevo precio base por m² fijado exitosamente.',
                'datos'   => $resultado
            ], 201);

        } catch (ValidacionExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $e->getMessage(),
                'errores' => $e->obtenerErrores()
            ], 422);
        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $e->getMessage()
            ], 404);
        } catch (ReglaNegocioExcepcion $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => $e->getCode() ?: 409,
                'mensaje' => $e->getMessage()
            ], $e->getCode() ?: 409);
        } catch (Throwable $e) {
            $this->json($respuesta, [
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al fijar nuevo precio del sector: ' . $e->getMessage()
            ], 500);
        }
    }
}
