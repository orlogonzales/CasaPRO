<?php

declare(strict_types=1);

/**
 * CasaPRO — Comando CLI Soberano de Aprovisionamiento Inicial (Bootstrap SUPERADMIN).
 *
 * Microfase: 1G-1
 * Soporta First Bootstrap en instalaciones limpias reutilizando PersonaServicio
 * bajo transacción atómica estricta.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado: Este script solo puede ejecutarse desde la interfaz de línea de comandos (CLI).\n";
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Modelos\Usuario;
use App\Modelos\EventoSeguridad;
use App\Repositorios\UsuarioRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\SeguridadRepositorio;
use App\Repositorios\PersonaRepositorio;
use App\Servicios\PersonaServicio;
use App\Servicios\AuditoriaServicio;
use App\DTOs\CrearPersonaDTO;
use App\Excepciones\ValidacionExcepcion;

// Cargar entorno
CargadorEntorno::cargar(dirname(__DIR__));

echo "====================================================================\n";
echo " CasaPRO — Aprovisionamiento Seguro del Primer SUPERADMIN (1G-1)\n";
echo "====================================================================\n\n";

// 1. Detección y Rechazo Estricto de Contraseñas en Argumentos CLI (--password=...)
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--password') || str_starts_with($arg, '-p')) {
        echo "[ERROR DE SEGURIDAD] Por directriz arquitectónica soberana, queda estrictamente prohibido\n";
        echo "pasar contraseñas mediante argumentos de línea de comandos (--password o -p) para evitar\n";
        echo "su exposición en la tabla de procesos del sistema operativo y en el historial del shell.\n";
        echo "La contraseña debe ingresarse de forma interactiva y enmascarada.\n\n";
        exit(1);
    }
}

// 2. Inicializar servicios y repositorios
$proveedorConexion = new ProveedorConexion();
$conexion = $proveedorConexion->obtenerConexion();
$usuarioRepo = new UsuarioRepositorio($proveedorConexion);
$rolRepo = new RolRepositorio($proveedorConexion);
$seguridadRepo = new SeguridadRepositorio($proveedorConexion);
$personaRepo = new PersonaRepositorio($proveedorConexion);
$auditoriaServicio = new AuditoriaServicio($proveedorConexion);
$personaServicio = new PersonaServicio($proveedorConexion, $personaRepo, $auditoriaServicio);

// Contexto CLI soberano
$contexto = new ContextoPeticion('BOOTSTRAP-CLI-' . strtoupper(bin2hex(random_bytes(6))), '127.0.0.1', 'CLI/casapro-bootstrap', 'CLI', 1);

// 3. Evaluar estado de SUPERADMIN en la base de datos
$totalSuperadmins = $rolRepo->contarSuperadmins($conexion);
$modoFirstBootstrap = ($totalSuperadmins === 0);

if (!$modoFirstBootstrap) {
    echo "[AVISO] Ya existen {$totalSuperadmins} usuario(s) SUPERADMIN activos en CasaPRO.\n";
    echo "El modo First Bootstrap extraordinario se encuentra DESHABILITADO.\n";
    echo "La Persona Natural a asociar debe existir previamente en el Padrón Central.\n\n";
} else {
    echo "[MODO DETECTADO] Base de datos sin SUPERADMIN activo.\n";
    echo "Habilitado modo FIRST BOOTSTRAP: Si la Persona no existe, será creada\n";
    echo "reutilizando el servicio soberano de Personas bajo transacción atómica.\n\n";
}

// Función auxiliar para solicitar entrada de texto en consola
function leerLinea(string $prompt, bool $obligatorio = true): string {
    while (true) {
        echo $prompt;
        $linea = trim((string) fgets(STDIN));
        if (!$obligatorio || $linea !== '') {
            return $linea;
        }
        echo "  [!] Este campo es obligatorio.\n";
    }
}

// Función auxiliar para lectura segura de contraseñas sin eco
function leerPasswordSeguro(string $prompt = 'Ingrese contraseña: '): string {
    echo $prompt;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // En Windows usamos powershell Read-Host -AsSecureString
        $comando = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $pwd = shell_exec($comando);
        echo "\n";
        return trim((string) $pwd);
    } else {
        $sttyModo = shell_exec('stty -g');
        shell_exec('stty -echo');
        $pwd = trim((string) fgets(STDIN));
        shell_exec("stty {$sttyModo}");
        echo "\n";
        return $pwd;
    }
}

// 4. Solicitar Identificador / DNI de la Persona
$dni = leerLinea("1. Ingrese el número de DNI de la Persona Natural: ");

// Buscar persona existente por documento principal o número de documento
$stmtDoc = $conexion->prepare("
    SELECT p.id, p.tipo_persona, p.estado, pn.nombres, pn.apellido_paterno, pn.apellido_materno
    FROM persona_documentos pd
    INNER JOIN personas p ON p.id = pd.persona_id
    LEFT JOIN persona_natural pn ON pn.persona_id = p.id
    WHERE pd.numero_documento = :dni AND pd.estado = 'ACTIVO'
    LIMIT 1
");
$stmtDoc->bindValue(':dni', $dni, PDO::PARAM_STR);
$stmtDoc->execute();
$personaEncontrada = $stmtDoc->fetch(PDO::FETCH_ASSOC);

$personaId = null;
$personaFueCreada = false;
$nombreCompleto = '';

if ($personaEncontrada) {
    if ($personaEncontrada['tipo_persona'] !== 'NATURAL') {
        echo "[ERROR] El documento ingresado pertenece a una Persona Jurídica.\n";
        echo "Las cuentas de usuario solo pueden asociarse a Personas Naturales.\n";
        exit(1);
    }

    if ($personaEncontrada['estado'] !== 'ACTIVO') {
        echo "[ERROR] La persona encontrada se encuentra en estado INACTIVO.\n";
        exit(1);
    }

    $personaId = (int) $personaEncontrada['id'];
    $nombreCompleto = trim(($personaEncontrada['apellido_paterno'] ?? '') . ' ' . ($personaEncontrada['apellido_materno'] ?? '') . ', ' . ($personaEncontrada['nombres'] ?? ''));

    // Verificar si ya tiene usuario
    if ($usuarioRepo->buscarPorPersonaId($personaId, $conexion) !== null) {
        echo "[ERROR] La persona encontrada (ID: {$personaId}, {$nombreCompleto}) ya cuenta con un usuario en CasaPRO.\n";
        exit(1);
    }

    echo "  [OK] Persona Natural existente verificada: {$nombreCompleto} (ID: {$personaId})\n\n";

} else {
    // Si la persona NO existe
    if (!$modoFirstBootstrap) {
        echo "[ERROR] No se encontró ninguna Persona con DNI '{$dni}' en el padrón central.\n";
        echo "En modo ordinario, debe registrar previamente a la persona antes de asignarle un usuario.\n";
        exit(1);
    }

    echo "  [*] La persona no existe. Procediendo a recopilar datos mínimos para First Bootstrap...\n";
    $nombres = leerLinea("  - Nombres: ");
    $apellidoPaterno = leerLinea("  - Apellido Paterno: ");
    $apellidoMaterno = leerLinea("  - Apellido Materno (opcional): ", false);
    $personaFueCreada = true;
    $nombreCompleto = trim("{$apellidoPaterno} {$apellidoMaterno}, {$nombres}");
    echo "\n";
}

// 5. Solicitar Datos de Usuario y Acceso
$nombreUsuario = leerLinea("2. Ingrese el nombre de usuario (username): ");
if ($usuarioRepo->existeNombreUsuario($nombreUsuario, null, $conexion)) {
    echo "[ERROR] El nombre de usuario '{$nombreUsuario}' ya está en uso.\n";
    exit(1);
}

$email = leerLinea("3. Ingrese el correo electrónico corporativo: ");
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "[ERROR] El formato del correo electrónico es inválido.\n";
    exit(1);
}
if ($usuarioRepo->existeEmail($email, null, $conexion)) {
    echo "[ERROR] El correo electrónico '{$email}' ya está registrado.\n";
    exit(1);
}

// Solicitar contraseña interactiva
while (true) {
    $password = leerPasswordSeguro("4. Ingrese la contraseña de acceso (mínimo 10 caracteres): ");
    if (strlen($password) < 10) {
        echo "  [!] La contraseña debe tener al menos 10 caracteres.\n";
        continue;
    }

    $passwordConf = leerPasswordSeguro("   Confirme la contraseña: ");
    if ($password !== $passwordConf) {
        echo "  [!] Las contraseñas no coinciden. Intente de nuevo.\n\n";
        continue;
    }
    break;
}

echo "\n[*] Ejecutando transacción atómica de aprovisionamiento...\n";

// 6. Ejecución Transaccional Atómica Integral
$conexion->beginTransaction();

try {
    // Si la persona debe crearse en First Bootstrap
    if ($personaFueCreada) {
        $datosPersonaDTO = [
            'tipo_persona'     => 'NATURAL',
            'nombres'          => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno !== '' ? $apellidoMaterno : null,
            'notas'            => 'Persona registrada automáticamente durante First Bootstrap CLI',
            'documentos'       => [
                [
                    'tipo_documento_id' => 1, // DNI según catálogo inicial 000002
                    'numero_documento'  => $dni,
                    'es_principal'      => true,
                    'estado'            => 'ACTIVO'
                ]
            ]
        ];

        $dtoCrearPersona = CrearPersonaDTO::desdeArray($datosPersonaDTO);
        $resPersona = $personaServicio->crear($dtoCrearPersona, $contexto, $conexion);
        $personaId = (int) $resPersona['id'];
        echo "  + [OK] Persona Natural creada con ID: {$personaId} mediante PersonaServicio soberano.\n";
    }

    // 7. Generar código inmutable de Actor USER
    $codigoActor = 'ACT_USR_' . strtoupper(bin2hex(random_bytes(8)));

    $sqlActor = "INSERT INTO `actores` (`tipo_actor`, `codigo`, `nombre`, `estado`, `metadatos`, `creado_en`)
                 VALUES ('USUARIO', :codigo, :nombre, 'ACTIVO', :metadatos, NOW())";
    $stmtActor = $conexion->prepare($sqlActor);
    $stmtActor->bindValue(':codigo', $codigoActor, PDO::PARAM_STR);
    $stmtActor->bindValue(':nombre', $nombreCompleto, PDO::PARAM_STR);
    $stmtActor->bindValue(':metadatos', json_encode([
        'origen' => 'BOOTSTRAP_CLI',
        'persona_id' => $personaId,
        'email' => $email
    ], JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $stmtActor->execute();

    $actorId = (int) $conexion->lastInsertId();
    echo "  + [OK] Actor USER creado con ID: {$actorId} y código canónico: {$codigoActor}.\n";

    // 8. Crear Usuario con hash PASSWORD_DEFAULT
    $hashPassword = password_hash($password, PASSWORD_DEFAULT);

    $usuario = new Usuario(
        $personaId,
        $actorId,
        $email,
        $nombreUsuario,
        $hashPassword,
        Usuario::ESTADO_ACTIVO,
        0,
        null,
        null,
        1
    );

    $usuarioId = $usuarioRepo->insertar($usuario, $conexion);
    echo "  + [OK] Usuario creado con ID: {$usuarioId} (Username: {$nombreUsuario}).\n";

    // 9. Asignar Rol SUPERADMIN (ID 1)
    $rolSuperadmin = $rolRepo->buscarPorCodigo('SUPERADMIN', $conexion);
    if (!$rolSuperadmin) {
        throw new RuntimeException("El rol del sistema SUPERADMIN no existe en la base de datos.");
    }

    $usuarioRepo->asignarRol($usuarioId, (int) $rolSuperadmin->obtenerId(), $conexion);
    echo "  + [OK] Rol SUPERADMIN asignado con éxito.\n";

    // 10. Auditoría y Bitácora de Eventos de Seguridad
    $auditoriaServicio->registrar([
        'modulo'           => 'seguridad',
        'entidad'          => 'usuarios',
        'registro_id'      => $usuarioId,
        'accion'           => 'BOOTSTRAP',
        'resultado'        => 'EXITO',
        'datos_anteriores' => null,
        'datos_nuevos'     => [
            'usuario_id'     => $usuarioId,
            'persona_id'     => $personaId,
            'actor_id'       => $actorId,
            'email'          => $email,
            'nombre_usuario' => $nombreUsuario,
            'rol'            => 'SUPERADMIN',
            'first_bootstrap'=> $personaFueCreada
        ],
        'metadatos'        => ['descripcion' => 'Aprovisionamiento inicial de SUPERADMIN via CLI'],
        'contexto'         => $contexto
    ], $conexion);

    $eventoBootstrap = new EventoSeguridad(
        EventoSeguridad::TIPO_BOOTSTRAP_ADMIN,
        $usuarioId,
        $nombreUsuario,
        '127.0.0.1',
        'CLI/casapro-bootstrap',
        ['origen' => 'CLI', 'first_bootstrap' => $personaFueCreada]
    );
    $seguridadRepo->registrarEvento($eventoBootstrap, $conexion);

    // Confirmación atómica definitiva
    $conexion->commit();

    echo "\n====================================================================\n";
    echo " [ÉXITO] Aprovisionamiento de SUPERADMIN completado al 100%.\n";
    echo " Credenciales listas para inicio de sesión en CasaPRO:\n";
    echo "   - Usuario: {$nombreUsuario}\n";
    echo "   - Correo : {$email}\n";
    echo "   - Rol    : SUPERADMIN\n";
    echo "====================================================================\n";
    exit(0);

} catch (Throwable $t) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo "\n[ERROR CRÍTICO] La operación fue revertida (Rollback). Cero datos huérfanos.\n";
    echo "Detalle del fallo: " . $t->getMessage() . "\n";
    exit(1);
}
