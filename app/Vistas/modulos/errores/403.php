<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de error 403 (Forbidden).
 * Basada en error_403.html de Alina con adaptación al español.
 */
?>
<div>
    <img alt="Error 403" class="img-fluid" src="<?= Vista::asset('images/error/error-403.png') ?>">
</div>

<div class="mb-3">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h4 class="text-dark fw-bold mt-4">Acceso Denegado</h4>
            <p class="text-center text-secondary f-w-500 mt-2">
                <?= Vista::e($mensaje ?? 'No cuenta con los permisos o privilegios suficientes para acceder a este recurso.') ?>
            </p>
        </div>
    </div>
</div>

<a class="btn btn-lg app-btn bg-gradient-danger text-white" href="<?= Vista::url() ?>" role="button">
    <i class="fa-solid fa-arrow-left f-s-18 align-text-top me-1"></i> Volver al Inicio
</a>
