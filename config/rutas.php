<?php

declare(strict_types=1);

use App\Core\Enrutador;
use App\Controladores\InicioControlador;
use App\Controladores\PersonaControlador;
use App\Controladores\AutenticacionControlador;
use App\Controladores\UsuarioControlador;
use App\Controladores\MenuControlador;
use App\Controladores\EmpresaControlador;
use App\Controladores\ContextoControlador;
use App\Middlewares\AutenticacionMiddleware;
use App\Middlewares\AutorizacionMiddleware;

/**
 * Tabla de enrutamiento oficial de CasaPRO.
 * Microfase: 1G-2 (Administración de Usuarios, Accesos y Contraseñas)
 */
return function (Enrutador $enrutador): void {

    // -------------------------------------------------------------------------
    // Módulo de Autenticación y Sesión (Público y Protegido)
    // -------------------------------------------------------------------------
    $enrutador->get('/login', [AutenticacionControlador::class, 'mostrarLogin']);
    $enrutador->post('/login', [AutenticacionControlador::class, 'iniciarSesion']);
    $enrutador->post('/logout', [AutenticacionControlador::class, 'cerrarSesion'], [AutenticacionMiddleware::class]);

    // -------------------------------------------------------------------------
    // Cambio de Contraseña (Obligatorio y Personal)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/cambiar-password-obligatorio',
        [UsuarioControlador::class, 'mostrarCambiarPasswordObligatorio'],
        [AutenticacionMiddleware::class]
    );

    $enrutador->post(
        '/api/mi-cuenta/cambiar-password',
        [UsuarioControlador::class, 'cambiarPasswordPersonal'],
        [AutenticacionMiddleware::class]
    );

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

    // -------------------------------------------------------------------------
    // Vistas y Pantallas del Módulo de Usuarios y Accesos (Microfase 1G-2)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/usuarios',
        [UsuarioControlador::class, 'index'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.ver')]
    );

    $enrutador->get(
        '/usuarios/{id}',
        [UsuarioControlador::class, 'ficha'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Administración de Usuarios y Seguridad (Microfase 1G-2)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/usuarios',
        [UsuarioControlador::class, 'listar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.ver')]
    );

    $enrutador->get(
        '/api/usuarios/personas-disponibles',
        [UsuarioControlador::class, 'personasDisponibles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.crear')]
    );

    $enrutador->get(
        '/api/usuarios/roles',
        [UsuarioControlador::class, 'rolesDisponibles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.ver')]
    );

    $enrutador->get(
        '/api/usuarios/{id}',
        [UsuarioControlador::class, 'detalle'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.ver')]
    );

    $enrutador->post(
        '/api/usuarios',
        [UsuarioControlador::class, 'crear'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.crear')]
    );

    $enrutador->put(
        '/api/usuarios/{id}',
        [UsuarioControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.editar')]
    );

    $enrutador->patch(
        '/api/usuarios/{id}/estado',
        [UsuarioControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.cambiar_estado')]
    );

    $enrutador->post(
        '/api/usuarios/{id}/desbloquear',
        [UsuarioControlador::class, 'desbloquear'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.desbloquear')]
    );

    $enrutador->put(
        '/api/usuarios/{id}/roles',
        [UsuarioControlador::class, 'sincronizarRoles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.asignar_roles')]
    );

    $enrutador->post(
        '/api/usuarios/{id}/reset-password',
        [UsuarioControlador::class, 'resetearPassword'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('usuarios.resetear_password')]
    );

    // -------------------------------------------------------------------------
    // Vistas y Pantallas del Módulo de Menú y Navegación (Microfase 1G-3)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/menu',
        [MenuControlador::class, 'index'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Gestión y Reordenamiento de Menú (Microfase 1G-3)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/menu',
        [MenuControlador::class, 'obtenerArbol'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.ver')]
    );

    $enrutador->get(
        '/api/menu/{id}',
        [MenuControlador::class, 'obtenerPorId'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.ver')]
    );

    $enrutador->post(
        '/api/menu',
        [MenuControlador::class, 'crear'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.crear')]
    );

    $enrutador->put(
        '/api/menu/{id}',
        [MenuControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.editar')]
    );

    $enrutador->patch(
        '/api/menu/{id}/estado',
        [MenuControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.cambiar_estado')]
    );

    $enrutador->post(
        '/api/menu/reordenar',
        [MenuControlador::class, 'reordenar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.reordenar')]
    );

    $enrutador->delete(
        '/api/menu/{id}',
        [MenuControlador::class, 'eliminar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('menu.eliminar')]
    );

    // -------------------------------------------------------------------------
    // Vistas y Pantallas del Módulo de Empresas (Microfase 2C)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/empresas',
        [EmpresaControlador::class, 'index'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Administración de Empresas (Microfase 2C)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/empresas',
        [EmpresaControlador::class, 'listar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.ver')]
    );

    $enrutador->get(
        '/api/empresas/personas-juridicas-disponibles',
        [EmpresaControlador::class, 'personasJuridicasDisponibles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.crear')]
    );

    $enrutador->get(
        '/api/empresas/{id}',
        [EmpresaControlador::class, 'detalle'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.ver')]
    );

    $enrutador->post(
        '/api/empresas',
        [EmpresaControlador::class, 'crear'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.crear')]
    );

    $enrutador->put(
        '/api/empresas/{id}',
        [EmpresaControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.editar')]
    );

    $enrutador->patch(
        '/api/empresas/{id}/estado',
        [EmpresaControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('empresas.cambiar_estado')]
    );

    // -------------------------------------------------------------------------
    // Contexto Territorial y Selector Corporativo (Microfase 2D)
    // Política: Autenticación requerida y validación en tiempo real en servicio
    // -------------------------------------------------------------------------
    $enrutador->post(
        '/api/contexto/cambiar-empresa',
        [ContextoControlador::class, 'cambiarEmpresa'],
        [AutenticacionMiddleware::class]
    );

    $enrutador->get(
        '/api/contexto/empresas',
        [ContextoControlador::class, 'listarEmpresasDisponibles'],
        [AutenticacionMiddleware::class]
    );
};

