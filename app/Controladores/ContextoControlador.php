<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\DTOs\Contexto\CambiarContextoEmpresaDTO;
use App\Servicios\ContextoServicio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use App\Excepciones\ValidacionExcepcion;

/**
 * ContextoControlador — Controlador REST para gestión del contexto empresarial activo.
 *
 * Microfase: 2D
 */
class ContextoControlador extends BaseControlador
{
    private ContextoServicio $contextoServicio;

    public function __construct(?ContextoServicio $contextoServicio = null)
    {
        $this->contextoServicio = $contextoServicio ?? new ContextoServicio();
    }

    /**
     * Conmuta la empresa activa en sesión verificando autorización en tiempo real.
     */
    public function cambiarEmpresa(
        Peticion $peticion,
        Respuesta $respuesta,
        ?array $parametros = null,
        ?ContextoPeticion $contexto = null
    ): void {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');
        $usuarioId = (int) ($auth['usuario_id'] ?? 0);

        if ($usuarioId <= 0) {
            $this->json($respuesta, [
                'estado'       => 'error',
                'codigo'       => 401,
                'codigo_error' => 'NO_AUTENTICADO',
                'mensaje'      => 'Debe iniciar sesión para conmutar el contexto empresarial.'
            ], 401);
            return;
        }

        try {
            // Cargar payload de JSON o cuerpo de petición
            $datos = $peticion->obtenerJson();
            if (empty($datos)) {
                $datos = $peticion->obtenerCuerpo() ?: [];
            }

            $dto = CambiarContextoEmpresaDTO::desdePeticion($datos);

            $resultado = $this->contextoServicio->cambiarEmpresa($usuarioId, $dto->empresaId, $contexto);

            $this->json($respuesta, [
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Contexto empresarial actualizado correctamente.',
                'datos'   => $resultado
            ], 200);

        } catch (ValidacionExcepcion $e) {
            $this->json($respuesta, [
                'estado'       => 'error',
                'codigo'       => 422,
                'codigo_error' => 'DATOS_INVALIDOS',
                'mensaje'      => $e->getMessage(),
                'errores'      => $e->obtenerErrores()
            ], 422);

        } catch (RecursoNoEncontradoExcepcion $e) {
            $this->json($respuesta, [
                'estado'       => 'error',
                'codigo'       => 404,
                'codigo_error' => 'EMPRESA_NO_ENCONTRADA',
                'mensaje'      => $e->getMessage()
            ], 404);

        } catch (ReglaNegocioExcepcion $e) {
            $codigoHttp = $e->getCode() >= 400 && $e->getCode() <= 499 ? $e->getCode() : 400;
            $codigoError = match ($codigoHttp) {
                403     => 'FUERA_DE_SCOPE',
                409     => 'EMPRESA_INACTIVA',
                default => 'REGLA_NEGOCIO'
            };

            $this->json($respuesta, [
                'estado'       => 'error',
                'codigo'       => $codigoHttp,
                'codigo_error' => $codigoError,
                'mensaje'      => $e->getMessage()
            ], $codigoHttp);
        }
    }

    /**
     * Retorna la lista canónica de empresas disponibles del usuario autenticado.
     */
    public function listarEmpresasDisponibles(Peticion $peticion, Respuesta $respuesta): void
    {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');
        $usuarioId = (int) ($auth['usuario_id'] ?? 0);

        if ($usuarioId <= 0) {
            $this->json($respuesta, [
                'estado'       => 'error',
                'codigo'       => 401,
                'codigo_error' => 'NO_AUTENTICADO',
                'mensaje'      => 'Debe iniciar sesión para consultar empresas disponibles.'
            ], 401);
            return;
        }

        $resultado = $this->contextoServicio->obtenerEmpresasDisponibles($usuarioId);

        $this->json($respuesta, [
            'estado'  => 'exito',
            'codigo'  => 200,
            'mensaje' => 'Empresas disponibles obtenidas correctamente.',
            'datos'   => $resultado
        ], 200);
    }
}
