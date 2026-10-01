<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Ficha 360° del Proyecto Inmobiliario — Microfase 3A.
 * Pestañas nav-bottom-line y modales Bootstrap 5 oficiales de Alina.
 *
 * Variables disponibles:
 * @var array $proyecto Datos del proyecto activo
 * @var array $predios Lista de predios matrices vinculados
 * @var array $conciliacion Evaluación técnica de balance de áreas
 * @var array $catalogos Catálogos del padrón (departamentos, etc.)
 * @var string $tokenCsrf Token de seguridad CSRF
 */

$monedaSimbolo = ($proyecto['moneda'] ?? 'PEN') === 'USD' ? '$' : 'S/';
?>

<div class="row" id="contenedorFichaProyecto"
     data-proyecto-id="<?= (int) $proyecto['id'] ?>"
     data-empresa-id="<?= (int) $proyecto['empresa_id'] ?>"
     data-api-predios-url="<?= Vista::url("api/proyectos/{$proyecto['id']}/predios") ?>"
     data-api-conciliacion-url="<?= Vista::url("api/proyectos/{$proyecto['id']}/conciliacion-areas") ?>"
     data-api-provincias-url="<?= Vista::url('api/ubigeo/provincias') ?>"
     data-api-distritos-url="<?= Vista::url('api/ubigeo/distritos') ?>"
     data-csrf-token="<?= $tokenCsrf ?>">

    <!-- Cabecera de la Ficha 360° -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar avatar-lg bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="fa-solid fa-city f-s-24"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="mb-0 text-dark f-w-700"><?= htmlspecialchars((string) $proyecto['nombre']) ?></h4>
                                <span class="badge font-monospace bg-dark text-white f-s-12"><?= htmlspecialchars((string) $proyecto['codigo']) ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary f-s-12"><?= htmlspecialchars((string) $proyecto['tipo_proyecto']) ?></span>
                            </div>
                            <p class="text-secondary f-s-13 mb-0 mt-1">
                                <i class="fa-solid fa-location-dot me-1 text-danger"></i>
                                <?= htmlspecialchars((string) ($proyecto['distrito_nombre'] ?? '')) ?>, <?= htmlspecialchars((string) ($proyecto['provincia_nombre'] ?? '')) ?> - <?= htmlspecialchars((string) ($proyecto['departamento_nombre'] ?? '')) ?>
                                <span class="mx-2">|</span>
                                <i class="fa-solid fa-coins me-1 text-warning"></i> Moneda: <strong><?= htmlspecialchars((string) $proyecto['moneda']) ?> (<?= $monedaSimbolo ?>)</strong>
                            </p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <?php
                            $badgeEstado = match ($proyecto['estado'] ?? '') {
                                'PLANIFICACION' => 'bg-info text-dark',
                                'EN_VENTA'      => 'bg-success text-white',
                                'CONSOLIDADO'   => 'bg-primary text-white',
                                'CERRADO'       => 'bg-secondary text-white',
                                default         => 'bg-danger text-white'
                            };
                        ?>
                        <span class="badge <?= $badgeEstado ?> px-3 py-2 f-s-13 f-w-600">
                            <?= htmlspecialchars((string) $proyecto['estado']) ?>
                        </span>
                        <a href="<?= Vista::url('proyectos') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-arrow-left me-1"></i> Volver a Proyectos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pestañas de Navegación Alina (nav-bottom-line) -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header border-bottom p-0">
                <ul class="nav nav-tabs nav-bottom-line px-3" id="tabsFichaProyecto" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3" id="tab-predios-btn" data-bs-toggle="tab" data-bs-target="#tab-predios-pane" type="button" role="tab" aria-selected="true">
                            <i class="fa-solid fa-layer-group me-2"></i>Predios / Terrenos Matrices (<?= count($predios) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3" id="tab-conciliacion-btn" data-bs-toggle="tab" data-bs-target="#tab-conciliacion-pane" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-scale-balanced me-2"></i>Conciliación de Áreas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3" id="tab-datos-btn" data-bs-toggle="tab" data-bs-target="#tab-datos-pane" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-circle-info me-2"></i>Datos Generales y Ubicación
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3" id="tab-sectores-btn" data-bs-toggle="tab" data-bs-target="#tab-sectores-pane" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-map-location-dot me-2"></i>Sectores y Balance Urbano (<span id="conteoBadgeSectores"><?= count($sectores ?? []) ?></span>)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="contenidoTabsFicha">
                    <!-- ========================================================= -->
                    <!-- TAB 1: PREDIOS MATRICES (1:N)                             -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade show active" id="tab-predios-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="text-dark f-w-600 mb-0">Terrenos Matrices y Partidas Registrales de Origen</h6>
                                <p class="text-secondary f-s-12 mb-0">Unidades prediales que conforman física y jurídicamente el terreno matriz del proyecto.</p>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btnNuevoPredio">
                                <i class="fa-solid fa-plus me-1"></i> Agregar Predio Matriz
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100" id="tablaPrediosMatrices">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Denominación</th>
                                        <th scope="col">Partida Registral (SUNARP)</th>
                                        <th scope="col" class="text-end">Área Registral</th>
                                        <th scope="col" class="text-end">Área Topográfica</th>
                                        <th scope="col">Ubicación</th>
                                        <th scope="col" class="text-center">Estado</th>
                                        <th scope="col" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPrediosMatrices">
                                    <?php if (empty($predios)): ?>
                                        <tr id="filaSinPredios">
                                            <td colspan="7" class="text-center py-4 text-secondary">
                                                <i class="fa-solid fa-mountain-sun fa-2x mb-2 d-block text-muted"></i>
                                                No se han incorporado predios matrices a este proyecto. Haga clic en <strong>Agregar Predio Matriz</strong>.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($predios as $pm): ?>
                                            <tr id="predio-fila-<?= (int) $pm['id'] ?>">
                                                <td>
                                                    <span class="f-w-600 text-dark"><?= htmlspecialchars((string) $pm['denominacion']) ?></span>
                                                    <?php if (!empty($pm['tomo_ficha'])): ?>
                                                        <small class="d-block text-muted">Ficha/Tomo: <?= htmlspecialchars((string) $pm['tomo_ficha']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($pm['partida_registral'])): ?>
                                                        <span class="badge bg-secondary font-monospace"><?= htmlspecialchars((string) $pm['partida_registral']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning f-s-11">
                                                            <i class="fa-solid fa-clock me-1"></i>En Saneamiento
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end font-monospace">
                                                    <?= number_format((float) $pm['area_registral_m2'], 4) ?> m²
                                                </td>
                                                <td class="text-end font-monospace">
                                                    <?php if ($pm['area_topografica_m2'] !== null): ?>
                                                        <?= number_format((float) $pm['area_topografica_m2'], 4) ?> m²
                                                    <?php else: ?>
                                                        <span class="badge bg-info-subtle text-info border border-info f-s-11">
                                                            <i class="fa-solid fa-ruler-combined me-1"></i>Pendiente
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-secondary"><?= htmlspecialchars((string) ($pm['distrito_nombre'] ?? '')) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= ($pm['estado'] ?? '') === 'ACTIVO' ? 'bg-success' : 'bg-secondary' ?> f-s-11">
                                                        <?= htmlspecialchars((string) ($pm['estado'] ?? '')) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-warning btn-sm btn-editar-predio"
                                                            data-id="<?= (int) $pm['id'] ?>"
                                                            data-denominacion="<?= htmlspecialchars((string) $pm['denominacion'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-partida="<?= htmlspecialchars((string) ($pm['partida_registral'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-tomo="<?= htmlspecialchars((string) ($pm['tomo_ficha'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-registral="<?= (float) $pm['area_registral_m2'] ?>"
                                                            data-topografica="<?= $pm['area_topografica_m2'] !== null ? (float) $pm['area_topografica_m2'] : '' ?>"
                                                            data-departamento-id="<?= (int) ($pm['departamento_id'] ?? 0) ?>"
                                                            data-provincia-id="<?= (int) ($pm['provincia_id'] ?? 0) ?>"
                                                            data-distrito-id="<?= (int) $pm['distrito_id'] ?>"
                                                            data-antecedente="<?= htmlspecialchars((string) ($pm['antecedente_dominial'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-bs-toggle="tooltip" data-bs-title="Editar Predio">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-<?= ($pm['estado'] ?? '') === 'ACTIVO' ? 'danger' : 'success' ?> btn-sm btn-conmutar-predio"
                                                            data-id="<?= (int) $pm['id'] ?>"
                                                            data-estado-actual="<?= htmlspecialchars((string) $pm['estado'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-bs-toggle="tooltip" data-bs-title="<?= ($pm['estado'] ?? '') === 'ACTIVO' ? 'Desactivar Predio' : 'Reactivar Predio' ?>">
                                                        <i class="fa-solid fa-<?= ($pm['estado'] ?? '') === 'ACTIVO' ? 'ban' : 'check' ?>"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: CONCILIACIÓN DE ÁREAS (SEMAFORO DETERMINISTA)     -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade" id="tab-conciliacion-pane" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card border bg-light-subtle h-100">
                                    <div class="card-body p-3">
                                        <h6 class="text-secondary f-s-12 f-w-600 text-uppercase mb-1">Nivel 1: Área Registral Matriz</h6>
                                        <h3 class="mb-0 text-dark f-w-700 font-monospace" id="kpiAreaRegistral">
                                            <?= number_format((float) ($conciliacion['balance_predios']['suma_area_registral_m2'] ?? 0), 4) ?> m²
                                        </h3>
                                        <small class="text-muted">Total legal en partidas SUNARP</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card border bg-light-subtle h-100">
                                    <div class="card-body p-3">
                                        <h6 class="text-secondary f-s-12 f-w-600 text-uppercase mb-1">Nivel 2: Área Topográfica Levantada</h6>
                                        <h3 class="mb-0 text-dark f-w-700 font-monospace" id="kpiAreaTopografica">
                                            <?= number_format((float) ($conciliacion['balance_predios']['suma_area_topografica_m2'] ?? 0), 4) ?> m²
                                        </h3>
                                        <small class="text-muted">Levantamiento físico verificado</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card border bg-light-subtle h-100">
                                    <div class="card-body p-3">
                                        <h6 class="text-secondary f-s-12 f-w-600 text-uppercase mb-1">Discrepancia Absoluta</h6>
                                        <h3 class="mb-0 text-dark f-w-700 font-monospace" id="kpiDiscrepancia">
                                            <?= number_format((float) ($conciliacion['balance_predios']['discrepancia_absoluta_m2'] ?? 0), 4) ?> m²
                                        </h3>
                                        <small class="text-muted" id="kpiDiscrepanciaPct">
                                            (<?= number_format((float) ($conciliacion['balance_predios']['discrepancia_porcentaje'] ?? 0), 2) ?>% de discrepancia)
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card border-0 shadow-sm p-4 text-center" id="cardSemaforoConciliacion">
                                    <?php
                                        $semaforo = $conciliacion['semaforo'] ?? 'SIN_PREDIOS';
                                        $alertClass = match ($semaforo) {
                                            'CONCILIADO'                    => 'alert-success border-success',
                                            'PENDIENTE_TOPOGRAFIA'          => 'alert-info border-info',
                                            'DISCREPANCIA_FUERA_TOLERANCIA' => 'alert-danger border-danger',
                                            default                         => 'alert-secondary border-secondary'
                                        };
                                        $alertIcon = match ($semaforo) {
                                            'CONCILIADO'                    => 'fa-circle-check text-success',
                                            'PENDIENTE_TOPOGRAFIA'          => 'fa-clock text-info',
                                            'DISCREPANCIA_FUERA_TOLERANCIA' => 'fa-triangle-exclamation text-danger',
                                            default                         => 'fa-circle-info text-secondary'
                                        };
                                    ?>
                                    <div class="alert <?= $alertClass ?> mb-0 py-3 d-flex align-items-center justify-content-center gap-3">
                                        <i class="fa-solid <?= $alertIcon ?> fa-2x"></i>
                                        <div class="text-start">
                                            <h5 class="mb-1 f-w-700" id="semaforoTitulo">
                                                <?= htmlspecialchars(str_replace('_', ' ', (string) $semaforo)) ?>
                                            </h5>
                                            <p class="mb-0 f-s-13" id="semaforoMensaje"><?= htmlspecialchars((string) ($conciliacion['mensaje'] ?? '')) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card border p-3">
                                    <h6 class="text-dark f-w-600 mb-2">Política Determinista de Tolerancia Aplicada (GATE 3A-04)</h6>
                                    <p class="f-s-13 text-secondary mb-0">
                                        Tipo de política configurada: <strong><?= htmlspecialchars((string) $proyecto['tipo_tolerancia']) ?></strong>.
                                        Valor máximo tolerado: <strong><?= number_format((float) $proyecto['valor_tolerancia'], 4) ?> <?= $proyecto['tipo_tolerancia'] === 'PORCENTUAL' ? '%' : 'm²' ?></strong>.
                                        Si la discrepancia física entre los títulos de propiedad y el plano topográfico supera este umbral, el sistema emite una alerta bloqueante antes de habilitar la venta de lotes.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 3: DATOS GENERALES Y UBICACIÓN                        -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade" id="tab-datos-pane" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label f-s-12 text-secondary mb-0">Nombre Comercial</label>
                                <p class="f-s-14 f-w-600 text-dark mb-2"><?= htmlspecialchars((string) $proyecto['nombre']) ?></p>

                                <label class="form-label f-s-12 text-secondary mb-0">Código Corporativo</label>
                                <p class="f-s-14 font-monospace text-dark mb-2"><?= htmlspecialchars((string) $proyecto['codigo']) ?></p>

                                <label class="form-label f-s-12 text-secondary mb-0">Modalidad Legal</label>
                                <p class="f-s-14 text-dark mb-2"><?= htmlspecialchars((string) $proyecto['tipo_proyecto']) ?></p>

                                <label class="form-label f-s-12 text-secondary mb-0">Moneda Soberana</label>
                                <p class="f-s-14 text-dark mb-2"><?= htmlspecialchars((string) $proyecto['moneda']) ?> (<?= $monedaSimbolo ?>)</p>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label f-s-12 text-secondary mb-0">Ubicación Política</label>
                                <p class="f-s-14 text-dark mb-2">
                                    <?= htmlspecialchars((string) ($proyecto['departamento_nombre'] ?? '')) ?> / <?= htmlspecialchars((string) ($proyecto['provincia_nombre'] ?? '')) ?> / <?= htmlspecialchars((string) ($proyecto['distrito_nombre'] ?? '')) ?> (UBIGEO <?= htmlspecialchars((string) ($proyecto['codigo_ubigeo'] ?? '')) ?>)
                                </p>

                                <label class="form-label f-s-12 text-secondary mb-0">Dirección / Referencia</label>
                                <p class="f-s-14 text-dark mb-2"><?= htmlspecialchars((string) ($proyecto['direccion_referencia'] ?? 'No especificada')) ?></p>

                                <label class="form-label f-s-12 text-secondary mb-0">Memoria Descriptiva</label>
                                <p class="f-s-14 text-secondary mb-2"><?= nl2br(htmlspecialchars((string) ($proyecto['descripcion'] ?? 'Sin descripción adicional'))) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 4: SECTORES Y BALANCE URBANO (MICROFASE 3B)           -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade" id="tab-sectores-pane" role="tabpanel">
                        <!-- Panel de Diagnóstico y Balance de Áreas -->
                        <div class="row g-3 mb-4" id="panelMetricasBalanceSectores">
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border shadow-none mb-0 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="f-s-12 text-secondary f-w-500">Área Matriz de Referencia</span>
                                            <?php if (($balanceSectores['tipo_balance'] ?? '') === 'DEFINITIVO'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle f-s-11" data-bs-toggle="tooltip" title="Levantamiento topográfico 100% completo">
                                                    <i class="fa-solid fa-check-double me-1"></i>Definitivo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle f-s-11" data-bs-toggle="tooltip" title="Levantamiento parcial o pendiente: referencia basada en áreas registrales">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Provisional
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="text-dark f-w-700 mb-1" id="kpiAreaReferenciaMatriz">
                                            <?= number_format((float) ($balanceSectores['area_referencia_matriz_m2'] ?? 0), 4) ?> <span class="f-s-12 text-muted">m²</span>
                                        </h5>
                                        <div class="f-s-11 text-muted d-flex justify-content-between">
                                            <span>Base: <strong><?= htmlspecialchars((string) ($balanceSectores['origen_area_referencia'] ?? 'REGISTRAL')) ?></strong></span>
                                            <span>Topog: <strong><?= htmlspecialchars((string) ($balanceSectores['estado_topografia'] ?? 'PENDIENTE')) ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <div class="card border shadow-none mb-0 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="f-s-12 text-secondary f-w-500">Área Bruta Sectorizada</span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle f-s-11" id="kpiOcupacionMatrizBadge">
                                                <?= number_format((float) ($balanceSectores['porcentaje_ocupacion_matriz'] ?? 0), 2) ?>% ocupado
                                            </span>
                                        </div>
                                        <h5 class="text-dark f-w-700 mb-1" id="kpiAreaSectoresBruta">
                                            <?= number_format((float) ($balanceSectores['area_sectores_bruta_m2'] ?? 0), 4) ?> <span class="f-s-12 text-muted">m²</span>
                                        </h5>
                                        <div class="f-s-11 text-muted">
                                            Sectores activos: <strong id="kpiTotalSectores"><?= (int) ($balanceSectores['total_sectores'] ?? 0) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <div class="card border shadow-none mb-0 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="f-s-12 text-secondary f-w-500">Remanente de Proyecto</span>
                                            <i class="fa-solid fa-mountain-sun text-secondary"></i>
                                        </div>
                                        <h5 class="text-dark f-w-700 mb-1 <?= ((float) ($balanceSectores['area_remanente_matriz_m2'] ?? 0) < 0) ? 'text-danger' : '' ?>" id="kpiAreaRemanenteMatriz">
                                            <?= number_format((float) ($balanceSectores['area_remanente_matriz_m2'] ?? 0), 4) ?> <span class="f-s-12 text-muted">m²</span>
                                        </h5>
                                        <div class="f-s-11 text-muted">
                                            Vías maestras y reservas sin sectorizar
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <div class="card border shadow-none mb-0 h-100">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="f-s-12 text-secondary f-w-500">Área Vendible Proyectada</span>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle f-s-11">
                                                <?= number_format((float) ($balanceSectores['porcentaje_util_sobre_bruta'] ?? 0), 1) ?>% útil
                                            </span>
                                        </div>
                                        <h5 class="text-dark f-w-700 mb-1" id="kpiAreaSectoresUtil">
                                            <?= number_format((float) ($balanceSectores['area_sectores_util_m2'] ?? 0), 4) ?> <span class="f-s-12 text-muted">m²</span>
                                        </h5>
                                        <div class="f-s-11 text-muted">
                                            Cesión/Vías: <strong><?= number_format((float) ($balanceSectores['area_sectores_cesion_m2'] ?? 0), 2) ?> m²</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alerta Semáforo del Balance Sectorial -->
                        <?php
                            $estadoBal = (string) ($balanceSectores['estado_balance'] ?? 'SIN_SECTORES');
                            $claseAlerta = match ($estadoBal) {
                                'BALANCE_CONCILIADO' => 'alert-success border-success-subtle',
                                'EXCEDE_AREA_MATRIZ' => 'alert-danger border-danger-subtle',
                                'SECTOR_INCONSISTENTE' => 'alert-warning border-warning-subtle',
                                default => 'alert-info border-info-subtle'
                            };
                            $iconoAlerta = match ($estadoBal) {
                                'BALANCE_CONCILIADO' => 'fa-circle-check text-success',
                                'EXCEDE_AREA_MATRIZ' => 'fa-circle-xmark text-danger',
                                'SECTOR_INCONSISTENTE' => 'fa-triangle-exclamation text-warning',
                                default => 'fa-circle-info text-info'
                            };
                        ?>
                        <div class="alert <?= $claseAlerta ?> d-flex align-items-center justify-content-between py-2 px-3 mb-4" id="bannerAlertaBalanceSectores" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid <?= $iconoAlerta ?> fs-5 me-2" id="iconoAlertaBalanceSectores"></i>
                                <div>
                                    <strong class="f-s-13 me-1" id="tituloAlertaBalanceSectores">Estado del Balance:</strong>
                                    <span class="f-s-13" id="mensajeAlertaBalanceSectores"><?= htmlspecialchars((string) ($balanceSectores['mensaje_balance'] ?? '')) ?></span>
                                </div>
                            </div>
                            <?php if (($balanceSectores['tipo_balance'] ?? '') === 'PROVISIONAL'): ?>
                                <span class="badge bg-warning text-dark f-s-11 ms-2">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i>Balance Provisional
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Barra de Encabezado y Botón de Creación -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="text-dark f-w-600 mb-0">Etapas y Sectores Catastrales</h6>
                                <p class="text-secondary f-s-12 mb-0">Subdivisión territorial del proyecto, matriz de precios base por m² y afectación de suelo.</p>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btnNuevoSector">
                                <i class="fa-solid fa-plus me-1"></i> Agregar Sector
                            </button>
                        </div>

                        <!-- Tabla de Sectores -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border mb-0" id="tablaSectores">
                                <thead class="table-light">
                                    <tr>
                                        <th class="f-s-12 f-w-600 text-secondary text-center" style="width: 60px;">Ord.</th>
                                        <th class="f-s-12 f-w-600 text-secondary">Código / Sector</th>
                                        <th class="f-s-12 f-w-600 text-secondary text-end">Área Bruta (m²)</th>
                                        <th class="f-s-12 f-w-600 text-secondary">Desglose de Áreas</th>
                                        <th class="f-s-12 f-w-600 text-secondary text-end">Precio m² Actual</th>
                                        <th class="f-s-12 f-w-600 text-secondary text-center">Estado</th>
                                        <th class="f-s-12 f-w-600 text-secondary text-center" style="width: 130px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodySectores">
                                    <?php if (empty($sectores)): ?>
                                        <tr id="trSectoresVacio">
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-map-location-dot fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                                <span>No hay sectores urbanísticos registrados en este proyecto.</span><br>
                                                <small class="text-secondary">Haga clic en <strong>"Agregar Sector"</strong> para subdividir el terreno matriz e inicializar precios base.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sectores as $sec): ?>
                                            <?php
                                                $badgeEstadoSec = match ($sec['estado'] ?? '') {
                                                    'EN_DESARROLLO' => 'bg-info-subtle text-info border border-info-subtle',
                                                    'EN_VENTA'      => 'bg-success-subtle text-success border border-success-subtle',
                                                    'CONSOLIDADO'   => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                    'CERRADO'       => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                                    default         => 'bg-danger-subtle text-danger border border-danger-subtle'
                                                };
                                                $precioActual = $sec['precio_m2_actual'] !== null ? (float) $sec['precio_m2_actual'] : null;
                                                $monedaSec = (string) ($sec['precio_moneda'] ?? $proyecto['moneda']);
                                            ?>
                                            <tr id="fila-sector-<?= (int) $sec['id'] ?>">
                                                <td class="text-center f-w-600 text-muted f-s-13">
                                                    <?= (int) $sec['orden'] ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border font-monospace f-s-12 mb-1">
                                                        <?= htmlspecialchars((string) $sec['codigo']) ?>
                                                    </span>
                                                    <div class="f-w-600 text-dark f-s-14"><?= htmlspecialchars((string) $sec['nombre']) ?></div>
                                                    <?php if (!empty($sec['descripcion'])): ?>
                                                        <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                                            <?= htmlspecialchars((string) $sec['descripcion']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end f-w-600 text-dark f-s-13">
                                                    <?= number_format((float) $sec['area_bruta_m2'], 4) ?> <span class="f-s-11 text-muted">m²</span>
                                                </td>
                                                <td>
                                                    <div class="f-s-11">
                                                        <span class="text-success f-w-500 me-2" title="Área vendible/útil">
                                                            <i class="fa-solid fa-shapes me-1"></i>Útil: <?= number_format((float) $sec['area_util_m2'], 2) ?> m²
                                                        </span>
                                                        <span class="text-warning-emphasis f-w-500 me-2" title="Vías y aportes">
                                                            <i class="fa-solid fa-road me-1"></i>Cesión: <?= number_format((float) $sec['area_cesion_m2'], 2) ?> m²
                                                        </span>
                                                        <span class="text-secondary f-w-500" title="Áreas comunes">
                                                            <i class="fa-solid fa-tree me-1"></i>Común: <?= number_format((float) $sec['area_comun_m2'], 2) ?> m²
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($precioActual !== null): ?>
                                                        <span class="badge bg-primary text-white f-s-12">
                                                            <?= $monedaSec ?> <?= number_format($precioActual, 2) ?> / m²
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary f-s-11">Sin precio</span>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 d-block ms-auto f-s-11 btn-ver-precios" data-id="<?= (int) $sec['id'] ?>" data-codigo="<?= htmlspecialchars((string) $sec['codigo']) ?>" data-nombre="<?= htmlspecialchars((string) $sec['nombre']) ?>">
                                                        <i class="fa-solid fa-clock-rotate-left me-1"></i>Historial
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $badgeEstadoSec ?> f-s-11 px-2 py-1">
                                                        <?= htmlspecialchars((string) $sec['estado']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-editar-sector"
                                                                data-id="<?= (int) $sec['id'] ?>"
                                                                title="Editar sector">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-success btn-ajustar-precio"
                                                                data-id="<?= (int) $sec['id'] ?>"
                                                                data-codigo="<?= htmlspecialchars((string) $sec['codigo']) ?>"
                                                                title="Actualizar precio m²">
                                                            <i class="fa-solid fa-dollar-sign"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary btn-estado-sector"
                                                                data-id="<?= (int) $sec['id'] ?>"
                                                                data-codigo="<?= htmlspecialchars((string) $sec['codigo']) ?>"
                                                                data-estado="<?= htmlspecialchars((string) $sec['estado']) ?>"
                                                                title="Cambiar estado">
                                                            <i class="fa-solid fa-power-off"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: AGREGAR PREDIO MATRIZ                                             -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalCrearPredio" tabindex="-1" aria-labelledby="tituloModalCrearPredio" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalCrearPredio">
                    <i class="fa-solid fa-plus me-2"></i>Incorporar Predio / Terreno Matriz
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formCrearPredio" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="predioDenominacion" class="form-label f-s-13 f-w-600">Denominación del Terreno Matriz <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="predioDenominacion" name="denominacion" placeholder="ej. Predio San Jerónimo Parcela B-1" required maxlength="150">
                            <div class="invalid-feedback f-s-12">Denominación del predio obligatoria.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="predioPartida" class="form-label f-s-13 f-w-600">Partida Registral (SUNARP)</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="predioPartida" name="partida_registral" placeholder="ej. 11029482 (dejar vacío si está en saneamiento)">
                            <div class="form-text f-s-11 text-muted">Admite vacío si el predio está en saneamiento.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="predioTomo" class="form-label f-s-13">Tomo / Ficha / Asiento</label>
                            <input type="text" class="form-control form-control-sm" id="predioTomo" name="tomo_ficha" placeholder="ej. Ficha 2938">
                        </div>

                        <div class="col-md-4">
                            <label for="predioAreaRegistral" class="form-label f-s-13 f-w-600">Área Registral (m²) <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" id="predioAreaRegistral" name="area_registral_m2" placeholder="ej. 50000.0000" required>
                            <div class="invalid-feedback f-s-12">El área registral debe ser mayor a cero.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="predioAreaTopografica" class="form-label f-s-13 f-w-600">Área Topográfica (m²)</label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" id="predioAreaTopografica" name="area_topografica_m2" placeholder="ej. 49980.5000 (dejar vacío si está pendiente)">
                            <div class="form-text f-s-11 text-muted">Opcional hasta que exista levantamiento.</div>
                        </div>

                        <!-- UBIGEO del Predio -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-md-4">
                            <label for="predioDepartamento" class="form-label f-s-13">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo-predio" id="predioDepartamento" required>
                                <option value="">Seleccione...</option>
                                <?php if (!empty($catalogos['departamentos'])): ?>
                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                        <option value="<?= (int) $dep['id'] ?>"><?= htmlspecialchars((string) $dep['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="predioProvincia" class="form-label f-s-13">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo-predio" id="predioProvincia" disabled required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="predioDistritoId" class="form-label f-s-13">Distrito <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="predioDistritoId" name="distrito_id" disabled required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="predioAntecedente" class="form-label f-s-13">Antecedente Dominial / Escritura Pública</label>
                            <textarea class="form-control form-control-sm" id="predioAntecedente" name="antecedente_dominial" rows="2" placeholder="Reseña de títulos anteriores, compraventa o donación..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarPredio">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Predio Matriz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDITAR PREDIO MATRIZ                                              -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditarPredio" tabindex="-1" aria-labelledby="tituloModalEditarPredio" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalEditarPredio">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Editar Predio Matriz
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formEditarPredio" class="needs-validation" novalidate>
                <input type="hidden" id="editarPredioId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="editarPredioDenominacion" class="form-label f-s-13 f-w-600">Denominación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="editarPredioDenominacion" name="denominacion" required maxlength="150">
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioPartida" class="form-label f-s-13 f-w-600">Partida Registral (SUNARP)</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="editarPredioPartida" name="partida_registral" placeholder="Dejar vacío si está en saneamiento">
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioTomo" class="form-label f-s-13">Tomo / Ficha</label>
                            <input type="text" class="form-control form-control-sm" id="editarPredioTomo" name="tomo_ficha">
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioAreaRegistral" class="form-label f-s-13 f-w-600">Área Registral (m²) <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" id="editarPredioAreaRegistral" name="area_registral_m2" required>
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioAreaTopografica" class="form-label f-s-13 f-w-600">Área Topográfica (m²)</label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" id="editarPredioAreaTopografica" name="area_topografica_m2" placeholder="Dejar vacío si está pendiente">
                        </div>

                        <!-- UBIGEO del Predio Edición -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-md-4">
                            <label for="editarPredioDepartamento" class="form-label f-s-13">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo-predio" id="editarPredioDepartamento" required>
                                <option value="">Seleccione...</option>
                                <?php if (!empty($catalogos['departamentos'])): ?>
                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                        <option value="<?= (int) $dep['id'] ?>"><?= htmlspecialchars((string) $dep['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioProvincia" class="form-label f-s-13">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo-predio" id="editarPredioProvincia" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="editarPredioDistritoId" class="form-label f-s-13">Distrito <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editarPredioDistritoId" name="distrito_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="editarPredioAntecedente" class="form-label f-s-13">Antecedente Dominial</label>
                            <textarea class="form-control form-control-sm" id="editarPredioAntecedente" name="antecedente_dominial" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnActualizarPredio">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Predio Matriz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: AGREGAR SECTOR URBANÍSTICO                                        -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalCrearSector" tabindex="-1" aria-labelledby="tituloModalCrearSector" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalCrearSector">
                    <i class="fa-solid fa-map-location-dot me-2"></i>Incorporar Sector Urbanístico
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formCrearSector" class="needs-validation" novalidate>
                <input type="hidden" name="proyecto_id" value="<?= (int) $proyecto['id'] ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="crearSectorCodigo" class="form-label f-s-13">Código Canónico <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="crearSectorCodigo" name="codigo" placeholder="SEC_A / ETAPA_1" maxlength="32" required>
                            <div class="invalid-feedback f-s-11">Indique un código identificador único (máx. 32 car.).</div>
                        </div>

                        <div class="col-md-5">
                            <label for="crearSectorNombre" class="form-label f-s-13">Nombre Comercial del Sector <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="crearSectorNombre" name="nombre" placeholder="Ej: Sector Los Cedros - Etapa I" maxlength="150" required>
                            <div class="invalid-feedback f-s-11">El nombre del sector es obligatorio.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="crearSectorOrden" class="form-label f-s-13">Orden Visual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="crearSectorOrden" name="orden" value="<?= count($sectores ?? []) + 1 ?>" min="1" required>
                        </div>

                        <div class="col-12">
                            <label for="crearSectorDescripcion" class="form-label f-s-13">Memoria Descriptiva / Alcance</label>
                            <textarea class="form-control form-control-sm" id="crearSectorDescripcion" name="descripcion" rows="2" placeholder="Detalles de zonificación o etapas de habilitación..."></textarea>
                        </div>

                        <!-- Dimensionamiento de Superficies (DECIMAL 14,4) -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12">
                            <h6 class="f-s-13 text-dark f-w-600 mb-1">
                                <i class="fa-solid fa-ruler-combined me-1 text-primary"></i>Distribución de Superficies (m²)
                            </h6>
                            <p class="f-s-11 text-muted mb-2">El área bruta asignada no puede superar el remanente disponible del proyecto (<strong id="textoRemanenteDisponibleModal"><?= number_format((float) ($balanceSectores['area_remanente_matriz_m2'] ?? 0), 4) ?> m²</strong>).</p>
                        </div>

                        <div class="col-md-3">
                            <label for="crearSectorAreaBruta" class="form-label f-s-13">Área Bruta (m²) <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm font-monospace text-end input-calculo-sector" id="crearSectorAreaBruta" name="area_bruta_m2" placeholder="0.0000" required>
                            <div class="invalid-feedback f-s-11">Área bruta obligatoria mayor a 0.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="crearSectorAreaUtil" class="form-label f-s-13">Área Útil Vendible (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-calculo-sector" id="crearSectorAreaUtil" name="area_util_m2" placeholder="0.0000" value="0.0000">
                        </div>

                        <div class="col-md-3">
                            <label for="crearSectorAreaCesion" class="form-label f-s-13">Cesión / Vías (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-calculo-sector" id="crearSectorAreaCesion" name="area_cesion_m2" placeholder="0.0000" value="0.0000">
                        </div>

                        <div class="col-md-3">
                            <label for="crearSectorAreaComun" class="form-label f-s-13">Área Común (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-calculo-sector" id="crearSectorAreaComun" name="area_comun_m2" placeholder="0.0000" value="0.0000">
                        </div>

                        <div class="col-12">
                            <div class="bg-light p-2 rounded border d-flex justify-content-between f-s-12">
                                <span>Suma interna desglosada: <strong id="resumenSumaInternaModal">0.0000</strong> m²</span>
                                <span>Reserva técnica local: <strong id="resumenRemanenteLocalModal">0.0000</strong> m²</span>
                            </div>
                        </div>

                        <!-- Precio Base Inicial por m² -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12">
                            <h6 class="f-s-13 text-dark f-w-600 mb-1">
                                <i class="fa-solid fa-tag me-1 text-success"></i>Precio Base Inicial por m²
                            </h6>
                            <p class="f-s-11 text-muted mb-2">Precio referencial de lanzamiento para cotizaciones en moneda oficial <strong><?= htmlspecialchars((string) $proyecto['moneda']) ?></strong>.</p>
                        </div>

                        <div class="col-md-4">
                            <label for="crearSectorPrecioInicial" class="form-label f-s-13">Precio Base (<?= htmlspecialchars((string) $proyecto['moneda']) ?>/m²) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm font-monospace text-end" id="crearSectorPrecioInicial" name="precio_m2_inicial" placeholder="0.00" required>
                            <div class="invalid-feedback f-s-11">Indique el precio base inicial por m².</div>
                        </div>

                        <div class="col-md-8">
                            <label for="crearSectorMotivoPrecio" class="form-label f-s-13">Justificación del Precio</label>
                            <input type="text" class="form-control form-control-sm" id="crearSectorMotivoPrecio" name="motivo_precio_inicial" value="Lanzamiento inicial de sector" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarSector">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Sector
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDITAR SECTOR URBANÍSTICO                                         -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditarSector" tabindex="-1" aria-labelledby="tituloModalEditarSector" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalEditarSector">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Modificar Configuración de Sector
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formEditarSector" class="needs-validation" novalidate>
                <input type="hidden" id="editarSectorId" name="id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 text-secondary">Código Inmutable</label>
                            <input type="text" class="form-control form-control-sm font-monospace text-uppercase bg-light" id="editarSectorCodigo" readonly disabled>
                        </div>

                        <div class="col-md-5">
                            <label for="editarSectorNombre" class="form-label f-s-13">Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="editarSectorNombre" name="nombre" maxlength="150" required>
                        </div>

                        <div class="col-md-3">
                            <label for="editarSectorOrden" class="form-label f-s-13">Orden Visual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" id="editarSectorOrden" name="orden" min="1" required>
                        </div>

                        <div class="col-12">
                            <label for="editarSectorDescripcion" class="form-label f-s-13">Memoria Descriptiva</label>
                            <textarea class="form-control form-control-sm" id="editarSectorDescripcion" name="descripcion" rows="2"></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1 text-muted"></div>

                        <div class="col-md-3">
                            <label for="editarSectorAreaBruta" class="form-label f-s-13">Área Bruta (m²) <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm font-monospace text-end input-editar-calculo-sector" id="editarSectorAreaBruta" name="area_bruta_m2" required>
                        </div>

                        <div class="col-md-3">
                            <label for="editarSectorAreaUtil" class="form-label f-s-13">Área Útil Vendible (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-editar-calculo-sector" id="editarSectorAreaUtil" name="area_util_m2">
                        </div>

                        <div class="col-md-3">
                            <label for="editarSectorAreaCesion" class="form-label f-s-13">Cesión / Vías (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-editar-calculo-sector" id="editarSectorAreaCesion" name="area_cesion_m2">
                        </div>

                        <div class="col-md-3">
                            <label for="editarSectorAreaComun" class="form-label f-s-13">Área Común (m²)</label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm font-monospace text-end input-editar-calculo-sector" id="editarSectorAreaComun" name="area_comun_m2">
                        </div>

                        <div class="col-12">
                            <div class="bg-light p-2 rounded border d-flex justify-content-between f-s-12">
                                <span>Suma interna desglosada: <strong id="resumenEditarSumaInternaModal">0.0000</strong> m²</span>
                                <span>Reserva técnica local: <strong id="resumenEditarRemanenteLocalModal">0.0000</strong> m²</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnActualizarSector">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Sector
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: HISTÓRICO Y AJUSTE DE PRECIOS POR SECTOR                          -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalPreciosSector" tabindex="-1" aria-labelledby="tituloModalPreciosSector" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalPreciosSector">
                    <i class="fa-solid fa-dollar-sign me-2"></i>Histórico y Ajuste de Precios por m²
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <span class="badge bg-light text-dark border font-monospace me-2" id="badgeModalPrecioSectorCodigo">SEC</span>
                        <strong class="text-dark f-s-15" id="textoModalPrecioSectorNombre">Sector</strong>
                    </div>
                    <div>
                        <span class="f-s-12 text-muted me-1">Precio Vigente:</span>
                        <strong class="text-success f-s-15" id="textoModalPrecioActualM2">-</strong>
                    </div>
                </div>

                <!-- Formulario para fijar nuevo precio -->
                <div class="card bg-light border mb-4">
                    <div class="card-body p-3">
                        <h6 class="f-s-13 f-w-600 text-dark mb-2">
                            <i class="fa-solid fa-plus-circle me-1 text-success"></i>Fijar Nuevo Precio Base
                        </h6>
                        <form id="formAjustarPrecioSector" class="needs-validation" novalidate>
                            <input type="hidden" id="precioSectorId" name="sector_id">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label for="nuevoPrecioM2" class="form-label f-s-12 mb-1">Precio Base / m² <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm font-monospace text-end" id="nuevoPrecioM2" name="precio_m2_base" placeholder="0.00" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="nuevoPrecioFechaInicio" class="form-label f-s-12 mb-1">Vigencia Desde <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="nuevoPrecioFechaInicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="nuevoPrecioMotivo" class="form-label f-s-12 mb-1">Motivo / Justificación <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="nuevoPrecioMotivo" name="motivo" placeholder="Ej: Ajuste trimestral / Avance obra" maxlength="255" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-success btn-sm w-100" id="btnGuardarPrecio">
                                        <i class="fa-solid fa-floppy-disk me-1"></i> Fijar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla del Historial Cronológico -->
                <h6 class="f-s-13 f-w-600 text-dark mb-2">
                    <i class="fa-solid fa-clock-rotate-left me-1 text-secondary"></i>Registro Inmutable de Precios
                </h6>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0" id="tablaHistorialPrecios">
                        <thead class="table-light">
                            <tr>
                                <th class="f-s-12 text-secondary">Vigencia Desde</th>
                                <th class="f-s-12 text-secondary">Vigencia Hasta</th>
                                <th class="f-s-12 text-secondary text-end">Precio Base (m²)</th>
                                <th class="f-s-12 text-secondary">Motivo</th>
                                <th class="f-s-12 text-secondary text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorialPrecios">
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">Cargando historial de precios...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer bg-light-subtle py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CAMBIAR ESTADO DE SECTOR                                          -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalCambiarEstadoSector" tabindex="-1" aria-labelledby="tituloModalCambiarEstadoSector" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalCambiarEstadoSector">
                    <i class="fa-solid fa-power-off me-2"></i>Cambiar Estado del Sector
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formCambiarEstadoSector" class="needs-validation" novalidate>
                <input type="hidden" id="estadoSectorId" name="id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label f-s-13 text-secondary">Sector Seleccionado</label>
                        <p class="f-w-600 text-dark mb-0" id="textoCambiarEstadoSectorCodigo">-</p>
                    </div>

                    <div class="mb-3">
                        <label for="nuevoEstadoSectorSelect" class="form-label f-s-13">Nuevo Estado Operativo <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="nuevoEstadoSectorSelect" name="estado" required>
                            <option value="">Seleccione...</option>
                            <option value="EN_DESARROLLO">EN DESARROLLO</option>
                            <option value="EN_VENTA">EN VENTA</option>
                            <option value="CONSOLIDADO">CONSOLIDADO</option>
                            <option value="CERRADO">CERRADO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                        <div class="invalid-feedback f-s-11">Seleccione el nuevo estado.</div>
                    </div>

                    <div class="mb-2">
                        <label for="motivoCambioEstadoSector" class="form-label f-s-13">Motivo del Cambio <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="motivoCambioEstadoSector" name="motivo" rows="2" placeholder="Justificación técnica o administrativa del cambio..." maxlength="255" required></textarea>
                        <div class="invalid-feedback f-s-11">Indique el motivo del cambio (mín. 3 caracteres).</div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnConfirmarCambioEstadoSector">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Cambio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
