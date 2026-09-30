<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Core\Vista;
use App\DTOs\AutenticarUsuarioDTO;
use App\Servicios\AutenticacionServicio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use Throwable;

/**
 * AutenticacionControlador — Maneja los flujos de presentación y procesamiento
 * de inicio y cierre de sesión seguro en CasaPRO.
 */
class AutenticacionControlador extends BaseControlador
{
    private AutenticacionServicio $autenticacionServicio;

    public function __construct(?AutenticacionServicio $autenticacionServicio = null)
    {
        $this->autenticacionServicio = $autenticacionServicio ?? new AutenticacionServicio();
    }

    /**
     * Muestra la pantalla institucional de inicio de sesión de Alina (sign_in.html).
     * Si ya existe una sesión autenticada válida, redirige automáticamente a /inicio.
     */
    public function mostrarLogin(
        Peticion $peticion,
        Respuesta $respuesta,
        array $parametros = [],
        ?ContextoPeticion $contexto = null
    ): void {
        GestorSesion::iniciar();

        // Si ya está autenticado, no tiene sentido mostrar login
        $auth = GestorSesion::obtener('auth');
        if (is_array($auth) && !empty($auth['usuario_id'])) {
            $respuesta->redireccionar('/inicio');
            return;
        }

        // Generar/obtener token CSRF para el formulario pre-autenticación
        $tokenCsrf = CsrfServicio::obtenerToken();
        $mensajeError = GestorSesion::obtenerFlash('error');
        $mensajeExito = GestorSesion::obtenerFlash('exito');

        $html = Vista::renderizar('modulos/autenticacion/login', [
            'tituloPagina'   => 'Iniciar Sesión | CasaPRO',
            'tokenCsrf'      => $tokenCsrf,
            'mensajeError'   => $mensajeError,
            'mensajeExito'   => $mensajeExito,
        ], null);

        $respuesta->establecerCuerpo($html);
        $respuesta->enviar();
    }

    /**
     * Procesa la solicitud de inicio de sesión mediante credenciales.
     */
    public function iniciarSesion(
        Peticion $peticion,
        Respuesta $respuesta,
        array $parametros = [],
        ?ContextoPeticion $contexto = null
    ): void {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $contexto->obtenerIdCorrelacion();

        try {
            $datos = $peticion->obtenerCuerpo();
            $dto = AutenticarUsuarioDTO::desdeArray($datos);

            $auth = $this->autenticacionServicio->autenticar($dto, $contexto);

            if ($peticion->esAjax()) {
                $respuesta->json([
                    'estado'         => 'exito',
                    'codigo'         => 200,
                    'mensaje'        => 'Inicio de sesión exitoso.',
                    'datos'          => [
                        'redireccion'     => '/inicio',
                        'nombre_usuario'  => $auth['nombre_usuario'],
                        'nombre_completo' => $auth['nombre_completo'],
                    ],
                    'id_correlacion' => $idCorrelacion
                ], 200);
                return;
            }

            $respuesta->redireccionar('/inicio');

        } catch (ValidacionExcepcion $ve) {
            if ($peticion->esAjax()) {
                $respuesta->json([
                    'estado'         => 'error',
                    'codigo'         => 422,
                    'mensaje'        => $ve->getMessage(),
                    'errores'        => $ve->obtenerErrores(),
                    'id_correlacion' => $idCorrelacion
                ], 422);
                return;
            }

            GestorSesion::iniciar();
            GestorSesion::establecerFlash('error', $ve->getMessage());
            $respuesta->redireccionar('/login');

        } catch (ReglaNegocioExcepcion $rne) {
            // Respuesta uniforme y opaca anti-enumeración (HTTP 401)
            if ($peticion->esAjax()) {
                $respuesta->json([
                    'estado'         => 'error',
                    'codigo'         => $rne->getCode() ?: 401,
                    'mensaje'        => $rne->getMessage(),
                    'id_correlacion' => $idCorrelacion
                ], $rne->getCode() ?: 401);
                return;
            }

            GestorSesion::iniciar();
            GestorSesion::establecerFlash('error', $rne->getMessage());
            $respuesta->redireccionar('/login');

        } catch (Throwable $t) {
            error_log("[LOGIN-ERROR] [{$idCorrelacion}] " . $t->getMessage());

            if ($peticion->esAjax()) {
                $respuesta->json([
                    'estado'         => 'error',
                    'codigo'         => 500,
                    'mensaje'        => 'Ocurrió un error inesperado al procesar la autenticación.',
                    'id_correlacion' => $idCorrelacion
                ], 500);
                return;
            }

            GestorSesion::iniciar();
            GestorSesion::establecerFlash('error', 'Ocurrió un error inesperado al procesar el acceso.');
            $respuesta->redireccionar('/login');
        }
    }

    /**
     * Cierra la sesión activa del usuario y redirige al login.
     */
    public function cerrarSesion(
        Peticion $peticion,
        Respuesta $respuesta,
        array $parametros = [],
        ?ContextoPeticion $contexto = null
    ): void {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $this->autenticacionServicio->cerrarSesion($contexto);

        if ($peticion->esAjax()) {
            $respuesta->json([
                'estado'         => 'exito',
                'codigo'         => 200,
                'mensaje'        => 'Sesión finalizada exitosamente.',
                'datos'          => ['redireccion' => '/login'],
                'id_correlacion' => $contexto->obtenerIdCorrelacion()
            ], 200);
            return;
        }

        GestorSesion::iniciar();
        GestorSesion::establecerFlash('exito', 'Ha cerrado sesión correctamente.');
        $respuesta->redireccionar('/login');
    }
}
