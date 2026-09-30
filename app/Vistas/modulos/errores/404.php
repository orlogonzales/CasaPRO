<?php

declare(strict_types=1);

use App\Core\Vista;
/**
 * Vista de error 404 basada en error_404.html de Alina.
 */
?>
<div class="row justify-content-center text-center py-5">
    <div class="col-lg-8">
        <div class="mb-4">
            <img alt="Error 404" class="img-fluid" style="max-height: 320px;" src="<?= Vista::asset('images/error/error-404.png') ?>">
        </div>
        <h3 class="fw-bold text-dark mb-2">Página o recurso no encontrado</h3>
        <p class="text-secondary mb-4">
            La ruta solicitada <code><?= Vista::e($rutaSolicitada ?? '') ?></code> no existe o no se encuentra disponible.
        </p>
        <a class="btn btn-primary b-r-12 px-4 py-2" href="<?= Vista::url() ?>">
            <i class="ti ti-arrow-left me-1"></i> Volver al Inicio
        </a>
    </div>
</div>
