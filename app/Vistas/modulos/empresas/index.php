<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista del Catálogo de Empresas Corporativas — Microfase 2C.
 * Basada estrictamente en la anatomía de blank.html, data_table.html y modals.html de Alina Bootstrap 5.
 *
 * Variables disponibles:
 * @var array $catalogos Catálogos del padrón (departamentos, tipos_direccion, tipos_contacto, etc.)
 * @var string $tokenCsrf Token de seguridad CSRF
 */

$rucTipoId = 0;
if (!empty($catalogos['tipos_documento'])) {
    foreach ($catalogos['tipos_documento'] as $td) {
        if (strtoupper($td['codigo'] ?? '') === 'RUC') {
            $rucTipoId = (int) $td['id'];
            break;
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <!-- Encabezado de la Tarjeta Principal -->
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 text-dark f-w-600">
                        <i class="fa-solid fa-building text-primary me-2"></i>Catálogo de Empresas Corporativas
                    </h5>
                    <p class="text-secondary f-s-13 mb-0">Estructura territorial soberana y gestión de unidades de negocio de CasaPRO.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaEmpresa">
                        <i class="fa-solid fa-plus me-1"></i> Nueva Empresa
                    </button>
                </div>
            </div>

            <!-- Barra de Filtros Rápidos -->
            <div class="card-body border-bottom bg-light-subtle py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4 col-sm-6">
                        <label for="filtroEstado" class="form-label f-s-13 text-secondary mb-1">Estado de Operación</label>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-8 col-sm-6 d-flex justify-content-md-end">
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contenedor DataTables Server-Side con Adaptador Fetch -->
            <div class="card-body p-0">
                <div class="app-datatable-default overflow-auto app-scroll p-3">
                    <table id="tablaEmpresas" class="display app-data-table default-data-table table table-hover align-middle w-100"
                        data-api-url="<?= Vista::url('api/empresas') ?>"
                        data-api-personas-disponibles-url="<?= Vista::url('api/empresas/personas-juridicas-disponibles') ?>"
                        data-api-provincias-url="<?= Vista::url('api/ubigeo/provincias') ?>"
                        data-api-distritos-url="<?= Vista::url('api/ubigeo/distritos') ?>"
                        data-api-consultar-documento-url="<?= Vista::url('api/personas/consultar-documento') ?>"
                        data-csrf-token="<?= $tokenCsrf ?>"
                        data-ruc-tipo-id="<?= $rucTipoId ?>">
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">RUC</th>
                                <th scope="col">Razón Social</th>
                                <th scope="col">Nombre Corto / Comercial</th>
                                <th scope="col" class="text-center">Estado</th>
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

<!-- ========================================================================= -->
<!-- MODAL: ALTA DE EMPRESA (VINCULADA U ORQUESTADA)                         -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalCrearEmpresa" tabindex="-1" aria-labelledby="tituloModalCrearEmpresa" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title f-w-600 text-dark" id="tituloModalCrearEmpresa">
                        <i class="fa-solid fa-building text-primary me-2"></i>Registrar Nueva Empresa Corporativa
                    </h5>
                    <p class="text-secondary f-s-12 mb-0">Seleccione la modalidad de constitución corporativa según el estado en el padrón central.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Pestañas de Modalidad de Alta -->
            <ul class="nav nav-tabs nav-bottom-line px-3 pt-2 bg-light-subtle" id="tabsModalidadCrear" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-vincular-btn" data-bs-toggle="tab" data-bs-target="#tab-vincular-pane" type="button" role="tab" aria-controls="tab-vincular-pane" aria-selected="true">
                        <i class="fa-solid fa-link me-1"></i> 1. Vincular Persona Jurídica Existente
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-orquestada-btn" data-bs-toggle="tab" data-bs-target="#tab-orquestada-pane" type="button" role="tab" aria-controls="tab-orquestada-pane" aria-selected="false">
                        <i class="fa-solid fa-building-circle-check me-1"></i> 2. Alta Orquestada (Nueva Persona Jurídica)
                    </button>
                </li>
            </ul>

            <div class="modal-body p-4">
                <!-- Contenido de Pestañas -->
                <div class="tab-content" id="tabContentModalidadCrear">

                    <!-- PESTAÑA 1: VINCULAR EXISTENTE -->
                    <div class="tab-pane fade show active" id="tab-vincular-pane" role="tabpanel" aria-labelledby="tab-vincular-btn">
                        <form id="formCrearEmpresaVinculada" class="needs-validation" novalidate autocomplete="off">
                            <div class="alert alert-info d-flex align-items-center mb-3 f-s-13 py-2 px-3" role="alert">
                                <i class="fa-solid fa-circle-info f-s-16 me-2"></i>
                                <div>Asocia una Persona Jurídica activa ya inscrita en el padrón como empresa operativa del sistema.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="crearPersonaId" class="form-label f-s-13 f-w-600">Persona Jurídica Titular <span class="text-danger">*</span></label>
                                    <select id="crearPersonaId" name="persona_id" class="form-select form-select-sm" style="width: 100%;" required>
                                        <option value="">Buscar por RUC o Razón Social...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar una persona jurídica disponible.</div>
                                </div>

                                <!-- Tarjeta de previsualización de datos civiles -->
                                <div class="col-12 d-none" id="cardPreviewPersonaVinculada">
                                    <div class="p-3 border rounded bg-light-subtle">
                                        <h6 class="f-s-13 f-w-600 text-dark mb-2"><i class="fa-solid fa-id-card me-1 text-primary"></i> Datos del Padrón Central</h6>
                                        <div class="row g-2 f-s-13">
                                            <div class="col-md-4">
                                                <span class="text-secondary d-block f-s-11">RUC:</span>
                                                <span class="f-w-600 text-dark" id="previewVincularRuc">—</span>
                                            </div>
                                            <div class="col-md-8">
                                                <span class="text-secondary d-block f-s-11">Razón Social:</span>
                                                <span class="f-w-600 text-dark" id="previewVincularRazonSocial">—</span>
                                            </div>
                                            <div class="col-12" id="previewVincularContenedorComercial">
                                                <span class="text-secondary d-block f-s-11">Nombre Comercial:</span>
                                                <span class="text-secondary" id="previewVincularNombreComercial">—</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="crearCodigo" class="form-label f-s-13 f-w-600">Código Corporativo <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                        <input type="text" class="form-control form-control-sm text-uppercase" id="crearCodigo" name="codigo" required minlength="3" maxlength="32" placeholder="Ej. CASAPRO_SAC" pattern="^[A-Z0-9_]+$">
                                    </div>
                                    <div class="form-text f-s-11 text-muted">Inmutable tras creación. Solo mayúsculas, números y guiones bajos (3 a 32 caracteres).</div>
                                    <div class="invalid-feedback">Ingrese un código válido (ej. CASAPRO_SAC).</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="crearNombreCorto" class="form-label f-s-13 f-w-600">Nombre Corto Operativo <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                                        <input type="text" class="form-control form-control-sm" id="crearNombreCorto" name="nombre_corto" required minlength="2" maxlength="64" placeholder="Ej. CasaPRO Perú">
                                    </div>
                                    <div class="form-text f-s-11 text-muted">Nombre compacto para membretes, reportes y selectores (2 a 64 caracteres).</div>
                                    <div class="invalid-feedback">El nombre corto debe tener entre 2 y 64 caracteres.</div>
                                </div>
                            </div>

                            <div class="modal-footer px-0 pb-0 mt-4 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarVinculada">
                                    <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerGuardarVinculada"></i>
                                    <i class="fa-solid fa-floppy-disk me-1" id="iconoGuardarVinculada"></i>
                                    <span>Vincular y Constituir Empresa</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- PESTAÑA 2: ALTA ORQUESTADA (CONTRATO COMPLETO PERSONA JURÍDICA) -->
                    <div class="tab-pane fade" id="tab-orquestada-pane" role="tabpanel" aria-labelledby="tab-orquestada-btn">
                        <form id="formCrearEmpresaOrquestada" class="needs-validation" novalidate autocomplete="off">
                            <div class="alert alert-success d-flex align-items-center mb-3 f-s-13 py-2 px-3" role="alert">
                                <i class="fa-solid fa-wand-magic-sparkles f-s-16 me-2"></i>
                                <div>Crea transaccionalmente la Persona Jurídica en el Padrón Central y constituye la Empresa en un solo paso atómico.</div>
                            </div>

                            <!-- Sección A: Identidad Jurídica -->
                            <h6 class="f-w-600 text-dark mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-landmark text-primary me-1"></i> A. Identidad Jurídica Soberana
                            </h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="orqRuc" class="form-label f-s-13 f-w-600">Número de RUC <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                        <input type="text" class="form-control form-control-sm" id="orqRuc" name="orq_ruc" required maxlength="11" placeholder="RUC de 11 dígitos">
                                        <button type="button" class="btn btn-outline-primary" id="btnConsultarSunat">
                                            <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerConsultarSunat"></i>
                                            <i class="fa-solid fa-magnifying-glass me-1" id="iconoConsultarSunat"></i>
                                            <span>Consultar SUNAT</span>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Ingrese un RUC válido de 11 dígitos que inicie con 10 o 20.</div>
                                    <div id="alertaConsultaSunat" class="alert d-none py-2 px-3 f-s-12 mt-2" role="alert"></div>
                                </div>

                                <div class="col-md-6">
                                    <label for="orqFechaConstitucion" class="form-label f-s-13">Fecha de Constitución</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-calendar-days text-muted"></i></span>
                                        <input type="date" class="form-control form-control-sm" id="orqFechaConstitucion" name="orq_fecha_constitucion">
                                    </div>
                                </div>

                                <div class="col-md-7">
                                    <label for="orqRazonSocial" class="form-label f-s-13 f-w-600">Razón Social <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="orqRazonSocial" name="orq_razon_social" required maxlength="200" placeholder="Ej. Constructora & Inmobiliaria del Sur S.A.C.">
                                    <div class="invalid-feedback">La razón social es obligatoria.</div>
                                </div>

                                <div class="col-md-5">
                                    <label for="orqNombreComercial" class="form-label f-s-13">Nombre Comercial</label>
                                    <input type="text" class="form-control form-control-sm" id="orqNombreComercial" name="orq_nombre_comercial" maxlength="200" placeholder="Ej. Construsur">
                                </div>

                                <div class="col-12">
                                    <label for="orqObjetoSocial" class="form-label f-s-13">Objeto Social / Actividad</label>
                                    <textarea class="form-control form-control-sm" id="orqObjetoSocial" name="orq_objeto_social" rows="2" placeholder="Giro, actividad económica o rubro principal"></textarea>
                                </div>
                            </div>

                            <!-- Sección B: Domicilio Fiscal -->
                            <h6 class="f-w-600 text-dark mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-location-dot text-primary me-1"></i> B. Domicilio Fiscal Principal
                            </h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label for="orqTipoDireccionId" class="form-label f-s-13">Tipo de Dirección</label>
                                    <select class="form-select form-select-sm" id="orqTipoDireccionId" name="orq_tipo_direccion_id">
                                        <option value="">Seleccione...</option>
                                        <?php if (!empty($catalogos['tipos_direccion'])): ?>
                                            <?php foreach ($catalogos['tipos_direccion'] as $tdir): ?>
                                                <option value="<?= (int) $tdir['id'] ?>" <?= strtoupper($tdir['codigo'] ?? '') === 'FISCAL' ? 'selected' : '' ?>>
                                                    <?= Vista::e($tdir['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label for="orqDepartamentoId" class="form-label f-s-13">Departamento</label>
                                            <select class="form-select form-select-sm" id="orqDepartamentoId">
                                                <option value="">Seleccione...</option>
                                                <?php if (!empty($catalogos['departamentos'])): ?>
                                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                                        <option value="<?= (int) $dep['id'] ?>"><?= Vista::e($dep['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="orqProvinciaId" class="form-label f-s-13">Provincia</label>
                                            <select class="form-select form-select-sm" id="orqProvinciaId" disabled>
                                                <option value="">Seleccione depto...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="orqDistritoId" class="form-label f-s-13">Distrito (UBIGEO)</label>
                                            <select class="form-select form-select-sm" id="orqDistritoId" name="orq_distrito_id" disabled>
                                                <option value="">Seleccione prov...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <label for="orqDireccion" class="form-label f-s-13">Dirección Física (Calle, Av., Jr., Nro.)</label>
                                    <input type="text" class="form-control form-control-sm" id="orqDireccion" name="orq_direccion" maxlength="255" placeholder="Ej. Av. Javier Prado Este 456, San Isidro">
                                </div>

                                <div class="col-md-4">
                                    <label for="orqCodigoPostal" class="form-label f-s-13">Código Postal</label>
                                    <input type="text" class="form-control form-control-sm" id="orqCodigoPostal" name="orq_codigo_postal" maxlength="20" placeholder="Ej. 15046">
                                </div>

                                <div class="col-12">
                                    <label for="orqReferencia" class="form-label f-s-13">Referencia</label>
                                    <input type="text" class="form-control form-control-sm" id="orqReferencia" name="orq_referencia" maxlength="255" placeholder="Ej. Frente a Centro Financiero">
                                </div>
                            </div>

                            <!-- Sección C: Medios de Contacto -->
                            <h6 class="f-w-600 text-dark mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-phone-volume text-primary me-1"></i> C. Medios de Contacto Institucional
                            </h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label for="orqTipoContactoId" class="form-label f-s-13">Tipo de Contacto</label>
                                    <select class="form-select form-select-sm" id="orqTipoContactoId" name="orq_tipo_contacto_id">
                                        <option value="">Seleccione...</option>
                                        <?php if (!empty($catalogos['tipos_contacto'])): ?>
                                            <?php foreach ($catalogos['tipos_contacto'] as $tc): ?>
                                                <option value="<?= (int) $tc['id'] ?>" <?= strtoupper($tc['codigo'] ?? '') === 'EMAIL' ? 'selected' : '' ?>>
                                                    <?= Vista::e($tc['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-5">
                                    <label for="orqContactoValor" class="form-label f-s-13">Teléfono o Correo Institucional</label>
                                    <input type="text" class="form-control form-control-sm" id="orqContactoValor" name="orq_contacto_valor" maxlength="150" placeholder="Ej. gerencia@empresa.com / 014567890">
                                </div>

                                <div class="col-md-3">
                                    <label for="orqContactoEtiqueta" class="form-label f-s-13">Etiqueta</label>
                                    <input type="text" class="form-control form-control-sm" id="orqContactoEtiqueta" name="orq_contacto_etiqueta" maxlength="50" placeholder="Ej. Central Telefónica">
                                </div>
                            </div>

                            <!-- Sección D: Atributos Corporativos de la Empresa -->
                            <h6 class="f-w-600 text-dark mb-2 border-bottom pb-1">
                                <i class="fa-solid fa-building text-primary me-1"></i> D. Datos de la Empresa en CasaPRO
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="orqCodigo" class="form-label f-s-13 f-w-600">Código Corporativo <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                        <input type="text" class="form-control form-control-sm text-uppercase" id="orqCodigo" name="orq_codigo" required minlength="3" maxlength="32" placeholder="Ej. CONSTRUSUR_SAC" pattern="^[A-Z0-9_]+$">
                                    </div>
                                    <div class="form-text f-s-11 text-muted">Inmutable. Solo letras mayúsculas, números y guiones bajos.</div>
                                    <div class="invalid-feedback">Código obligatorio (3 a 32 caracteres).</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="orqNombreCorto" class="form-label f-s-13 f-w-600">Nombre Corto Operativo <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                                        <input type="text" class="form-control form-control-sm" id="orqNombreCorto" name="orq_nombre_corto" required minlength="2" maxlength="64" placeholder="Ej. Construsur Perú">
                                    </div>
                                    <div class="invalid-feedback">Nombre corto obligatorio (2 a 64 caracteres).</div>
                                </div>
                            </div>

                            <div class="modal-footer px-0 pb-0 mt-4 border-top">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarOrquestada">
                                    <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerGuardarOrquestada"></i>
                                    <i class="fa-solid fa-floppy-disk me-1" id="iconoGuardarOrquestada"></i>
                                    <span>Crear Persona y Constituir Empresa</span>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDICIÓN DE EMPRESA (INMUTABILIDAD: SOLO NOMBRE CORTO)              -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditarEmpresa" tabindex="-1" aria-labelledby="tituloModalEditarEmpresa" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title f-w-600 text-dark" id="tituloModalEditarEmpresa">
                        <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Editar Empresa Corporativa
                    </h5>
                    <p class="text-secondary f-s-12 mb-0">Actualización de denominación operativa. Los datos de identidad y código son inmutables.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formEditarEmpresa" class="needs-validation" novalidate autocomplete="off">
                <input type="hidden" id="editarEmpresaId" name="empresa_id" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label f-s-13 text-secondary mb-1">Razón Social Vinculada</label>
                            <input type="text" class="form-control form-control-sm bg-light text-dark f-w-600" id="editarRazonSocial" readonly disabled>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-s-13 text-secondary mb-1">Número de RUC</label>
                            <input type="text" class="form-control form-control-sm bg-light" id="editarRuc" readonly disabled>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label f-s-13 text-secondary mb-1">Código Corporativo</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="text" class="form-control form-control-sm bg-light font-monospace" id="editarCodigo" readonly disabled>
                            </div>
                            <div class="form-text f-s-11 text-muted">Inmutable por mandato de gobernanza.</div>
                        </div>

                        <div class="col-12">
                            <label for="editarNombreCorto" class="form-label f-s-13 f-w-600">Nombre Corto Operativo <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-solid fa-tag"></i></span>
                                <input type="text" class="form-control form-control-sm" id="editarNombreCorto" name="nombre_corto" required minlength="2" maxlength="64" placeholder="Ej. CasaPRO Perú">
                            </div>
                            <div class="form-text f-s-11 text-muted">Denominación para membretes, documentos y selectores territoriales.</div>
                            <div class="invalid-feedback">El nombre corto es obligatorio (2 a 64 caracteres).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label f-s-13 text-secondary mb-1">Estado Actual</label>
                            <div id="editarEstadoPreview"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnActualizarEmpresa">
                        <i class="fa-solid fa-spinner fa-spin me-1 d-none" id="spinnerActualizarEmpresa"></i>
                        <i class="fa-solid fa-floppy-disk me-1" id="iconoActualizarEmpresa"></i>
                        <span>Guardar Cambios</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: FICHA 360° INTEGRAL DE LA EMPRESA (ALINA MODAL-LG SCROLLABLE)     -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalFichaEmpresa" tabindex="-1" aria-labelledby="tituloModalFichaEmpresa" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title f-w-600 mb-1 text-dark" id="tituloModalFichaEmpresa">
                        <i class="fa-solid fa-building text-primary me-2"></i>Ficha 360° de la Empresa
                    </h5>
                    <div id="fichaEmpresaSubtitulo" class="d-flex align-items-center gap-2">
                        <span id="fichaBadgeCodigo" class="badge bg-primary-subtle text-primary font-monospace"></span>
                        <span id="fichaBadgeEstado" class="badge"></span>
                        <span id="fichaTextoId" class="text-muted f-s-12"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Spinner de Carga -->
                <div id="fichaSpinnerCarga" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando ficha 360°...</span>
                    </div>
                    <p class="text-secondary mt-2 mb-0 f-s-13">Consultando estructura corporativa e identidad central...</p>
                </div>

                <!-- Contenido de la Ficha 360 -->
                <div id="fichaContenidoDetalle">
                    <ul class="nav nav-tabs nav-bottom-line px-3 pt-2" id="fichaEmpresaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-ficha-empresa-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-empresa-pane" type="button" role="tab" aria-controls="tab-ficha-empresa-pane" aria-selected="true">
                                <i class="fa-solid fa-briefcase me-1"></i> Empresa
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-ficha-juridica-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-juridica-pane" type="button" role="tab" aria-controls="tab-ficha-juridica-pane" aria-selected="false">
                                <i class="fa-solid fa-landmark me-1"></i> Persona Jurídica
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-ficha-domicilios-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-domicilios-pane" type="button" role="tab" aria-controls="tab-ficha-domicilios-pane" aria-selected="false">
                                <i class="fa-solid fa-location-dot me-1"></i> Domicilios
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-ficha-contactos-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-contactos-pane" type="button" role="tab" aria-controls="tab-ficha-contactos-pane" aria-selected="false">
                                <i class="fa-solid fa-address-book me-1"></i> Contactos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation" id="tabItemFichaRepresentacion" style="display: none;">
                            <button class="nav-link" id="tab-ficha-representantes-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-representantes-pane" type="button" role="tab" aria-controls="tab-ficha-representantes-pane" aria-selected="false">
                                <i class="fa-solid fa-user-tie me-1"></i> Representantes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-ficha-colaboradores-btn" data-bs-toggle="tab" data-bs-target="#tab-ficha-colaboradores-pane" type="button" role="tab" aria-controls="tab-ficha-colaboradores-pane" aria-selected="false">
                                <i class="fa-solid fa-users-gear me-1"></i> Colaboradores Asignados
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4" id="fichaEmpresaTabContent">
                        <!-- Panel 1: Atributos de Empresa -->
                        <div class="tab-pane fade show active" id="tab-ficha-empresa-pane" role="tabpanel" aria-labelledby="tab-ficha-empresa-btn">
                            <div class="row g-3" id="fichaGridEmpresa"></div>
                        </div>

                        <!-- Panel 2: Persona Jurídica -->
                        <div class="tab-pane fade" id="tab-ficha-juridica-pane" role="tabpanel" aria-labelledby="tab-ficha-juridica-btn">
                            <div class="row g-3 mb-4" id="fichaGridJuridica"></div>
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-file-lines me-1 text-primary"></i> Documentos Registrados</h6>
                            <div id="fichaContenedorDocumentos" class="table-responsive"></div>
                        </div>

                        <!-- Panel 3: Domicilios -->
                        <div class="tab-pane fade" id="tab-ficha-domicilios-pane" role="tabpanel" aria-labelledby="tab-ficha-domicilios-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-location-dot me-1 text-primary"></i> Direcciones Registradas</h6>
                            <div id="fichaContenedorDirecciones" class="table-responsive"></div>
                        </div>

                        <!-- Panel 4: Contactos -->
                        <div class="tab-pane fade" id="tab-ficha-contactos-pane" role="tabpanel" aria-labelledby="tab-ficha-contactos-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-phone-volume me-1 text-primary"></i> Medios de Contacto</h6>
                            <div id="fichaContenedorContactos" class="table-responsive"></div>
                        </div>

                        <!-- Panel 5: Representantes Legales -->
                        <div class="tab-pane fade" id="tab-ficha-representantes-pane" role="tabpanel" aria-labelledby="tab-ficha-representantes-btn">
                            <h6 class="f-w-600 mb-2 text-dark"><i class="fa-solid fa-user-tie me-1 text-primary"></i> Representantes Legales Inscritos</h6>
                            <div id="fichaContenedorRepresentantes" class="table-responsive"></div>
                        </div>

                        <!-- Panel 6: Colaboradores Asignados (Vista espejo de solo consulta) -->
                        <div class="tab-pane fade" id="tab-ficha-colaboradores-pane" role="tabpanel" aria-labelledby="tab-ficha-colaboradores-btn">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="f-w-600 mb-0 text-dark">
                                        <i class="fa-solid fa-users-gear me-1 text-primary"></i> Colaboradores con Asignación Territorial
                                    </h6>
                                    <span class="text-secondary f-s-12">Usuarios autorizados para operar en el ámbito de esta empresa (vista de consulta/auditoría)</span>
                                </div>
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" id="badgeTotalColaboradoresEmpresa">0 Asignaciones</span>
                            </div>
                            <div id="fichaContenedorColaboradores" class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light-subtle">
                                        <tr>
                                            <th scope="col" class="f-s-12">Usuario</th>
                                            <th scope="col" class="f-s-12">Correo</th>
                                            <th scope="col" class="f-s-12">Rol Territorial</th>
                                            <th scope="col" class="f-s-12">Estado Asignación</th>
                                            <th scope="col" class="f-s-12">Asignado En</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyFichaColaboradores">
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted f-s-12">Sin colaboradores asignados.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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

<!-- Catálogos Sembrados de CasaPRO en JSON Seguro -->
<script id="datosCatalogosCasaPro" type="application/json"><?= json_encode($catalogos ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
