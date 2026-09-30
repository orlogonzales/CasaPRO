<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<!-- jQuery de Alina (requerido para el preloader y compatibilidad de plugins) -->
<script src="<?= Vista::asset('js/jquery-3.6.3.min.js') ?>"></script>

<!-- Bootstrap 5 Bundle JS oficial de Alina -->
<script src="<?= Vista::asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>

<!-- Simplebar JS oficial de Alina -->
<script src="<?= Vista::asset('vendor/simplebar/simplebar.js') ?>"></script>

<!-- App JS oficial de Alina (manejo de sidebar, navegación, modo oscuro y responsive) -->
<script src="<?= Vista::asset('js/script.js') ?>"></script>

<!-- Scripts específicos inyectados por cada módulo/pantalla -->
<?php if (!empty($jsAdicionales)): ?>
    <?php foreach ($jsAdicionales as $archivoJs): ?>
        <script src="<?= Vista::asset($archivoJs) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
