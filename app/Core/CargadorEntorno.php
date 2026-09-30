<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cargador determinista de variables de entorno (.env) para CasaPRO.
 *
 * Utiliza vlucas/phpdotenv como motor principal a través de Composer,
 * proveyendo respaldo determinista seguro y métodos de lectura con valores por defecto.
 */
class CargadorEntorno
{
    /**
     * @var bool Indica si el entorno ya fue cargado en el ciclo de vida actual.
     */
    private static bool $cargado = false;

    /**
     * Carga el archivo .env desde el directorio raíz especificado hacia $_ENV, $_SERVER y getenv().
     *
     * @param string $directorioRaiz Directorio base donde reside el archivo .env
     * @param string $nombreArchivo Nombre del archivo de entorno (por defecto '.env')
     * @return void
     */
    public static function cargar(string $directorioRaiz, string $nombreArchivo = '.env'): void
    {
        if (self::$cargado) {
            return;
        }

        $directorioRaiz = rtrim($directorioRaiz, '/\\');
        $rutaCompleta = $directorioRaiz . DIRECTORY_SEPARATOR . $nombreArchivo;

        // Si vlucas/phpdotenv está disponible vía Composer, utilizarlo prioritariamente
        if (class_exists(\Dotenv\Dotenv::class) && file_exists($rutaCompleta)) {
            $dotenv = \Dotenv\Dotenv::createImmutable($directorioRaiz, $nombreArchivo);
            $dotenv->safeLoad();
            self::$cargado = true;
            return;
        }

        // Respaldo determinista nativo sin dependencias externas
        if (file_exists($rutaCompleta) && is_readable($rutaCompleta)) {
            $lineas = file($rutaCompleta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lineas !== false) {
                foreach ($lineas as $linea) {
                    $linea = trim($linea);
                    if ($linea === '' || str_starts_with($linea, '#')) {
                        continue;
                    }

                    $partes = explode('=', $linea, 2);
                    if (count($partes) === 2) {
                        $clave = trim($partes[0]);
                        $valor = trim($partes[1]);

                        // Remover comillas envolventes si existen
                        if (
                            (str_starts_with($valor, '"') && str_ends_with($valor, '"')) ||
                            (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
                        ) {
                            $valor = substr($valor, 1, -1);
                        }

                        if (!array_key_exists($clave, $_ENV)) {
                            $_ENV[$clave] = $valor;
                            $_SERVER[$clave] = $valor;
                            putenv("{$clave}={$valor}");
                        }
                    }
                }
            }
        }

        self::$cargado = true;
    }

    /**
     * Obtiene el valor de una variable de entorno tipada con fallback por defecto.
     *
     * @param string $clave Nombre de la variable de entorno
     * @param mixed $defecto Valor retornado si la clave no está definida
     * @return mixed
     */
    public static function obtener(string $clave, mixed $defecto = null): mixed
    {
        $valor = $_ENV[$clave] ?? $_SERVER[$clave] ?? getenv($clave);

        if ($valor === false || $valor === null) {
            return $defecto;
        }

        // Conversión básica de tipos literales
        $valorLower = strtolower(trim((string) $valor));
        return match ($valorLower) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $valor,
        };
    }

    /**
     * Reinicia el estado de carga (útil para pruebas unitarias).
     *
     * @return void
     */
    public static function reiniciar(): void
    {
        self::$cargado = false;
    }
}
