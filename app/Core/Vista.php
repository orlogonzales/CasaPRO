<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Motor de renderizado de vistas y layouts de CasaPRO.
 */
class Vista
{
    private static string $directorioVistas = __DIR__ . '/../Vistas';

    /**
     * Renderiza una vista dentro de un layout maestro.
     *
     * @param string $nombreVista Ruta relativa dentro de app/Vistas (ej. 'modulos/inicio/index')
     * @param array $datos Variables a inyectar en la vista
     * @param string|null $nombreLayout Nombre del layout en app/Vistas/layouts (ej. 'maestro') o null para sin layout
     * @return string HTML resultante
     */
    public static function renderizar(string $nombreVista, array $datos = [], ?string $nombreLayout = 'maestro'): string
    {
        $archivoVista = self::$directorioVistas . '/' . ltrim($nombreVista, '/') . '.php';

        if (!file_exists($archivoVista)) {
            throw new \RuntimeException("La vista no existe: {$archivoVista}");
        }

        // Extraer variables en el ámbito de la vista
        extract($datos, EXTR_SKIP);

        // Variables predeterminadas de layout si no vienen en datos
        $tituloPagina = $datos['tituloPagina'] ?? 'CasaPRO — Gestión Inmobiliaria';
        $migaPan = $datos['migaPan'] ?? [];
        $cssAdicionales = $datos['cssAdicionales'] ?? [];
        $jsAdicionales = $datos['jsAdicionales'] ?? [];

        // Capturar contenido de la vista
        ob_start();
        require $archivoVista;
        $contenido = ob_get_clean();

        // Si no se requiere layout, retornar el contenido directamente
        if ($nombreLayout === null) {
            return $contenido;
        }

        $archivoLayout = self::$directorioVistas . '/layouts/' . ltrim($nombreLayout, '/') . '.php';

        if (!file_exists($archivoLayout)) {
            throw new \RuntimeException("El layout no existe: {$archivoLayout}");
        }

        // Capturar el layout con el contenido inyectado
        ob_start();
        require $archivoLayout;
        return ob_get_clean();
    }

    /**
     * Retorna el token CSRF activo para incrustar en JavaScript o metadatos.
     */
    public static function csrfToken(): string
    {
        return CsrfServicio::obtenerToken();
    }

    /**
     * Renderiza un campo input hidden con el token CSRF para formularios HTML.
     */
    public static function csrfCampo(): string
    {
        $token = self::csrfToken();
        $nombreCampo = CsrfServicio::obtenerNombreCampo();
        return sprintf('<input type="hidden" name="%s" value="%s">', $nombreCampo, self::e($token));
    }

    /**
     * Escapa caracteres especiales para prevenir ataques XSS.
     */
    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Genera la URL base adecuada del sistema.
     */
    public static function url(string $ruta = ''): string
    {
        $uriCompleta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $baseDir = str_replace('\\', '/', $baseDir);

        if (str_ends_with($baseDir, '/public') && !str_starts_with($uriCompleta, $baseDir)) {
            $baseDir = substr($baseDir, 0, -7);
        }

        if ($baseDir === '/' || $baseDir === '.') {
            $baseDir = '';
        }

        return $baseDir . '/' . ltrim($ruta, '/');
    }

    /**
     * Genera la URL hacia un recurso estático en assets.
     */
    public static function asset(string $ruta): string
    {
        return self::url('assets/' . ltrim($ruta, '/'));
    }
}
