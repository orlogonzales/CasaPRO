<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Modelos\Usuario;
use App\Modelos\Rol;
use App\Modelos\EventoSeguridad;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Servicios\AuditoriaServicio;
use App\Servicios\PoliticaContrasenaServicio;
use App\DTOs\CrearUsuarioDTO;
use App\DTOs\ActualizarUsuarioDTO;
use App\DTOs\CambiarEstadoUsuarioDTO;
use App\DTOs\DesbloquearUsuarioDTO;
use App\DTOs\SincronizarRolesDTO;
use App\DTOs\ResetearPasswordDTO;
use App\DTOs\CambiarPasswordPersonalDTO;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use PDO;
use Throwable;

/**
 * UsuarioServicio — Servicio soberano de administración de usuarios, credenciales,
 * jerarquía SUPERADMIN, control de ciclo de vida, roles y sincronización de sesiones.
 */
class UsuarioServicio
{
    private ProveedorConexion $proveedorConexion;
    private UsuarioRepositorio $usuarioRepositorio;
    private RolRepositorio $rolRepositorio;
    private PersonaRepositorio $personaRepositorio;
    private SeguridadRepositorio $seguridadRepositorio;
    private AuditoriaServicio $auditoriaServicio;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?UsuarioRepositorio $usuarioRepositorio = null,
        ?RolRepositorio $rolRepositorio = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->usuarioRepositorio = $usuarioRepositorio ?? new UsuarioRepositorio($this->proveedorConexion);
        $this->rolRepositorio = $rolRepositorio ?? new RolRepositorio($this->proveedorConexion);
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio($this->proveedorConexion);
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
    }

    /**
     * Crea un nuevo usuario vinculado a una Persona Natural activa, generando su Actor USER inmutable.
     *
     * @throws ValidacionExcepcion
     * @throws ReglaNegocioExcepcion
     */
    public function crearUsuario(CrearUsuarioDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        // 1. Validar lista de roles
        $rolesLimpios = array_values(array_filter(array_unique(array_map('intval', $dto->roles)), fn($id) => $id > 0));
        if (empty($rolesLimpios)) {
            throw new ValidacionExcepcion('Debe asignar al menos un rol al usuario.', ['roles' => ['Seleccione al menos un rol activo.']], 422);
        }

        $rolesValidos = $this->rolRepositorio->validarIdsRolesActivos($rolesLimpios, $conn);
        if (count($rolesValidos) !== count($rolesLimpios)) {
            throw new ValidacionExcepcion('Uno o más roles seleccionados no existen o se encuentran inactivos.', ['roles' => ['Roles inválidos.']], 422);
        }

        // 2. Jerarquía SUPERADMIN: solo un SUPERADMIN puede asignar el rol SUPERADMIN
        $incluyeSuperadmin = false;
        foreach ($rolesValidos as $r) {
            if (($r['codigo'] ?? '') === Rol::ROL_SUPERADMIN) {
                $incluyeSuperadmin = true;
                break;
            }
        }

        if ($incluyeSuperadmin && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene privilegios para asignar el rol SUPERADMIN.', 403);
        }

        // 3. Validar Persona: debe existir, ser NATURAL, estar ACTIVA y no tener usuario
        $persona = $this->personaRepositorio->buscarPorId($dto->personaId, $conn);
        if ($persona === null) {
            throw new ValidacionExcepcion('La persona seleccionada no existe.', ['persona_id' => ['Persona inexistente.']], 422);
        }

        if ($persona['tipo_persona'] !== 'NATURAL') {
            throw new ValidacionExcepcion('Solo las personas naturales pueden contar con una cuenta de usuario.', ['persona_id' => ['Tipo inválido.']], 422);
        }

        if ($persona['estado'] !== 'ACTIVO') {
            throw new ValidacionExcepcion('No se puede crear un usuario para una persona inactiva.', ['persona_id' => ['Persona inactiva.']], 422);
        }

        $usuarioExistente = $this->usuarioRepositorio->buscarPorPersonaId($dto->personaId, $conn);
        if ($usuarioExistente !== null) {
            throw new ValidacionExcepcion('La persona ya cuenta con una cuenta de usuario registrada.', ['persona_id' => ['Usuario ya registrado.']], 422);
        }

        // 4. Validar unicidad de email y nombre_usuario
        $email = strtolower(trim($dto->email));
        if ($this->usuarioRepositorio->existeEmail($email, null, $conn)) {
            throw new ValidacionExcepcion("El correo electrónico '{$email}' ya se encuentra registrado.", ['email' => ['Ya registrado.']], 422);
        }

        $nombreUsuario = trim($dto->nombreUsuario);
        if ($this->usuarioRepositorio->existeNombreUsuario($nombreUsuario, null, $conn)) {
            throw new ValidacionExcepcion("El nombre de usuario '{$nombreUsuario}' ya se encuentra registrado.", ['nombre_usuario' => ['Ya registrado.']], 422);
        }

        // 5. Validar política de contraseña
        $erroresPassword = PoliticaContrasenaServicio::obtenerErrores($dto->password);
        if (!empty($erroresPassword)) {
            throw new ValidacionExcepcion('La contraseña no cumple con los requisitos mínimos de seguridad.', ['password' => $erroresPassword], 422);
        }


        // 6. Transacción PDO de persistencia
        $conn->beginTransaction();

        try {
            // Generar Actor USER
            $codigoActor = 'ACT_USR_' . strtoupper(bin2hex(random_bytes(8)));
            $nombreActor = (string) ($persona['nombre_completo'] ?? $nombreUsuario);

            $sqlActor = "INSERT INTO `actores` (`tipo_actor`, `codigo`, `nombre`, `estado`, `metadatos`, `creado_en`)
                         VALUES ('USUARIO', :codigo, :nombre, 'ACTIVO', :metadatos, NOW())";
            $stmtActor = $conn->prepare($sqlActor);
            $stmtActor->bindValue(':codigo', $codigoActor, PDO::PARAM_STR);
            $stmtActor->bindValue(':nombre', $nombreActor, PDO::PARAM_STR);
            $stmtActor->bindValue(':metadatos', json_encode(['persona_id' => $dto->personaId, 'email' => $email], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmtActor->execute();

            $actorId = (int) $conn->lastInsertId();

            // Hash de contraseña
            $hash = password_hash($dto->password, PASSWORD_DEFAULT);

            // Insertar Usuario
            $usuario = new Usuario(
                $dto->personaId,
                $actorId,
                $email,
                $nombreUsuario,
                $hash,
                Usuario::ESTADO_ACTIVO,
                0,
                null,
                null,
                1,
                true // debe_cambiar_password = true por defecto en alta administrativa
            );
            $usuarioId = $this->usuarioRepositorio->insertar($usuario, $conn);

            // Sincronizar roles
            $this->rolRepositorio->sincronizarRolesUsuario($usuarioId, $rolesLimpios, $conn);

            // Registrar Auditoría (sin password ni hash)
            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $usuarioId,
                'accion'           => 'CREAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => [
                    'usuario_id'     => $usuarioId,
                    'persona_id'     => $dto->personaId,
                    'actor_id'       => $actorId,
                    'email'          => $email,
                    'nombre_usuario' => $nombreUsuario,
                    'roles_asignados'=> $rolesLimpios,
                ],
                'metadatos'        => ['descripcion' => 'Alta de usuario con contraseña temporal'],
                'contexto'         => $contexto
            ], $conn);

            // Registrar Evento de Seguridad
            $evento = new EventoSeguridad(
                'CREACION_USUARIO',
                $usuarioId,
                $nombreUsuario,
                $contexto->obtenerIp(),
                $contexto->obtenerUserAgent(),
                ['operador_id' => $operadorId, 'roles' => $rolesLimpios]
            );
            $this->seguridadRepositorio->registrarEvento($evento, $conn);

            $conn->commit();

            return [
                'id'                     => $usuarioId,
                'persona_id'             => $dto->personaId,
                'actor_id'               => $actorId,
                'email'                  => $email,
                'nombre_usuario'         => $nombreUsuario,
                'estado'                 => Usuario::ESTADO_ACTIVO,
                'debe_cambiar_password'  => true,
                'roles'                  => $rolesLimpios
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza datos de perfil general de un usuario (email y nombre_usuario).
     */
    public function actualizarUsuario(int $id, ActualizarUsuarioDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($id, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }

        // Jerarquía SUPERADMIN: si el objetivo es SUPERADMIN, el operador debe ser SUPERADMIN
        $esObjetivoSuperadmin = $this->rolRepositorio->usuarioTieneRolCodigo($id, Rol::ROL_SUPERADMIN, $conn);
        if ($esObjetivoSuperadmin && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene permisos para modificar a un usuario SUPERADMIN.', 403);
        }

        $email = strtolower(trim($dto->email));
        if ($this->usuarioRepositorio->existeEmail($email, $id, $conn)) {
            throw new ValidacionExcepcion("El correo electrónico '{$email}' ya se encuentra en uso por otra cuenta.", ['email' => ['Ya registrado.']], 422);
        }

        $nombreUsuario = trim($dto->nombreUsuario);
        if ($this->usuarioRepositorio->existeNombreUsuario($nombreUsuario, $id, $conn)) {
            throw new ValidacionExcepcion("El nombre de usuario '{$nombreUsuario}' ya se encuentra en uso por otra cuenta.", ['nombre_usuario' => ['Ya registrado.']], 422);
        }

        $conn->beginTransaction();

        try {
            $datosAnteriores = [
                'email'          => $usuario->obtenerEmail(),
                'nombre_usuario' => $usuario->obtenerNombreUsuario()
            ];

            $this->usuarioRepositorio->actualizarDatosGenerales($id, $email, $nombreUsuario, $conn);

            $datosNuevos = [
                'email'          => $email,
                'nombre_usuario' => $nombreUsuario
            ];

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos'     => $datosNuevos,
                'metadatos'        => ['descripcion' => 'Actualización de credenciales de identificación'],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();

            return [
                'id'             => $id,
                'email'          => $email,
                'nombre_usuario' => $nombreUsuario
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambia el estado administrativo de un usuario (ACTIVO / INACTIVO) con motivo obligatorio.
     */
    public function cambiarEstado(int $id, CambiarEstadoUsuarioDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($id, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }

        $esObjetivoSuperadmin = $this->rolRepositorio->usuarioTieneRolCodigo($id, Rol::ROL_SUPERADMIN, $conn);

        // 1. Jerarquía SUPERADMIN
        if ($esObjetivoSuperadmin && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene permisos para modificar el estado de un usuario SUPERADMIN.', 403);
        }

        // 2. Prohibición de auto-desactivación de SUPERADMIN
        if ($id === $operadorId && $esObjetivoSuperadmin && $dto->nuevoEstado === Usuario::ESTADO_INACTIVO) {
            throw new ReglaNegocioExcepcion('Un usuario SUPERADMIN no puede desactivar su propia cuenta.', 422);
        }

        // 3. Anti-orfandad de SUPERADMIN
        if ($esObjetivoSuperadmin && $dto->nuevoEstado === Usuario::ESTADO_INACTIVO) {
            $totalSuperadminsActivos = $this->rolRepositorio->contarUsuariosActivosConRolCodigo(Rol::ROL_SUPERADMIN, $conn);
            if ($totalSuperadminsActivos <= 1) {
                throw new ReglaNegocioExcepcion('No se puede desactivar al único usuario SUPERADMIN activo del sistema.', 422);
            }
        }

        $conn->beginTransaction();

        try {
            $estadoAnterior = $usuario->obtenerEstado();
            $nuevaVersion = $usuario->obtenerVersionAutorizacion() + 1;

            $this->usuarioRepositorio->cambiarEstado($id, $dto->nuevoEstado, $nuevaVersion, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $id,
                'accion'           => 'CAMBIO_ESTADO',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $estadoAnterior],
                'datos_nuevos'     => ['estado' => $dto->nuevoEstado, 'motivo' => $dto->motivo],
                'metadatos'        => ['motivo' => $dto->motivo],
                'contexto'         => $contexto
            ], $conn);

            $evento = new EventoSeguridad(
                'ESTADO_USUARIO_CAMBIADO',
                $id,
                $usuario->obtenerNombreUsuario(),
                $contexto->obtenerIp(),
                $contexto->obtenerUserAgent(),
                [
                    'operador_id'     => $operadorId,
                    'estado_anterior' => $estadoAnterior,
                    'nuevo_estado'    => $dto->nuevoEstado,
                    'motivo'          => $dto->motivo
                ]
            );
            $this->seguridadRepositorio->registrarEvento($evento, $conn);

            $conn->commit();

            return [
                'id'                   => $id,
                'estado'               => $dto->nuevoEstado,
                'version_autorizacion' => $nuevaVersion
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Desbloquea administrativamente una cuenta bloqueada por intentos fallidos de autenticación.
     */
    public function desbloquear(int $id, DesbloquearUsuarioDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($id, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }

        // Jerarquía SUPERADMIN
        $esObjetivoSuperadmin = $this->rolRepositorio->usuarioTieneRolCodigo($id, Rol::ROL_SUPERADMIN, $conn);
        if ($esObjetivoSuperadmin && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene permisos para desbloquear a un usuario SUPERADMIN.', 403);
        }

        $conn->beginTransaction();

        try {
            $this->usuarioRepositorio->desbloquear($id, $conn);

            $evento = new EventoSeguridad(
                'DESBLOQUEO_ADMINISTRATIVO',
                $id,
                $usuario->obtenerNombreUsuario(),
                $contexto->obtenerIp(),
                $contexto->obtenerUserAgent(),
                [
                    'operador_id' => $operadorId,
                    'motivo'      => $dto->motivo
                ]
            );
            $this->seguridadRepositorio->registrarEvento($evento, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $id,
                'accion'           => 'DESBLOQUEO',
                'resultado'        => 'EXITO',
                'datos_anteriores' => [
                    'intentos_fallidos' => $usuario->obtenerIntentosFallidos(),
                    'bloqueado_hasta'   => $usuario->obtenerBloqueadoHasta()
                ],
                'datos_nuevos'     => [
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta'   => null,
                    'motivo'            => $dto->motivo
                ],
                'metadatos'        => ['motivo' => $dto->motivo],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();

            return [
                'id'                => $id,
                'intentos_fallidos' => 0,
                'bloqueado_hasta'   => null,
                'mensaje'           => 'Usuario desbloqueado exitosamente.'
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sincroniza los roles asignados a un usuario con validaciones de jerarquía y anti-orfandad.
     */
    public function sincronizarRoles(int $id, SincronizarRolesDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($id, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }

        $nuevosRoles = array_values(array_filter(array_unique(array_map('intval', $dto->roles)), fn($r) => $r > 0));
        if (empty($nuevosRoles)) {
            throw new ValidacionExcepcion('El usuario debe conservar al menos un rol activo.', ['roles' => ['Seleccione al menos un rol.']], 422);
        }

        $rolesValidos = $this->rolRepositorio->validarIdsRolesActivos($nuevosRoles, $conn);
        if (count($rolesValidos) !== count($nuevosRoles)) {
            throw new ValidacionExcepcion('Uno o más roles seleccionados no existen o están inactivos.', ['roles' => ['Roles inválidos.']], 422);
        }

        $rolesActuales = $this->rolRepositorio->obtenerRolesPorUsuario($id, $conn);
        $teniaSuperadmin = false;
        foreach ($rolesActuales as $r) {
            if (($r['codigo'] ?? '') === Rol::ROL_SUPERADMIN) {
                $teniaSuperadmin = true;
                break;
            }
        }

        $tendraSuperadmin = false;
        foreach ($rolesValidos as $r) {
            if (($r['codigo'] ?? '') === Rol::ROL_SUPERADMIN) {
                $tendraSuperadmin = true;
                break;
            }
        }

        // 1. Jerarquía: si tenía o tendrá SUPERADMIN, el operador debe ser SUPERADMIN
        if (($teniaSuperadmin || $tendraSuperadmin) && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene permisos para modificar la asignación del rol SUPERADMIN.', 403);
        }

        // 2. Anti-orfandad: no revocar SUPERADMIN al último activo
        if ($teniaSuperadmin && !$tendraSuperadmin && $usuario->estaActivo()) {
            $totalSuperadminsActivos = $this->rolRepositorio->contarUsuariosActivosConRolCodigo(Rol::ROL_SUPERADMIN, $conn);
            if ($totalSuperadminsActivos <= 1) {
                throw new ReglaNegocioExcepcion('No se puede revocar el rol SUPERADMIN al único administrador activo del sistema.', 422);
            }
        }

        $conn->beginTransaction();

        try {
            $this->rolRepositorio->sincronizarRolesUsuario($id, $nuevosRoles, $conn);

            // Incrementar version_autorizacion para forzar revalidación inmediata de privilegios
            $nuevaVersion = $this->usuarioRepositorio->incrementarVersionAutorizacion($id, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $id,
                'accion'           => 'SINCRONIZAR_ROLES',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['roles' => array_column($rolesActuales, 'id')],
                'datos_nuevos'     => ['roles' => $nuevosRoles],
                'metadatos'        => ['descripcion' => 'Actualización de roles del usuario'],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();

            return [
                'id'                   => $id,
                'roles'                => $nuevosRoles,
                'version_autorizacion' => $nuevaVersion
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reseteo administrativo de contraseña: asigna clave temporal y fuerza debe_cambiar_password = 1.
     */
    public function resetearPassword(int $id, ResetearPasswordDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($id, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('El usuario especificado no existe.', 404);
        }

        // 1. Prohibición de auto-reset administrativo
        if ($id === $operadorId) {
            throw new ReglaNegocioExcepcion('No puede resetear administrativamente su propia contraseña. Utilice la opción de cambio personal.', 422);
        }

        // 2. Jerarquía SUPERADMIN
        $esObjetivoSuperadmin = $this->rolRepositorio->usuarioTieneRolCodigo($id, Rol::ROL_SUPERADMIN, $conn);
        if ($esObjetivoSuperadmin && !$this->rolRepositorio->usuarioTieneRolCodigo($operadorId, Rol::ROL_SUPERADMIN, $conn)) {
            throw new ReglaNegocioExcepcion('No tiene permisos para resetear la contraseña de un usuario SUPERADMIN.', 403);
        }

        // 3. Validar política de contraseña temporal
        $erroresClave = PoliticaContrasenaServicio::obtenerErrores($dto->passwordTemporal);
        if (!empty($erroresClave)) {
            throw new ValidacionExcepcion('La contraseña temporal no cumple con la política de seguridad.', ['password_temporal' => $erroresClave], 422);
        }


        $conn->beginTransaction();

        try {
            $hashTemporal = password_hash($dto->passwordTemporal, PASSWORD_DEFAULT);
            $nuevaVersion = $usuario->obtenerVersionAutorizacion() + 1;

            $this->usuarioRepositorio->resetearPasswordAdministrativo($id, $hashTemporal, $nuevaVersion, $conn);

            $evento = new EventoSeguridad(
                'PASSWORD_RESET_ADMIN',
                $id,
                $usuario->obtenerNombreUsuario(),
                $contexto->obtenerIp(),
                $contexto->obtenerUserAgent(),
                [
                    'operador_id' => $operadorId,
                    'motivo'      => $dto->motivo
                ]
            );
            $this->seguridadRepositorio->registrarEvento($evento, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $id,
                'accion'           => 'RESET_PASSWORD',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['debe_cambiar_password' => $usuario->debeCambiarPassword()],
                'datos_nuevos'     => [
                    'debe_cambiar_password' => true,
                    'motivo'                => $dto->motivo,
                    'password'              => '[PROTEGIDO]'
                ],
                'metadatos'        => ['motivo' => $dto->motivo],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();

            return [
                'id'                    => $id,
                'debe_cambiar_password' => true,
                'password_temporal'     => $dto->passwordTemporal,
                'mensaje'               => 'Contraseña reseteada exitosamente.'
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cambio personal de contraseña por el propio usuario autenticado.
     * Valida contraseña actual, aplica política de seguridad, resetea debe_cambiar_password a 0
     * e incrementa version_autorizacion, renovando la sesión activa para evitar auto-expulsión.
     */
    public function cambiarPasswordPersonal(int $usuarioId, CambiarPasswordPersonalDTO $dto, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedorConexion->obtenerConexion();

        $usuario = $this->usuarioRepositorio->buscarPorId($usuarioId, $conn);
        if ($usuario === null) {
            throw new ReglaNegocioExcepcion('No fue posible identificar al usuario autenticado.', 401);
        }

        // 1. Validar coincidencia de nueva contraseña con su confirmación
        if ($dto->nuevoPassword !== $dto->confirmarPassword) {
            throw new ValidacionExcepcion('La confirmación de la nueva contraseña no coincide.', ['confirmar_password' => ['No coincide con la nueva contraseña.']], 422);
        }

        // 2. Validar contraseña actual con verificación estricta de hash
        if (!password_verify($dto->passwordActual, $usuario->obtenerPasswordHash())) {
            throw new ReglaNegocioExcepcion('La contraseña actual ingresada es incorrecta.', 422);
        }

        // 3. Validar política de seguridad de la nueva contraseña
        $erroresClave = PoliticaContrasenaServicio::obtenerErrores($dto->nuevoPassword);
        if (!empty($erroresClave)) {
            throw new ValidacionExcepcion('La nueva contraseña no cumple con la política de seguridad requerida.', ['nuevo_password' => $erroresClave], 422);
        }


        // 4. Prohibir contraseña idéntica a la actual
        if (password_verify($dto->nuevoPassword, $usuario->obtenerPasswordHash())) {
            throw new ReglaNegocioExcepcion('La nueva contraseña no puede ser idéntica a su contraseña actual.', 422);
        }

        $conn->beginTransaction();

        try {
            $nuevoHash = password_hash($dto->nuevoPassword, PASSWORD_DEFAULT);
            $nuevaVersion = $usuario->obtenerVersionAutorizacion() + 1;

            $this->usuarioRepositorio->actualizarPasswordPersonal($usuarioId, $nuevoHash, $nuevaVersion, $conn);

            $evento = new EventoSeguridad(
                'PASSWORD_CAMBIO_EXITOSO',
                $usuarioId,
                $usuario->obtenerNombreUsuario(),
                $contexto->obtenerIp(),
                $contexto->obtenerUserAgent(),
                ['origen' => 'PERSONAL']
            );
            $this->seguridadRepositorio->registrarEvento($evento, $conn);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'seguridad',
                'entidad'          => 'usuarios',
                'registro_id'      => $usuarioId,
                'accion'           => 'CAMBIO_PASSWORD_PERSONAL',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['debe_cambiar_password' => $usuario->debeCambiarPassword()],
                'datos_nuevos'     => [
                    'debe_cambiar_password' => false,
                    'password'              => '[PROTEGIDO]'
                ],
                'metadatos'        => ['descripcion' => 'Cambio voluntario/obligatorio de contraseña personal'],
                'contexto'         => $contexto
            ], $conn);

            $conn->commit();

            // Renovar sesión activa en caliente para no auto-expulsar la sesión actual
            $auth = GestorSesion::obtener('auth');
            if (is_array($auth)) {
                $auth['debe_cambiar_password'] = false;
                $auth['version_autorizacion'] = $nuevaVersion;
                GestorSesion::establecer('auth', $auth);
                GestorSesion::regenerar(true);
            }

            return [
                'usuario_id'            => $usuarioId,
                'debe_cambiar_password' => false,
                'version_autorizacion'  => $nuevaVersion,
                'mensaje'               => 'Contraseña actualizada correctamente.'
            ];

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtiene los datos detallados de la Ficha de Seguridad y Acceso.
     */
    public function obtenerFichaSeguridad(int $id): ?array
    {
        return $this->usuarioRepositorio->obtenerFichaSeguridad($id);
    }

    /**
     * Procesa los datos para la tabla paginada de usuarios (DataTables).
     */
    public function obtenerDataTables(array $parametros): array
    {
        $draw = (int) ($parametros['draw'] ?? 1);
        $total = $this->usuarioRepositorio->contarTotal();
        $filtrados = $this->usuarioRepositorio->contarFiltrados($parametros);
        $registros = $this->usuarioRepositorio->obtenerListadoDataTables($parametros);

        return [
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtrados,
            'data'            => $registros
        ];
    }

    /**
     * Obtiene personas disponibles (sin usuario asignado) para el formulario de alta.
     */
    public function obtenerPersonasDisponibles(string $termino = '', int $limite = 20): array
    {
        return $this->usuarioRepositorio->obtenerPersonasDisponiblesParaUsuario($termino, $limite);
    }
}
