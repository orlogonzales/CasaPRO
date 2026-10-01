/**
 * ficha-proyecto.js — Módulo JavaScript Vanilla ES6+ para la Ficha 360° del Proyecto Inmobiliario.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 * Reglas Inviolables:
 * - Cero window.location.reload() / location.reload() — Mutaciones 100% asíncronas.
 * - Cero llamadas a $.ajax, $.get, $.post, $.getJSON en código propio de CasaPRO.
 * - Recarga asíncrona de predios matrices y semáforo de conciliación determinista.
 * - Saneamiento progresivo: partida registral y área topográfica opcionales.
 * - Inmunidad XSS en manipulación de DOM y escape de datos.
 */

(function () {
    'use strict';

    if (window.CasaProFichaProyecto) {
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

    const MODULO = {
        proyectoId: 0,
        empresaId: 0,
        apiPrediosUrl: '',
        apiConciliacionUrl: '',
        provinciasUrl: '',
        distritosUrl: '',
        tokenCsrf: '',
        modalCrearPredioBs: null,
        modalEditarPredioBs: null,

        /**
         * Inicialización del módulo al cargar la página.
         */
        init: function () {
            const contenedor = document.getElementById('contenedorFichaProyecto');
            if (!contenedor) return;

            this.proyectoId = parseInt(contenedor.getAttribute('data-proyecto-id') || '0', 10);
            this.empresaId = parseInt(contenedor.getAttribute('data-empresa-id') || '0', 10);
            this.apiPrediosUrl = contenedor.getAttribute('data-api-predios-url') || '';
            this.apiConciliacionUrl = contenedor.getAttribute('data-api-conciliacion-url') || '';
            this.provinciasUrl = contenedor.getAttribute('data-api-provincias-url') || '/api/ubigeo/provincias';
            this.distritosUrl = contenedor.getAttribute('data-api-distritos-url') || '/api/ubigeo/distritos';
            this.tokenCsrf = contenedor.getAttribute('data-csrf-token') || '';

            // Inicializar modales Bootstrap 5
            const modalCrearEl = document.getElementById('modalCrearPredio');
            if (modalCrearEl) this.modalCrearPredioBs = bootstrap.Modal.getOrCreateInstance(modalCrearEl);

            const modalEditarEl = document.getElementById('modalEditarPredio');
            if (modalEditarEl) this.modalEditarPredioBs = bootstrap.Modal.getOrCreateInstance(modalEditarEl);

            this.vincularCascadaUbigeo();
            this.vincularFormularios();
            this.vincularEventosTabla();
            this.inicializarTooltips();
        },

        /**
         * Inicializa tooltips de Bootstrap en la ficha.
         */
        inicializarTooltips: function () {
            const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltips.map(el => bootstrap.Tooltip.getOrCreateInstance(el));
        },

        /**
         * Cascada geográfica UBIGEO de 3 niveles para predios matrices.
         */
        vincularCascadaUbigeo: function () {
            const self = this;

            // Cascada en Modal Crear Predio
            const crearDep = document.getElementById('predioDepartamento');
            const crearProv = document.getElementById('predioProvincia');
            const crearDist = document.getElementById('predioDistritoId');

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

            // Cascada en Modal Editar Predio
            const editDep = document.getElementById('editarPredioDepartamento');
            const editProv = document.getElementById('editarPredioProvincia');
            const editDist = document.getElementById('editarPredioDistritoId');

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
         * Vincula envíos de formularios para predios matrices.
         */
        vincularFormularios: function () {
            const self = this;

            // Botón abrir modal nuevo predio
            const btnNuevo = document.getElementById('btnNuevoPredio');
            if (btnNuevo) {
                btnNuevo.addEventListener('click', () => {
                    const form = document.getElementById('formCrearPredio');
                    if (form) {
                        form.reset();
                        form.classList.remove('was-validated');
                        const prov = document.getElementById('predioProvincia');
                        if (prov) {
                            prov.innerHTML = '<option value="">Seleccione...</option>';
                            prov.disabled = true;
                        }
                        const dist = document.getElementById('predioDistritoId');
                        if (dist) {
                            dist.innerHTML = '<option value="">Seleccione...</option>';
                            dist.disabled = true;
                        }
                    }
                    if (self.modalCrearPredioBs) self.modalCrearPredioBs.show();
                });
            }

            // Formulario Crear Predio
            const formCrear = document.getElementById('formCrearPredio');
            if (formCrear) {
                formCrear.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    if (!formCrear.checkValidity()) {
                        e.stopPropagation();
                        formCrear.classList.add('was-validated');
                        return;
                    }

                    const btnGuardar = document.getElementById('btnGuardarPredio');
                    if (btnGuardar) {
                        btnGuardar.disabled = true;
                        btnGuardar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';
                    }

                    const partida = document.getElementById('predioPartida').value.trim();
                    const tomo = document.getElementById('predioTomo').value.trim();
                    const topograficaVal = document.getElementById('predioAreaTopografica').value.trim();
                    const antecedente = document.getElementById('predioAntecedente').value.trim();

                    const payload = {
                        proyecto_id: self.proyectoId,
                        denominacion: document.getElementById('predioDenominacion').value.trim(),
                        partida_registral: partida !== '' ? partida : null,
                        tomo_ficha: tomo !== '' ? tomo : null,
                        area_registral_m2: parseFloat(document.getElementById('predioAreaRegistral').value || '0'),
                        area_topografica_m2: topograficaVal !== '' ? parseFloat(topograficaVal) : null,
                        distrito_id: parseInt(document.getElementById('predioDistritoId').value || '0', 10),
                        antecedente_dominial: antecedente !== '' ? antecedente : null,
                        csrf_token: self.tokenCsrf
                    };

                    try {
                        const respuesta = await window.fetch(self.apiPrediosUrl, {
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
                            self.notificarAlerta('success', '¡Predio Incorporado!', resultado.mensaje || 'Predio matriz agregado exitosamente.');
                            if (self.modalCrearPredioBs) self.modalCrearPredioBs.hide();
                            formCrear.reset();
                            formCrear.classList.remove('was-validated');
                            await self.recargarPredios();
                            await self.recargarConciliacion();
                        } else {
                            const mensaje = resultado.mensaje || 'No se pudo guardar el predio matriz.';
                            self.notificarAlerta('error', 'Error al Guardar', mensaje);
                        }
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnGuardar) {
                            btnGuardar.disabled = false;
                            btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Predio Matriz';
                        }
                    }
                });
            }

            // Formulario Editar Predio
            const formEditar = document.getElementById('formEditarPredio');
            if (formEditar) {
                formEditar.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    if (!formEditar.checkValidity()) {
                        e.stopPropagation();
                        formEditar.classList.add('was-validated');
                        return;
                    }

                    const id = document.getElementById('editarPredioId').value;
                    const btnActualizar = document.getElementById('btnActualizarPredio');
                    if (btnActualizar) {
                        btnActualizar.disabled = true;
                        btnActualizar.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Actualizando...';
                    }

                    const partida = document.getElementById('editarPredioPartida').value.trim();
                    const tomo = document.getElementById('editarPredioTomo').value.trim();
                    const topograficaVal = document.getElementById('editarPredioAreaTopografica').value.trim();
                    const antecedente = document.getElementById('editarPredioAntecedente').value.trim();

                    const payload = {
                        denominacion: document.getElementById('editarPredioDenominacion').value.trim(),
                        partida_registral: partida !== '' ? partida : null,
                        tomo_ficha: tomo !== '' ? tomo : null,
                        area_registral_m2: parseFloat(document.getElementById('editarPredioAreaRegistral').value || '0'),
                        area_topografica_m2: topograficaVal !== '' ? parseFloat(topograficaVal) : null,
                        distrito_id: parseInt(document.getElementById('editarPredioDistritoId').value || '0', 10),
                        antecedente_dominial: antecedente !== '' ? antecedente : null,
                        csrf_token: self.tokenCsrf
                    };

                    try {
                        const respuesta = await window.fetch(`/api/predios/${id}`, {
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
                            self.notificarAlerta('success', '¡Predio Actualizado!', resultado.mensaje || 'Predio matriz actualizado exitosamente.');
                            if (self.modalEditarPredioBs) self.modalEditarPredioBs.hide();
                            formEditar.classList.remove('was-validated');
                            await self.recargarPredios();
                            await self.recargarConciliacion();
                        } else {
                            const mensaje = resultado.mensaje || 'No se pudo actualizar el predio matriz.';
                            self.notificarAlerta('error', 'Error al Actualizar', mensaje);
                        }
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnActualizar) {
                            btnActualizar.disabled = false;
                            btnActualizar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Predio Matriz';
                        }
                    }
                });
            }
        },

        /**
         * Vincula eventos de la tabla de predios (editar y conmutar estado).
         */
        vincularEventosTabla: function () {
            const self = this;
            const tabla = document.getElementById('tablaPrediosMatrices');
            if (!tabla) return;

            tabla.addEventListener('click', async function (e) {
                const btnEdit = e.target.closest('.btn-editar-predio');
                if (btnEdit) {
                    await self.abrirModalEditarPredio(btnEdit);
                    return;
                }

                const btnToggle = e.target.closest('.btn-conmutar-predio');
                if (btnToggle) {
                    const id = btnToggle.getAttribute('data-id');
                    const estadoActual = btnToggle.getAttribute('data-estado-actual');
                    await self.confirmarConmutacionEstadoPredio(id, estadoActual);
                    return;
                }
            });
        },

        /**
         * Abre el modal de edición de predio poblando datos y cascada geográfica.
         */
        abrirModalEditarPredio: async function (btn) {
            const self = this;

            const id = btn.getAttribute('data-id');
            const denominacion = btn.getAttribute('data-denominacion') || '';
            const partida = btn.getAttribute('data-partida') || '';
            const tomo = btn.getAttribute('data-tomo') || '';
            const registral = btn.getAttribute('data-registral') || '';
            const topografica = btn.getAttribute('data-topografica') || '';
            const depId = btn.getAttribute('data-departamento-id');
            const provId = btn.getAttribute('data-provincia-id');
            const distId = btn.getAttribute('data-distrito-id');
            const antecedente = btn.getAttribute('data-antecedente') || '';

            document.getElementById('editarPredioId').value = id;
            document.getElementById('editarPredioDenominacion').value = denominacion;
            document.getElementById('editarPredioPartida').value = partida;
            document.getElementById('editarPredioTomo').value = tomo;
            document.getElementById('editarPredioAreaRegistral').value = registral;
            document.getElementById('editarPredioAreaTopografica').value = topografica;
            document.getElementById('editarPredioAntecedente').value = antecedente;

            // Cascada UBIGEO
            const depSelect = document.getElementById('editarPredioDepartamento');
            const provSelect = document.getElementById('editarPredioProvincia');
            const distSelect = document.getElementById('editarPredioDistritoId');

            if (depSelect && depId && parseInt(depId, 10) > 0) {
                depSelect.value = depId;

                try {
                    const resProv = await window.fetch(`${self.provinciasUrl}?departamento_id=${depId}`);
                    const jsonProv = await resProv.json();
                    const listaProv = jsonProv.datos || [];
                    let optsProv = '<option value="">Seleccione...</option>';
                    listaProv.forEach(p => {
                        optsProv += `<option value="${p.id}">${escaparHtml(p.nombre)}</option>`;
                    });
                    provSelect.innerHTML = optsProv;
                    provSelect.disabled = false;
                    provSelect.value = provId;

                    const resDist = await window.fetch(`${self.distritosUrl}?provincia_id=${provId}`);
                    const jsonDist = await resDist.json();
                    const listaDist = jsonDist.datos || [];
                    let optsDist = '<option value="">Seleccione...</option>';
                    listaDist.forEach(d => {
                        optsDist += `<option value="${d.id}">${escaparHtml(d.nombre)}</option>`;
                    });
                    distSelect.innerHTML = optsDist;
                    distSelect.disabled = false;
                    distSelect.value = distId;
                } catch {
                    // Fallback silencioso si falla carga UBIGEO
                }
            }

            const form = document.getElementById('formEditarPredio');
            if (form) form.classList.remove('was-validated');

            if (self.modalEditarPredioBs) self.modalEditarPredioBs.show();
        },

        /**
         * Diálogo de confirmación y conmutación de estado de un predio.
         */
        confirmarConmutacionEstadoPredio: async function (id, estadoActual) {
            const self = this;
            const nuevoEstado = estadoActual === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
            const accionTexto = estadoActual === 'ACTIVO' ? 'Desactivar' : 'Reactivar';
            const alertaIcon = estadoActual === 'ACTIVO' ? 'warning' : 'question';

            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    title: `¿${accionTexto} Predio Matriz?`,
                    text: `El predio pasará a estado "${nuevoEstado}" y sus áreas ${nuevoEstado === 'INACTIVO' ? 'dejarán de' : 'volverán a'} sumar en el balance técnico.`,
                    icon: alertaIcon,
                    showCancelButton: true,
                    confirmButtonText: `Sí, ${accionTexto.toLowerCase()}`,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: estadoActual === 'ACTIVO' ? '#dc3545' : '#198754'
                });

                if (!result.isConfirmed) return;
            } else {
                if (!confirm(`¿Desea cambiar el estado del predio a "${nuevoEstado}"?`)) {
                    return;
                }
            }

            try {
                const res = await window.fetch(`/api/predios/${id}/estado`, {
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
                    self.notificarAlerta('success', '¡Estado Actualizado!', data.mensaje || `Predio actualizado a ${nuevoEstado}.`);
                    await self.recargarPredios();
                    await self.recargarConciliacion();
                } else {
                    self.notificarAlerta('error', 'Error al Cambiar Estado', data.mensaje || 'Operación rechazada.');
                }
            } catch (err) {
                self.notificarAlerta('error', 'Error de Red', 'No se pudo comunicar con el servidor.');
            }
        },

        /**
         * Recarga dinámicamente la tabla de predios matrices sin recargar la página.
         */
        recargarPredios: async function () {
            const self = this;
            const tbody = document.getElementById('tbodyPrediosMatrices');
            if (!tbody) return;

            try {
                const res = await window.fetch(self.apiPrediosUrl, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) return;

                const json = await res.json();
                const lista = json.datos || [];

                // Actualizar contador en pestaña
                const tabBtn = document.getElementById('tab-predios-btn');
                if (tabBtn) {
                    tabBtn.innerHTML = `<i class="fa-solid fa-layer-group me-2"></i>Predios / Terrenos Matrices (${lista.length})`;
                }

                if (lista.length === 0) {
                    tbody.innerHTML = `
                        <tr id="filaSinPredios">
                            <td colspan="7" class="text-center py-4 text-secondary">
                                <i class="fa-solid fa-mountain-sun fa-2x mb-2 d-block text-muted"></i>
                                No se han incorporado predios matrices a este proyecto. Haga clic en <strong>Agregar Predio Matriz</strong>.
                            </td>
                        </tr>
                    `;
                    return;
                }

                let html = '';
                lista.forEach(pm => {
                    const esActivo = pm.estado === 'ACTIVO';
                    const badgePartida = pm.partida_registral
                        ? `<span class="badge bg-secondary font-monospace">${escaparHtml(pm.partida_registral)}</span>`
                        : `<span class="badge bg-warning-subtle text-warning border border-warning f-s-11"><i class="fa-solid fa-clock me-1"></i>En Saneamiento</span>`;

                    const tomoHtml = pm.tomo_ficha ? `<small class="d-block text-muted">Ficha/Tomo: ${escaparHtml(pm.tomo_ficha)}</small>` : '';

                    const areaTopoHtml = pm.area_topografica_m2 !== null
                        ? `${formatearNumero(pm.area_topografica_m2, 4)} m²`
                        : `<span class="badge bg-info-subtle text-info border border-info f-s-11"><i class="fa-solid fa-ruler-combined me-1"></i>Pendiente</span>`;

                    const badgeEstado = esActivo
                        ? '<span class="badge bg-success f-s-11">ACTIVO</span>'
                        : '<span class="badge bg-secondary f-s-11">INACTIVO</span>';

                    const toggleColor = esActivo ? 'danger' : 'success';
                    const toggleIcon = esActivo ? 'ban' : 'check';
                    const toggleTitulo = esActivo ? 'Desactivar Predio' : 'Reactivar Predio';

                    html += `
                        <tr id="predio-fila-${pm.id}">
                            <td>
                                <span class="f-w-600 text-dark">${escaparHtml(pm.denominacion)}</span>
                                ${tomoHtml}
                            </td>
                            <td>${badgePartida}</td>
                            <td class="text-end font-monospace">${formatearNumero(pm.area_registral_m2, 4)} m²</td>
                            <td class="text-end font-monospace">${areaTopoHtml}</td>
                            <td><small class="text-secondary">${escaparHtml(pm.distrito_nombre || '')}</small></td>
                            <td class="text-center">${badgeEstado}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-warning btn-sm btn-editar-predio"
                                        data-id="${pm.id}"
                                        data-denominacion="${escaparHtml(pm.denominacion)}"
                                        data-partida="${escaparHtml(pm.partida_registral || '')}"
                                        data-tomo="${escaparHtml(pm.tomo_ficha || '')}"
                                        data-registral="${parseFloat(pm.area_registral_m2)}"
                                        data-topografica="${pm.area_topografica_m2 !== null ? parseFloat(pm.area_topografica_m2) : ''}"
                                        data-departamento-id="${pm.departamento_id || 0}"
                                        data-provincia-id="${pm.provincia_id || 0}"
                                        data-distrito-id="${pm.distrito_id}"
                                        data-antecedente="${escaparHtml(pm.antecedente_dominial || '')}"
                                        data-bs-toggle="tooltip" data-bs-title="Editar Predio">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-outline-${toggleColor} btn-sm btn-conmutar-predio"
                                        data-id="${pm.id}"
                                        data-estado-actual="${escaparHtml(pm.estado)}"
                                        data-bs-toggle="tooltip" data-bs-title="${toggleTitulo}">
                                    <i class="fa-solid fa-${toggleIcon}"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
                self.inicializarTooltips();
            } catch (err) {
                // Silencioso
            }
        },

        /**
         * Recarga y actualiza los indicadores técnicos y el semáforo de conciliación determinista.
         */
        recargarConciliacion: async function () {
            const self = this;
            if (!self.apiConciliacionUrl) return;

            try {
                const res = await window.fetch(self.apiConciliacionUrl, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!res.ok) return;

                const json = await res.json();
                const c = json.datos;
                if (!c) return;

                const balance = c.balance_predios || {};

                // Actualizar KPIs
                const kpiReg = document.getElementById('kpiAreaRegistral');
                if (kpiReg) kpiReg.textContent = `${formatearNumero(balance.suma_area_registral_m2 || 0, 4)} m²`;

                const kpiTopo = document.getElementById('kpiAreaTopografica');
                if (kpiTopo) kpiTopo.textContent = `${formatearNumero(balance.suma_area_topografica_m2 || 0, 4)} m²`;

                const kpiDisc = document.getElementById('kpiDiscrepancia');
                if (kpiDisc) kpiDisc.textContent = `${formatearNumero(balance.discrepancia_absoluta_m2 || 0, 4)} m²`;

                const kpiDiscPct = document.getElementById('kpiDiscrepanciaPct');
                if (kpiDiscPct) kpiDiscPct.textContent = `(${formatearNumero(balance.discrepancia_porcentaje || 0, 2)}% de discrepancia)`;

                // Actualizar Card Semáforo
                const semaforoCard = document.getElementById('cardSemaforoConciliacion');
                if (semaforoCard) {
                    const semaforo = c.semaforo || 'SIN_PREDIOS';
                    let alertClass = 'alert-secondary border-secondary';
                    let alertIcon = 'fa-circle-info text-secondary';

                    switch (semaforo) {
                        case 'CONCILIADO':
                            alertClass = 'alert-success border-success';
                            alertIcon = 'fa-circle-check text-success';
                            break;
                        case 'PENDIENTE_TOPOGRAFIA':
                            alertClass = 'alert-info border-info';
                            alertIcon = 'fa-clock text-info';
                            break;
                        case 'DISCREPANCIA_FUERA_TOLERANCIA':
                            alertClass = 'alert-danger border-danger';
                            alertIcon = 'fa-triangle-exclamation text-danger';
                            break;
                    }

                    semaforoCard.innerHTML = `
                        <div class="alert ${alertClass} mb-0 py-3 d-flex align-items-center justify-content-center gap-3">
                            <i class="fa-solid ${alertIcon} fa-2x"></i>
                            <div class="text-start">
                                <h5 class="mb-1 f-w-700" id="semaforoTitulo">${escaparHtml(semaforo.replace(/_/g, ' '))}</h5>
                                <p class="mb-0 f-s-13" id="semaforoMensaje">${escaparHtml(c.mensaje || '')}</p>
                            </div>
                        </div>
                    `;
                }
            } catch (err) {
                // Silencioso
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

    window.CasaProFichaProyecto = MODULO;
})();
