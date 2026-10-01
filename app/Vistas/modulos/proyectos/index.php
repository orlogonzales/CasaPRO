<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista del Catálogo de Proyectos Inmobiliarios — Microfase 3A.
 * Basada estrictamente en la anatomía de blank.html, data_table.html y modals.html de Alina Bootstrap 5.
 *
 * Variables disponibles:
 * @var array $catalogos Catálogos del padrón (departamentos, etc.)
 * @var string $tokenCsrf Token de seguridad CSRF
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <!-- Encabezado de la Tarjeta Principal -->
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 text-dark f-w-600">
                        <i class="fa-solid fa-city text-primary me-2"></i>Catálogo de Proyectos Inmobiliarios
                    </h5>
                    <p class="text-secondary f-s-13 mb-0">Gestión de desarrollos urbanísticos, terrenos matrices y áreas bajo la empresa activa.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevoProyecto">
                        <i class="fa-solid fa-plus me-1"></i> Nuevo Proyecto
                    </button>
                </div>
            </div>

            <!-- Barra de Filtros Rápidos -->
            <div class="card-body border-bottom bg-light-subtle py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label for="filtroEstado" class="form-label f-s-13 text-secondary mb-1">Estado de Proyecto</label>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="PLANIFICACION">Planificación</option>
                            <option value="EN_VENTA">En Venta</option>
                            <option value="CONSOLIDADO">Consolidado</option>
                            <option value="CERRADO">Cerrado</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label for="filtroTipoProyecto" class="form-label f-s-13 text-secondary mb-1">Modalidad Legal</label>
                        <select id="filtroTipoProyecto" class="form-select form-select-sm">
                            <option value="">Todas las modalidades</option>
                            <option value="PROPIO">Terreno Propio</option>
                            <option value="CONVENIO_APV">Convenio APV</option>
                            <option value="ASOCIATIVO">Asociativo / Copropiedad</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-sm-12 d-flex justify-content-md-end">
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contenedor DataTables Server-Side -->
            <div class="card-body p-0">
                <div class="app-datatable-default overflow-auto app-scroll p-3">
                    <table id="tablaProyectos" class="display app-data-table default-data-table table table-hover align-middle w-100"
                        data-api-url="<?= Vista::url('api/proyectos') ?>"
                        data-api-provincias-url="<?= Vista::url('api/ubigeo/provincias') ?>"
                        data-api-distritos-url="<?= Vista::url('api/ubigeo/distritos') ?>"
                        data-ficha-url-base="<?= Vista::url('proyectos') ?>"
                        data-csrf-token="<?= $tokenCsrf ?>">
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre del Proyecto</th>
                                <th scope="col">Modalidad</th>
                                <th scope="col">Ubicación</th>
                                <th scope="col" class="text-center">Predios</th>
                                <th scope="col" class="text-end">Área Registral</th>
                                <th scope="col" class="text-center">Moneda</th>
                                <th scope="col" class="text-center">Estado</th>
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
<!-- MODAL: ALTA DE NUEVO PROYECTO INMOBILIARIO                               -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalCrearProyecto" tabindex="-1" aria-labelledby="tituloModalCrearProyecto" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalCrearProyecto">
                    <i class="fa-solid fa-city me-2"></i>Registrar Nuevo Proyecto Inmobiliario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formCrearProyecto" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Código Corporativo y Modalidad -->
                        <div class="col-md-4">
                            <label for="crearCodigo" class="form-label f-s-13 f-w-600">Código del Proyecto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="crearCodigo" name="codigo" placeholder="EJ: PRJ_VALLE_SUR" required maxlength="32">
                            <div class="invalid-feedback f-s-12">Código alfanumérico obligatorio (3-32 carácteres).</div>
                        </div>

                        <div class="col-md-5">
                            <label for="crearNombre" class="form-label f-s-13 f-w-600">Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="crearNombre" name="nombre" placeholder="Nombre comercial del proyecto" required maxlength="150">
                            <div class="invalid-feedback f-s-12">Nombre comercial obligatorio.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="crearTipoProyecto" class="form-label f-s-13 f-w-600">Modalidad Legal <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="crearTipoProyecto" name="tipo_proyecto" required>
                                <option value="PROPIO">Terreno Propio</option>
                                <option value="CONVENIO_APV">Convenio APV</option>
                                <option value="ASOCIATIVO">Asociativo</option>
                            </select>
                        </div>

                        <!-- Moneda y Política de Tolerancia -->
                        <div class="col-md-4">
                            <label for="crearMoneda" class="form-label f-s-13 f-w-600">Moneda Oficial <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="crearMoneda" name="moneda" required>
                                <option value="PEN">PEN (Soles - S/)</option>
                                <option value="USD">USD (Dólares Americanos - $)</option>
                            </select>
                            <div class="form-text f-s-11 text-muted">Soberana e inmutable tras la creación.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="crearTipoTolerancia" class="form-label f-s-13 f-w-600">Tipo de Tolerancia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="crearTipoTolerancia" name="tipo_tolerancia" required>
                                <option value="ABSOLUTA_M2">Absoluta en m²</option>
                                <option value="PORCENTUAL">Porcentual (%)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="crearValorTolerancia" class="form-label f-s-13 f-w-600">Valor de Tolerancia <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm" id="crearValorTolerancia" name="valor_tolerancia" value="0.5000" required>
                            <div class="invalid-feedback f-s-12">Valor de tolerancia válido obligatorio.</div>
                        </div>

                        <!-- Ubicación Geográfica en Cascada (UBIGEO) -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12"><h6 class="f-s-13 text-secondary f-w-600 mb-0"><i class="fa-solid fa-map-pin me-1"></i>Ubicación Geográfica Matriz (UBIGEO)</h6></div>

                        <div class="col-md-4">
                            <label for="crearDepartamento" class="form-label f-s-13">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo" id="crearDepartamento" required>
                                <option value="">Seleccione...</option>
                                <?php if (!empty($catalogos['departamentos'])): ?>
                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                        <option value="<?= (int) $dep['id'] ?>"><?= htmlspecialchars((string) $dep['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="crearProvincia" class="form-label f-s-13">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo" id="crearProvincia" disabled required>
                                <option value="">Seleccione dpto...</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="crearDistritoId" class="form-label f-s-13">Distrito <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="crearDistritoId" name="distrito_id" disabled required>
                                <option value="">Seleccione prov...</option>
                            </select>
                            <div class="invalid-feedback f-s-12">Debe seleccionar el distrito.</div>
                        </div>

                        <div class="col-12">
                            <label for="crearDireccion" class="form-label f-s-13">Dirección / Referencia de Ubicación</label>
                            <input type="text" class="form-control form-control-sm" id="crearDireccion" name="direccion_referencia" placeholder="ej. Km 14.5 Carretera Cusco - Paruro, Paraje Huayllabamba">
                        </div>

                        <div class="col-12">
                            <label for="crearDescripcion" class="form-label f-s-13">Memoria Descriptiva / Observaciones</label>
                            <textarea class="form-control form-control-sm" id="crearDescripcion" name="descripcion" rows="2" placeholder="Reseña del proyecto, topografía, servicios previstos..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarProyecto">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Proyecto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDICIÓN DE PROYECTO INMOBILIARIO                                  -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalEditarProyecto" tabindex="-1" aria-labelledby="tituloModalEditarProyecto" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title f-s-16 f-w-600" id="tituloModalEditarProyecto">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Editar Proyecto Inmobiliario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formEditarProyecto" class="needs-validation" novalidate>
                <input type="hidden" id="editarProyectoId" name="id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600 text-muted">Código Corporativo (Inmutable)</label>
                            <input type="text" class="form-control form-control-sm bg-light text-muted font-monospace" id="editarCodigo" readonly>
                        </div>

                        <div class="col-md-5">
                            <label for="editarNombre" class="form-label f-s-13 f-w-600">Nombre Comercial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="editarNombre" name="nombre" required maxlength="150">
                        </div>

                        <div class="col-md-3">
                            <label for="editarTipoProyecto" class="form-label f-s-13 f-w-600">Modalidad Legal <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editarTipoProyecto" name="tipo_proyecto" required>
                                <option value="PROPIO">Terreno Propio</option>
                                <option value="CONVENIO_APV">Convenio APV</option>
                                <option value="ASOCIATIVO">Asociativo</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label f-s-13 f-w-600 text-muted">Moneda (Inmutable)</label>
                            <input type="text" class="form-control form-control-sm bg-light text-muted font-monospace" id="editarMoneda" readonly>
                        </div>

                        <div class="col-md-4">
                            <label for="editarTipoTolerancia" class="form-label f-s-13 f-w-600">Tipo de Tolerancia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editarTipoTolerancia" name="tipo_tolerancia" required>
                                <option value="ABSOLUTA_M2">Absoluta en m²</option>
                                <option value="PORCENTUAL">Porcentual (%)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="editarValorTolerancia" class="form-label f-s-13 f-w-600">Valor de Tolerancia <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0" class="form-control form-control-sm" id="editarValorTolerancia" name="valor_tolerancia" required>
                        </div>

                        <!-- UBIGEO Edición -->
                        <div class="col-12"><hr class="my-1 text-muted"></div>
                        <div class="col-12"><h6 class="f-s-13 text-secondary f-w-600 mb-0"><i class="fa-solid fa-map-pin me-1"></i>Ubicación Geográfica Matriz</h6></div>

                        <div class="col-md-4">
                            <label for="editarDepartamento" class="form-label f-s-13">Departamento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo" id="editarDepartamento" required>
                                <option value="">Seleccione...</option>
                                <?php if (!empty($catalogos['departamentos'])): ?>
                                    <?php foreach ($catalogos['departamentos'] as $dep): ?>
                                        <option value="<?= (int) $dep['id'] ?>"><?= htmlspecialchars((string) $dep['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="editarProvincia" class="form-label f-s-13">Provincia <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select-ubigeo" id="editarProvincia" required>
                                <option value="">Seleccione dpto...</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="editarDistritoId" class="form-label f-s-13">Distrito <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editarDistritoId" name="distrito_id" required>
                                <option value="">Seleccione prov...</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="editarDireccion" class="form-label f-s-13">Dirección / Referencia</label>
                            <input type="text" class="form-control form-control-sm" id="editarDireccion" name="direccion_referencia">
                        </div>

                        <div class="col-12">
                            <label for="editarDescripcion" class="form-label f-s-13">Memoria Descriptiva</label>
                            <textarea class="form-control form-control-sm" id="editarDescripcion" name="descripcion" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light-subtle py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnActualizarProyecto">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Proyecto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
