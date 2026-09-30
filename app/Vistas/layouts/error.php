<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Layout oficial de CasaPRO para páginas de error (400, 403, 404, 500, 503).
 * Basado estrictamente en la estructura y estilos .error-container de Alina.
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="CasaPRO — Sistema Integral de Gestión Inmobiliaria" name="description">
    <link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="icon" type="image/x-icon">
    <link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="shortcut icon" type="image/x-icon">
    <title><?= Vista::e($tituloPagina ?? 'Error | CasaPRO') ?></title>

    <!-- Fuentes oficiales de Alina -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">

    <!-- Iconos Tabler -->
    <link href="<?= Vista::asset('vendor/tabler-icons/tabler-icons.css') ?>" rel="stylesheet" type="text/css">

    <!-- Bootstrap 5 CSS -->
    <link href="<?= Vista::asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">

    <!-- Estilos de Alina -->
    <link href="<?= Vista::asset('css/style.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= Vista::asset('css/responsive.css') ?>" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="error-container p-0">
        <div class="container">
            <div>
                <?= $contenido ?? '' ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="<?= Vista::asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
