<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;

/**
 * Controlador de inicio y verificación de infraestructura de CasaPRO.
 */
class InicioControlador extends BaseControlador
{
    /**
     * Renderiza la pantalla inicial de bienvenida basada en la plantilla Alina.
     */
    public function index(Peticion $peticion, Respuesta $respuesta): string
    {
        return $this->renderizar('modulos/inicio/index', [
            'tituloPagina' => 'Panel de Control | CasaPRO Inmobiliario',
            'migaPan' => [
                ['texto' => 'Inicio', 'url' => '#'],
                ['texto' => 'Plantilla Maestra Alina', 'url' => null]
            ],
            'datosSistema' => [
                'entorno' => 'Local Laragon',
                'phpVersion' => PHP_VERSION,
                'plantillaBase' => 'blank.html (Alina Bootstrap 5)',
                'estado' => 'Infraestructura y Plantilla Maestra Operativas'
            ]
        ]);
    }
}
