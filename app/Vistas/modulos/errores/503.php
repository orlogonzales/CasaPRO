<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de error 503 (Service Unavailable).
 * Basada en error_503.html de Alina con adaptación al español.
 */
?>
<div>
    <img alt="Error 503" class="img-fluid" src="<?= Vista::asset('images/error/error-503.png') ?>">
</div>

<div class="mb-3">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h4 class="text-dark fw-bold mt-4">Servicio Temporalmente No Disponible</h4>
            <p class="text-center text-secondary f-w-500 mt-2">
                <?= Vista::e($mensaje ?? 'El sistema se encuentra temporalmente fuera de servicio por mantenimiento o alta demanda. Por favor, reintente en unos minutos.') ?>
            </p>
        </div>
    </div>
</div>

<a class="btn btn-lg app-btn bg-gradient-primary text-white" href="<?= Vista::url() ?>" role="button">
    <i class="ti ti-arrow-bar-to-left f-s-20 align-text-top me-1"></i> Volver al Inicio
</a>
