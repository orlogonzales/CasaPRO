<?php

declare(strict_types=1);

namespace Tests\Comun;

use App\Core\ProveedorConexion;
use App\Core\GestorSesion;
use App\Modelos\Usuario;
use App\Modelos\Rol;
use App\Modelos\Persona;
use App\Modelos\PersonaNatural;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\PersonaRepositorio;
use PDO;

/**
 * FixtureAutenticacion — Generador de fixtures de sesión y usuarios reales en BD
 * para pruebas unitarias y de integración sin backdoors productivos.
 */
class FixtureAutenticacion
{
    public static function autenticarComoSuperadmin(?PDO $conexion = null): array
    {
        GestorSesion::iniciar();
        $proveedor = new ProveedorConexion();
        $conn = $conexion ?? $proveedor->obtenerConexion();

        $usuarioRepo = new UsuarioRepositorio($proveedor);
        $rolRepo = new RolRepositorio($proveedor);
        $personaRepo = new PersonaRepositorio($proveedor);

        // 1. Buscar si ya existe un usuario de prueba para SUPERADMIN
        $usuarioExistente = $usuarioRepo->buscarPorIdentificador('superadmin_test', false, $conn);

        if ($usuarioExistente !== null) {
            $datosAuth = [
                'usuario_id'           => $usuarioExistente->obtenerId(),
                'persona_id'           => $usuarioExistente->obtenerPersonaId(),
                'actor_id'             => $usuarioExistente->obtenerActorId(),
                'nombre_usuario'       => $usuarioExistente->obtenerNombreUsuario(),
                'nombre_completo'      => 'Superadministrador de Pruebas',
                'email'                => $usuarioExistente->obtenerEmail(),
                'version_autorizacion' => $usuarioExistente->obtenerVersionAutorizacion(),
                'autenticado_en'       => time(),
            ];

            GestorSesion::establecer('auth', $datosAuth);
            GestorSesion::establecer('actor_id', $usuarioExistente->obtenerActorId());
            return $datosAuth;
        }

        // 2. Si no existe, crear Persona Natural de prueba
        $persona = new Persona('NATURAL', 'ACTIVO', 'Persona fixture para pruebas');
        $personaId = $personaRepo->insertarPersona($persona, $conn);
        $natural = new PersonaNatural(
            $personaId,
            'Superadmin',
            'Pruebas',
            'Sistema',
            '1990-01-01',
            1,
            1,
            1,
            'Auditor'
        );
        $personaRepo->insertarNatural($natural, $conn);

        // 3. Crear Actor USER
        $codigoActor = 'ACT_USR_TEST_SUPERADMIN';
        $sqlActor = "INSERT INTO `actores` (`tipo_actor`, `codigo`, `nombre`, `estado`, `metadatos`, `creado_en`)
                     VALUES ('USUARIO', :codigo, 'Superadministrador de Pruebas', 'ACTIVO', '{\"origen\":\"FIXTURE\"}', NOW())
                     ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`)";
        $stmtActor = $conn->prepare($sqlActor);
        $stmtActor->bindValue(':codigo', $codigoActor, PDO::PARAM_STR);
        $stmtActor->execute();

        $stmtGetActor = $conn->prepare("SELECT id FROM `actores` WHERE `codigo` = :codigo LIMIT 1");
        $stmtGetActor->bindValue(':codigo', $codigoActor, PDO::PARAM_STR);
        $stmtGetActor->execute();
        $actorId = (int) $stmtGetActor->fetchColumn();

        // 4. Crear Usuario
        $hash = password_hash('SuperAdminTest#2026', PASSWORD_DEFAULT);
        $usuario = new Usuario(
            $personaId,
            $actorId,
            'superadmin_test@casapro.pe',
            'superadmin_test',
            $hash,
            Usuario::ESTADO_ACTIVO,
            0,
            null,
            null,
            1
        );
        $usuarioId = $usuarioRepo->insertar($usuario, $conn);

        // 5. Asignar rol SUPERADMIN (ID 1)
        $rolSuperadmin = $rolRepo->buscarPorCodigo('SUPERADMIN', $conn);
        $rolId = $rolSuperadmin ? (int) $rolSuperadmin->obtenerId() : 1;
        $usuarioRepo->asignarRol($usuarioId, $rolId, $conn);

        $datosAuth = [
            'usuario_id'           => $usuarioId,
            'persona_id'           => $personaId,
            'actor_id'             => $actorId,
            'nombre_usuario'       => 'superadmin_test',
            'nombre_completo'      => 'Superadministrador de Pruebas',
            'email'                => 'superadmin_test@casapro.pe',
            'version_autorizacion' => 1,
            'autenticado_en'       => time(),
        ];

        GestorSesion::establecer('auth', $datosAuth);
        GestorSesion::establecer('actor_id', $actorId);
        return $datosAuth;
    }

    public static function cerrarSesion(): void
    {
        GestorSesion::iniciar();
        GestorSesion::destruir();
    }
}
