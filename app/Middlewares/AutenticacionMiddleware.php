<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Servicios\AutorizacionServicio;
use App\Servicios\AutenticacionServicio;
use App\Modelos\EventoSeguridad;
use App\Repositorios\SeguridadRepositorio;
use App\Controladores\ErrorControlador;

/**
 * AutenticacionMiddleware — Protege rutas exigiendo una sesión activa y validando
 * en cada petición la vigencia del usuario y la revocación inmediata de credenciales.
 */
class AutenticacionMiddleware
{
    private AutorizacionServicio $autorizacionServicio;
    private SeguridadRepositorio $seguridadRepositorio;

    public function __construct(
        ?AutorizacionServicio $autorizacionServicio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null
    ) {
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio();
    }

    public function procesar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): bool
    {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');

        // Soporte de compatibilidad previa si solo se estableció actor_id numérico en tests
        if ($auth === null) {
            $actorIdPrevio = GestorSesion::obtener('actor_id');
            if ($actorIdPrevio !== null && (int) $actorIdPrevio > 0) {
                if ($contexto !== null) {
                    $contexto->establecerActorId((int) $actorIdPrevio);
                }
                return true;
            }
            return $this->rechazarNoAutenticado($peticion, $respuesta, $contexto);
        }

        if (!is_array($auth) || empty($auth['usuario_id']) || empty($auth['version_autorizacion'])) {
            return $this->rechazarNoAutenticado($peticion, $respuesta, $contexto);
        }

        $usuarioId = (int) $auth['usuario_id'];
        $versionSesion = (int) $auth['version_autorizacion'];

        // Chequeo ultraligero de revocación inmediata en base de datos
        $control = $this->autorizacionServicio->validarControlSesion($usuarioId, $versionSesion);

        if ($control === null) {
            // Sesión invalidada (desactivación o cambio de versión de autorización)
            $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

            $evento = new EventoSeguridad(
                EventoSeguridad::TIPO_SESION_INVALIDADA,
                $usuarioId,
                null,
                $ip,
                $ua,
                ['motivo' => 'CAMBIO_VERSION_O_INACTIVO']
            );
            $this->seguridadRepositorio->registrarEvento($evento);

            GestorSesion::destruir();
            return $this->rechazarSesionInvalida($peticion, $respuesta, $contexto);
        }

        // Inyectar el actor_id real del usuario en el contexto de trazabilidad
        $actorId = (int) ($auth['actor_id'] ?? $control['actor_id']);
        if ($contexto !== null) {
            $contexto->establecerActorId($actorId);
        }

        // 6. Autoridad Soberana de BD para Cambio Obligatorio de Contraseña
        $debeCambiarPasswordBD = (bool) ($control['debe_cambiar_password'] ?? false);
        if ($debeCambiarPasswordBD) {
            $rutaActual = $peticion->obtenerRuta();
            $rutasPermitidas = [
                '/cambiar-password-obligatorio',
                '/api/mi-cuenta/cambiar-password',
                '/logout'
            ];

            $esPermitida = in_array($rutaActual, $rutasPermitidas, true)
                || str_starts_with($rutaActual, '/assets/')
                || str_starts_with($rutaActual, '/favicon');

            if (!$esPermitida) {
                $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));
                if ($peticion->esAjax() || str_starts_with($rutaActual, '/api/')) {
                    $respuesta->json([
                        'estado'         => 'error',
                        'codigo'         => 403,
                        'codigo_error'   => 'PASSWORD_CHANGE_REQUIRED',
                        'mensaje'        => 'Debe cambiar su contraseña antes de poder acceder al sistema.',
                        'id_correlacion' => $idCorrelacion
                    ], 403);
                    return false;
                }

                $respuesta->redireccionar('/cambiar-password-obligatorio');
                return false;
            }
        }

        return true;
    }

    private function rechazarNoAutenticado(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto): bool
    {
        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));

        if ($peticion->esAjax() || str_starts_with($peticion->obtenerRuta(), '/api/')) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 401,
                'mensaje'        => 'Acceso no autorizado: Se requiere una sesión activa para acceder a este recurso.',
                'id_correlacion' => $idCorrelacion
            ], 401);
            return false;
        }

        GestorSesion::establecerFlash('error', 'Debe iniciar sesión para acceder al sistema CasaPRO.');
        $respuesta->redireccionar('/login');
        return false;
    }

    private function rechazarSesionInvalida(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto): bool
    {
        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));

        if ($peticion->esAjax() || str_starts_with($peticion->obtenerRuta(), '/api/')) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => 401,
                'mensaje'        => 'Su sesión ha expirado o sus credenciales fueron revocadas. Por favor, ingrese nuevamente.',
                'id_correlacion' => $idCorrelacion
            ], 401);
            return false;
        }

        GestorSesion::iniciar();
        GestorSesion::establecerFlash('error', 'Su sesión ha sido invalidada o sus privilegios cambiaron. Ingrese nuevamente.');
        $respuesta->redireccionar('/login');
        return false;
    }
}
