<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Vista;
use App\Core\Respuesta;
use App\Servicios\ContextoServicio;

/**
 * Controlador base que proporciona utilidades de renderizado y respuestas para CasaPRO.
 */
abstract class BaseControlador
{
    /**
     * Renderiza una vista y retorna el HTML generado.
     */
    protected function renderizar(string $vista, array $datos = [], ?string $layout = 'maestro'): string
    {
        if ($layout === 'maestro') {
            $contextoServicio = new ContextoServicio();
            $datos = array_merge($contextoServicio->obtenerDatosParaLayout(), $datos);
        }

        return Vista::renderizar($vista, $datos, $layout);
    }

    /**
     * Emite una respuesta JSON estandarizada.
     */
    protected function json(Respuesta $respuesta, array $datos, int $codigo = 200): void
    {
        $respuesta->json($datos, $codigo);
    }
}
