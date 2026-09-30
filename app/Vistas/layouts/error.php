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

    <!-- Fuentes oficiales CasaPRO: Fira Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans+Extra+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Font Awesome oficial (sistema iconográfico único de CasaPRO) -->
    <link href="<?= Vista::asset('vendor/fontawesome/css/all.css') ?>" rel="stylesheet" type="text/css">

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
