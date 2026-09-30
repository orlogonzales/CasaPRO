<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Servicios\AutorizacionServicio;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Modelos\EventoSeguridad;

/**
 * ScopeMiddleware — Validador soberano de ámbito territorial y contexto empresarial.
 *
 * Reglas de seguridad vinculantes:
 * 1. El contexto operativo emana exclusivamente de la sesión del servidor ($_SESSION['contexto_empresa_id']).
 * 2. Ausencia de empresa activa en sesión -> HTTP 409 Conflict.
 * 3. Empresa inactiva -> HTTP 409 Conflict.
 * 4. Gate Anti-IDOR / Anti-Tampering: Si el cliente envía un empresa_id en GET, POST o JSON y este
 *    difiere de la sesión activa, se rechaza de inmediato con HTTP 403 Forbidden.
 * 5. Usuario sin autorización territorial en la empresa -> HTTP 403 Forbidden.
 */
class ScopeMiddleware
{
    private AutenticacionMiddleware $autenticacionMiddleware;
    private AutorizacionServicio $autorizacionServicio;
    private EmpresaRepositorio $empresaRepositorio;
    private SeguridadRepositorio $seguridadRepositorio;

    public function __construct(
        ?AutenticacionMiddleware $autenticacionMiddleware = null,
        ?AutorizacionServicio $autorizacionServicio = null,
        ?EmpresaRepositorio $empresaRepositorio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null
    ) {
        $this->autenticacionMiddleware = $autenticacionMiddleware ?? new AutenticacionMiddleware();
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->empresaRepositorio = $empresaRepositorio ?? new EmpresaRepositorio();
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio();
    }

    public static function exigir(): self
    {
        return new self();
    }

    public function procesar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): bool
    {
        // 1. Garantizar primero que la petición esté legítimamente autenticada
        if (!$this->autenticacionMiddleware->procesar($peticion, $respuesta, $contexto)) {
            return false;
        }

        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');
        $usuarioId = (int) ($auth['usuario_id'] ?? 0);

        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(6))));

        // 2. Extraer contexto soberano de sesión
        $empresaIdSesion = GestorSesion::obtener('contexto_empresa_id');

        if ($empresaIdSesion === null || (int) $empresaIdSesion <= 0) {
            return $this->responderError(
                $peticion,
                $respuesta,
                409,
                'CONTEXTO_EMPRESA_REQUERIDO',
                'Operación territorial denegada: No se ha seleccionado una empresa activa en la sesión.',
                $idCorrelacion
            );
        }

        $empresaId = (int) $empresaIdSesion;

        // 3. Gate Anti-IDOR / Anti-Tampering: Verificar discrepancia con empresa_id enviado por cliente
        $empresaIdCliente = $this->extraerEmpresaIdCliente($peticion);
        if ($empresaIdCliente !== null && $empresaIdCliente !== $empresaId) {
            // Registrar telemetría de intento de manipulación territorial
            $this->registrarAlertaSeguridad(
                $usuarioId,
                $peticion,
                $contexto,
                'DISCORDANCIA_TERRITORIAL_IDOR',
                [
                    'empresa_sesion'  => $empresaId,
                    'empresa_cliente' => $empresaIdCliente,
                    'ruta'            => $peticion->obtenerRuta()
                ]
            );

            return $this->responderError(
                $peticion,
                $respuesta,
                403,
                'DISCORDANCIA_TERRITORIAL',
                'Acceso denegado: El identificador de empresa proporcionado no coincide con el contexto empresarial activo en su sesión.',
                $idCorrelacion
            );
        }

        // 4. Validar existencia y estado de la empresa
        $empresa = $this->empresaRepositorio->buscarPorId($empresaId);
        if ($empresa === null) {
            // Empresa inexistente
            GestorSesion::eliminar('contexto_empresa_id');
            return $this->responderError(
                $peticion,
                $respuesta,
                404,
                'EMPRESA_NO_ENCONTRADA',
                'La empresa activa configurada en la sesión no existe en el sistema.',
                $idCorrelacion
            );
        }

        if (($empresa['estado'] ?? '') !== 'ACTIVO') {
            // Empresa inactiva -> 409 Conflict, desasignar contexto activo de sesión
            GestorSesion::eliminar('contexto_empresa_id');
            return $this->responderError(
                $peticion,
                $respuesta,
                409,
                'EMPRESA_INACTIVA',
                'La empresa seleccionada se encuentra inactiva y no admite operaciones.',
                $idCorrelacion
            );
        }

        // 5. Validar autorización territorial del usuario (Scope)
        $puedeAcceder = $this->autorizacionServicio->puedeAccederEmpresa($usuarioId, $empresaId);
        if (!$puedeAcceder) {
            $this->registrarAlertaSeguridad(
                $usuarioId,
                $peticion,
                $contexto,
                'ACCESO_TERRITORIAL_DENEGADO',
                [
                    'empresa_id' => $empresaId,
                    'ruta'       => $peticion->obtenerRuta()
                ]
            );

            return $this->responderError(
                $peticion,
                $respuesta,
                403,
                'FUERA_DE_SCOPE',
                'Acceso denegado: El usuario no cuenta con autorización territorial para la empresa activa.',
                $idCorrelacion
            );
        }

        // 6. Inyectar metadatos territoriales en el contexto de la petición
        if ($contexto !== null) {
            $contexto->establecerEmpresaId($empresaId);
            $contexto->establecerScopeTipo('EMPRESA');
        }

        return true;
    }

    /**
     * Inspecciona exhaustivamente todas las fuentes soportadas por Peticion para detectar
     * si el cliente intentó suministrar o alterar un empresa_id (Gate Anti-IDOR Multifuente).
     */
    private function extraerEmpresaIdCliente(Peticion $peticion): ?int
    {
        // 1. Query parameters (?empresa_id=X)
        $deQuery = $peticion->obtenerConsulta('empresa_id') ?? $peticion->obtenerQuery('empresa_id');
        if ($deQuery !== null && is_numeric($deQuery) && (int) $deQuery > 0) {
            return (int) $deQuery;
        }

        // 2. Parámetros de cuerpo POST / formulario multipart
        $deCuerpo = $peticion->obtenerCuerpo('empresa_id');
        if ($deCuerpo !== null && is_numeric($deCuerpo) && (int) $deCuerpo > 0) {
            return (int) $deCuerpo;
        }

        // 3. Payload JSON ({"empresa_id": X})
        $json = $peticion->obtenerJson();
        if (isset($json['empresa_id']) && is_numeric($json['empresa_id']) && (int) $json['empresa_id'] > 0) {
            return (int) $json['empresa_id'];
        }

        // 4. Parámetro unificado de petición
        $deGeneral = $peticion->obtener('empresa_id');
        if ($deGeneral !== null && is_numeric($deGeneral) && (int) $deGeneral > 0) {
            return (int) $deGeneral;
        }

        return null;
    }

    private function responderError(
        Peticion $peticion,
        Respuesta $respuesta,
        int $codigoHttp,
        string $codigoError,
        string $mensaje,
        string $idCorrelacion
    ): bool {
        if ($peticion->esAjax() || str_starts_with($peticion->obtenerRuta(), '/api/')) {
            $respuesta->json([
                'estado'         => 'error',
                'codigo'         => $codigoHttp,
                'codigo_error'   => $codigoError,
                'mensaje'        => $mensaje,
                'id_correlacion' => $idCorrelacion
            ], $codigoHttp);
            return false;
        }

        GestorSesion::iniciar();
        GestorSesion::establecerFlash('error', $mensaje);
        $respuesta->redireccionar('/inicio');
        return false;
    }

    private function registrarAlertaSeguridad(
        int $usuarioId,
        Peticion $peticion,
        ?ContextoPeticion $contexto,
        string $tipo,
        array $metadatos
    ): void {
        $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

        $evento = new EventoSeguridad(
            EventoSeguridad::TIPO_ACCESO_DENEGADO,
            $usuarioId > 0 ? $usuarioId : null,
            null,
            $ip,
            $ua,
            array_merge(['alerta_scope' => $tipo], $metadatos)
        );

        $this->seguridadRepositorio->registrarEvento($evento);
    }
}
