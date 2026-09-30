<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de error 404 (Not Found).
 * Basada en error_404.html de Alina con adaptación al español.
 */
?>
<div>
    <img alt="Error 404" class="img-fluid" src="<?= Vista::asset('images/error/error-404.png') ?>">
</div>

<div class="mb-3">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h4 class="text-dark fw-bold mt-4">Página No Encontrada</h4>
            <p class="text-center text-secondary f-w-500 mt-2">
                <?= Vista::e($mensaje ?? 'La página o recurso que busca no existe, ha sido movido o no se encuentra disponible temporalmente.') ?>
            </p>
        </div>
    </div>
</div>

<a class="btn btn-lg app-btn bg-gradient-primary text-white" href="<?= Vista::url() ?>" role="button">
    <i class="fa-solid fa-arrow-left f-s-18 align-text-top me-1"></i> Volver al Inicio
</a>
