<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de error 400 (Bad Request).
 * Basada en error_400.html de Alina con adaptación al español.
 */
?>
<div>
    <img alt="Error 400" class="img-fluid" src="<?= Vista::asset('images/error/error-400.png') ?>">
</div>

<div class="mb-3">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h4 class="text-dark fw-bold mt-4">Solicitud Incorrecta</h4>
            <p class="text-center text-secondary f-w-500 mt-2">
                <?= Vista::e($mensaje ?? 'El servidor no puede procesar la solicitud debido a un formato erróneo o parámetros no válidos.') ?>
            </p>
        </div>
    </div>
</div>

<a class="btn btn-lg app-btn bg-gradient-warning text-white" href="<?= Vista::url() ?>" role="button">
    <i class="fa-solid fa-arrow-left f-s-18 align-text-top me-1"></i> Volver al Inicio
</a>
