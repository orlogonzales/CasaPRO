<?php

declare(strict_types=1);

use App\Core\Vista;
/**
 * Vista de prueba inicial para verificación de la Plantilla Maestra Alina en CasaPRO.
 *
 * @var array $datosSistema Información del entorno y estado de la plataforma
 */
?>
<!-- Fila de bienvenida y métricas de infraestructura -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm b-r-16 overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success px-3 py-2 b-r-8 f-s-12">
                                <i class="ti ti-check me-1"></i> <?= Vista::e($datosSistema['estado'] ?? 'Operativo') ?>
                            </span>
                            <span class="badge bg-light-primary text-primary px-3 py-2 b-r-8 f-s-12">
                                <?= Vista::e($datosSistema['entorno'] ?? 'Local') ?>
                            </span>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Plantilla Maestra Oficial de CasaPRO</h3>
                        <p class="text-secondary mb-0">
                            Construida estrictamente a partir de la anatomía real de <code>blank.html</code> de Alina Bootstrap 5.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="https://localhost/app.casa-pro/admin-dashboard/documentation/index.html" target="_blank" class="btn btn-outline-secondary b-r-10 d-flex align-items-center gap-2">
                            <i class="ti ti-book f-s-18"></i> Documentación Alina
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fila de 4 tarjetas de verificación técnica (basada en el grid de blank.html) -->
<div class="row g-4">
    <div class="col-md-6 col-xxl-3">
        <div class="card equal-card border-0 shadow-sm b-r-16 h-100">
            <div class="card-body p-4">
                <div class="h-45 w-45 d-flex-center b-r-12 bg-light-primary text-primary mb-3">
                    <i class="ti ti-brand-php f-s-24"></i>
                </div>
                <h5 class="card-title fw-bold text-dark mb-2">PHP 8.3 Nativo</h5>
                <p class="card-text text-secondary f-s-14">
                    Motor MVC desacoplado con tipado estricto, <code>declare(strict_types=1);</code>, sin sobrecarga de frameworks externos.
                </p>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-secondary f-s-13">Versión activa:</span>
                    <span class="fw-bold text-dark f-s-13"><?= Vista::e($datosSistema['phpVersion']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xxl-3">
        <div class="card equal-card border-0 shadow-sm b-r-16 h-100">
            <div class="card-body p-4">
                <div class="h-45 w-45 d-flex-center b-r-12 bg-light-success text-success mb-3">
                    <i class="ti ti-layout-dashboard f-s-24"></i>
                </div>
                <h5 class="card-title fw-bold text-dark mb-2">Alina Bootstrap 5</h5>
                <p class="card-text text-secondary f-s-14">
                    Derivada fielmente de <code>blank.html</code>. Utiliza Lexend Deca, Tabler Icons, SimpleBar y CSS compilado oficial.
                </p>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-secondary f-s-13">Referencia:</span>
                    <span class="badge bg-light-dark text-dark f-s-12">Solo lectura</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xxl-3">
        <div class="card equal-card border-0 shadow-sm b-r-16 h-100">
            <div class="card-body p-4">
                <div class="h-45 w-45 d-flex-center b-r-12 bg-light-warning text-warning mb-3">
                    <i class="ti ti-shield-check f-s-24"></i>
                </div>
                <h5 class="card-title fw-bold text-dark mb-2">Gobernanza y RBAC</h5>
                <p class="card-text text-secondary f-s-14">
                    Arquitectura preparada para autorización multidimensional (Privilegios + Scopes territoriales), CSRF y auditoría.
                </p>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-secondary f-s-13">Regla rectora:</span>
                    <span class="badge bg-light-warning text-dark f-s-12">Anti-Invención</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xxl-3">
        <div class="card equal-card border-0 shadow-sm b-r-16 h-100">
            <div class="card-body p-4">
                <div class="h-45 w-45 d-flex-center b-r-12 bg-light-info text-info mb-3">
                    <i class="ti ti-device-laptop f-s-24"></i>
                </div>
                <h5 class="card-title fw-bold text-dark mb-2">Responsive & Temas</h5>
                <p class="card-text text-secondary f-s-14">
                    Navegación adaptable a pantallas de escritorio, laptops, tablets y móviles con soporte nativo de modo oscuro.
                </p>
                <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-secondary f-s-13">Modo oscuro:</span>
                    <span class="badge bg-dark text-white f-s-12">Integrado</span>
                </div>
            </div>
        </div>
    </div>
</div>
