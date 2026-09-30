<?php

declare(strict_types=1);

use App\Core\Vista;
?>
<!-- Breadcrumbs oficiales de Alina -->
<?php if (!empty($migaPan)): ?>
<div>
    <ul class="app-breadcrumbs">
        <?php foreach ($migaPan as $indice => $item): ?>
            <?php $esUltimo = ($indice === array_key_last($migaPan)); ?>
            <li class="<?= $esUltimo ? 'active' : '' ?>">
                <?php if ($esUltimo || empty($item['url'])): ?>
                    <span class="f-s-14 f-w-500"><?= Vista::e($item['texto']) ?></span>
                <?php else: ?>
                    <a class="f-s-14 f-w-500" href="<?= Vista::e($item['url']) ?>">
                        <span><?= Vista::e($item['texto']) ?></span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
