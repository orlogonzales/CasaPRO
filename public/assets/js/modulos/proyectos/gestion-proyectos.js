/**
 * gestion-proyectos.js — Módulo JavaScript Vanilla ES6+ para la administración de Proyectos Inmobiliarios.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 * Reglas Inviolables:
 * - Cero window.location.reload() / location.reload() — Mutaciones 100% asíncronas.
 * - Cero llamadas a $.ajax, $.get, $.post, $.getJSON en código propio de CasaPRO.
 * - Reutilización de jQuery exclusivamente como dependencia técnica interna de DataTables.
 * - Recarga asíncrona de DataTables con self.tabla.ajax.reload(null, false) conservando paginación.
 * - Cascada geográfica UBIGEO de 3 niveles nativa con fetch.
 * - Inmunidad XSS en manipulación de DOM y escape de datos.
 */

(function () {
    'use strict';

    if (window.CasaProProyectos) {
        return;
    }

    /**
     * Función utilitaria de escape HTML contra inyecciones XSS.
     */
    function escaparHtml(valor) {
        if (valor === null || valor === undefined) return '';
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Formatea número con decimales y separadores de miles.
     */
    function formatearNumero(valor, decimales = 4) {
        const num = parseFloat(valor);
        if (isNaN(num)) return '0.0000';
        return num.toLocaleString('es-PE', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        });
    }

    /**
     * Mapa de ordenamiento de columnas para DataTables server-side whitelist.
     */
    const MAPA_ORDEN_COLUMNAS = {
        0: 'codigo',
        1: 'nombre',
        2: 'tipo_proyecto',
        3: 'distrito',
        7: 'estado'
    };

    const MODULO = {
        tabla: null,
        apiUrl: '',
        provinciasUrl: '',
        distritosUrl: '',
        fichaUrlBase: '',
        tokenCsrf: '',
        modalCrearBs: null,
        modalEditarBs: null,

        /**
         * Inicialización del módulo al cargar la página.
         */
        init: function () {
            const tablaEl = document.getElementById('tablaProyectos');
            if (!tablaEl) return;

            this.apiUrl = tablaEl.getAttribute('data-api-url') || '/api/proyectos';
            this.provinciasUrl = tablaEl.getAttribute('data-api-provincias-url') || '/api/ubigeo/provincias';
            this.distritosUrl = tablaEl.getAttribute('data-api-distritos-url') || '/api/ubigeo/distritos';
            this.fichaUrlBase = tablaEl.getAttribute('data-ficha-url-base') || '/proyectos';
            this.tokenCsrf = tablaEl.getAttribute('data-csrf-token') || '';

            // Inicializar modales Bootstrap 5
            const modalCrearEl = document.getElementById('modalCrearProyecto');
            if (modalCrearEl) this.modalCrearBs = bootstrap.Modal.getOrCreateInstance(modalCrearEl);

            const modalEditarEl = document.getElementById('modalEditarProyecto');
            if (modalEditarEl) this.modalEditarBs = bootstrap.Modal.getOrCreateInstance(modalEditarEl);

            this.inicializarDataTables(tablaEl);
            this.vincularFiltros();
            this.vincularCascadaUbigeo();
            this.vincularFormularios();
            this.vincularEventosTabla();
        },

        /**
         * Inicializa DataTables con adaptador asíncrono window.fetch().
         */
        inicializarDataTables: function (tablaEl) {
            const self = this;

            this.tabla = $(tablaEl).DataTable({
                serverSide: true,
                processing: true,
                searchDelay: 400,
                order: [[0, 'desc']],
                language: {
                    search: "Buscar:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ proyectos",
                    infoEmpty: "Mostrando 0 a 0 de 0 proyectos",
                    infoFiltered: "(filtrado de _MAX_ registros totales)",
                    loadingRecords: "Cargando...",
                    zeroRecords: "No se encontraron proyectos coincidentes",
                    emptyTable: "No hay proyectos registrados para la empresa activa",
                    paginate: {
                        first: "Primero",
                        previous: "Anterior",
                        next: "Siguiente",
                        last: "Último"
                    }
                },
                ajax: async function (dtParams, callback, settings) {
                    try {
                        const params = new URLSearchParams();
                        params.append('draw', String(dtParams.draw));
                        params.append('start', String(dtParams.start));
                        params.append('length', String(dtParams.length));

                        if (dtParams.search && dtParams.search.value) {
                            const val = dtParams.search.value.trim();
                            if (val !== '') params.append('search', val);
                        }

                        if (dtParams.order && dtParams.order.length > 0) {
                            const colIdx = dtParams.order[0].column;
                            const colDir = dtParams.order[0].dir;
                            const campoDb = MAPA_ORDEN_COLUMNAS[colIdx] || 'codigo';
                            params.append('order_column', campoDb);
                            params.append('order_dir', colDir ? colDir.toUpperCase() : 'DESC');
                        }

                        const filtroEstado = document.getElementById('filtroEstado')?.value || '';
                        if (filtroEstado) {
                            params.append('estado', filtroEstado);
                        }

                        const filtroTipo = document.getElementById('filtroTipoProyecto')?.value || '';
                        if (filtroTipo) {
                            params.append('tipo_proyecto', filtroTipo);
                        }

                        const urlCompleta = `${self.apiUrl}?${params.toString()}`;
                        const respuesta = await window.fetch(urlCompleta, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (respuesta.status === 401) {
                            self.notificarAlerta('error', 'Sesión expirada', 'Debe iniciar sesión nuevamente.');
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        if (respuesta.status === 403) {
                            self.notificarAlerta('error', 'Acceso denegado', 'No cuenta con el privilegio requerido (proyectos.ver).');
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        if (!respuesta.ok) {
                            const errJson = await respuesta.json().catch(() => null);
                            const msj = errJson && errJson.mensaje ? errJson.mensaje : `Error del servidor (HTTP ${respuesta.status})`;
                            self.notificarAlerta('error', 'Error en consulta', msj);
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        const json = await respuesta.json();
                        callback(json);
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de red', 'No se pudo comunicar con el servidor.');
                        callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                    }
                },
                columns: [
                    // Columna 0: Código
                    {
                        data: 'codigo',
                        render: function (data) {
                            return `<span class="badge bg-primary-subtle text-primary font-monospace f-s-12 f-w-600 px-2 py-1">${escaparHtml(data)}</span>`;
                        }
                    },
                    // Columna 1: Nombre del Proyecto
                    {
                        data: 'nombre',
                        render: function (data, type, row) {
                            const urlFicha = `${self.fichaUrlBase}/${row.id}`;
                            let html = `<a href="${urlFicha}" class="text-dark f-w-600 text-decoration-none hover-primary">${escaparHtml(data)}</a>`;
                            if (row.direccion_referencia) {
                                html += `<br><small class="text-muted f-s-11"><i class="fa-solid fa-location-dot me-1 text-danger"></i>${escaparHtml(row.direccion_referencia)}</small>`;
                            }
                            return html;
                        }
                    },
                    // Columna 2: Modalidad Legal
                    {
                        data: 'tipo_proyecto',
                        render: function (data) {
                            let badgeClass = 'bg-secondary-subtle text-secondary';
                            let label = data;
                            if (data === 'PROPIO') {
                                badgeClass = 'bg-primary-subtle text-primary';
                                label = 'Terreno Propio';
                            } else if (data === 'CONVENIO_APV') {
                                badgeClass = 'bg-info-subtle text-info';
                                label = 'Convenio APV';
                            } else if (data === 'ASOCIATIVO') {
                                badgeClass = 'bg-warning-subtle text-warning';
                                label = 'Asociativo';
                            }
                            return `<span class="badge ${badgeClass} f-s-12 px-2 py-1">${escaparHtml(label)}</span>`;
                        }
                    },
                    // Columna 3: Ubicación
                    {
                        data: null,
                        render: function (data, type, row) {
                            const distrito = row.distrito_nombre ? escaparHtml(row.distrito_nombre) : '—';
                            const depto = row.departamento_nombre ? escaparHtml(row.departamento_nombre) : '';
                            return `<span class="text-dark f-s-13">${distrito}</span><br><small class="text-muted f-s-11">${depto}</small>`;
                        }
                    },
                    // Columna 4: Predios Matrices
                    {
                        data: 'total_predios',
                        className: 'text-center',
                        render: function (data) {
                            const cantidad = parseInt(data || 0, 10);
                            if (cantidad > 0) {
                                return `<span class="badge bg-light text-dark border px-2 py-1 font-monospace"><i class="fa-solid fa-layer-group text-primary me-1"></i>${cantidad}</span>`;
                            }
                            return `<span class="badge bg-light text-secondary border px-2 py-1 f-s-11">0 predios</span>`;
                        }
                    },
                    // Columna 5: Área Registral Matriz
                    {
                        data: 'area_registral_total_m2',
                        className: 'text-end',
                        render: function (data) {
                            const num = parseFloat(data || 0);
                            if (num > 0) {
                                return `<span class="font-monospace f-w-600 text-dark">${formatearNumero(num, 4)} m²</span>`;
                            }
                            return '<span class="text-muted f-s-12 font-monospace">—</span>';
                        }
                    },
                    // Columna 6: Moneda
                    {
                        data: 'moneda',
                        className: 'text-center',
                        render: function (data) {
                            const badge = data === 'USD' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                            const simbolo = data === 'USD' ? '$' : 'S/';
                            return `<span class="badge ${badge} font-monospace f-s-12 px-2 py-1">${escaparHtml(data)} (${simbolo})</span>`;
                        }
                    },
                    // Columna 7: Estado
                    {
                        data: 'estado',
                        className: 'text-center',
                        render: function (data) {
                            let badgeClass = 'bg-secondary text-white';
                            let icon = 'fa-circle-question';
                            let label = data;

                            switch (data) {
                                case 'PLANIFICACION':
                                    badgeClass = 'bg-info-subtle text-info border border-info';
                                    icon = 'fa-compass';
                                    label = 'Planificación';
                                    break;
                                case 'EN_VENTA':
                                    badgeClass = 'bg-success-subtle text-success border border-success';
                                    icon = 'fa-tag';
                                    label = 'En Venta';
                                    break;
                                case 'CONSOLIDADO':
                                    badgeClass = 'bg-primary-subtle text-primary border border-primary';
                                    icon = 'fa-city';
                                    label = 'Consolidado';
                                    break;
                                case 'CERRADO':
                                    badgeClass = 'bg-secondary-subtle text-secondary border border-secondary';
                                    icon = 'fa-lock';
                                    label = 'Cerrado';
                                    break;
                                case 'INACTIVO':
                                    badgeClass = 'bg-danger-subtle text-danger border border-danger';
                                    icon = 'fa-ban';
                                    label = 'Inactivo';
                                    break;
                            }

                            return `<span class="badge ${badgeClass} f-s-12 px-2 py-1"><i class="fa-solid ${icon} me-1"></i>${label}</span>`;
                        }
                    },
                    // Columna 8: Acciones
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const urlFicha = `${self.fichaUrlBase}/${row.id}`;
                            const esInactivo = row.estado === 'INACTIVO';
                            const toggleColor = esInactivo ? 'success' : 'danger';
                            const toggleIcon = esInactivo ? 'check' : 'ban';
                            const toggleTitulo = esInactivo ? 'Reactivar Proyecto' : 'Desactivar Proyecto';

                            return `
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="${urlFicha}" class="btn btn-outline-primary" data-bs-toggle="tooltip" data-bs-title="Ver Ficha 360°">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-warning btn-editar-proyecto" data-id="${row.id}" data-bs-toggle="tooltip" data-bs-title="Editar Proyecto">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-${toggleColor} btn-conmutar-estado" data-id="${row.id}" data-estado-actual="${escaparHtml(row.estado)}" data-nombre="${escaparHtml(row.nombre)}" data-bs-toggle="tooltip" data-bs-title="${toggleTitulo}">
                                        <i class="fa-solid fa-${toggleIcon}"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ],
                drawCallback: function () {
                    // Inicializar tooltips de Bootstrap
                    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltips.map(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }
            });
        },

        /**
         * Vincula los filtros rápidos.
         */
        vincularFiltros: function () {
            const self = this;

            const filtroEstado = document.getElementById('filtroEstado');
            if (filtroEstado) {
                filtroEstado.addEventListener('change', () => {
                    self.tabla.ajax.reload();
                });
            }

            const filtroTipo = document.getElementById('filtroTipoProyecto');
            if (filtroTipo) {
                filtroTipo.addEventListener('change', () => {
                    self.tabla.ajax.reload();
                });
            }

            const btnLimpiar = document.getElementById('btnLimpiarFiltros');
            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', () => {
                    if (filtroEstado) filtroEstado.value = '';
                    if (filtroTipo) filtroTipo.value = '';
                    self.tabla.search('').draw();
                });
            }

            const btnNuevo = document.getElementById('btnNuevoProyecto');
            if (btnNuevo) {
                btnNuevo.addEventListener('click', () => {
                    const form = document.getElementById('formCrearProyecto');
                    if (form) {
                        form.reset();
                        form.classList.remove('was-validated');
                        // Restablecer valores por defecto
                        const selTol = document.getElementById('crearTipoTolerancia');
                        if (selTol) selTol.value = 'ABSOLUTA_M2';
                        const valTol = document.getElementById('crearValorTolerancia');
                        if (valTol) valTol.value = '0.5000';
                        const selMoneda = document.getElementById('crearMoneda');
                        if (selMoneda) selMoneda.value = 'PEN';
                        const selProv = document.getElementById('crearProvincia');
                        if (selProv) {
                            selProv.innerHTML = '<option value="">Seleccione dpto...</option>';
                            selProv.disabled = true;
                        }
                        const selDist = document.getElementById('crearDistritoId');
                        if (selDist) {
                            selDist.innerHTML = '<option value="">Seleccione prov...</option>';
                            selDist.disabled = true;
                        }
                    }
                    if (self.modalCrearBs) self.modalCrearBs.show();
                });
            }
        },

        /**
         * Cascada geográfica UBIGEO de 3 niveles para creación y edición.
         */
        vincularCascadaUbigeo: function () {
            const self = this;

            // Cascada en Modal Crear
            const crearDep = document.getElementById('crearDepartamento');
            const crearProv = document.getElementById('crearProvincia');
            const crearDist = document.getElementById('crearDistritoId');

            if (crearDep && crearProv && crearDist) {
                crearDep.addEventListener('change', async function () {
                    const depId = this.value;
                    crearProv.innerHTML = '<option value="">Cargando provincias...</option>';
                    crearProv.disabled = true;
                    crearDist.innerHTML = '<option value="">Seleccione prov...</option>';
                    crearDist.disabled = true;

                    if (!depId) {
                        crearProv.innerHTML = '<option value="">Seleccione dpto...</option>';
                        return;
                    }

                    try {
                        const res = await window.fetch(`${self.provinciasUrl}?departamento_id=${depId}`);
                        const json = await res.json();
                        const lista = json.datos || [];
                        let opts = '<option value="">Seleccione provincia...</option>';
                        lista.forEach(p => {
                            opts += `<option value="${p.id}">${escaparHtml(p.nombre)}</option>`;
                        });
                        crearProv.innerHTML = opts;
                        crearProv.disabled = false;
                    } catch {
                        crearProv.innerHTML = '<option value="">Error al cargar</option>';
                    }
                });

                crearProv.addEventListener('change', async function () {
                    const provId = this.value;
                    crearDist.innerHTML = '<option value="">Cargando distritos...</option>';
                    crearDist.disabled = true;

                    if (!provId) {
                        crearDist.innerHTML = '<option value="">Seleccione prov...</option>';
                        return;
                    }

                    try {
                        const res = await window.fetch(`${self.distritosUrl}?provincia_id=${provId}`);
                        const json = await res.json();
                        const lista = json.datos || [];
                        let opts = '<option value="">Seleccione distrito...</option>';
                        lista.forEach(d => {
                            opts += `<option value="${d.id}">${escaparHtml(d.nombre)}</option>`;
                        });
                        crearDist.innerHTML = opts;
                        crearDist.disabled = false;
                    } catch {
                        crearDist.innerHTML = '<option value="">Error al cargar</option>';
                    }
                });
            }

            // Cascada en Modal Editar
            const editDep = document.getElementById('editarDepartamento');
            const editProv = document.getElementById('editarProvincia');
            const editDist = document.getElementById('editarDistritoId');

            if (editDep && editProv && editDist) {
                editDep.addEventListener('change', async function () {
                    const depId = this.value;
                    editProv.innerHTML = '<option value="">Cargando provincias...</option>';
                    editProv.disabled = true;
                    editDist.innerHTML = '<option value="">Seleccione prov...</option>';
                    editDist.disabled = true;

                    if (!depId) {
                        editProv.innerHTML = '<option value="">Seleccione dpto...</option>';
                        return;
                    }

                    try {
                        const res = await window.fetch(`${self.provinciasUrl}?departamento_id=${depId}`);
                        const json = await res.json();
                        const lista = json.datos || [];
                        let opts = '<option value="">Seleccione provincia...</option>';
                        lista.forEach(p => {
                            opts += `<option value="${p.id}">${escaparHtml(p.nombre)}</option>`;
                        });
                        editProv.innerHTML = opts;
                        editProv.disabled = false;
                    } catch {
                        editProv.innerHTML = '<option value="">Error al cargar</option>';
                    }
                });

                editProv.addEventListener('change', async function () {
                    const provId = this.value;
                    editDist.innerHTML = '<option value="">Cargando distritos...</option>';
                    editDist.disabled = true;

                    if (!provId) {
                        editDist.innerHTML = '<option value="">Seleccione prov...</option>';
                        return;
                    }

                    try {
                        const res = await window.fetch(`${self.distritosUrl}?provincia_id=${provId}`);
                        const json = await res.json();
                        const lista = json.datos || [];
                        let opts = '<option value="">Seleccione distrito...</option>';
                        lista.forEach(d => {
                            opts += `<option value="${d.id}">${escaparHtml(d.nombre)}</option>`;
                        });
                        editDist.innerHTML = opts;
                        editDist.disabled = false;
                    } catch {
                        editDist.innerHTML = '<option value="">Error al cargar</option>';
                    }
                });
            }
        },

        /**
         * Vincula envíos de formularios con validación Bootstrap y fetch.
         */
        vincularFormularios: function () {
            const self = this;

            // Formulario Crear Proyecto
            const formCrear = document.getElementById('formCrearProyecto');
            if (formCrear) {
                formCrear.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    if (!formCrear.checkValidity()) {
                        e.stopPropagation();
                        formCrear.classList.add('was-validated');
                        return;
                    }

                    const btnGuardar = document.getElementById('btnGuardarProyecto');
                    if (btnGuardar) {
                        btnGuardar.disabled = true;
                        btnGuardar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';
                    }

                    const payload = {
                        codigo: document.getElementById('crearCodigo').value.trim().toUpperCase(),
                        nombre: document.getElementById('crearNombre').value.trim(),
                        tipo_proyecto: document.getElementById('crearTipoProyecto').value,
                        moneda: document.getElementById('crearMoneda').value,
                        tipo_tolerancia: document.getElementById('crearTipoTolerancia').value,
                        valor_tolerancia: parseFloat(document.getElementById('crearValorTolerancia').value || '0.5'),
                        distrito_id: parseInt(document.getElementById('crearDistritoId').value || '0', 10),
                        direccion_referencia: document.getElementById('crearDireccion').value.trim() || null,
                        descripcion: document.getElementById('crearDescripcion').value.trim() || null,
                        csrf_token: self.tokenCsrf
                    };

                    try {
                        const respuesta = await window.fetch(self.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify(payload)
                        });

                        const resultado = await respuesta.json();

                        if (respuesta.status === 201 && resultado.estado === 'exito') {
                            self.notificarAlerta('success', '¡Proyecto Registrado!', resultado.mensaje || 'Proyecto creado exitosamente.');
                            if (self.modalCrearBs) self.modalCrearBs.hide();
                            formCrear.reset();
                            formCrear.classList.remove('was-validated');
                            self.tabla.ajax.reload(null, false);
                        } else {
                            const mensaje = resultado.mensaje || 'No se pudo registrar el proyecto.';
                            self.notificarAlerta('error', 'Error al Guardar', mensaje);
                        }
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnGuardar) {
                            btnGuardar.disabled = false;
                            btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Proyecto';
                        }
                    }
                });
            }

            // Formulario Editar Proyecto
            const formEditar = document.getElementById('formEditarProyecto');
            if (formEditar) {
                formEditar.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    if (!formEditar.checkValidity()) {
                        e.stopPropagation();
                        formEditar.classList.add('was-validated');
                        return;
                    }

                    const id = document.getElementById('editarProyectoId').value;
                    const btnActualizar = document.getElementById('btnActualizarProyecto');
                    if (btnActualizar) {
                        btnActualizar.disabled = true;
                        btnActualizar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Actualizando...';
                    }

                    const payload = {
                        nombre: document.getElementById('editarNombre').value.trim(),
                        tipo_proyecto: document.getElementById('editarTipoProyecto').value,
                        tipo_tolerancia: document.getElementById('editarTipoTolerancia').value,
                        valor_tolerancia: parseFloat(document.getElementById('editarValorTolerancia').value || '0.5'),
                        distrito_id: parseInt(document.getElementById('editarDistritoId').value || '0', 10),
                        direccion_referencia: document.getElementById('editarDireccion').value.trim() || null,
                        descripcion: document.getElementById('editarDescripcion').value.trim() || null,
                        csrf_token: self.tokenCsrf
                    };

                    try {
                        const respuesta = await window.fetch(`${self.apiUrl}/${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify(payload)
                        });

                        const resultado = await respuesta.json();

                        if (respuesta.status === 200 && resultado.estado === 'exito') {
                            self.notificarAlerta('success', '¡Proyecto Actualizado!', resultado.mensaje || 'Proyecto actualizado exitosamente.');
                            if (self.modalEditarBs) self.modalEditarBs.hide();
                            formEditar.classList.remove('was-validated');
                            self.tabla.ajax.reload(null, false);
                        } else {
                            const mensaje = resultado.mensaje || 'No se pudo actualizar el proyecto.';
                            self.notificarAlerta('error', 'Error al Actualizar', mensaje);
                        }
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnActualizar) {
                            btnActualizar.disabled = false;
                            btnActualizar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Proyecto';
                        }
                    }
                });
            }
        },

        /**
         * Vincula eventos de la tabla (editar y conmutar estado).
         */
        vincularEventosTabla: function () {
            const self = this;
            const tablaEl = document.getElementById('tablaProyectos');
            if (!tablaEl) return;

            // Delegación de clic en Editar Proyecto
            tablaEl.addEventListener('click', async function (e) {
                const btnEdit = e.target.closest('.btn-editar-proyecto');
                if (btnEdit) {
                    const id = btnEdit.getAttribute('data-id');
                    await self.cargarProyectoParaEditar(id);
                    return;
                }

                const btnToggle = e.target.closest('.btn-conmutar-estado');
                if (btnToggle) {
                    const id = btnToggle.getAttribute('data-id');
                    const estadoActual = btnToggle.getAttribute('data-estado-actual');
                    const nombre = btnToggle.getAttribute('data-nombre');
                    await self.confirmarConmutacionEstado(id, estadoActual, nombre);
                    return;
                }
            });
        },

        /**
         * Carga datos del proyecto y abre modal de edición con UBIGEO preseleccionado.
         */
        cargarProyectoParaEditar: async function (id) {
            const self = this;

            try {
                const res = await window.fetch(`${self.apiUrl}/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) {
                    self.notificarAlerta('error', 'Error', 'No se pudieron recuperar los datos del proyecto.');
                    return;
                }

                const json = await res.json();
                const p = json.datos;
                if (!p) return;

                document.getElementById('editarProyectoId').value = p.id;
                document.getElementById('editarCodigo').value = p.codigo;
                document.getElementById('editarNombre').value = p.nombre;
                document.getElementById('editarTipoProyecto').value = p.tipo_proyecto;
                document.getElementById('editarMoneda').value = `${p.moneda} (${p.moneda === 'USD' ? '$' : 'S/'})`;
                document.getElementById('editarTipoTolerancia').value = p.tipo_tolerancia;
                document.getElementById('editarValorTolerancia').value = parseFloat(p.valor_tolerancia);
                document.getElementById('editarDireccion').value = p.direccion_referencia || '';
                document.getElementById('editarDescripcion').value = p.descripcion || '';

                // Cargar cascada geográfica UBIGEO
                const depSelect = document.getElementById('editarDepartamento');
                const provSelect = document.getElementById('editarProvincia');
                const distSelect = document.getElementById('editarDistritoId');

                if (depSelect && p.departamento_id) {
                    depSelect.value = p.departamento_id;

                    // Cargar Provincias
                    const resProv = await window.fetch(`${self.provinciasUrl}?departamento_id=${p.departamento_id}`);
                    const jsonProv = await resProv.json();
                    const listaProv = jsonProv.datos || [];

                    let optsProv = '<option value="">Seleccione provincia...</option>';
                    listaProv.forEach(pr => {
                        optsProv += `<option value="${pr.id}">${escaparHtml(pr.nombre)}</option>`;
                    });
                    provSelect.innerHTML = optsProv;
                    provSelect.disabled = false;
                    provSelect.value = p.provincia_id;

                    // Cargar Distritos
                    const resDist = await window.fetch(`${self.distritosUrl}?provincia_id=${p.provincia_id}`);
                    const jsonDist = await resDist.json();
                    const listaDist = jsonDist.datos || [];

                    let optsDist = '<option value="">Seleccione distrito...</option>';
                    listaDist.forEach(d => {
                        optsDist += `<option value="${d.id}">${escaparHtml(d.nombre)}</option>`;
                    });
                    distSelect.innerHTML = optsDist;
                    distSelect.disabled = false;
                    distSelect.value = p.distrito_id;
                }

                const form = document.getElementById('formEditarProyecto');
                if (form) form.classList.remove('was-validated');

                if (self.modalEditarBs) self.modalEditarBs.show();
            } catch (err) {
                self.notificarAlerta('error', 'Error de Conexión', 'No fue posible cargar el proyecto.');
            }
        },

        /**
         * Diálogo de confirmación y conmutación de estado.
         */
        confirmarConmutacionEstado: async function (id, estadoActual, nombre) {
            const self = this;
            const nuevoEstado = estadoActual === 'INACTIVO' ? 'PLANIFICACION' : 'INACTIVO';
            const accionTexto = estadoActual === 'INACTIVO' ? 'Reactivar' : 'Desactivar';
            const alertaIcon = estadoActual === 'INACTIVO' ? 'question' : 'warning';

            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    title: `¿${accionTexto} Proyecto?`,
                    text: `¿Desea cambiar el estado del proyecto "${nombre}" a ${nuevoEstado}?`,
                    icon: alertaIcon,
                    showCancelButton: true,
                    confirmButtonText: `Sí, ${accionTexto.toLowerCase()}`,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: estadoActual === 'INACTIVO' ? '#198754' : '#dc3545'
                });

                if (!result.isConfirmed) return;
            } else {
                if (!confirm(`¿Desea cambiar el estado del proyecto "${nombre}" a ${nuevoEstado}?`)) {
                    return;
                }
            }

            try {
                const res = await window.fetch(`${self.apiUrl}/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': self.tokenCsrf
                    },
                    body: JSON.stringify({
                        estado: nuevoEstado,
                        motivo: `Cambio de estado administrativo a ${nuevoEstado}`,
                        csrf_token: self.tokenCsrf
                    })
                });

                const data = await res.json();

                if (res.ok && data.estado === 'exito') {
                    self.notificarAlerta('success', '¡Estado Actualizado!', data.mensaje || `El proyecto ahora está ${nuevoEstado}.`);
                    self.tabla.ajax.reload(null, false);
                } else {
                    self.notificarAlerta('error', 'Error al Cambiar Estado', data.mensaje || 'Operación rechazada.');
                }
            } catch (err) {
                self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
            }
        },

        /**
         * Emite notificación toast/alert mediante SweetAlert2 o fallback.
         */
        notificarAlerta: function (tipo, titulo, mensaje) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: tipo,
                    title: titulo,
                    text: mensaje,
                    timer: tipo === 'success' ? 2500 : undefined,
                    showConfirmButton: tipo !== 'success'
                });
            } else {
                alert(`${titulo}: ${mensaje}`);
            }
        }
    };

    // Auto-inicialización al cargar DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => MODULO.init());
    } else {
        MODULO.init();
    }

    window.CasaProProyectos = MODULO;
})();
