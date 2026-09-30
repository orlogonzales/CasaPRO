<?php

declare(strict_types=1);

namespace Tests\Comun;

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Core\MigradorSQL;
use PDO;
use RuntimeException;

/**
 * AmbientePruebas — Aislador determinista de entorno y base de datos para pruebas.
 *
 * Garantiza que ninguna suite de pruebas automatizada se ejecute sobre la base de datos
 * de desarrollo (casapro_dev) ni producción. Implementa una guardia de seguridad fail-closed
 * y reconstrucción determinista de casapro_test basada en el esquema consolidado oficial.
 */
class AmbientePruebas
{
    private static bool $inicializado = false;
    private static ?ProveedorConexion $proveedorTest = null;

    /**
     * Tablas que contienen datos mutables generados por pruebas y que deben
     * limpiarse para garantizar aislamiento determinista entre suites.
     */
    private const TABLAS_MUTABLES = [
        'eventos_seguridad',
        'auditorias',
        'usuario_roles',
        'usuarios',
        'menu_opciones',
        'empresas',
        'persona_representantes',
        'persona_direcciones',
        'persona_contactos',
        'persona_documentos',
        'persona_juridica',
        'persona_natural',
        'personas',
        'actores'
    ];

    /**
     * Inicializa el entorno aislado de testing de forma determinista.
     *
     * @param bool $limpiarDatos Si es true, limpia tablas mutables para iniciar con estado limpio
     * @return PDO Conexión PDO exclusiva a casapro_test
     * @throws RuntimeException Si la guardia de seguridad es violada
     */
    public static function iniciar(bool $limpiarDatos = false): PDO
    {
        $raiz = defined('CASAPRO_RAIZ') ? CASAPRO_RAIZ : dirname(__DIR__, 2);
        defined('CASAPRO_RAIZ') || define('CASAPRO_RAIZ', $raiz);
        defined('CASAPRO_TESTING') || define('CASAPRO_TESTING', true);

        // 1. Forzar variables de entorno soberanas para testing
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        putenv('DB_DATABASE=casapro_test');
        $_ENV['DB_DATABASE'] = 'casapro_test';
        $_SERVER['DB_DATABASE'] = 'casapro_test';

        // 2. Reiniciar CargadorEntorno y cargar .env.testing
        CargadorEntorno::reiniciar();
        $archivoTesting = file_exists($raiz . '/.env.testing') ? '.env.testing' : '.env';
        CargadorEntorno::cargar($raiz, $archivoTesting);

        // Reasegurar flags soberanos
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        putenv('DB_DATABASE=casapro_test');
        $_ENV['DB_DATABASE'] = 'casapro_test';
        $_SERVER['DB_DATABASE'] = 'casapro_test';

        // 3. Crear proveedor de conexión con base de datos de pruebas
        $config = require $raiz . '/config/database.php';
        $config['database'] = 'casapro_test';

        // Asegurar que la base de datos casapro_test exista en MySQL
        $proveedorServidor = new ProveedorConexion($config);
        $pdoServidor = $proveedorServidor->crearConexion(false);
        $pdoServidor->exec("CREATE DATABASE IF NOT EXISTS `casapro_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 4. Conectar a casapro_test
        self::$proveedorTest = new ProveedorConexion($config);
        $pdoTest = self::$proveedorTest->obtenerConexion();

        // 5. GUARDIA FAIL-CLOSED ESTRICTA
        self::verificarGuardia($pdoTest);

        // 6. Asegurar que las tablas existan desde SQL/casa-pro.sql
        if (!self::$inicializado) {
            self::asegurarEsquemaOficial($pdoTest);
            self::$inicializado = true;
        }

        // 7. Si se solicitó limpieza de datos, resetear tablas mutables
        if ($limpiarDatos) {
            self::limpiarTablasMutables($pdoTest);
        }

        return $pdoTest;
    }

    /**
     * Guardia de seguridad estricta (Fail-Closed).
     * Aborta de inmediato si detecta cualquier intento de ejecutar sobre desarrollo o producción.
     *
     * @param PDO $conexion Conexión activa a inspeccionar
     * @throws RuntimeException
     */
    public static function verificarGuardia(PDO $conexion): void
    {
        $appEnv = (string) CargadorEntorno::obtener('APP_ENV', 'unknown');
        if ($appEnv !== 'testing') {
            throw new RuntimeException(
                "GUARDIA DE SEGURIDAD VIOLADA: Se intentó ejecutar una suite de pruebas en entorno '{$appEnv}'. Solo se permite 'testing'."
            );
        }

        $dbActual = (string) $conexion->query('SELECT DATABASE()')->fetchColumn();
        $dbActualLower = strtolower($dbActual);

        // Lista negra estricta: base de datos normal de desarrollo o producción
        if ($dbActualLower === 'casapro' || $dbActualLower === 'casapro_dev' || $dbActualLower === 'casapro_prod' || $dbActualLower === 'casapro_production') {
            throw new RuntimeException(
                "GUARDIA DE SEGURIDAD VIOLADA: Conexión apuntando a base de datos protegida '{$dbActual}'. Abortando para evitar contaminación de datos de desarrollo/producción."
            );
        }

        // Lista blanca obligatoria: debe tener sufijo _test, prefijo test_ o _testing
        if (!str_ends_with($dbActualLower, '_test') && !str_starts_with($dbActualLower, 'test_') && !str_contains($dbActualLower, '_testing')) {
            throw new RuntimeException(
                "GUARDIA DE SEGURIDAD VIOLADA: La base de datos activa '{$dbActual}' no cumple el patrón obligatorio de testing (*_test). Abortando."
            );
        }
    }

    /**
     * Asegura que casapro_test cuente con las 27 tablas del esquema oficial de CasaPRO (SQL/casa-pro.sql).
     */
    public static function asegurarEsquemaOficial(PDO $pdoTest): void
    {
        $totalTablas = (int) $pdoTest->query("
            SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'casapro_test'
        ")->fetchColumn();

        if ($totalTablas < 27) {
            $rutaSql = defined('CASAPRO_RAIZ') ? CASAPRO_RAIZ . '/SQL/casa-pro.sql' : dirname(__DIR__, 2) . '/SQL/casa-pro.sql';
            if (!file_exists($rutaSql)) {
                throw new RuntimeException("No se encontró el esquema oficial en {$rutaSql}");
            }

            // Recrear limpiamente casapro_test para evitar conflictos de duplicados
            $pdoServidor = self::$proveedorTest->crearConexion(false);
            $pdoServidor->exec("DROP DATABASE IF EXISTS `casapro_test`");
            $pdoServidor->exec("CREATE DATABASE `casapro_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $migrador = new MigradorSQL(self::$proveedorTest);
            $migrador->ejecutarConsolidado($rutaSql);
        }
    }

    /**
     * Limpia de forma rápida y determinista las tablas mutables de prueba en casapro_test,
     * restaurando el actor raíz del sistema y las opciones de menú oficiales.
     */
    public static function limpiarTablasMutables(?PDO $pdoTest = null): void
    {
        $pdo = $pdoTest ?? (self::$proveedorTest !== null ? self::$proveedorTest->obtenerConexion() : self::iniciar(false));
        self::verificarGuardia($pdo);

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (self::TABLAS_MUTABLES as $tabla) {
            $pdo->exec("TRUNCATE TABLE `{$tabla}`");
        }
        // Restaurar actor raíz 1 (SISTEMA_CASAPRO) exigido por el modelo de auditoría
        $pdo->exec("INSERT INTO `actores` (`id`, `tipo_actor`, `codigo`, `nombre`, `estado`, `creado_en`) VALUES (1, 'SISTEMA', 'SISTEMA_CASAPRO', 'Sistema CasaPRO', 'ACTIVO', NOW())");

        // Restaurar seed mínimo oficial de menu_opciones
        $pdo->exec("INSERT INTO `menu_opciones` (`id`, `padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`) VALUES
            (1, NULL, 'ENLACE', 'MOD_INICIO', 'Inicio', '/inicio', 'fa-solid fa-house', 1, NULL, 'ACTIVO', 1),
            (2, NULL, 'AGRUPADOR', 'MOD_IDENTIDAD', 'Identidad y Seguridad', NULL, 'fa-solid fa-user-shield', 2, NULL, 'ACTIVO', 1),
            (3, 2, 'AGRUPADOR', 'GRP_PERSONAS', 'Gestión de Personas', NULL, NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'personas.ver'), 'ACTIVO', 1),
            (4, 2, 'AGRUPADOR', 'GRP_SEGURIDAD', 'Seguridad y Accesos', NULL, NULL, 2, NULL, 'ACTIVO', 1),
            (5, 3, 'ENLACE', 'OPC_PERSONAS_LISTADO', 'Directorio de Personas', 'personas', NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'personas.ver'), 'ACTIVO', 1),
            (6, 4, 'ENLACE', 'OPC_USUARIOS_LISTADO', 'Usuarios y Accesos', 'usuarios', NULL, 1, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'usuarios.ver'), 'ACTIVO', 1),
            (7, 4, 'ENLACE', 'OPC_MENU_LISTADO', 'Gestión de Menú', 'menu', NULL, 2, (SELECT `id` FROM `privilegios` WHERE `codigo` = 'menu.ver'), 'ACTIVO', 1)");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    /**
     * Retorna la instancia de ProveedorConexion configurada para casapro_test.
     */
    public static function obtenerProveedorTest(): ProveedorConexion
    {
        if (self::$proveedorTest === null) {
            self::iniciar();
        }
        return self::$proveedorTest;
    }
}
