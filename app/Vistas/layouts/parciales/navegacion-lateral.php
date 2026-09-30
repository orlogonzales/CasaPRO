<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<!-- Menu Navigation starts -->
<nav class="app-navbar">
    <!-- Mini Navegación Lateral (semi-side-nav) Nivel 1 -->
    <div class="semi-side-nav">
        <div class="py-4">
           <a href="<?= Vista::url() ?>" class="bg-white h-40 w-40 d-flex-center b-r-12 mx-auto text-decoration-none shadow-sm">
                   <span class="f-w-700 text-primary">CP</span>
           </a>
        </div>

        <ul class="navbar-menu-list" role="tablist">
            <li class="nav-item">
                <a href="#" class="nav-link active" data-target="menuInicio" title="Inicio">
                    <i class="ti ti-smart-home"></i>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" data-target="menuIdentidad" title="Identidad y Accesos">
                    <i class="ti ti-users"></i>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" data-target="menuCatastro" title="Catastro y Lotes">
                    <i class="ti ti-map-pin"></i>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" data-target="menuComercial" title="Comercial y Ventas">
                    <i class="ti ti-briefcase"></i>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" data-target="menuTesoreria" title="Tesorería y Finanzas">
                    <i class="ti ti-cash"></i>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link" data-target="menuOperaciones" title="Operaciones y Postventa">
                    <i class="ti ti-home-check"></i>
                </a>
            </li>
        </ul>

        <div class="mt-auto pb-3">
            <span class="bg-primary-800 h-45 w-45 d-flex-center b-r-30 position-relative mx-auto cursor-pointer" title="Usuario activo">
               <img alt="avatar" class="img-fluid b-r-30" src="<?= Vista::asset('images/avatar/01.png') ?>">
               <span class="position-absolute top-0 end-0 p-1 bg-gradient-success border border-light rounded-circle"></span>
           </span>
        </div>
    </div>

    <!-- Navegación Lateral Detallada (main-side-nav) Niveles 2 y 3 -->
    <div class="main-side-nav">
        <div>
            <div class="px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                <a class="logo d-inline-block text-decoration-none" href="<?= Vista::url() ?>">
                    <h4 class="mb-0 fw-bold text-primary tracking-wide">Casa<span class="text-dark">PRO</span></h4>
                </a>
                <span class="w-30 h-30 d-none bg-gradient-danger b-r-8 cursor-pointer side-toggle d-flex-center">
                    <i class="ti ti-x f-s-18 text-white"></i>
                </span>
            </div>

            <div class="side-search p-3 pt-1">
                <div class="position-relative">
                    <input aria-label="Buscar en el sistema" class="form-control py-2 b-r-18" placeholder="Buscar..." type="search">
                    <i class="ti ti-command f-s-20 text-secondary"></i>
                </div>
            </div>
        </div>

        <div class="nav-wrapper app-scroll app-simple-bar">
            <div class="main-side-menu">
                <!-- Menú 1: Inicio y Tableros -->
                <ul class="main-menu" id="menuInicio">
                    <li>
                        <a aria-expanded="true" data-bs-toggle="collapse" href="#submenuDashboard">
                            Tableros de Control
                            <span class="badge bg-gradient-danger badge-dashboard badge-notification ms-2">Activo</span>
                        </a>
                        <ul class="collapse show" id="submenuDashboard">
                            <li><a href="<?= Vista::url() ?>" class="active">Resumen General</a></li>
                            <li><a href="#">Indicadores Comerciales</a></li>
                            <li><a href="#">Resumen de Tesorería</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Menú 2: Identidad y Accesos -->
                <ul class="main-menu" id="menuIdentidad" style="display: none;">
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuPersonas">
                            Gestión de Personas
                        </a>
                        <ul class="collapse" id="submenuPersonas">
                            <li><a href="#">Padrón de Personas</a></li>
                            <li><a href="#">Registro de Persona</a></li>
                        </ul>
                    </li>
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuSeguridad">
                            Seguridad y RBAC
                        </a>
                        <ul class="collapse" id="submenuSeguridad">
                            <li><a href="#">Usuarios</a></li>
                            <li><a href="#">Roles y Privilegios</a></li>
                            <li><a href="#">Ámbitos (Scopes)</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Menú 3: Catastro y Lotes -->
                <ul class="main-menu" id="menuCatastro" style="display: none;">
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuProyectos">
                            Estructura Catastral
                        </a>
                        <ul class="collapse" id="submenuProyectos">
                            <li><a href="#">Proyectos</a></li>
                            <li><a href="#">Sectores y Etapas</a></li>
                            <li><a href="#">Manzanas</a></li>
                            <li><a href="#">Inventario de Lotes</a></li>
                        </ul>
                    </li>
                    <li class="no-sub">
                        <a href="#">
                            Visor GIS (Planimetría)
                        </a>
                    </li>
                </ul>

                <!-- Menú 4: Comercial y Ventas -->
                <ul class="main-menu" id="menuComercial" style="display: none;">
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuCrm">
                            CRM Comercial
                        </a>
                        <ul class="collapse" id="submenuCrm">
                            <li><a href="#">Prospectos</a></li>
                            <li><a href="#">Bitácora de Visitas</a></li>
                            <li><a href="#">Simulador y Cotizaciones</a></li>
                            <li><a href="#">Reservas</a></li>
                        </ul>
                    </li>
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuVentas">
                            Contratación
                        </a>
                        <ul class="collapse" id="submenuVentas">
                            <li><a href="#">Ventas Formalizadas</a></li>
                            <li><a href="#">Cronogramas de Cuotas</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Menú 5: Tesorería y Finanzas -->
                <ul class="main-menu" id="menuTesoreria" style="display: none;">
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuCajas">
                            Caja y Recaudación
                        </a>
                        <ul class="collapse" id="submenuCajas">
                            <li><a href="#">Apertura y Cierre de Caja</a></li>
                            <li><a href="#">Cobranza de Cuotas</a></li>
                            <li><a href="#">Recibos Emitidos</a></li>
                        </ul>
                    </li>
                    <li class="no-sub">
                        <a href="#">
                            Auditoría Financiera
                        </a>
                    </li>
                </ul>

                <!-- Menú 6: Operaciones y Postventa -->
                <ul class="main-menu" id="menuOperaciones" style="display: none;">
                    <li>
                        <a aria-expanded="false" data-bs-toggle="collapse" href="#submenuPostventa">
                            Entregas y Garantías
                        </a>
                        <ul class="collapse" id="submenuPostventa">
                            <li><a href="#">Actas de Entrega</a></li>
                            <li><a href="#">Tickets de Postventa</a></li>
                            <li><a href="#">Libro de Reclamaciones</a></li>
                        </ul>
                    </li>
                    <li class="no-sub">
                        <a href="#">
                            Módulo APV
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<!-- Menu Navigation ends -->
