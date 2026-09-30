<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Vista;
use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Servicios\UsuarioServicio;
use App\Repositorios\RolRepositorio;
use App\DTOs\CrearUsuarioDTO;
use App\DTOs\ActualizarUsuarioDTO;
use App\DTOs\CambiarEstadoUsuarioDTO;
use App\DTOs\DesbloquearUsuarioDTO;
use App\DTOs\SincronizarRolesDTO;
use App\DTOs\ResetearPasswordDTO;
use App\DTOs\CambiarPasswordPersonalDTO;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use Throwable;

/**
 * UsuarioControlador — Controlador para la administración integral de usuarios,
 * credenciales, asignación de roles y control de acceso en CasaPRO.
 */
class UsuarioControlador extends BaseControlador
{
    private UsuarioServicio $usuarioServicio;
    private RolRepositorio $rolRepositorio;

    public function __construct(
        ?UsuarioServicio $usuarioServicio = null,
        ?RolRepositorio $rolRepositorio = null
    ) {
        $this->usuarioServicio = $usuarioServicio ?? new UsuarioServicio();
        $this->rolRepositorio = $rolRepositorio ?? new RolRepositorio();
    }

    /**
     * GET /usuarios
     * Renderiza la vista principal del directorio de usuarios con DataTables y modales.
     */
    public function index(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $roles = $this->rolRepositorio->listarTodosActivos();

        return $this->renderizar('modulos/usuarios/index', [
            'tituloPagina'   => 'Administración de Usuarios | CasaPRO',
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Seguridad', 'url' => null],
                ['texto' => 'Usuarios y Accesos', 'url' => null]
            ],
            'roles'          => $roles,
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
                'js/modulos/usuarios/listado-usuarios.js'
            ]
        ]);
    }

    /**
     * GET /usuarios/{id}
     * Renderiza la Ficha de Seguridad y Acceso del usuario.
     */
    public function ficha(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): string
    {
        $id = (int) ($parametros['id'] ?? 0);
        $usuario = $this->usuarioServicio->obtenerFichaSeguridad($id);

        if ($usuario === null) {
            $respuesta->redireccionar('/usuarios');
            return '';
        }

        $rolesCatalogo = $this->rolRepositorio->listarTodosActivos();

        return $this->renderizar('modulos/usuarios/ficha', [
            'tituloPagina'   => 'Ficha de Seguridad — ' . htmlspecialchars($usuario['nombre_usuario'], ENT_QUOTES, 'UTF-8') . ' | CasaPRO',
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Seguridad', 'url' => null],
                ['texto' => 'Usuarios', 'url' => Vista::url('usuarios')],
                ['texto' => 'Ficha de Seguridad', 'url' => null]
            ],
            'usuario'        => $usuario,
            'rolesCatalogo'  => $rolesCatalogo,
            'tokenCsrf'      => CsrfServicio::obtenerToken(),
            'cssAdicionales' => [
                'vendor/select/select2.min.css'
            ],
            'jsAdicionales'  => [
                'vendor/sweetalert/sweetalert.js',
                'vendor/select/select2.min.js',
                'vendor/pristine/pristine.min.js',
                'js/modulos/usuarios/ficha-usuario.js'
            ]
        ]);
    }

    /**
     * GET /cambiar-password-obligatorio
     * Renderiza la pantalla dedicada para cambio de contraseña obligatorio (debe_cambiar_password = 1).
     */
    public function mostrarCambiarPasswordObligatorio(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $auth = GestorSesion::obtener('auth');
        if (!is_array($auth) || empty($auth['usuario_id'])) {
            $respuesta->redireccionar('/login');
            return;
        }

        $tokenCsrf = CsrfServicio::obtenerToken();

        $html = Vista::renderizar('modulos/autenticacion/cambiar-password-obligatorio', [
            'tituloPagina'   => 'Cambio Obligatorio de Contraseña | CasaPRO',
            'usuario'        => $auth,
            'tokenCsrf'      => $tokenCsrf
        ], null);

        $respuesta->establecerCuerpo($html);
        $respuesta->enviar();
    }

    /**
     * GET /api/usuarios
     * Consulta paginada server-side compatible con DataTables.
     */
    public function listar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $parametrosConsulta = $_GET;
            $resultado = $this->usuarioServicio->obtenerDataTables($parametrosConsulta);

            $respuesta->json($resultado, 200);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/usuarios/personas-disponibles
     * Búsqueda de personas naturales sin usuario para el selector de nuevo usuario.
     */
    public function personasDisponibles(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $busqueda = trim((string) ($_GET['q'] ?? ''));
            $personas = $this->usuarioServicio->obtenerPersonasDisponibles($busqueda);

            $this->responderExito($respuesta, 200, 'Personas disponibles recuperadas.', $personas, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/usuarios/roles
     * Catálogo de roles activos para formularios.
     */
    public function rolesDisponibles(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $roles = $this->rolRepositorio->listarTodosActivos();
            $this->responderExito($respuesta, 200, 'Roles activos recuperados.', $roles, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * GET /api/usuarios/{id}
     * Detalle completo de un usuario para modales de edición o consulta.
     */
    public function detalle(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $usuario = $this->usuarioServicio->obtenerFichaSeguridad($id);

            if ($usuario === null) {
                $this->responderError($respuesta, 404, 'El usuario solicitado no existe.', null, $idCorrelacion);
                return;
            }

            $this->responderExito($respuesta, 200, 'Detalle de usuario recuperado exitosamente.', $usuario, $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/usuarios
     * Crea un nuevo usuario y su actor correspondiente.
     */
    public function crear(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CrearUsuarioDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->crearUsuario($dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 201, 'Usuario creado exitosamente con contraseña temporal.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PUT /api/usuarios/{id}
     * Actualiza email y nombre de usuario.
     */
    public function actualizar(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = ActualizarUsuarioDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->actualizarUsuario($id, $dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 200, 'Datos del usuario actualizados exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PATCH /api/usuarios/{id}/estado
     * Cambia el estado administrativo (ACTIVO / INACTIVO).
     */
    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CambiarEstadoUsuarioDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->cambiarEstado($id, $dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 200, 'Estado del usuario actualizado exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/usuarios/{id}/desbloquear
     * Desbloquea administrativamente una cuenta bloqueada por fuerza bruta.
     */
    public function desbloquear(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = DesbloquearUsuarioDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->desbloquear($id, $dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 200, 'Usuario desbloqueado exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * PUT /api/usuarios/{id}/roles
     * Sincroniza los roles asignados al usuario.
     */
    public function sincronizarRoles(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = SincronizarRolesDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->sincronizarRoles($id, $dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 200, 'Roles sincronizados exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/usuarios/{id}/reset-password
     * Reseteo administrativo de contraseña.
     */
    public function resetearPassword(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $id = (int) ($parametros['id'] ?? 0);
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = ResetearPasswordDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->usuarioServicio->resetearPassword($id, $dto, $operadorId, $contexto);

            $this->responderExito($respuesta, 200, 'Contraseña reseteada exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (Throwable $t) {
            $this->manejarErrorInterno($t, $respuesta, $idCorrelacion);
        }
    }

    /**
     * POST /api/mi-cuenta/cambiar-password
     * Cambio personal de contraseña por el propio usuario (voluntario u obligatorio).
     */
    public function cambiarPasswordPersonal(Peticion $peticion, Respuesta $respuesta, array $parametros = [], ?ContextoPeticion $contexto = null): void
    {
        $contexto = $contexto ?? ContextoPeticion::crearDesdeEntorno($peticion);
        $idCorrelacion = $this->resolverIdCorrelacion($contexto);

        try {
            $auth = GestorSesion::obtener('auth');
            $usuarioId = (int) ($auth['usuario_id'] ?? 0);

            if ($usuarioId <= 0) {
                $this->responderError($respuesta, 401, 'Debe iniciar sesión para realizar esta operación.', null, $idCorrelacion);
                return;
            }

            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CambiarPasswordPersonalDTO::desdeArray($datos);

            $resultado = $this->usuarioServicio->cambiarPasswordPersonal($usuarioId, $dto, $contexto);

            $this->responderExito($respuesta, 200, 'Contraseña actualizada exitosamente.', $resultado, $idCorrelacion);
        } catch (ValidacionExcepcion $e) {
            $this->responderError($respuesta, 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
        } catch (ReglaNegocioExcepcion $e) {
            $this->responderError($respuesta, $e->getCode() ?: 422, $e->getMessage(), $e->obtenerErrores(), $idCorrelacion);
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
            "[%s] [%s] ERROR 500 en UsuarioControlador: %s en %s:%d\nTraza:\n%s",
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
            'Ocurrió un error inesperado al procesar la solicitud en el servidor.',
            null,
            $idCorrelacion
        );
    }

    private function resolverIdCorrelacion(?ContextoPeticion $contexto): string
    {
        return $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(8))));
    }
}
