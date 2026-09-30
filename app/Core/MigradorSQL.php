<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Motor determinista de migraciones SQL para CasaPRO.
 *
 * Ejecuta cronológicamente scripts SQL incrementales desde SQL/migraciones/,
 * registrando su estado en la tabla de control `migraciones` y garantizando
 * idempotencia y parada inmediata ante errores DDL/DML.
 */
class MigradorSQL
{
    private ProveedorConexion $proveedorConexion;
    private string $directorioMigraciones;

    /**
     * @param ProveedorConexion $proveedorConexion Instancia inyectable del proveedor de conexión
     * @param string|null $directorioMigraciones Ruta absoluta al directorio de migraciones o null para default
     */
    public function __construct(
        ProveedorConexion $proveedorConexion,
        ?string $directorioMigraciones = null
    ) {
        $this->proveedorConexion = $proveedorConexion;

        if ($directorioMigraciones === null) {
            $directorioRaiz = defined('CASAPRO_RAIZ') ? CASAPRO_RAIZ : dirname(__DIR__, 2);
            $directorioMigraciones = $directorioRaiz . '/SQL/migraciones';
        }

        $this->directorioMigraciones = rtrim($directorioMigraciones, '/\\');
    }

    /**
     * Asegura que la base de datos configurada exista en el servidor.
     *
     * @return void
     * @throws RuntimeException
     */
    public function asegurarBaseDatosExiste(): void
    {
        $config = $this->proveedorConexion->obtenerConfiguracion();
        $nombreBd = (string) ($config['database'] ?? '');

        if ($nombreBd === '') {
            return;
        }

        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');

        // Conectar al servidor sin seleccionar base de datos
        $pdo = $this->proveedorConexion->crearConexion(false);

        // Sanitizar nombre de BD para identificador SQL
        $nombreBdSeguro = preg_replace('/[^a-zA-Z0-9_-]/', '', $nombreBd);
        $sql = "CREATE DATABASE IF NOT EXISTS `{$nombreBdSeguro}` CHARACTER SET {$charset} COLLATE {$collation}";

        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException("Error al asegurar la base de datos '{$nombreBdSeguro}': " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Asegura que la tabla de control `migraciones` exista.
     *
     * @param PDO $pdo
     * @return void
     */
    public function asegurarTablaMigraciones(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migraciones` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migracion` VARCHAR(255) NOT NULL UNIQUE,
            `lote` INT UNSIGNED NOT NULL,
            `ejecutado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
    }

    /**
     * Obtiene el listado de archivos de migración disponibles ordenados alfabéticamente/cronológicamente.
     *
     * @return array<int, string> Nombres de archivo (ej. ['2026_09_29_000001_crear_tabla_migraciones.sql'])
     */
    public function obtenerArchivosMigracion(): array
    {
        if (!is_dir($this->directorioMigraciones)) {
            return [];
        }

        $archivos = scandir($this->directorioMigraciones);
        if ($archivos === false) {
            return [];
        }

        $migraciones = [];
        foreach ($archivos as $archivo) {
            if (str_ends_with($archivo, '.sql')) {
                $migraciones[] = $archivo;
            }
        }

        sort($migraciones, SORT_STRING);
        return $migraciones;
    }

    /**
     * Obtiene los nombres de las migraciones que ya han sido aplicadas en la base de datos.
     *
     * @param PDO $pdo
     * @return array<int, string>
     */
    public function obtenerMigracionesAplicadas(PDO $pdo): array
    {
        $this->asegurarTablaMigraciones($pdo);

        $stmt = $pdo->query("SELECT `migracion` FROM `migraciones` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retorna el estado completo de cada migración (aplicada o pendiente).
     *
     * @return array<int, array{migracion: string, estado: string, lote: int|null, ejecutado_en: string|null}>
     */
    public function obtenerEstado(): array
    {
        $this->asegurarBaseDatosExiste();
        $pdo = $this->proveedorConexion->obtenerConexion();
        $this->asegurarTablaMigraciones($pdo);

        $stmt = $pdo->query("SELECT `migracion`, `lote`, `ejecutado_en` FROM `migraciones`");
        $registros = [];
        while ($fila = $stmt->fetch()) {
            $registros[$fila['migracion']] = [
                'lote' => (int) $fila['lote'],
                'ejecutado_en' => (string) $fila['ejecutado_en'],
            ];
        }

        $archivos = $this->obtenerArchivosMigracion();
        $estado = [];

        foreach ($archivos as $archivo) {
            if (isset($registros[$archivo])) {
                $estado[] = [
                    'migracion' => $archivo,
                    'estado' => 'APLICADA',
                    'lote' => $registros[$archivo]['lote'],
                    'ejecutado_en' => $registros[$archivo]['ejecutado_en'],
                ];
            } else {
                $estado[] = [
                    'migracion' => $archivo,
                    'estado' => 'PENDIENTE',
                    'lote' => null,
                    'ejecutado_en' => null,
                ];
            }
        }

        return $estado;
    }

    /**
     * Ejecuta todas las migraciones pendientes en orden determinista.
     *
     * @return array{aplicadas: array<int, string>, omitidas: array<int, string>, lote: int, total_pendientes: int}
     * @throws RuntimeException Si una migración falla
     */
    public function ejecutar(): array
    {
        $this->asegurarBaseDatosExiste();
        $pdo = $this->proveedorConexion->obtenerConexion();
        $this->asegurarTablaMigraciones($pdo);

        $aplicadasAnteriores = $this->obtenerMigracionesAplicadas($pdo);
        $setAplicadas = array_flip($aplicadasAnteriores);

        $todosArchivos = $this->obtenerArchivosMigracion();
        $pendientes = [];
        $omitidas = [];

        foreach ($todosArchivos as $archivo) {
            if (isset($setAplicadas[$archivo])) {
                $omitidas[] = $archivo;
            } else {
                $pendientes[] = $archivo;
            }
        }

        if (empty($pendientes)) {
            return [
                'aplicadas' => [],
                'omitidas' => $omitidas,
                'lote' => 0,
                'total_pendientes' => 0,
            ];
        }

        // Obtener siguiente número de lote
        $stmtLote = $pdo->query("SELECT COALESCE(MAX(`lote`), 0) + 1 FROM `migraciones`");
        $loteActual = (int) $stmtLote->fetchColumn();

        $aplicadasEnEstaCorrida = [];

        foreach ($pendientes as $archivoMigracion) {
            $rutaArchivo = $this->directorioMigraciones . '/' . $archivoMigracion;
            $contenidoSql = file_get_contents($rutaArchivo);

            if ($contenidoSql === false) {
                throw new RuntimeException("No se pudo leer el archivo de migración: {$rutaArchivo}");
            }

            try {
                // Ejecutar el script SQL de la migración
                $this->ejecutarScriptSql($pdo, $contenidoSql);

                // Registrar en la tabla de control
                $stmtInsert = $pdo->prepare("INSERT INTO `migraciones` (`migracion`, `lote`) VALUES (:migracion, :lote)");
                $stmtInsert->bindValue(':migracion', $archivoMigracion, PDO::PARAM_STR);
                $stmtInsert->bindValue(':lote', $loteActual, PDO::PARAM_INT);
                $stmtInsert->execute();

                $aplicadasEnEstaCorrida[] = $archivoMigracion;
            } catch (PDOException $e) {
                // Detención obligatoria inmediata: no ejecutar las siguientes migraciones
                throw new RuntimeException(
                    "Fallo al ejecutar la migración [{$archivoMigracion}]: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return [
            'aplicadas' => $aplicadasEnEstaCorrida,
            'omitidas' => $omitidas,
            'lote' => $loteActual,
            'total_pendientes' => count($pendientes),
        ];
    }

    /**
     * Ejecuta un script SQL consolidado (como SQL/casa-pro.sql) para reconstrucción completa limpia.
     *
     * @param string $rutaArchivo Ruta al archivo SQL
     * @return void
     * @throws RuntimeException
     */
    public function ejecutarConsolidado(string $rutaArchivo): void
    {
        if (!file_exists($rutaArchivo) || !is_readable($rutaArchivo)) {
            throw new RuntimeException("El archivo SQL consolidado no existe o no se puede leer: {$rutaArchivo}");
        }

        $this->asegurarBaseDatosExiste();
        $pdo = $this->proveedorConexion->obtenerConexion();

        $contenidoSql = file_get_contents($rutaArchivo);
        if ($contenidoSql === false) {
            throw new RuntimeException("Error al leer el archivo consolidado: {$rutaArchivo}");
        }

        try {
            $this->ejecutarScriptSql($pdo, $contenidoSql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Fallo al importar el archivo SQL consolidado [{$rutaArchivo}]: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Ejecuta un bloque SQL dividiendo sentencias de manera limpia respetando comentarios.
     *
     * @param PDO $pdo
     * @param string $sql
     * @return void
     */
    private function ejecutarScriptSql(PDO $pdo, string $sql): void
    {
        // En PDO MySQL con ATTR_EMULATE_PREPARES=false, exec() puede ejecutar múltiples
        // sentencias continuas separadas por punto y coma si el servidor lo permite,
        // o podemos descomponer las sentencias para mayor diagnóstico.
        $sentencias = $this->descomponerSentencias($sql);

        foreach ($sentencias as $sentencia) {
            $sentenciaLimpia = trim($sentencia);
            if ($sentenciaLimpia !== '') {
                $pdo->exec($sentenciaLimpia);
            }
        }
    }

    /**
     * Divide un script SQL en sentencias individuales respetando cadenas de texto y comentarios.
     *
     * @param string $sql
     * @return array<int, string>
     */
    private function descomponerSentencias(string $sql): array
    {
        $sentencias = [];
        $buffer = '';
        $enComentarioLinea = false;
        $enComentarioBloque = false;
        $enComillas = false;
        $caracterComilla = '';
        $longitud = strlen($sql);

        for ($i = 0; $i < $longitud; $i++) {
            $char = $sql[$i];
            $sig = ($i + 1 < $longitud) ? $sql[$i + 1] : '';

            // Manejo de comentarios de línea (-- o #)
            if (!$enComillas && !$enComentarioBloque && !$enComentarioLinea) {
                if (($char === '-' && $sig === '-') || $char === '#') {
                    $enComentarioLinea = true;
                }
            }

            if ($enComentarioLinea) {
                if ($char === "\n") {
                    $enComentarioLinea = false;
                }
                continue;
            }

            // Manejo de comentarios de bloque (/* ... */)
            if (!$enComillas && !$enComentarioLinea && !$enComentarioBloque) {
                if ($char === '/' && $sig === '*') {
                    $enComentarioBloque = true;
                    $i++;
                    continue;
                }
            }

            if ($enComentarioBloque) {
                if ($char === '*' && $sig === '/') {
                    $enComentarioBloque = false;
                    $i++;
                }
                continue;
            }

            // Manejo de comillas (' o ")
            if (!$enComentarioLinea && !$enComentarioBloque) {
                if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    if (!$enComillas) {
                        $enComillas = true;
                        $caracterComilla = $char;
                    } elseif ($char === $caracterComilla) {
                        $enComillas = false;
                        $caracterComilla = '';
                    }
                }
            }

            // Delimitador de sentencia (;)
            if ($char === ';' && !$enComillas && !$enComentarioLinea && !$enComentarioBloque) {
                $sentencia = trim($buffer);
                if ($sentencia !== '') {
                    $sentencias[] = $sentencia;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $sentenciaRestante = trim($buffer);
        if ($sentenciaRestante !== '') {
            $sentencias[] = $sentenciaRestante;
        }

        return $sentencias;
    }
}
