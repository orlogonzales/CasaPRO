<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\DTOs\AutenticarUsuarioDTO;
use App\Modelos\Usuario;
use App\Modelos\EventoSeguridad;
use App\Modelos\Actor;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\ValidacionExcepcion;
use PDO;
use Throwable;

/**
 * AutenticacionServicio — Servicio soberano de autenticación, control de fuerza bruta,
 * sesiones seguras y mitigación de temporización.
 */
class AutenticacionServicio
{
    /**
     * Hash Bcrypt sintáctica y criptográficamente válido generado con PASSWORD_DEFAULT en PHP 8.3.
     * No pertenece a ningún usuario ni cuenta del sistema; su único propósito es reducir
     * diferencias temporales observables entre la ruta de usuario existente y la de usuario
     * inexistente mediante una verificación criptográfica de coste comparable.
     */
    public const HASH_DUMMY_DEFECTO = '$2y$10$sh1uvtgEbxH52dcRl9C6rOjHQok8L3aw1DCMukGWUPdMf7RicS5Rm';

    private const MENSAJE_ERROR_UNIFORME = 'No fue posible iniciar sesión con las credenciales proporcionadas.';

    private ProveedorConexion $proveedorConexion;
    private UsuarioRepositorio $usuarioRepositorio;
    private RolRepositorio $rolRepositorio;
    private SeguridadRepositorio $seguridadRepositorio;
    private PersonaRepositorio $personaRepositorio;
    private AuditoriaServicio $auditoriaServicio;
    private AutorizacionServicio $autorizacionServicio;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?UsuarioRepositorio $usuarioRepositorio = null,
        ?RolRepositorio $rolRepositorio = null,
        ?SeguridadRepositorio $seguridadRepositorio = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?AuditoriaServicio $auditoriaServicio = null,
        ?AutorizacionServicio $autorizacionServicio = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->usuarioRepositorio = $usuarioRepositorio ?? new UsuarioRepositorio($this->proveedorConexion);
        $this->rolRepositorio = $rolRepositorio ?? new RolRepositorio($this->proveedorConexion);
        $this->seguridadRepositorio = $seguridadRepositorio ?? new SeguridadRepositorio($this->proveedorConexion);
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio($this->proveedorConexion);
        $this->auditoriaServicio = $auditoriaServicio ?? new AuditoriaServicio($this->proveedorConexion);
        $this->autorizacionServicio = $autorizacionServicio ?? new AutorizacionServicio(
            $this->proveedorConexion,
            $this->usuarioRepositorio,
            $this->rolRepositorio
        );
    }

    /**
     * Autentica a un usuario aplicando rate-limiting con ventana de tiempo, mitigación
     * de temporización, locking contra concurrencia y respuesta pública anti-enumeración.
     *
     * @throws ReglaNegocioExcepcion Ante cualquier fallo de autenticación con mensaje opaco y uniforme (HTTP 401)
     */
    public function autenticar(AutenticarUsuarioDTO $dto, ContextoPeticion $contexto): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();
        $ip = $contexto->obtenerIp();
        $ua = $contexto->obtenerUserAgent();

        $maxIntentos = (int) ($_ENV['AUTH_MAX_INTENTOS'] ?? 5);
        $ventanaMinutos = (int) ($_ENV['AUTH_VENTANA_INTENTOS'] ?? 15);
        $bloqueoMinutos = (int) ($_ENV['AUTH_TIEMPO_BLOQUEO'] ?? 15);

        $conexion->beginTransaction();

        try {
            // 1. Buscar usuario aplicando FOR UPDATE para serializar intentos concurrentes sobre la misma cuenta
            $usuario = $this->usuarioRepositorio->buscarPorIdentificador($dto->identificador, true, $conexion);

            // 2. Caso Usuario Inexistente: Mitigación de temporización con hash dummy válido
            if ($usuario === null) {
                password_verify($dto->password, self::HASH_DUMMY_DEFECTO);

                $evento = new EventoSeguridad(
                    EventoSeguridad::TIPO_LOGIN_FALLO,
                    null,
                    $dto->identificador,
                    $ip,
                    $ua,
                    ['motivo' => 'USUARIO_INEXISTENTE']
                );
                $this->seguridadRepositorio->registrarEvento($evento, $conexion);
                $conexion->commit();

                throw new ReglaNegocioExcepcion(self::MENSAJE_ERROR_UNIFORME, 401);
            }

            // 3. Caso Usuario Inactivo
            if (!$usuario->estaActivo()) {
                password_verify($dto->password, self::HASH_DUMMY_DEFECTO);

                $evento = new EventoSeguridad(
                    EventoSeguridad::TIPO_LOGIN_FALLO,
                    $usuario->obtenerId(),
                    $dto->identificador,
                    $ip,
                    $ua,
                    ['motivo' => 'CUENTA_INACTIVA']
                );
                $this->seguridadRepositorio->registrarEvento($evento, $conexion);
                $conexion->commit();

                throw new ReglaNegocioExcepcion(self::MENSAJE_ERROR_UNIFORME, 401);
            }

            $ahora = time();

            // 4. Caso Bloqueo Temporal Defensivo Vigente
            if ($usuario->estaBloqueado()) {
                $minutosRestantes = (int) ceil((strtotime((string) $usuario->obtenerBloqueadoHasta()) - $ahora) / 60);

                $evento = new EventoSeguridad(
                    EventoSeguridad::TIPO_LOGIN_FALLO,
                    $usuario->obtenerId(),
                    $dto->identificador,
                    $ip,
                    $ua,
                    [
                        'motivo' => 'BLOQUEO_TEMPORAL_ACTIVO',
                        'minutos_restantes' => max(1, $minutosRestantes)
                    ]
                );
                $this->seguridadRepositorio->registrarEvento($evento, $conexion);
                $conexion->commit();

                throw new ReglaNegocioExcepcion(self::MENSAJE_ERROR_UNIFORME, 401);
            }

            // 5. Expiración de Bloqueo Previo o Expiración de Ventana de Intentos
            if ($usuario->obtenerBloqueadoHasta() !== null && strtotime((string) $usuario->obtenerBloqueadoHasta()) <= $ahora) {
                // Bloqueo finalizado por paso del tiempo: reseteo defensivo completo
                $usuario->reiniciarIntentosFallidos();
            } elseif ($usuario->obtenerUltimoIntentoFallido() !== null) {
                $tiempoTranscurrido = $ahora - strtotime((string) $usuario->obtenerUltimoIntentoFallido());
                if ($tiempoTranscurrido > ($ventanaMinutos * 60)) {
                    // Ha expirado la ventana de intentos: se reinicia el acumulador de fallos
                    $usuario->reiniciarIntentosFallidos();
                }
            }

            // 6. Verificación Criptográfica de la Contraseña
            $passwordValido = password_verify($dto->password, $usuario->obtenerPasswordHash());

            if (!$passwordValido) {
                $usuario->incrementarIntentosFallidos();

                // Evaluar si se alcanzó el límite dentro de la ventana
                if ($usuario->obtenerIntentosFallidos() >= $maxIntentos) {
                    $usuario->establecerBloqueoTemporal($bloqueoMinutos);

                    $eventoBloqueo = new EventoSeguridad(
                        EventoSeguridad::TIPO_BLOQUEO_TEMPORAL,
                        $usuario->obtenerId(),
                        $dto->identificador,
                        $ip,
                        $ua,
                        [
                            'intentos' => $usuario->obtenerIntentosFallidos(),
                            'duracion_min' => $bloqueoMinutos
                        ]
                    );
                    $this->seguridadRepositorio->registrarEvento($eventoBloqueo, $conexion);
                }

                $eventoFallo = new EventoSeguridad(
                    EventoSeguridad::TIPO_LOGIN_FALLO,
                    $usuario->obtenerId(),
                    $dto->identificador,
                    $ip,
                    $ua,
                    [
                        'motivo' => 'CREDENCIALES_INVALIDAS',
                        'intento_nro' => $usuario->obtenerIntentosFallidos()
                    ]
                );
                $this->seguridadRepositorio->registrarEvento($eventoFallo, $conexion);

                $this->usuarioRepositorio->actualizarSeguridad($usuario, $conexion);
                $conexion->commit();

                throw new ReglaNegocioExcepcion(self::MENSAJE_ERROR_UNIFORME, 401);
            }

            // 7. Autenticación Exitosa
            $usuario->registrarLoginExitoso($ip);

            // Rehash transparente si las políticas criptográficas del motor PHP cambiaron
            if (password_needs_rehash($usuario->obtenerPasswordHash(), PASSWORD_DEFAULT)) {
                $usuario->setPasswordHash(password_hash($dto->password, PASSWORD_DEFAULT));
            }

            $this->usuarioRepositorio->actualizarSeguridad($usuario, $conexion);

            $eventoExito = new EventoSeguridad(
                EventoSeguridad::TIPO_LOGIN_EXITO,
                $usuario->obtenerId(),
                $dto->identificador,
                $ip,
                $ua,
                ['metodo' => 'PASSWORD']
            );
            $this->seguridadRepositorio->registrarEvento($eventoExito, $conexion);

            $conexion->commit();

            // 8. Establecimiento Seguro de Sesión
            GestorSesion::iniciar();
            GestorSesion::regenerar(true);

            // Obtener datos de la persona para visualización en layout
            $persona = $this->personaRepositorio->buscarPorId($usuario->obtenerPersonaId());
            $nombreCompleto = $persona ? ($persona['nombre_completo'] ?? $usuario->obtenerNombreUsuario()) : $usuario->obtenerNombreUsuario();

            $datosAuth = [
                'usuario_id'           => $usuario->obtenerId(),
                'persona_id'           => $usuario->obtenerPersonaId(),
                'actor_id'             => $usuario->obtenerActorId(),
                'nombre_usuario'       => $usuario->obtenerNombreUsuario(),
                'nombre_completo'      => $nombreCompleto,
                'email'                => $usuario->obtenerEmail(),
                'version_autorizacion' => $usuario->obtenerVersionAutorizacion(),
                'debe_cambiar_password'=> $usuario->debeCambiarPassword(),
                'autenticado_en'       => time(),
            ];

            GestorSesion::establecer('auth', $datosAuth);
            GestorSesion::establecer('actor_id', $usuario->obtenerActorId());
            $contexto->establecerActorId($usuario->obtenerActorId());

            // 9. Resolución Determinista del Contexto Empresarial Inicial (Microfase 2D)
            $empresasDisponibles = $this->autorizacionServicio->obtenerEmpresasDisponiblesParaUsuario($usuario->obtenerId());
            if (!empty($empresasDisponibles)) {
                // Selección determinista: primer registro canónico (ORDER BY e.codigo ASC, e.nombre_corto ASC, e.id ASC)
                $empresaInicialId = (int) $empresasDisponibles[0]['id'];
                GestorSesion::establecer('contexto_empresa_id', $empresaInicialId);
            } else {
                GestorSesion::eliminar('contexto_empresa_id');
            }

            // Invalidación preventiva obligatoria de contextos subordinados
            GestorSesion::eliminar('contexto_proyecto_id');
            GestorSesion::eliminar('contexto_sector_id');

            return $datosAuth;

        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cierra la sesión activa de forma controlada registrando telemetría técnica.
     */
    public function cerrarSesion(?ContextoPeticion $contexto = null): void
    {
        GestorSesion::iniciar();
        $auth = GestorSesion::obtener('auth');

        if (is_array($auth) && isset($auth['usuario_id'])) {
            $ip = $contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $ua = $contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido');

            $evento = new EventoSeguridad(
                EventoSeguridad::TIPO_LOGOUT,
                (int) $auth['usuario_id'],
                null,
                $ip,
                $ua,
                ['motivo' => 'VOLUNTARIO']
            );
            $this->seguridadRepositorio->registrarEvento($evento);
        }

        GestorSesion::destruir();
    }

    /**
     * Registra atómicamente a un nuevo Actor USER, Usuario y asignación de Rol
     * vinculado estrictamente a una Persona Natural.
     */
    public function crearUsuarioConActor(
        int $personaId,
        string $email,
        string $nombreUsuario,
        string $passwordPlano,
        int $rolId,
        ?PDO $conexion = null,
        ?ContextoPeticion $contexto = null,
        bool $debeCambiarPassword = false
    ): array {
        $conn = $conexion ?? $this->proveedorConexion->obtenerConexion();
        $transaccionPropia = !$conn->inTransaction();

        if ($transaccionPropia) {
            $conn->beginTransaction();
        }

        try {
            // 1. Validar Persona Natural existente y activa
            $persona = $this->personaRepositorio->buscarPorId($personaId, $conn);
            if (!$persona) {
                throw new ValidacionExcepcion("La persona con ID {$personaId} no existe.", ['persona_id' => ['No encontrada.']], 422);
            }

            if (($persona['tipo_persona'] ?? '') !== 'NATURAL') {
                throw new ValidacionExcepcion('Las cuentas de usuario solo pueden asociarse a Personas Naturales.', ['tipo_persona' => ['Debe ser NATURAL.']], 422);
            }

            if (($persona['estado'] ?? '') !== 'ACTIVO') {
                throw new ValidacionExcepcion('No se puede crear un usuario para una persona inactiva.', ['estado' => ['Debe ser ACTIVO.']], 422);
            }

            // 2. Validar que la persona no tenga ya un usuario (1:1 estricto)
            $usuarioExistente = $this->usuarioRepositorio->buscarPorPersonaId($personaId, $conn);
            if ($usuarioExistente !== null) {
                throw new ValidacionExcepcion('La persona ya cuenta con un usuario registrado en el sistema.', ['persona_id' => ['Usuario ya existente.']], 422);
            }

            // 3. Validar unicidad de email y nombre de usuario
            if ($this->usuarioRepositorio->existeEmail($email, null, $conn)) {
                throw new ValidacionExcepcion("El correo electrónico '{$email}' ya está registrado.", ['email' => ['Ya en uso.']], 422);
            }

            if ($this->usuarioRepositorio->existeNombreUsuario($nombreUsuario, null, $conn)) {
                throw new ValidacionExcepcion("El nombre de usuario '{$nombreUsuario}' ya está registrado.", ['nombre_usuario' => ['Ya en uso.']], 422);
            }

            // 4. Validar rol existente y activo
            $rol = $this->rolRepositorio->buscarPorId($rolId, $conn);
            if (!$rol || !$rol->estaActivo()) {
                throw new ValidacionExcepcion("El rol seleccionado con ID {$rolId} no existe o está inactivo.", ['rol_id' => ['Inválido.']], 422);
            }

            // 5. Generar Actor USER con código inmutable independiente
            $codigoActor = 'ACT_USR_' . strtoupper(bin2hex(random_bytes(8)));
            $nombreActor = (string) ($persona['nombre_completo'] ?? $nombreUsuario);

            $sqlActor = "INSERT INTO `actores` (`tipo_actor`, `codigo`, `nombre`, `estado`, `metadatos`, `creado_en`)
                         VALUES ('USUARIO', :codigo, :nombre, 'ACTIVO', :metadatos, NOW())";
            $stmtActor = $conn->prepare($sqlActor);
            $stmtActor->bindValue(':codigo', $codigoActor, PDO::PARAM_STR);
            $stmtActor->bindValue(':nombre', $nombreActor, PDO::PARAM_STR);
            $stmtActor->bindValue(':metadatos', json_encode(['persona_id' => $personaId, 'email' => $email], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
            $stmtActor->execute();

            $actorId = (int) $conn->lastInsertId();

            // 6. Hashear contraseña con PASSWORD_DEFAULT
            $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

            // 7. Insertar Usuario
            $usuario = new Usuario(
                $personaId,
                $actorId,
                $email,
                $nombreUsuario,
                $hash,
                Usuario::ESTADO_ACTIVO,
                0,
                null,
                null,
                1,
                $debeCambiarPassword
            );
            $usuarioId = $this->usuarioRepositorio->insertar($usuario, $conn);

            // 8. Asignar Rol
            $this->usuarioRepositorio->asignarRol($usuarioId, $rolId, $conn);

            // 9. Registrar auditoría y evento de seguridad
            if ($contexto !== null) {
                $this->auditoriaServicio->registrar([
                    'modulo'           => 'seguridad',
                    'entidad'          => 'usuarios',
                    'registro_id'      => $usuarioId,
                    'accion'           => 'CREAR',
                    'resultado'        => 'EXITO',
                    'datos_anteriores' => null,
                    'datos_nuevos'     => [
                        'usuario_id'     => $usuarioId,
                        'persona_id'     => $personaId,
                        'actor_id'       => $actorId,
                        'email'          => $email,
                        'nombre_usuario' => $nombreUsuario,
                        'rol'            => $rol->obtenerCodigo(),
                    ],
                    'metadatos'        => ['descripcion' => 'Creación de cuenta de usuario con Actor USER'],
                    'contexto'         => $contexto
                ], $conn);
            }

            if ($transaccionPropia) {
                $conn->commit();
            }

            return [
                'usuario_id'     => $usuarioId,
                'persona_id'     => $personaId,
                'actor_id'       => $actorId,
                'codigo_actor'   => $codigoActor,
                'email'          => $email,
                'nombre_usuario' => $nombreUsuario,
                'rol_codigo'     => $rol->obtenerCodigo(),
            ];

        } catch (Throwable $e) {
            if ($transaccionPropia && $conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
