<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Proveedor inyectable de conexiones PDO para CasaPRO.
 *
 * Administra la instanciación de conexiones PDO bajo configuración estricta,
 * permitiendo inyección de dependencias en Repositorios y Servicios sin
 * recurrir a un Singleton global rígido.
 */
class ProveedorConexion
{
    /**
     * @var array<string, mixed> Parámetros de configuración de la conexión.
     */
    private array $configuracion;

    /**
     * @var PDO|null Instancia activa de conexión administrada por este proveedor.
     */
    private ?PDO $conexion = null;

    /**
     * Constructor del proveedor.
     *
     * @param array<string, mixed>|null $configuracion Configuración personalizada o null para cargar config/database.php
     * @throws RuntimeException Si el archivo de configuración no existe
     */
    public function __construct(?array $configuracion = null)
    {
        if ($configuracion === null) {
            $directorioRaiz = defined('CASAPRO_RAIZ') ? CASAPRO_RAIZ : dirname(__DIR__, 2);
            $rutaConfig = $directorioRaiz . '/config/database.php';

            if (!file_exists($rutaConfig)) {
                throw new RuntimeException("El archivo de configuración de base de datos no existe: {$rutaConfig}");
            }

            $configuracion = require $rutaConfig;
        }

        $this->configuracion = $configuracion;
    }

    /**
     * Obtiene la conexión activa administrada por este proveedor (reutiliza en el ciclo de vida del objeto).
     *
     * @return PDO
     * @throws RuntimeException Si ocurre un error al conectar a la base de datos
     */
    public function obtenerConexion(): PDO
    {
        if ($this->conexion === null) {
            $this->conexion = $this->crearConexion(true);
        }

        return $this->conexion;
    }

    /**
     * Crea una nueva instancia de conexión PDO con los parámetros configurados.
     *
     * @param bool $incluirBaseDatos Si es false, conecta sin seleccionar base de datos (útil para creación o verificación de catálogo)
     * @return PDO
     * @throws RuntimeException Si las credenciales o el servidor son inalcanzables
     */
    public function crearConexion(bool $incluirBaseDatos = true): PDO
    {
        $driver = (string) ($this->configuracion['driver'] ?? 'mysql');
        $host = (string) ($this->configuracion['host'] ?? '127.0.0.1');
        $port = (int) ($this->configuracion['port'] ?? 3306);
        $database = (string) ($this->configuracion['database'] ?? '');
        $charset = (string) ($this->configuracion['charset'] ?? 'utf8mb4');
        $username = (string) ($this->configuracion['username'] ?? 'root');
        $password = (string) ($this->configuracion['password'] ?? '');

        $dsn = "{$driver}:host={$host};port={$port};charset={$charset}";
        if ($incluirBaseDatos && $database !== '') {
            $dsn .= ";dbname={$database}";
        }

        // Opciones obligatorias de gobernanza CasaPRO
        $opciones = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Combinar con opciones personalizadas si existieran
        if (isset($this->configuracion['options']) && is_array($this->configuracion['options'])) {
            $opciones = $this->configuracion['options'] + $opciones;
        }

        try {
            return new PDO($dsn, $username, $password, $opciones);
        } catch (PDOException $e) {
            // Registrar log técnico en el servidor sin exponer jamás la contraseña
            $logSeguro = sprintf(
                "Error de conexión PDO [%s]: %s en %s:%d (BD: %s)",
                $driver,
                $e->getMessage(),
                $host,
                $port,
                $incluirBaseDatos ? $database : '[ninguna]'
            );
            error_log($logSeguro);

            // Mensaje de excepción seguro sin cadenas de autenticación
            throw new RuntimeException(
                "No fue posible establecer la conexión con la base de datos en {$host}:{$port}. " .
                "Verifique que el servicio de base de datos se encuentre activo y las credenciales sean válidas.",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retorna la configuración cargada en el proveedor (sin exponer contraseñas si se usa para inspección).
     *
     * @return array<string, mixed>
     */
    public function obtenerConfiguracion(): array
    {
        return $this->configuracion;
    }

    /**
     * Cierra explícitamente la conexión activa liberando el recurso PDO.
     *
     * @return void
     */
    public function cerrarConexion(): void
    {
        $this->conexion = null;
    }
}
