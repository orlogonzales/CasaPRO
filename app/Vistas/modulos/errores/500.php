<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de error 500 (Internal Server Error).
 * Basada en error_500.html de Alina con adaptación al español y código de correlación seguro.
 */
?>
<div>
    <img alt="Error 500" class="img-fluid" src="<?= Vista::asset('images/error/error-500.png') ?>">
</div>

<div class="mb-3">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <h4 class="text-dark fw-bold mt-4">Error Interno del Servidor</h4>
            <p class="text-center text-secondary f-w-500 mt-2">
                <?= Vista::e($mensaje ?? 'El servidor encontró una condición inesperada que le impidió completar la solicitud. Nuestro equipo técnico ha sido notificado.') ?>
            </p>
            <?php if (!empty($idCorrelacion)): ?>
                <p class="text-muted small mt-2">
                    Código de seguimiento técnico: <code><?= Vista::e($idCorrelacion) ?></code>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<a class="btn btn-lg app-btn bg-gradient-success text-white" href="<?= Vista::url() ?>" role="button">
    <i class="fa-solid fa-arrow-left f-s-18 align-text-top me-1"></i> Volver al Inicio
</a>
