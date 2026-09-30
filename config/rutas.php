<?php

declare(strict_types=1);

use App\Core\Enrutador;
use App\Controladores\InicioControlador;

/**
 * Tabla de enrutamiento oficial de CasaPRO.
 */
return function (Enrutador $enrutador): void {
    // Ruta principal del panel de control
    $enrutador->get('/', [InicioControlador::class, 'index']);
    $enrutador->get('/inicio', [InicioControlador::class, 'index']);

    // Endpoint técnico de salud para monitoreo de infraestructura
    $enrutador->get('/api/salud', function ($peticion, $respuesta) {
        $respuesta->json([
            'estado' => 'exito',
            'codigo' => 200,
            'mensaje' => 'Servicio CasaPRO operativo.',
            'datos' => [
                'sistema' => 'CasaPRO',
                'estado' => 'ACTIVO',
                'timestamp' => date('c')
            ]
        ]);
    });
};
