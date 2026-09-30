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
                    <!-- Selector de Ámbito / Empresa Activa -->
                    <li class="d-none d-md-block">
                        <span class="badge bg-light-primary text-primary px-3 py-2 b-r-12 f-s-13 d-flex align-items-center gap-1">
                            <i class="fa-solid fa-building f-s-16"></i> Empresa Principal / Proyecto Matriz
                        </span>
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
