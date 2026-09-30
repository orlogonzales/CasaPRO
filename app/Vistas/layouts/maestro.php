<?php

declare(strict_types=1);

/**
 * Plantilla Maestra Oficial de CasaPRO basada en Alina Bootstrap 5 (blank.html).
 *
 * Variables disponibles:
 * @var string $contenido Código HTML inyectado por la vista actual
 * @var string $tituloPagina Título para la etiqueta <title> y el encabezado
 * @var array $migaPan Array asociativo con la jerarquía de navegación actual
 * @var array $cssAdicionales Archivos CSS específicos del módulo
 * @var array $jsAdicionales Archivos JS específicos del módulo
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/parciales/cabecera-head.php'; ?>
</head>
<body>

<div class="app-wrapper">
    <!-- Preloader animado Alina -->
    <?php require __DIR__ . '/parciales/preloader.php'; ?>

    <!-- Barra de navegación lateral (2 niveles: iconos + acordeón) -->
    <?php require __DIR__ . '/parciales/navegacion-lateral.php'; ?>

    <!-- Contenedor principal de la aplicación -->
    <div class="app-content">
        <!-- Barra superior de encabezado -->
        <?php require __DIR__ . '/parciales/barra-superior.php'; ?>

        <!-- Ruta de navegación / Breadcrumbs -->
        <?php require __DIR__ . '/parciales/migas-pan.php'; ?>

        <!-- Área de contenido dinámico de la vista -->
        <main>
            <div class="container-fluid">
                <?= $contenido ?>
            </div>
        </main>

        <!-- Pie de página y botón tap-to-top -->
        <?php require __DIR__ . '/parciales/pie-pagina.php'; ?>
    </div>
</div>

<!-- Scripts globales y específicos -->
<?php require __DIR__ . '/parciales/pie-scripts.php'; ?>

</body>
</html>
