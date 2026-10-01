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
use App\Controladores\AsignacionTerritorialControlador;
use App\Controladores\ProyectoControlador;
use App\Controladores\SectorControlador;
use App\Middlewares\AutenticacionMiddleware;
use App\Middlewares\AutorizacionMiddleware;
use App\Middlewares\ScopeMiddleware;

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

    // -------------------------------------------------------------------------
    // Asignaciones Territoriales Usuario ↔ Empresa ↔ Rol (Cierre Complementario Fase 2)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/usuarios/{id}/asignaciones',
        [AsignacionTerritorialControlador::class, 'listarPorUsuario'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.ver')]
    );

    $enrutador->post(
        '/api/usuarios/{id}/asignaciones',
        [AsignacionTerritorialControlador::class, 'asignar'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.crear')]
    );

    $enrutador->patch(
        '/api/asignaciones/{id}/estado',
        [AsignacionTerritorialControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.editar')]
    );

    $enrutador->get(
        '/api/empresas/{id}/colaboradores',
        [AsignacionTerritorialControlador::class, 'listarPorEmpresa'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.ver')]
    );

    $enrutador->get(
        '/api/asignaciones/empresas-disponibles',
        [AsignacionTerritorialControlador::class, 'empresasDisponibles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.ver')]
    );

    $enrutador->get(
        '/api/asignaciones/roles-disponibles',
        [AsignacionTerritorialControlador::class, 'rolesDisponibles'],
        [AutenticacionMiddleware::class, AutorizacionMiddleware::exigir('asignaciones.ver')]
    );

    // -------------------------------------------------------------------------
    // Vistas y Pantallas del Módulo de Proyectos (Fase 3A)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/proyectos',
        [ProyectoControlador::class, 'index'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.ver')]
    );

    $enrutador->get(
        '/proyectos/{id}',
        [ProyectoControlador::class, 'ficha'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Proyectos y Predios Matrices (Fase 3A)
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/proyectos',
        [ProyectoControlador::class, 'listar'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.ver')]
    );

    $enrutador->get(
        '/api/proyectos/{id}',
        [ProyectoControlador::class, 'obtener'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.ver')]
    );

    $enrutador->post(
        '/api/proyectos',
        [ProyectoControlador::class, 'crear'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.crear')]
    );

    $enrutador->put(
        '/api/proyectos/{id}',
        [ProyectoControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.editar')]
    );

    $enrutador->patch(
        '/api/proyectos/{id}/estado',
        [ProyectoControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.cambiar_estado')]
    );

    $enrutador->get(
        '/api/proyectos/{id}/predios',
        [ProyectoControlador::class, 'listarPredios'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('predios.ver')]
    );

    $enrutador->post(
        '/api/proyectos/{id}/predios',
        [ProyectoControlador::class, 'crearPredio'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('predios.crear')]
    );

    $enrutador->put(
        '/api/predios/{id}',
        [ProyectoControlador::class, 'actualizarPredio'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('predios.editar')]
    );

    $enrutador->patch(
        '/api/predios/{id}/estado',
        [ProyectoControlador::class, 'cambiarEstadoPredio'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('predios.cambiar_estado')]
    );

    $enrutador->get(
        '/api/proyectos/{id}/conciliacion-areas',
        [ProyectoControlador::class, 'conciliarAreas'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('proyectos.ver')]
    );

    // -------------------------------------------------------------------------
    // API REST de Sectores Urbanísticos y Balance de Áreas (Microfase 3B)
    // Política: Autenticación obligatoria, Scope Territorial y Privilegio Granular
    // -------------------------------------------------------------------------
    $enrutador->get(
        '/api/proyectos/{id}/sectores',
        [SectorControlador::class, 'listarPorProyecto'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.ver')]
    );

    $enrutador->get(
        '/api/proyectos/{id}/balance-areas',
        [SectorControlador::class, 'balanceAreas'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.ver')]
    );

    $enrutador->post(
        '/api/proyectos/{id}/sectores',
        [SectorControlador::class, 'crear'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.crear')]
    );

    $enrutador->get(
        '/api/sectores/{id}',
        [SectorControlador::class, 'obtener'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.ver')]
    );

    $enrutador->put(
        '/api/sectores/{id}',
        [SectorControlador::class, 'actualizar'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.editar')]
    );

    $enrutador->patch(
        '/api/sectores/{id}/estado',
        [SectorControlador::class, 'cambiarEstado'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.cambiar_estado')]
    );

    $enrutador->get(
        '/api/sectores/{id}/precios',
        [SectorControlador::class, 'listarPrecios'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.ver')]
    );

    $enrutador->post(
        '/api/sectores/{id}/precios',
        [SectorControlador::class, 'ajustarPrecio'],
        [AutenticacionMiddleware::class, ScopeMiddleware::exigir(), AutorizacionMiddleware::exigir('sectores.precios')]
    );
};
