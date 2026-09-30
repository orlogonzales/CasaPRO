<?php

declare(strict_types=1);

use App\Core\Enrutador;
use App\Controladores\InicioControlador;
use App\Controladores\PersonaControlador;
use App\Controladores\AutenticacionControlador;
use App\Middlewares\AutenticacionMiddleware;
use App\Middlewares\AutorizacionMiddleware;

/**
 * Tabla de enrutamiento oficial de CasaPRO.
 * Microfase: 1G-1 (Núcleo de Autenticación, RBAC y Autorización Multidimensional)
 */
return function (Enrutador $enrutador): void {

    // -------------------------------------------------------------------------
    // Módulo de Autenticación y Sesión (Público y Protegido)
    // -------------------------------------------------------------------------
    $enrutador->get('/login', [AutenticacionControlador::class, 'mostrarLogin']);
    $enrutador->post('/login', [AutenticacionControlador::class, 'iniciarSesion']);
    $enrutador->post('/logout', [AutenticacionControlador::class, 'cerrarSesion'], [AutenticacionMiddleware::class]);

    // -------------------------------------------------------------------------
    // Panel de Control Principal (Requiere Sesión Activa)
    // -------------------------------------------------------------------------
    $enrutador->get('/', [InicioControlador::class, 'index'], [AutenticacionMiddleware::class]);
    $enrutador->get('/inicio', [InicioControlador::class, 'index'], [AutenticacionMiddleware::class]);

    // -------------------------------------------------------------------------
    // Endpoint técnico de salud para monitoreo de infraestructura (Público)
    // -------------------------------------------------------------------------
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
    // Política: Deny by Default (AutenticacionMiddleware + AutorizacionMiddleware)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/personas',
        [PersonaControlador::class, 'index'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Identidad — Módulo de Personas (Fase 1D / 1F)
    // Política: Deny by Default y Privilegio Granular modulo.accion
    // Mutaciones protegidas globalmente por CsrfMiddleware en public/index.php
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/personas',
        [PersonaControlador::class, 'listar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.ver')]
    );

    $enrutador->get(
        '/api/personas/{id}',
        [PersonaControlador::class, 'obtener'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.ver')]
    );

    $enrutador->post(
        '/api/personas',
        [PersonaControlador::class, 'crear'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.crear')]
    );

    $enrutador->put(
        '/api/personas/{id}',
        [PersonaControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.editar')]
    );

    $enrutador->patch(
        '/api/personas/{id}/estado',
        [PersonaControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.cambiar_estado')]
    );

    $enrutador->post(
        '/api/personas/consultar-documento',
        [PersonaControlador::class, 'consultarDocumento'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.consultar_documento')]
    );

    // -------------------------------------------------------------------------
    // Catálogos Geográficos (UBIGEO) para Formularios
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/ubigeo/provincias',
        [PersonaControlador::class, 'obtenerProvincias'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.ver')]
    );

    $enrutador->get(
        '/api/ubigeo/distritos',
        [PersonaControlador::class, 'obtenerDistritos'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('personas.ver')]
    );
};
