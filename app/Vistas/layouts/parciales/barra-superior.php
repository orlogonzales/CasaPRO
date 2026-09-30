<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<!-- Header Section starts -->
<header class="header-main">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-6 head-left">
                <div class="d-flex align-items-center gap-3">
                    <span class="cursor-pointer main-side-toggle" title="Alternar menú lateral">
                       <i class="fa-solid fa-bars f-s-20 text-secondary"></i>
                    </span>
                    <h4 class="txt-ellipsis-2 mb-0"><?= Vista::e($tituloPagina ?? 'Panel de Control') ?></h4>
                </div>
            </div>
            <div class="col-6 head-right">
                <ul class="d-flex gap-2 align-items-center justify-content-end mb-0 list-unstyled">
                    <!-- Selector Corporativo de Empresa Activa (Microfase 2D) -->
                    <?php
                    $empresasLista = $empresasDisponibles ?? [];
                    $totalEmps = $totalEmpresas ?? count($empresasLista);
                    $activaData = $empresaActiva ?? null;
                    $activaId = $empresaActivaId ?? null;
                    ?>
                    <li class="head-selector-empresa" id="contenedorSelectorEmpresa">
                        <?php if ($totalEmps === 0): ?>
                            <!-- Escenario 6: Sin empresa asignada (Usuario huérfano) -->
                            <span class="badge bg-light-danger text-danger px-3 py-2 b-r-12 f-s-13 d-flex align-items-center gap-2 cursor-default"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="No cuenta con empresas activas asignadas en el sistema">
                                <i class="fa-solid fa-triangle-exclamation f-s-16"></i>
                                <span class="d-none d-sm-inline f-w-600">Sin empresa asignada</span>
                                <span class="d-inline d-sm-none f-s-11 f-w-600">Sin Empresa</span>
                            </span>

                        <?php elseif ($totalEmps === 1): ?>
                            <!-- Escenario 2: Empresa única asignada (Badge no interactivo) -->
                            <?php $empUnica = $empresasLista[0]; ?>
                            <span class="badge bg-light-primary text-primary px-3 py-2 b-r-12 f-s-13 d-flex align-items-center gap-2 cursor-default"
                                  data-bs-toggle="tooltip" data-bs-placement="bottom"
                                  data-bs-title="Empresa asignada única: <?= htmlspecialchars((string) ($empUnica['nombre_corto'] ?? $empUnica['codigo']), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-building f-s-16"></i>
                                <span class="d-none d-md-inline txt-ellipsis-1 f-w-600" style="max-width: 180px;">
                                    <?= htmlspecialchars((string) ($empUnica['nombre_corto'] ?? $empUnica['codigo']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="d-inline d-md-none f-s-12 f-w-600">
                                    <?= htmlspecialchars((string) $empUnica['codigo'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="badge bg-primary text-white f-s-10 b-r-8 ms-1">Única</span>
                            </span>

                        <?php else: ?>
                            <!-- Escenarios 1 y 3: Múltiples empresas o SUPERADMIN (Dropdown Alina interactivo) -->
                            <div class="dropdown">
                                <a href="#" class="badge bg-light-primary text-primary px-2 px-md-3 py-2 b-r-12 f-s-13 d-flex align-items-center gap-2 text-decoration-none cursor-pointer dropdown-toggle-empresa"
                                   data-bs-toggle="dropdown" aria-expanded="false" id="dropdownBotonEmpresa">
                                    <i class="fa-solid fa-building f-s-15"></i>
                                    <span class="d-none d-md-inline txt-ellipsis-1 f-w-600" style="max-width: 180px;" id="etiquetaEmpresaActivaDesktop">
                                        <?= htmlspecialchars((string) ($activaData['nombre_corto'] ?? 'Seleccionar Empresa'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="d-inline d-md-none f-s-12 f-w-600" id="etiquetaEmpresaActivaMovil">
                                        <?= htmlspecialchars((string) ($activaData['codigo'] ?? 'EMPRESA'), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <i class="fa-solid fa-chevron-down f-s-10 text-primary ms-1"></i>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow b-r-12 py-2"
                                    style="min-width: 290px; max-width: 90vw; max-height: 400px; overflow-y: auto;"
                                    id="menuListaEmpresas">
                                    <li>
                                        <div class="d-flex align-items-center justify-content-between px-3 py-1">
                                            <h6 class="dropdown-header text-uppercase f-s-11 text-secondary px-0 mb-0">Empresas Asignadas</h6>
                                            <span class="badge bg-light-secondary text-secondary f-s-10"><?= $totalEmps ?> activas</span>
                                        </div>
                                    </li>

                                    <?php if ($totalEmps > 5): ?>
                                        <!-- Buscador rápido en tiempo real para listas extensas -->
                                        <li class="px-3 py-2">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light border-0"><i class="fa-solid fa-magnifying-glass f-s-12 text-secondary"></i></span>
                                                <input type="text" class="form-control form-control-sm bg-light border-0 f-s-12"
                                                       id="buscadorEmpresasTopbar" placeholder="Buscar empresa..." autocomplete="off">
                                            </div>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                    <?php endif; ?>

                                    <?php foreach ($empresasLista as $emp): ?>
                                        <?php $esActiva = ($activaId !== null && (int) $emp['id'] === (int) $activaId); ?>
                                        <li class="item-empresa-wrapper">
                                            <a class="dropdown-item py-2 px-3 d-flex align-items-center justify-content-between cursor-pointer btn-conmutar-empresa <?= $esActiva ? 'active bg-light-primary text-primary fw-bold' : '' ?>"
                                               href="#"
                                               data-empresa-id="<?= (int) $emp['id'] ?>"
                                               data-empresa-codigo="<?= htmlspecialchars((string) $emp['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                                               data-empresa-nombre="<?= htmlspecialchars((string) $emp['nombre_corto'], ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="d-flex align-items-center gap-2 txt-ellipsis-1">
                                                    <i class="fa-solid fa-building f-s-13 <?= $esActiva ? 'text-primary' : 'text-secondary' ?>"></i>
                                                    <span class="f-s-13 txt-empresa-nombre"><?= htmlspecialchars((string) $emp['nombre_corto'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="badge bg-secondary-subtle text-secondary f-s-10"><?= htmlspecialchars((string) $emp['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <?php if ($esActiva): ?>
                                                    <i class="fa-solid fa-check text-primary f-s-13 ms-2"></i>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </li>

                    <!-- Modo Pantalla Completa -->
                    <li class="head-maximize-screen">
                        <span class="h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer" title="Pantalla completa">
                            <i class="fa-solid fa-expand"></i>
                        </span>
                    </li>

                    <!-- Modo Oscuro / Claro (Alina Dark Mode) -->
                    <li class="header-dark">
                        <div class="sun-logo h-40 w-40 d-flex-center b-r-50 head-icon cursor-pointer" title="Alternar tema oscuro/claro">
                            <i id="theme-icon" class="fa-solid fa-moon"></i>
                        </div>
                    </li>

                    <!-- Perfil de Usuario -->
                    <?php
                    $usuarioSesion = \App\Core\GestorSesion::obtener('auth');
                    $nombreUsuarioHeader = $usuarioSesion['nombre_completo'] ?? ($usuarioSesion['nombre_usuario'] ?? 'Administrador');
                    $nombreCortoHeader = $usuarioSesion['nombre_usuario'] ?? 'Superadmin';
                    ?>
                    <li class="dropdown">
                        <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="bg-primary-800 h-40 w-40 d-flex-center b-r-50 position-relative">
                                <img alt="avatar" class="img-fluid b-r-50" src="<?= Vista::asset('images/avatar/01.png') ?>">
                                <span class="position-absolute top-0 end-0 p-1 bg-gradient-success border border-light rounded-circle"></span>
                            </span>
                            <div class="d-none d-lg-block text-start">
                                <p class="mb-0 f-s-14 f-w-600 text-dark txt-ellipsis-1" style="max-width: 150px;"><?= htmlspecialchars((string) $nombreUsuarioHeader, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mb-0 f-s-12 text-secondary"><?= htmlspecialchars((string) $nombreCortoHeader, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm b-r-12 py-2">
                            <li><h6 class="dropdown-header text-uppercase f-s-11 text-secondary">Usuario Activo</h6></li>
                            <li><a class="dropdown-item py-2" href="#"><i class="fa-solid fa-user me-2"></i> Mi Perfil</a></li>
                            <li><a class="dropdown-item py-2" href="#"><i class="fa-solid fa-shield-halved me-2"></i> Seguridad</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form id="formCerrarSesionHeader" action="/logout" method="POST" class="d-none">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfServicio::obtenerToken(), ENT_QUOTES, 'UTF-8') ?>">
                                </form>
                                <a class="dropdown-item py-2 text-danger cursor-pointer" href="#" onclick="event.preventDefault(); document.getElementById('formCerrarSesionHeader').submit();">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
<!-- Header Section ends -->
