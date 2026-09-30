<?php

declare(strict_types=1);

namespace App\Core;

/**
 * GestorSesion — Administrador seguro de sesiones HTTP en CasaPRO.
 * Aplica políticas estrictas de cookies, expiración y prevención de fijación de sesión.
 */
class GestorSesion
{
    private const NOMBRE_SESION_DEFECTO = 'CASAPRO_SESION';
    private const DURACION_DEFECTO = 1800; // 30 minutos

    private static bool $iniciada = false;

    /**
     * Inicia la sesión PHP aplicando las políticas de seguridad si no está ya activa.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$iniciada = true;
            self::verificarExpiracion();
            return;
        }

        // Si se ejecuta en CLI pura sin cabeceras, no inicializar cookies HTTP a menos que sea en pruebas
        if (PHP_SAPI === 'cli' && !defined('CASAPRO_TESTING')) {
            return;
        }

        $duracion = (int) ($_ENV['SESSION_LIFETIME'] ?? self::DURACION_DEFECTO);
        if ($duracion <= 0) {
            $duracion = self::DURACION_DEFECTO;
        }

        $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        $requiereSecure = filter_var($_ENV['SESSION_SECURE'] ?? $esHttps, FILTER_VALIDATE_BOOLEAN);

        // Parámetros de seguridad ini de PHP si no se han enviado cabeceras
        if (!headers_sent()) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', (string) $duracion);

            $nombreSesion = (string) ($_ENV['SESSION_NAME'] ?? self::NOMBRE_SESION_DEFECTO);
            session_name($nombreSesion);

            session_set_cookie_params([
                'lifetime' => $duracion,
                'path' => '/',
                'domain' => '',
                'secure' => $requiereSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        self::$iniciada = (session_status() === PHP_SESSION_ACTIVE);
        self::verificarExpiracion();
    }

    /**
     * Verifica inactividad de sesión y renueva la marca de tiempo de última actividad.
     */
    private static function verificarExpiracion(): void
    {
        if (!self::$iniciada || !isset($_SESSION)) {
            return;
        }

        $ahora = time();
        $duracion = (int) ($_ENV['SESSION_LIFETIME'] ?? self::DURACION_DEFECTO);

        if (isset($_SESSION['_ultima_actividad']) && ($ahora - (int) $_SESSION['_ultima_actividad'] > $duracion)) {
            self::destruir();
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            self::$iniciada = true;
        }

        $_SESSION['_ultima_actividad'] = $ahora;
    }

    /**
     * Regenera el ID de sesión para prevenir fijación de sesión (e.g. tras login o escalamiento).
     */
    public static function regenerar(bool $destruirAnterior = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id($destruirAnterior);
        }
    }

    /**
     * Destruye completamente la sesión activa y elimina la cookie de sesión.
     */
    public static function destruir(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            @session_destroy();
            self::$iniciada = false;
        }
    }

    public static function estaIniciada(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public static function obtener(string $clave, mixed $defecto = null): mixed
    {
        self::iniciar();
        return $_SESSION[$clave] ?? $defecto;
    }

    public static function establecer(string $clave, mixed $valor): void
    {
        self::iniciar();
        $_SESSION[$clave] = $valor;
    }

    public static function eliminar(string $clave): void
    {
        self::iniciar();
        unset($_SESSION[$clave]);
    }

    public static function tiene(string $clave): bool
    {
        self::iniciar();
        return array_key_exists($clave, $_SESSION ?? []);
    }

    public static function establecerFlash(string $clave, mixed $valor): void
    {
        self::iniciar();
        $_SESSION['_flash'][$clave] = $valor;
    }

    public static function obtenerFlash(string $clave, mixed $defecto = null): mixed
    {
        self::iniciar();
        if (isset($_SESSION['_flash'][$clave])) {
            $valor = $_SESSION['_flash'][$clave];
            unset($_SESSION['_flash'][$clave]);
            return $valor;
        }
        return $defecto;
    }
}
