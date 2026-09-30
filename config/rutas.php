<?php

declare(strict_types=1);

use App\Core\Enrutador;
use App\Controladores\InicioControlador;
use App\Controladores\PersonaControlador;
use App\Middlewares\GuardiaActorMiddleware;

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

    // -------------------------------------------------------------------------
    // Vistas y Pantallas del Módulo de Personas (Fase 1E)
    // Política: Deny by Default (GuardiaActorMiddleware)
    // -------------------------------------------------------------------------
    $enrutador->get('/personas', [PersonaControlador::class, 'index'], [GuardiaActorMiddleware::class]);

    // -------------------------------------------------------------------------
    // API REST de Identidad — Módulo de Personas (Fase 1D)
    // Política: Deny by Default (GuardiaActorMiddleware en lectura y mutación)
    // Mutaciones protegidas globalmente por CsrfMiddleware en public/index.php
    // -------------------------------------------------------------------------
    $enrutador->get('/api/personas', [PersonaControlador::class, 'listar'], [GuardiaActorMiddleware::class]);
    $enrutador->get('/api/personas/{id}', [PersonaControlador::class, 'obtener'], [GuardiaActorMiddleware::class]);
    $enrutador->post('/api/personas', [PersonaControlador::class, 'crear'], [GuardiaActorMiddleware::class]);
    $enrutador->put('/api/personas/{id}', [PersonaControlador::class, 'actualizar'], [GuardiaActorMiddleware::class]);
    $enrutador->patch('/api/personas/{id}/estado', [PersonaControlador::class, 'cambiarEstado'], [GuardiaActorMiddleware::class]);
    $enrutador->post('/api/personas/consultar-documento', [PersonaControlador::class, 'consultarDocumento'], [GuardiaActorMiddleware::class]);

    // -------------------------------------------------------------------------
    // Catálogos Geográficos (UBIGEO) para Formularios
    // -------------------------------------------------------------------------
    $enrutador->get('/api/ubigeo/provincias', [PersonaControlador::class, 'obtenerProvincias'], [GuardiaActorMiddleware::class]);
    $enrutador->get('/api/ubigeo/distritos', [PersonaControlador::class, 'obtenerDistritos'], [GuardiaActorMiddleware::class]);
};
