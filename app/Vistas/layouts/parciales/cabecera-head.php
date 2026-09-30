<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<meta content="IE=edge" http-equiv="X-UA-Compatible">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta content="CasaPRO — Plataforma de Gestión Inmobiliaria Integral" name="description">
<meta content="CasaPRO" name="author">
<meta name="csrf-token" content="<?= Vista::csrfToken() ?>">
<link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="icon" type="image/x-icon">
<link href="<?= Vista::asset('images/logo/favicon.png') ?>" rel="shortcut icon" type="image/x-icon">
<title><?= Vista::e($tituloPagina ?? 'CasaPRO — Gestión Inmobiliaria') ?></title>

<!-- Fonts: Fira Sans (Normal, Condensed, Extra Condensed) oficial de CasaPRO -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans+Extra+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Fira+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

<!-- Font Awesome oficial de Alina (sistema iconográfico único de CasaPRO) -->
<link href="<?= Vista::asset('vendor/fontawesome/css/all.css') ?>" rel="stylesheet" type="text/css">

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
