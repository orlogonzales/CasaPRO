<?php

declare(strict_types=1);

use App\Core\Vista;
use App\Core\GestorSesion;
use App\Servicios\MenuServicio;

$auth = GestorSesion::obtener('auth');
$usuarioId = (int) ($auth['usuario_id'] ?? 0);
$usuarioNombre = (string) ($auth['nombre_completo'] ?? $auth['username'] ?? 'Usuario');

try {
    $menuServicio = new MenuServicio();
    $arbolMenu = $menuServicio->obtenerArbolParaUsuario($usuarioId);
} catch (\Throwable) {
    $arbolMenu = [];
}

// Detección de ruta relativa actual
$uriCompleta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = dirname($scriptName);
$baseDir = str_replace('\\', '/', $baseDir);
if (str_ends_with($baseDir, '/public') && !str_starts_with($uriCompleta, $baseDir)) {
    $baseDir = substr($baseDir, 0, -7);
}
if ($baseDir === '/' || $baseDir === '.') {
    $baseDir = '';
}
$rutaActual = trim(substr($uriCompleta, strlen($baseDir)), '/');
if ($rutaActual === '' || $rutaActual === 'index.php') {
    $rutaActual = 'inicio';
}

$coincideRuta = function (?string $rutaOpcion, string $rutaActual): bool {
    if (empty($rutaOpcion)) {
        return false;
    }
    $rOpcion = trim($rutaOpcion, '/');
    $rActual = trim($rutaActual, '/');
    if ($rOpcion === 'inicio' && ($rActual === '' || $rActual === 'inicio')) {
        return true;
    }
    if ($rActual === $rOpcion || str_starts_with($rActual, $rOpcion . '/')) {
        return true;
    }
    return false;
};

$contieneActivo = function (array $nodo) use (&$contieneActivo, $coincideRuta, $rutaActual): bool {
    if ($nodo['tipo'] === 'ENLACE' && !empty($nodo['ruta'])) {
        if ($coincideRuta($nodo['ruta'], $rutaActual)) {
            return true;
        }
    }
    foreach ($nodo['hijos'] ?? [] as $hijo) {
        if ($contieneActivo($hijo)) {
            return true;
        }
    }
    return false;
};

// Determinar qué nodo raíz debe estar activo por defecto
$raizActivaId = null;
foreach ($arbolMenu as $raiz) {
    if ($contieneActivo($raiz)) {
        $raizActivaId = (int) $raiz['id'];
        break;
    }
}
if ($raizActivaId === null && !empty($arbolMenu)) {
    $raizActivaId = (int) $arbolMenu[0]['id'];
}
?>
<!-- Menu Navigation starts -->
<nav class="app-navbar">
    <!-- Mini Navegación Lateral (semi-side-nav) Nivel 0 -->
    <div class="semi-side-nav">
        <div class="py-4">
           <a href="<?= Vista::url() ?>" class="bg-white h-40 w-40 d-flex-center b-r-12 mx-auto text-decoration-none shadow-sm" data-bs-toggle="tooltip" data-bs-placement="right" title="CasaPRO - Inicio" aria-label="CasaPRO - Inicio">
                   <span class="f-w-700 text-primary">CP</span>
           </a>
        </div>

        <ul class="navbar-menu-list" role="tablist">
            <?php if (!empty($arbolMenu)): ?>
                <?php foreach ($arbolMenu as $raiz): ?>
                    <?php
                        $esRaizActiva = ((int) $raiz['id'] === $raizActivaId);
                        $tituloRaiz = (string) ($raiz['titulo'] ?? $raiz['etiqueta'] ?? '');
                    ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= $esRaizActiva ? 'active' : '' ?>" data-target="menu_<?= Vista::e($raiz['codigo']) ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="<?= Vista::e($tituloRaiz) ?>" aria-label="<?= Vista::e($tituloRaiz) ?>">
                            <i class="<?= Vista::e(!empty($raiz['icono']) ? $raiz['icono'] : 'fa-solid fa-folder') ?>"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback accesible con tooltips cuando no hay menú activo -->
                <li class="nav-item">
                    <a href="#" class="nav-link active" data-target="menuInicio" data-bs-toggle="tooltip" data-bs-placement="right" title="Inicio" aria-label="Inicio">
                        <i class="fa-solid fa-house"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuIdentidad" data-bs-toggle="tooltip" data-bs-placement="right" title="Identidad y Accesos" aria-label="Identidad y Accesos">
                        <i class="fa-solid fa-users"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuCatastro" data-bs-toggle="tooltip" data-bs-placement="right" title="Catastro y Lotes" aria-label="Catastro y Lotes">
                        <i class="fa-solid fa-location-dot"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuComercial" data-bs-toggle="tooltip" data-bs-placement="right" title="Comercial y Ventas" aria-label="Comercial y Ventas">
                        <i class="fa-solid fa-briefcase"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuTesoreria" data-bs-toggle="tooltip" data-bs-placement="right" title="Tesorería y Finanzas" aria-label="Tesorería y Finanzas">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-target="menuOperaciones" data-bs-toggle="tooltip" data-bs-placement="right" title="Operaciones y Postventa" aria-label="Operaciones y Postventa">
                        <i class="fa-solid fa-house-circle-check"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="mt-auto pb-3">
            <span class="bg-primary-800 h-45 w-45 d-flex-center b-r-30 position-relative mx-auto cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="right" title="<?= Vista::e($usuarioNombre) ?>" aria-label="<?= Vista::e($usuarioNombre) ?>">
               <img alt="avatar" class="img-fluid b-r-30" src="<?= Vista::asset('images/avatar/01.png') ?>">
               <span class="position-absolute top-0 end-0 p-1 bg-gradient-success border border-light rounded-circle"></span>
           </span>
        </div>
    </div>

    <!-- Navegación Lateral Detallada (main-side-nav) Niveles 1 y 2 -->
    <div class="main-side-nav">
        <div>
            <div class="px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                <a class="logo d-inline-block text-decoration-none" href="<?= Vista::url() ?>">
                    <h4 class="mb-0 fw-bold text-primary tracking-wide">Casa<span class="text-dark">PRO</span></h4>
                </a>
                <span class="w-30 h-30 d-none bg-gradient-danger b-r-8 cursor-pointer side-toggle d-flex-center">
                    <i class="fa-solid fa-xmark f-s-18 text-white"></i>
                </span>
            </div>

            <div class="side-search p-3 pt-1">
                <div class="position-relative">
                    <input aria-label="Buscar en el sistema" class="form-control py-2 b-r-18" placeholder="Buscar..." type="search">
                    <i class="fa-solid fa-magnifying-glass f-s-18 text-secondary"></i>
                </div>
            </div>
        </div>

        <div class="nav-wrapper app-scroll app-simple-bar">
            <div class="main-side-menu">
                <?php if (!empty($arbolMenu)): ?>
                    <?php foreach ($arbolMenu as $raiz): ?>
                        <?php
                            $esRaizActiva = ((int) $raiz['id'] === $raizActivaId);
                            $tituloRaiz = (string) ($raiz['titulo'] ?? $raiz['etiqueta'] ?? '');
                        ?>
                        <ul class="main-menu" id="menu_<?= Vista::e($raiz['codigo']) ?>" style="<?= $esRaizActiva ? 'display: block;' : 'display: none;' ?>">
                            <?php if ($raiz['tipo'] === 'ENLACE'): ?>
                                <li class="no-sub">
                                    <a href="<?= Vista::url($raiz['ruta'] ?? '') ?>" class="<?= $coincideRuta($raiz['ruta'] ?? '', $rutaActual) ? 'active' : '' ?>">
                                        <?php if (!empty($raiz['icono'])): ?><i class="<?= Vista::e($raiz['icono']) ?> me-2"></i><?php endif; ?>
                                        <?= Vista::e($tituloRaiz) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <?php foreach ($raiz['hijos'] ?? [] as $hijo1): ?>
                                    <?php $tituloHijo1 = (string) ($hijo1['titulo'] ?? $hijo1['etiqueta'] ?? ''); ?>
                                    <?php if ($hijo1['tipo'] === 'ENLACE'): ?>
                                        <li class="no-sub">
                                            <a href="<?= Vista::url($hijo1['ruta'] ?? '') ?>" class="<?= $coincideRuta($hijo1['ruta'] ?? '', $rutaActual) ? 'active' : '' ?>">
                                                <?php if (!empty($hijo1['icono'])): ?><i class="<?= Vista::e($hijo1['icono']) ?> me-2"></i><?php endif; ?>
                                                <?= Vista::e($tituloHijo1) ?>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <?php $hijo1Activo = $contieneActivo($hijo1); ?>
                                        <li>
                                            <a aria-expanded="<?= $hijo1Activo ? 'true' : 'false' ?>" data-bs-toggle="collapse" href="#submenu_<?= Vista::e($hijo1['codigo']) ?>" class="<?= $hijo1Activo ? '' : 'collapsed' ?>">
                                                <?php if (!empty($hijo1['icono'])): ?><i class="<?= Vista::e($hijo1['icono']) ?> me-2"></i><?php endif; ?>
                                                <?= Vista::e($tituloHijo1) ?>
                                            </a>
                                            <ul class="collapse <?= $hijo1Activo ? 'show' : '' ?>" id="submenu_<?= Vista::e($hijo1['codigo']) ?>">
                                                <?php foreach ($hijo1['hijos'] ?? [] as $hijo2): ?>
                                                    <?php $tituloHijo2 = (string) ($hijo2['titulo'] ?? $hijo2['etiqueta'] ?? ''); ?>
                                                    <li>
                                                        <a href="<?= Vista::url($hijo2['ruta'] ?? '') ?>" class="<?= $coincideRuta($hijo2['ruta'] ?? '', $rutaActual) ? 'active' : '' ?>">
                                                            <?php if (!empty($hijo2['icono'])): ?><i class="<?= Vista::e($hijo2['icono']) ?> me-2"></i><?php endif; ?>
                                                            <?= Vista::e($tituloHijo2) ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Menú de fallback estático accesible cuando no hay árbol dinámico -->
                    <ul class="main-menu" id="menuInicio" style="display: block;">
                        <li class="no-sub">
                            <a href="<?= Vista::url() ?>">
                                <i class="fa-solid fa-house me-2"></i>
                                Inicio
                            </a>
                        </li>
                    </ul>
                    <ul class="main-menu" id="menuIdentidad" style="display: none;">
                        <li class="no-sub">
                            <a href="<?= Vista::url('personas') ?>">
                                <i class="fa-solid fa-id-card me-2"></i>
                                Padrón de Personas
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= Vista::url('usuarios') ?>">
                                <i class="fa-solid fa-user-shield me-2"></i>
                                Usuarios
                            </a>
                        </li>
                        <li class="no-sub">
                            <a href="<?= Vista::url('menu') ?>">
                                <i class="fa-solid fa-bars me-2"></i>
                                Menú y Navegación
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<!-- Menu Navigation ends -->
