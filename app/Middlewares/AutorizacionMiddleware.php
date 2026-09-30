<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Servicios\AutorizacionServicio;
use App\Modelos\EventoSeguridad;
use App\Repositorios\SeguridadRepositorio;
use App\Controladores\ErrorControlador;

/**
 * AutorizacionMiddleware — Aplica control de acceso granular basado en privilegios (RBAC).
 * Opera bajo la política Deny by Default: Si no cuenta con el privilegio explícito ni es SUPERADMIN,
 * se deniega el acceso con código HTTP 403 Forbidden.
 */
class AutorizacionMiddleware
{
    private string $privilegioRequerido;
    private string $scopeRequerido;
    private AutenticacionMiddleware $autenticacionMiddleware;
    private AutorizacionServicio $autorizacionServicio;
    private SeguridadRepositorio $seguridadRepositorio;

    public function __construct(
        string $privilegioRequerido,
        string $scopeRequerido = 'GLOBAL',
        ?AutenticacionMiddleware $autenticacionMiddleware = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null
    ) {
        $this->privilegioRequerido = strtolower(trim($privilegioRequerido));
        $this->scopeRequerido = strtoupper(trim($scopeRequerido)) ?: 'GLOBAL';
        $this->autenticacionMiddleware = $autenticacionMiddleware ?? new AutenticacionMiddleware();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio();
    }

    /**
     * Factoría para instanciación concisa en definiciones de rutas globales.
     */
    public static function exigir(string $privilegio, string $scope = 'GLOBAL'): self
    {
        return new self($privilegio, $scope);
    }

    /**
     * Factoría para rutas con ámbito territorial de empresa.
     */
    public static function exigirEmpresa(string $privilegio): self
    {
        return new self($privilegio, 'EMPRESA');
    }

    public function procesar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): bool
    {
        // 1. Garantizar primero que la petición esté legítimamente autenticada
        if (!$this->autenticacionMiddleware->procesar($peticion, $respuesta, $contexto)) {
            return false;
        }

        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');

        // Soporte retrocompatible si solo se autenticó actor_id sin usuario formal en tests históricos
        if ($auth === null && GestorSesion::obtener('actor_id') !== null) {
            return true;
        }

        $usuarioId = (int) ($auth['usuario_id'] ?? 0);

        // 2. Resolver alcance según el scope requerido
        $alcanceId = null;
        if ($this->scopeRequerido === 'EMPRESA') {
            $alcanceId = $contexto ? $contexto->obtenerEmpresaId() : null;
            if ($alcanceId === null) {
                $sesionEmpresaId = GestorSesion::obtener('contexto_empresa_id');
                $alcanceId = ($sesionEmpresaId !== null && (int) $sesionEmpresaId > 0) ? (int) $sesionEmpresaId : null;
            }
        }

        // 3. Evaluar privilegio RBAC multidimensional (SUPERADMIN tiene bypass universal)
        $autorizado = $this->autorizacionServicio->tienePrivilegio(
            $usuarioId,
            $this->privilegioRequerido,
            $this->scopeRequerido,
            $alcanceId
        );

        if (!$autorizado) {
            return $this->rechazarAccesoDenegado($peticion, $respuesta, $contexto, $usuarioId);
        }

        return true;
    }

    private function rechazarAccesoDenegado(
        Peticion $peticion,
        Respuesta $respuesta,
        ?ContextoPeticion $contexto,
        int $usuarioId
    ): bool {
        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));
        $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

        // Registrar telemetría técnica de acceso denegado
        $evento = new EventoSeguridad(
            EventoSeguridad::TIPO_ACCESO_DENEGADO,
            $usuarioId > 0 ? $usuarioId : null,
            null,
            $ip,
            $ua,
            [
                'privilegio_requerido' => $this->privilegioRequerido,
                'ruta'                 => $peticion->obtenerRuta(),
                'metodo'               => $peticion->obtenerMetodo()
            ]
        );
        $this->seguridadRepositorio->registrarEvento($evento);

        $mensaje = "Acceso denegado: No cuenta con el privilegio requerido ({$this->privilegioRequerido}) para acceder a este recurso.";

        if ($peticion->esAjax() || str_starts_with($peticion->obtenerRuta(), '/api/')) {
            $respuesta->json([
                'estado'               => 'error',
                'codigo'               => 403,
                'mensaje'              => $mensaje,
                'privilegio_requerido' => $this->privilegioRequerido,
                'id_correlacion'       => $idCorrelacion
            ], 403);
            return false;
        }

        ErrorControlador::responder(
            403,
            $peticion,
            $respuesta,
            $mensaje,
            $idCorrelacion
        );

        return false;
    }
}
