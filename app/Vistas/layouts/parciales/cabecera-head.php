<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<meta content="IE=edge" http-equiv="X-UA-Compatible">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta content="CasaPRO — Plataforma de Gestión Inmobiliaria Integral" name="description">
<meta content="CasaPRO" name="author">
<link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="icon" type="image/x-icon">
<link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="shortcut icon" type="image/x-icon">
<title><?= Vista::e($tituloPagina ?? 'CasaPRO — Gestión Inmobiliaria') ?></title>

<!-- Fonts: Lexend Deca oficial de Alina -->
<link href="https://fonts.googleapis.com" rel="preconnect">
<link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">

<!-- Tabler Icons oficial de Alina -->
<link href="<?= Vista::asset('vendor/tabler-icons/tabler-icons.css') ?>" rel="stylesheet" type="text/css">

<!-- Bootstrap 5 CSS oficial de Alina -->
<link href="<?= Vista::asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">

<!-- Simplebar CSS oficial de Alina -->
<link href="<?= Vista::asset('vendor/simplebar/simplebar.css') ?>" rel="stylesheet" type="text/css">

<!-- CSS Adicionales específicos por vista/módulo -->
<?php if (!empty($cssAdicionales)): ?>
    <?php foreach ($cssAdicionales as $archivoCss): ?>
        <link href="<?= Vista::asset($archivoCss) ?>" rel="stylesheet" type="text/css">
    <?php endforeach; ?>
<?php endif; ?>

<!-- App CSS Alina -->
<link href="<?= Vista::asset('css/style.css') ?>" rel="stylesheet" type="text/css">

<!-- Responsive CSS Alina -->
<link href="<?= Vista::asset('css/responsive.css') ?>" rel="stylesheet" type="text/css">
