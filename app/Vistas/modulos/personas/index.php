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
                    <table id="tablaPersonas" class="display app-data-table default-data-table table table-hover align-middle w-100" data-api-personas-url="<?= Vista::url('api/personas') ?>">
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
