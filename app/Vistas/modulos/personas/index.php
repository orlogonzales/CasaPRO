<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de Listado y Directorio de Personas — Microfase 1E.
 * Ensamblada sobre la anatomía de blank.html, data_table.html y profile.html de Alina.
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 text-dark f-w-600">Directorio de Personas</h5>
                    <p class="text-secondary f-s-13 mb-0">Padrón centralizado de identidades civiles y mercantiles (Personas Naturales y Jurídicas).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaPersona">
                        <i class="fa-solid fa-user-plus me-1"></i> Registrar Persona
                    </button>
                </div>
            </div>

            <!-- Barra de Filtros Rápidos -->
            <div class="card-body border-bottom bg-light-subtle py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4 col-sm-6">
                        <label for="filtroTipoPersona" class="form-label f-s-13 text-secondary mb-1">Tipo de Persona</label>
                        <select id="filtroTipoPersona" class="form-select form-select-sm">
                            <option value="">Todos los tipos</option>
                            <option value="NATURAL">Persona Natural</option>
                            <option value="JURIDICA">Persona Jurídica</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <label for="filtroEstado" class="form-label f-s-13 text-secondary mb-1">Estado</label>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-12 d-flex justify-content-md-end">
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contenedor DataTables Server-Side -->
            <div class="card-body p-0">
                <div class="app-datatable-default overflow-auto app-scroll p-3">
                    <table id="tablaPersonas" class="display app-data-table default-data-table table table-hover align-middle w-100"
                        data-api-personas-url="<?= Vista::url('api/personas') ?>"
                        data-api-consultar-doc-url="<?= Vista::url('api/personas/consultar-documento') ?>"
                        data-api-provincias-url="<?= Vista::url('api/ubigeo/provincias') ?>"
                        data-api-distritos-url="<?= Vista::url('api/ubigeo/distritos') ?>">
                        <thead>
                            <tr>
                                <th scope="col">Documento</th>
                                <th scope="col">Nombre Completo / Razón Social</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Contacto Principal</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Fecha Registro</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Poblado dinámicamente vía fetch por DataTables Server-Side -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ficha de Identidad de Persona (Alina modal-lg scrollable) -->
<div class="modal fade" id="modalFichaPersona" tabindex="-1" aria-labelledby="tituloModalFichaPersona" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title f-w-600 mb-1 text-dark" id="tituloModalFichaPersona">Ficha de Identidad de Persona</h5>
                    <div id="fichaPersonaSubtitulo" class="d-flex align-items-center gap-2">
                        <span id="fichaBadgeTipo" class="chip"></span>
                        <span id="fichaBadgeEstado" class="badge"></span>
                        <span id="fichaTextoId" class="text-muted f-s-12"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Spinner de carga para detalle 360 -->
                <div id="fichaSpinnerCarga" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando datos de identidad...</span>
                    </div>
                    <p class="text-secondary mt-2 mb-0 f-s-13">Consultando ficha de identidad...</p>
                </div>

                <!-- Contenido estructurado de la Ficha -->
                <div id="fichaContenidoDetalle">
                    <!-- Pestañas de navegación según profile.html de Alina -->
                    <ul class="nav nav-tabs nav-bottom-line px-3 pt-2" id="fichaPersonaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab" data-bs-target="#tab-general-pane" type="button" role="tab" aria-controls="tab-general-pane" aria-selected="true">
                                <i class="fa-solid fa-id-card me-1"></i> Datos Generales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-documentos-contactos-btn" data-bs-toggle="tab" data-bs-target="#tab-documentos-contactos-pane" type="button" role="tab" aria-controls="tab-documentos-contactos-pane" aria-selected="false">
                                <i class="fa-solid fa-address-book me-1"></i> Documentos y Contactos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-domicilios-btn" data-bs-toggle="tab" data-bs-target="#tab-domicilios-pane" type="button" role="tab" aria-controls="tab-domicilios-pane" aria-selected="false">
                                <i class="fa-solid fa-location-dot me-1"></i> Domicilios
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" id="tabItemRepresentacion" style="display: none;">
                            <button class="nav-link" id="tab-representacion-btn" data-bs-toggle="tab" data-bs-target="#tab-representacion-pane" type="button" role="tab" aria-controls="tab-representacion-pane" aria-selected="false">
                                <i class="fa-solid fa-users me-1"></i> Representación
                            </button>
                        </li>
                    </ul>

                    <!-- Paneles de Contenido -->
                    <div class="tab-content p-4" id="fichaPersonaTabContent">
                        <!-- Panel 1: Datos Generales -->
                        <div class="tab-pane fade show active" id="tab-general-pane" role="tabpanel" aria-labelledby="tab-general-btn">
                            <div id="fichaDatosGenerales"></div>
                        </div>

                        <!-- Panel 2: Documentos y Contactos -->
                        <div class="tab-pane fade" id="tab-documentos-contactos-pane" role="tabpanel" aria-labelledby="tab-documentos-contactos-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-file-lines me-1 text-primary"></i> Documentos de Identidad</h6>
                            <div id="fichaContenedorDocumentos" class="table-responsive mb-4"></div>

                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-phone-volume me-1 text-primary"></i> Medios de Contacto</h6>
                            <div id="fichaContenedorContactos" class="table-responsive"></div>
                        </div>

                        <!-- Panel 3: Domicilios -->
                        <div class="tab-pane fade" id="tab-domicilios-pane" role="tabpanel" aria-labelledby="tab-domicilios-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-location-dot me-1 text-primary"></i> Direcciones Registradas</h6>
                            <div id="fichaContenedorDirecciones" class="table-responsive"></div>
                        </div>

                        <!-- Panel 4: Representación Legal -->
                        <div class="tab-pane fade" id="tab-representacion-pane" role="tabpanel" aria-labelledby="tab-representacion-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-user-tie me-1 text-primary"></i> Representantes Legales Registrados</h6>
                            <div id="fichaContenedorRepresentantes" class="table-responsive"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Alta y Edición de Persona (Alina modal-xl scrollable) -->
<div class="modal fade" id="modalFormularioPersona" tabindex="-1" aria-labelledby="tituloModalFormularioPersona" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title f-w-600 mb-0 text-dark" id="tituloModalFormularioPersona">
                        <i class="fa-solid fa-user-plus me-2 text-primary"></i> <span id="textoTituloModal">Registrar Persona</span>
                    </h5>
                    <!-- Selector de Tipo de Persona (Alta) -->
                    <div class="btn-group btn-group-sm" role="group" id="grupoSelectorTipoPersona" aria-label="Tipo de Persona">
                        <input type="radio" class="btn-check" name="selector_tipo_persona" id="tipoRadioNatural" value="NATURAL" checked autocomplete="off">
                        <label class="btn btn-outline-primary f-s-12" for="tipoRadioNatural">
                            <i class="fa-solid fa-user me-1"></i> Natural
                        </label>
                        <input type="radio" class="btn-check" name="selector_tipo_persona" id="tipoRadioJuridica" value="JURIDICA" autocomplete="off">
                        <label class="btn btn-outline-primary f-s-12" for="tipoRadioJuridica">
                            <i class="fa-solid fa-building me-1"></i> Jurídica
                        </label>
                    </div>
                    <!-- Badge indicador inmutable (Edición) -->
                    <span id="badgeTipoPersonaEdicion" class="chip bg-light-primary text-primary d-none"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formPersona" class="needs-validation" novalidate autocomplete="off">
                <input type="hidden" id="persona_id" name="persona_id" value="">
                <input type="hidden" id="tipo_persona" name="tipo_persona" value="NATURAL">

                <div class="modal-body p-0">
                    <!-- Pestañas de navegación según profile.html / tabs.html de Alina -->
                    <ul class="nav nav-tabs nav-bottom-line px-3 pt-2" id="formPersonaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-form-principales-btn" data-bs-toggle="tab" data-bs-target="#tab-form-principales-pane" type="button" role="tab" aria-controls="tab-form-principales-pane" aria-selected="true">
                                <i class="fa-solid fa-id-card me-1"></i> Datos Principales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-form-documentos-btn" data-bs-toggle="tab" data-bs-target="#tab-form-documentos-pane" type="button" role="tab" aria-controls="tab-form-documentos-pane" aria-selected="false">
                                <i class="fa-solid fa-file-lines me-1"></i> Documentos y Contactos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-form-domicilios-btn" data-bs-toggle="tab" data-bs-target="#tab-form-domicilios-pane" type="button" role="tab" aria-controls="tab-form-domicilios-pane" aria-selected="false">
                                <i class="fa-solid fa-location-dot me-1"></i> Domicilio
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" id="tabItemFormRepresentacion" style="display: none;">
                            <button class="nav-link" id="tab-form-representantes-btn" data-bs-toggle="tab" data-bs-target="#tab-form-representantes-pane" type="button" role="tab" aria-controls="tab-form-representantes-pane" aria-selected="false">
                                <i class="fa-solid fa-user-tie me-1"></i> Representante Legal
                            </button>
                        </li>
                    </ul>

                    <!-- Paneles de Contenido del Formulario -->
                    <div class="tab-content p-4" id="formPersonaTabContent">
                        <!-- Panel 1: Datos Principales -->
                        <div class="tab-pane fade show active" id="tab-form-principales-pane" role="tabpanel" aria-labelledby="tab-form-principales-btn">
                            <!-- Campos para Persona Natural -->
                            <div id="seccionCamposNatural">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="campo_nombres" class="form-label f-s-13 text-secondary mb-1">Nombres <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="campo_nombres" name="nombres" maxlength="100" placeholder="Ej. Juan Carlos" required>
                                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="campo_apellido_paterno" class="form-label f-s-13 text-secondary mb-1">Apellido Paterno <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="campo_apellido_paterno" name="apellido_paterno" maxlength="100" placeholder="Ej. Pérez" required>
                                        <div class="invalid-feedback">El apellido paterno es obligatorio.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="campo_apellido_materno" class="form-label f-s-13 text-secondary mb-1">Apellido Materno</label>
                                        <input type="text" class="form-control form-control-sm" id="campo_apellido_materno" name="apellido_materno" maxlength="100" placeholder="Ej. Gómez">
                                        <div class="invalid-feedback"></div>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="campo_fecha_nacimiento" class="form-label f-s-13 text-secondary mb-1">Fecha de Nacimiento</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fa-solid fa-calendar-days text-muted"></i></span>
                                            <input type="text" class="form-control form-control-sm flatpickr-input" id="campo_fecha_nacimiento" name="fecha_nacimiento" placeholder="AAAA-MM-DD">
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="campo_sexo_id" class="form-label f-s-13 text-secondary mb-1">Sexo</label>
                                        <select class="form-select form-select-sm" id="campo_sexo_id" name="sexo_id">
                                            <option value="">Seleccione...</option>
                                            <?php if (!empty($catalogos['sexos'])): ?>
                                                <?php foreach ($catalogos['sexos'] as $sexo): ?>
                                                    <option value="<?= (int) $sexo['id'] ?>"><?= Vista::e($sexo['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="campo_estado_civil_id" class="form-label f-s-13 text-secondary mb-1">Estado Civil</label>
                                        <select class="form-select form-select-sm" id="campo_estado_civil_id" name="estado_civil_id">
                                            <option value="">Seleccione...</option>
                                            <?php if (!empty($catalogos['estados_civiles'])): ?>
                                                <?php foreach ($catalogos['estados_civiles'] as $ec): ?>
                                                    <option value="<?= (int) $ec['id'] ?>"><?= Vista::e($ec['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="campo_profesion_ocupacion" class="form-label f-s-13 text-secondary mb-1">Profesión u Ocupación</label>
                                        <input type="text" class="form-control form-control-sm" id="campo_profesion_ocupacion" name="profesion_ocupacion" maxlength="150" placeholder="Ej. Ingeniero Civil">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos para Persona Jurídica -->
                            <div id="seccionCamposJuridica" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label for="campo_razon_social" class="form-label f-s-13 text-secondary mb-1">Razón Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="campo_razon_social" name="razon_social" maxlength="200" placeholder="Ej. Constructora del Sur S.A.C.">
                                        <div class="invalid-feedback">La razón social es obligatoria para personas jurídicas.</div>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="campo_nombre_comercial" class="form-label f-s-13 text-secondary mb-1">Nombre Comercial</label>
                                        <input type="text" class="form-control form-control-sm" id="campo_nombre_comercial" name="nombre_comercial" maxlength="200" placeholder="Ej. Construsur">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="campo_fecha_constitucion" class="form-label f-s-13 text-secondary mb-1">Fecha de Constitución</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="fa-solid fa-calendar-days text-muted"></i></span>
                                            <input type="text" class="form-control form-control-sm flatpickr-input" id="campo_fecha_constitucion" name="fecha_constitucion" placeholder="AAAA-MM-DD">
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="campo_objeto_social" class="form-label f-s-13 text-secondary mb-1">Objeto Social</label>
                                        <input type="text" class="form-control form-control-sm" id="campo_objeto_social" name="objeto_social" placeholder="Breve descripción del rubro o actividad">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notas Generales (Comunes a ambos tipos) -->
                            <div class="row g-3 mt-1">
                                <div class="col-12">
                                    <label for="campo_notas" class="form-label f-s-13 text-secondary mb-1">Notas u Observaciones Internas</label>
                                    <textarea class="form-control form-control-sm" id="campo_notas" name="notas" rows="2" placeholder="Observaciones de identidad o antecedentes relevantes"></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 2: Documentos y Contactos -->
                        <div class="tab-pane fade" id="tab-form-documentos-pane" role="tabpanel" aria-labelledby="tab-form-documentos-btn">
                            <h6 class="f-w-600 mb-3 text-dark"><i class="fa-solid fa-file-lines me-1 text-primary"></i> Documento Oficial de Identidad</h6>

                            <div class="row g-3 align-items-start mb-3">
                                <div class="col-md-4">
                                    <label for="campo_tipo_documento_id" class="form-label f-s-13 text-secondary mb-1">Tipo de Documento</label>
                                    <select class="form-select form-select-sm" id="campo_tipo_documento_id" name="tipo_documento_id">
                                        <option value="">Sin documento inicial</option>
                                        <?php if (!empty($catalogos['tipos_documento'])): ?>
                                            <?php foreach ($catalogos['tipos_documento'] as $td): ?>
                                                <option value="<?= (int) $td['id'] ?>" data-codigo="<?= Vista::e($td['codigo']) ?>" data-longitud-min="<?= (int) $td['longitud_minima'] ?>" data-longitud-max="<?= (int) $td['longitud_maxima'] ?>" data-longitud-exacta="<?= $td['longitud_exacta'] !== null ? (int) $td['longitud_exacta'] : '' ?>" data-regex="<?= Vista::e($td['patron_regex'] ?? '') ?>" data-alfanumerico="<?= (int) $td['alfanumerico'] ?>" data-tipo-persona="<?= Vista::e($td['tipo_persona']) ?>">
                                                    <?= Vista::e($td['nombre']) ?> (<?= Vista::e($td['codigo']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <div class="col-md-5">
                                    <label for="campo_numero_documento" class="form-label f-s-13 text-secondary mb-1">Número de Documento</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="campo_numero_documento" name="numero_documento" maxlength="30" placeholder="Ingrese número">
                                        <button class="btn btn-outline-primary" type="button" id="btnConsultarDocumento" title="Consultar padrón oficial de identidad">
                                            <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerConsultarDoc"></i>
                                            <i class="fa-solid fa-magnifying-glass me-1" id="iconoConsultarDoc"></i>
                                            <span id="textoBtnConsultarDoc">Consultar</span>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback" id="feedbackNumeroDocumento"></div>
                                </div>

                                <div class="col-md-3 d-flex align-items-center pt-md-4">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="campo_doc_es_principal" name="doc_es_principal" value="1" checked>
                                        <label class="form-check-label f-s-13" for="campo_doc_es_principal">Documento Principal</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerta reactiva de resultado de consulta documental -->
                            <div id="alertaConsultaDocumento" class="alert d-none py-2 px-3 f-s-13 mb-4" role="alert"></div>

                            <hr class="my-4 text-muted">

                            <h6 class="f-w-600 mb-3 text-dark"><i class="fa-solid fa-phone-volume me-1 text-primary"></i> Medio de Contacto Principal</h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="campo_tipo_contacto_id" class="form-label f-s-13 text-secondary mb-1">Tipo de Contacto</label>
                                    <select class="form-select form-select-sm" id="campo_tipo_contacto_id" name="tipo_contacto_id">
                                        <option value="">Sin contacto inicial</option>
                                        <?php if (!empty($catalogos['tipos_contacto'])): ?>
                                            <?php foreach ($catalogos['tipos_contacto'] as $tc): ?>
                                                <option value="<?= (int) $tc['id'] ?>"><?= Vista::e($tc['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-5">
                                    <label for="campo_contacto_valor" class="form-label f-s-13 text-secondary mb-1">Teléfono o Correo</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_contacto_valor" name="contacto_valor" maxlength="150" placeholder="Ej. 987654321 / contacto@empresa.com">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-3">
                                    <label for="campo_contacto_etiqueta" class="form-label f-s-13 text-secondary mb-1">Etiqueta</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_contacto_etiqueta" name="contacto_etiqueta" maxlength="50" placeholder="Ej. Celular Personal">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 3: Domicilio -->
                        <div class="tab-pane fade" id="tab-form-domicilios-pane" role="tabpanel" aria-labelledby="tab-form-domicilios-btn">
                            <h6 class="f-w-600 mb-3 text-dark"><i class="fa-solid fa-location-dot me-1 text-primary"></i> Dirección Principal</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label for="campo_tipo_direccion_id" class="form-label f-s-13 text-secondary mb-1">Tipo de Dirección</label>
                                    <select class="form-select form-select-sm" id="campo_tipo_direccion_id" name="tipo_direccion_id">
                                        <option value="">Sin dirección inicial</option>
                                        <?php if (!empty($catalogos['tipos_direccion'])): ?>
                                            <?php foreach ($catalogos['tipos_direccion'] as $tdir): ?>
                                                <option value="<?= (int) $tdir['id'] ?>"><?= Vista::e($tdir['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>

                                <!-- Cascada Geográfica UBIGEO -->
                                <div class="col-md-8">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label for="campo_departamento_id" class="form-label f-s-13 text-secondary mb-1">Departamento</label>
                                            <select class="form-select form-select-sm" id="campo_departamento_id">
                                                <option value="">Seleccione...</option>
                                                <?php if (!empty($catalogos['departamentos'])): ?>
                                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                                        <option value="<?= (int) $dep['id'] ?>"><?= Vista::e($dep['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="campo_provincia_id" class="form-label f-s-13 text-secondary mb-1">Provincia</label>
                                            <select class="form-select form-select-sm" id="campo_provincia_id" disabled>
                                                <option value="">Seleccione depto...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="campo_distrito_id" class="form-label f-s-13 text-secondary mb-1">Distrito (UBIGEO)</label>
                                            <select class="form-select form-select-sm" id="campo_distrito_id" name="distrito_id" disabled>
                                                <option value="">Seleccione prov...</option>
                                            </select>
                                            <div class="invalid-feedback"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="campo_direccion" class="form-label f-s-13 text-secondary mb-1">Dirección Física (Calle, Av., Jr., Manzana, Lote) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="campo_direccion" name="direccion" maxlength="255" placeholder="Ej. Av. Los Álamos 123, Urb. San Antonio">
                                    <div class="invalid-feedback">La dirección es requerida si se registra un domicilio.</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="campo_codigo_postal" class="form-label f-s-13 text-secondary mb-1">Código Postal</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_codigo_postal" name="codigo_postal" maxlength="20" placeholder="Ej. 15001">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-12">
                                    <label for="campo_referencia" class="form-label f-s-13 text-secondary mb-1">Referencia</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_referencia" name="referencia" maxlength="255" placeholder="Ej. Frente al Parque Central, a espaldas del Banco">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 4: Representación Legal (Solo Jurídicas) -->
                        <div class="tab-pane fade" id="tab-form-representantes-pane" role="tabpanel" aria-labelledby="tab-form-representantes-btn">
                            <h6 class="f-w-600 mb-3 text-dark"><i class="fa-solid fa-user-tie me-1 text-primary"></i> Representante Legal Registrado</h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="campo_rep_persona_id" class="form-label f-s-13 text-secondary mb-1">ID Persona Natural Representante</label>
                                    <input type="number" class="form-control form-control-sm" id="campo_rep_persona_id" name="rep_persona_natural_id" min="1" placeholder="ID de Persona Natural">
                                    <div class="form-text f-s-11 text-muted">ID de la persona natural ya registrada en CasaPRO.</div>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="campo_rep_cargo" class="form-label f-s-13 text-secondary mb-1">Cargo</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_rep_cargo" name="rep_cargo" maxlength="100" placeholder="Ej. Gerente General">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="campo_rep_partida" class="form-label f-s-13 text-secondary mb-1">Partida Registral</label>
                                    <input type="text" class="form-control form-control-sm" id="campo_rep_partida" name="rep_partida_registral" maxlength="50" placeholder="Ej. 11029384 SUNARP">
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-4">
                                    <label for="campo_rep_fecha_inicio" class="form-label f-s-13 text-secondary mb-1">Fecha de Inicio</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-calendar-days text-muted"></i></span>
                                        <input type="text" class="form-control form-control-sm flatpickr-input" id="campo_rep_fecha_inicio" name="rep_fecha_inicio" placeholder="AAAA-MM-DD">
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarPersona">
                        <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerGuardarPersona"></i>
                        <i class="fa-solid fa-floppy-disk me-1" id="iconoGuardarPersona"></i>
                        <span id="textoBtnGuardarPersona">Guardar Persona</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Catálogos Sembrados de CasaPRO en JSON Seguro (Soberanía de Catálogos) -->
<script id="datosCatalogosCasaPro" type="application/json"><?= json_encode($catalogos ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>

