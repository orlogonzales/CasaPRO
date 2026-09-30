<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\GestorSesion;
use App\Core\ContextoPeticion;
use App\Repositorios\EmpresaRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Modelos\EventoSeguridad;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;

/**
 * ContextoServicio — Gestión desacoplada del ámbito territorial activo en sesión.
 *
 * Microfase: 2D (Selector Corporativo y Conmutación en Caliente)
 * Reglas Vinculantes:
 * 1. $_SESSION['contexto_empresa_id'] es la ÚNICA fuente de verdad territorial en sesión.
 * 2. Cero duplicación de código/nombre en sesión (se obtienen del catálogo en BD).
 * 3. Selección automática determinista canónica: ORDER BY e.codigo ASC, e.nombre_corto ASC, e.id ASC.
 * 4. Invalidación preventiva obligatoria de contextos subordinados (contexto_proyecto_id, contexto_sector_id).
 * 5. Auditoría forense mínima no sensible.
 */
class ContextoServicio
{
    private AutorizacionServicio $autorizacionServicio;
    private EmpresaRepositorio $empresaRepositorio;
    private AuditoriaServicio $auditoriaServicio;
    private SeguridadRepositorio $seguridadRepositorio;

    public function __construct(
        ?AutorizacionServicio $autorizacionServicio = null,
        ?EmpresaRepositorio $empresaRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null
    ) {
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio();
        $this->empresaRepositorio = $empresaRepositorio ?? new EmpresaRepositorio();
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio();
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio();
    }

    /**
     * Obtiene el paquete de variables territoriales preparadas para el layout maestro.
     * Evita instanciar servicios o ejecutar SQL dentro de las vistas (MVC desacoplado).
     *
     * @return array{
     *     empresaActivaId: int|null,
     *     empresaActiva: array<string, mixed>|null,
     *     empresasDisponibles: array<int, array<string, mixed>>,
     *     totalEmpresas: int
     * }
     */
    public function obtenerDatosParaLayout(): array
    {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');
        $usuarioId = (int) ($auth['usuario_id'] ?? 0);

        if ($usuarioId <= 0) {
            return [
                'empresaActivaId'     => null,
                'empresaActiva'       => null,
                'empresasDisponibles' => [],
                'totalEmpresas'       => 0,
            ];
        }

        $empresaActivaIdSesion = GestorSesion::obtener('contexto_empresa_id');
        $empresaActivaId = ($empresaActivaIdSesion !== null && (int) $empresaActivaIdSesion > 0)
            ? (int) $empresaActivaIdSesion
            : null;

        // Lista determinista canónica (ORDER BY e.codigo ASC, e.nombre_corto ASC, e.id ASC)
        $empresas = $this->autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuarioId);

        $empresaActiva = null;
        if ($empresaActivaId !== null) {
            foreach ($empresas as $emp) {
                if ((int) $emp['id'] === $empresaActivaId) {
                    $empresaActiva = $emp;
                    break;
                }
            }
        }

        return [
            'empresaActivaId'     => $empresaActivaId,
            'empresaActiva'       => $empresaActiva,
            'empresasDisponibles' => $empresas,
            'totalEmpresas'       => count($empresas),
        ];
    }

    /**
     * Obtiene la lista canónica de empresas disponibles del usuario con indicador de activa.
     */
    public function obtenerEmpresasDisponibles(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [
                'empresa_activa_id' => null,
                'total_disponibles' => 0,
                'empresas'          => []
            ];
        }

        GestorSesion::iniciar();
        $empresaActivaId = GestorSesion::obtener('contexto_empresa_id');
        $empresaActivaId = ($empresaActivaId !== null && (int) $empresaActivaId > 0) ? (int) $empresaActivaId : null;

        $empresas = $this->autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuarioId);
        $resultado = [];

        foreach ($empresas as $emp) {
            $esActiva = ($empresaActivaId !== null && (int) $emp['id'] === $empresaActivaId);
            $resultado[] = [
                'id'           => (int) $emp['id'],
                'codigo'       => (string) $emp['codigo'],
                'nombre_corto' => (string) $emp['nombre_corto'],
                'es_activa'    => $esActiva
            ];
        }

        return [
            'empresa_activa_id' => $empresaActivaId,
            'total_disponibles' => count($resultado),
            'empresas'          => $resultado
        ];
    }

    /**
     * Ejecuta la conmutación en caliente hacia una nueva empresa activa.
     *
     * @param int $usuarioId ID del usuario autenticado
     * @param int $nuevaEmpresaId ID de la empresa solicitada
     * @param ContextoPeticion|null $contexto Contexto técnico de trazabilidad
     * @return array{empresa_id: int, codigo: string, nombre_corto: string}
     */
    public function cambiarEmpresa(int $usuarioId, int $nuevaEmpresaId, ?ContextoPeticion $contexto = null): array
    {
        if ($usuarioId <= 0 || $nuevaEmpresaId <= 0) {
            throw new ReglaNegocioExcepcion('Identificadores de usuario y empresa inválidos para conmutación.', 422);
        }

        // 1. Verificación Soberana de la Empresa en BD (Fresh read)
        $empresa = $this->empresaRepositorio->buscarPorId($nuevaEmpresaId);
        if ($empresa === null) {
            throw new RecursoNoEncontradoExcepcion("La empresa solicitada (ID {$nuevaEmpresaId}) no existe en el sistema.");
        }

        if (($empresa['estado'] ?? '') !== 'ACTIVO') {
            throw new ReglaNegocioExcepcion('La empresa seleccionada se encuentra inactiva y no admite operaciones.', 409);
        }

        // 2. Verificación de Autorización Territorial Actual en BD
        $puedeAcceder = $this->autorizacionServicio->puedeAccederEmpresa($usuarioId, $nuevaEmpresaId);
        if (!$puedeAcceder) {
            // Telemetría forense de acceso fuera de ámbito
            $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

            $evento = new EventoSeguridad(
                EventoSeguridad::TIPO_ACCESO_DENEGADO,
                $usuarioId,
                null,
                $ip,
                $ua,
                [
                    'alerta_scope' => 'CONMUTACION_FUERA_DE_SCOPE',
                    'empresa_id'   => $nuevaEmpresaId,
                    'origen'       => 'cambiar_empresa'
                ]
            );
            $this->seguridadRepositorio->registrarEvento($evento);

            throw new ReglaNegocioExcepcion('Acceso denegado: El usuario no cuenta con autorización territorial para la empresa solicitada.', 403);
        }

        // 3. Conmutación Atómica de Sesión
        GestorSesion::iniciar();
        $empresaAnteriorId = GestorSesion::obtener('contexto_empresa_id');
        $empresaAnteriorId = ($empresaAnteriorId !== null && (int) $empresaAnteriorId > 0) ? (int) $empresaAnteriorId : null;

        // Regla: Única fuente territorial de verdad en sesión
        GestorSesion::establecer('contexto_empresa_id', $nuevaEmpresaId);

        // Regla: Criterio Mandatorio 7 — Invalidación de Contextos Hijos Incompatibles (Proyectos y Sectores)
        GestorSesion::eliminar('contexto_proyecto_id');
        GestorSesion::eliminar('contexto_sector_id');

        // Invalidar caché en memoria del servicio
        $this->autorizacionServicio->invalidarCacheUsuario($usuarioId);

        // 4. Auditoría Forense Mínima y No Sensible
        $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

        $this->auditoriaServicio->registrar([
            'modulo'           => 'seguridad',
            'entidad'          => 'empresas',
            'registro_id'      => $nuevaEmpresaId,
            'accion'           => 'CAMBIO_CONTEXTO_EMPRESA',
            'resultado'        => 'EXITO',
            'datos_anteriores' => $empresaAnteriorId !== null ? ['empresa_id' => $empresaAnteriorId] : null,
            'datos_nuevos'     => [
                'empresa_id' => $nuevaEmpresaId,
                'codigo'     => (string) $empresa['codigo']
            ],
            'metadatos'        => [
                'origen'     => 'selector_topbar',
                'ip'         => $ip,
                'user_agent' => $ua
            ],
            'contexto'         => $contexto
        ]);

        return [
            'empresa_id'   => $nuevaEmpresaId,
            'codigo'       => (string) $empresa['codigo'],
            'nombre_corto' => (string) $empresa['nombre_corto']
        ];
    }
}
