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
                        <button class="nav-link py-3 text-muted" id="tab-sectores-btn" data-bs-toggle="tab" data-bs-target="#tab-sectores-pane" type="button" role="tab" aria-selected="false">
                            <i class="fa-solid fa-map-location-dot me-2"></i>Sectores y Loteo (3B)
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
                    <!-- TAB 4: SECTORES Y LOTEO (SLOT FASE 3B)                    -->
                    <!-- ========================================================= -->
                    <div class="tab-pane fade" id="tab-sectores-pane" role="tabpanel">
                        <div class="text-center py-5">
                            <i class="fa-solid fa-map-location-dot fa-3x text-muted mb-3 d-block"></i>
                            <h5 class="text-dark f-w-600">Estructuración de Sectores, Precios Históricos y Balance Urbano</h5>
                            <p class="text-secondary f-s-13 max-w-500 mx-auto">
                                Este módulo se habilitará en la <strong>Microfase 3B</strong> para definir etapas urbanísticas, matriz de precios base por m² con vigencias y reservas técnicas de vías y áreas verdes.
                            </p>
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
