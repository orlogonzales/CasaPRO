/**
 * gestion-sectores.js — Módulo JavaScript Vanilla ES6+ para la Gestión de Sectores, Precios y Balance de Áreas.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 * Reglas Inviolables:
 * - Cero window.location.reload() / location.reload() — Mutaciones 100% asíncronas.
 * - Cero llamadas a jQuery ($.ajax, $.get, $.post) en código propio de CasaPRO. Persistencia con fetch nativo.
 * - Cálculo reactivo en vivo de consistencia interna de áreas e impacto sobre el remanente de proyecto.
 * - Recarga asíncrona de tabla de sectores, tarjetas KPI y semáforo de balance.
 * - Inmunidad XSS en manipulación de DOM.
 */

(function () {
    'use strict';

    if (window.CasaProGestionSectores) {
        return;
    }

    function escaparHtml(valor) {
        if (valor === null || valor === undefined) return '';
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

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
        monedaProyecto: 'PEN',
        tokenCsrf: '',
        modalCrearSectorBs: null,
        modalEditarSectorBs: null,
        modalPreciosSectorBs: null,
        modalCambiarEstadoSectorBs: null,

        init: function () {
            const contenedor = document.getElementById('contenedorFichaProyecto');
            if (!contenedor) return;

            this.proyectoId = parseInt(contenedor.dataset.proyectoId || '0', 10);
            this.monedaProyecto = contenedor.dataset.proyectoMoneda || 'PEN';
            this.tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="csrf_token"]')?.value
                || '';

            this.inicializarModales();
            this.inicializarEventos();
            this.vincularCalculosEnVivo();
        },

        inicializarModales: function () {
            const modalCrearEl = document.getElementById('modalCrearSector');
            if (modalCrearEl && typeof bootstrap !== 'undefined') {
                this.modalCrearSectorBs = new bootstrap.Modal(modalCrearEl);
            }

            const modalEditarEl = document.getElementById('modalEditarSector');
            if (modalEditarEl && typeof bootstrap !== 'undefined') {
                this.modalEditarSectorBs = new bootstrap.Modal(modalEditarEl);
            }

            const modalPreciosEl = document.getElementById('modalPreciosSector');
            if (modalPreciosEl && typeof bootstrap !== 'undefined') {
                this.modalPreciosSectorBs = new bootstrap.Modal(modalPreciosEl);
            }

            const modalEstadoEl = document.getElementById('modalCambiarEstadoSector');
            if (modalEstadoEl && typeof bootstrap !== 'undefined') {
                this.modalCambiarEstadoSectorBs = new bootstrap.Modal(modalEstadoEl);
            }
        },

        inicializarEventos: function () {
            const btnNuevo = document.getElementById('btnNuevoSector');
            if (btnNuevo) {
                btnNuevo.addEventListener('click', () => {
                    const form = document.getElementById('formCrearSector');
                    if (form) {
                        form.reset();
                        form.classList.remove('was-validated');
                    }
                    this.actualizarCalculoResumen('Crear');
                    if (this.modalCrearSectorBs) {
                        this.modalCrearSectorBs.show();
                    }
                });
            }

            const formCrear = document.getElementById('formCrearSector');
            if (formCrear) {
                formCrear.addEventListener('submit', (e) => this.guardarNuevoSector(e));
            }

            const formEditar = document.getElementById('formEditarSector');
            if (formEditar) {
                formEditar.addEventListener('submit', (e) => this.actualizarSector(e));
            }

            const formPrecio = document.getElementById('formAjustarPrecioSector');
            if (formPrecio) {
                formPrecio.addEventListener('submit', (e) => this.guardarNuevoPrecio(e));
            }

            const formEstado = document.getElementById('formCambiarEstadoSector');
            if (formEstado) {
                formEstado.addEventListener('submit', (e) => this.confirmarCambioEstado(e));
            }

            const tbodySectores = document.getElementById('tbodySectores');
            if (tbodySectores) {
                tbodySectores.addEventListener('click', (e) => {
                    const btnEditar = e.target.closest('.btn-editar-sector');
                    if (btnEditar) {
                        const sectorId = parseInt(btnEditar.dataset.id, 10);
                        this.abrirModalEdicion(sectorId);
                        return;
                    }

                    const btnPrecios = e.target.closest('.btn-ver-precios') || e.target.closest('.btn-ajustar-precio');
                    if (btnPrecios) {
                        const sectorId = parseInt(btnPrecios.dataset.id, 10);
                        const codigo = btnPrecios.dataset.codigo || '';
                        const nombre = btnPrecios.dataset.nombre || '';
                        this.abrirModalPrecios(sectorId, codigo, nombre);
                        return;
                    }

                    const btnEstado = e.target.closest('.btn-estado-sector');
                    if (btnEstado) {
                        const sectorId = parseInt(btnEstado.dataset.id, 10);
                        const codigo = btnEstado.dataset.codigo || '';
                        const estadoActual = btnEstado.dataset.estado || '';
                        this.abrirModalEstado(sectorId, codigo, estadoActual);
                    }
                });
            }
        },

        vincularCalculosEnVivo: function () {
            document.querySelectorAll('.input-calculo-sector').forEach(input => {
                input.addEventListener('input', () => this.actualizarCalculoResumen('Crear'));
            });

            document.querySelectorAll('.input-editar-calculo-sector').forEach(input => {
                input.addEventListener('input', () => this.actualizarCalculoResumen('Editar'));
            });
        },

        actualizarCalculoResumen: function (tipo) {
            const prefijo = tipo === 'Crear' ? 'crearSector' : 'editarSector';
            const bruta = parseFloat(document.getElementById(`${prefijo}AreaBruta`)?.value || '0');
            const util = parseFloat(document.getElementById(`${prefijo}AreaUtil`)?.value || '0');
            const cesion = parseFloat(document.getElementById(`${prefijo}AreaCesion`)?.value || '0');
            const comun = parseFloat(document.getElementById(`${prefijo}AreaComun`)?.value || '0');

            const sumaInterna = util + cesion + comun;
            const remanente = bruta - sumaInterna;

            const spanSuma = document.getElementById(tipo === 'Crear' ? 'resumenSumaInternaModal' : 'resumenEditarSumaInternaModal');
            const spanRemanente = document.getElementById(tipo === 'Crear' ? 'resumenRemanenteLocalModal' : 'resumenEditarRemanenteLocalModal');

            if (spanSuma) spanSuma.textContent = formatearNumero(sumaInterna, 4);
            if (spanRemanente) {
                spanRemanente.textContent = formatearNumero(remanente, 4);
                if (remanente < -0.0001) {
                    spanRemanente.parentElement.classList.add('text-danger');
                    spanRemanente.parentElement.classList.remove('text-success');
                } else {
                    spanRemanente.parentElement.classList.remove('text-danger');
                    spanRemanente.parentElement.classList.add('text-success');
                }
            }
        },

        guardarNuevoSector: async function (e) {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const btn = document.getElementById('btnGuardarSector');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

            const payload = {
                codigo: form.codigo.value.trim().toUpperCase(),
                nombre: form.nombre.value.trim(),
                orden: parseInt(form.orden.value, 10),
                descripcion: form.descripcion.value.trim() || null,
                area_bruta_m2: parseFloat(form.area_bruta_m2.value),
                area_util_m2: parseFloat(form.area_util_m2.value || '0'),
                area_cesion_m2: parseFloat(form.area_cesion_m2.value || '0'),
                area_comun_m2: parseFloat(form.area_comun_m2.value || '0'),
                precio_m2_inicial: parseFloat(form.precio_m2_inicial.value),
                motivo_precio_inicial: form.motivo_precio_inicial.value.trim() || 'Lanzamiento inicial de sector'
            };

            try {
                const response = await fetch(`/api/proyectos/${this.proyectoId}/sectores`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.tokenCsrf
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.status === 201 && data.estado === 'exito') {
                    if (this.modalCrearSectorBs) {
                        this.modalCrearSectorBs.hide();
                    }
                    form.reset();
                    form.classList.remove('was-validated');

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sector Incorporado',
                            text: data.mensaje || 'Sector registrado exitosamente.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }

                    await this.recargarSectoresYBalance();
                } else {
                    const msj = data.mensaje || 'No se pudo crear el sector.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: msj });
                    } else {
                        alert(msj);
                    }
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Sector';
            }
        },

        abrirModalEdicion: async function (sectorId) {
            try {
                const response = await fetch(`/api/sectores/${sectorId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (data.estado !== 'exito' || !data.datos) {
                    throw new Error(data.mensaje || 'Error al cargar datos del sector');
                }

                const s = data.datos;
                document.getElementById('editarSectorId').value = s.id;
                document.getElementById('editarSectorCodigo').value = s.codigo;
                document.getElementById('editarSectorNombre').value = s.nombre;
                document.getElementById('editarSectorOrden').value = s.orden;
                document.getElementById('editarSectorDescripcion').value = s.descripcion || '';
                document.getElementById('editarSectorAreaBruta').value = parseFloat(s.area_bruta_m2);
                document.getElementById('editarSectorAreaUtil').value = parseFloat(s.area_util_m2);
                document.getElementById('editarSectorAreaCesion').value = parseFloat(s.area_cesion_m2);
                document.getElementById('editarSectorAreaComun').value = parseFloat(s.area_comun_m2);

                this.actualizarCalculoResumen('Editar');

                if (this.modalEditarSectorBs) {
                    this.modalEditarSectorBs.show();
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                }
            }
        },

        actualizarSector: async function (e) {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const sectorId = parseInt(document.getElementById('editarSectorId').value, 10);
            const btn = document.getElementById('btnActualizarSector');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Actualizando...';

            const payload = {
                nombre: form.nombre.value.trim(),
                orden: parseInt(form.orden.value, 10),
                descripcion: form.descripcion.value.trim() || null,
                area_bruta_m2: parseFloat(form.area_bruta_m2.value),
                area_util_m2: parseFloat(form.area_util_m2.value || '0'),
                area_cesion_m2: parseFloat(form.area_cesion_m2.value || '0'),
                area_comun_m2: parseFloat(form.area_comun_m2.value || '0')
            };

            try {
                const response = await fetch(`/api/sectores/${sectorId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.tokenCsrf
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.status === 200 && data.estado === 'exito') {
                    if (this.modalEditarSectorBs) {
                        this.modalEditarSectorBs.hide();
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sector Actualizado',
                            text: data.mensaje || 'Sector actualizado exitosamente.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }

                    await this.recargarSectoresYBalance();
                } else {
                    const msj = data.mensaje || 'No se pudo actualizar el sector.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: msj });
                    } else {
                        alert(msj);
                    }
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Sector';
            }
        },

        abrirModalPrecios: async function (sectorId, codigo, nombre) {
            document.getElementById('precioSectorId').value = sectorId;
            document.getElementById('badgeModalPrecioSectorCodigo').textContent = codigo;
            document.getElementById('textoModalPrecioSectorNombre').textContent = nombre;

            const form = document.getElementById('formAjustarPrecioSector');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
                document.getElementById('nuevoPrecioFechaInicio').value = new Date().toISOString().slice(0, 10);
            }

            if (this.modalPreciosSectorBs) {
                this.modalPreciosSectorBs.show();
            }

            await this.cargarHistorialPrecios(sectorId);
        },

        cargarHistorialPrecios: async function (sectorId) {
            const tbody = document.getElementById('tbodyHistorialPrecios');
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Cargando historial...</td></tr>';

            try {
                const response = await fetch(`/api/sectores/${sectorId}/precios`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (data.estado === 'exito' && data.datos) {
                    const precios = data.datos.precios || [];
                    const moneda = data.datos.sector?.proyecto_moneda || this.monedaProyecto;

                    const precioVigenteObj = precios.find(p => p.fecha_fin === null);
                    const labelVigente = document.getElementById('textoModalPrecioActualM2');
                    if (labelVigente) {
                        if (precioVigenteObj) {
                            labelVigente.textContent = `${moneda} ${formatearNumero(precioVigenteObj.precio_m2_base, 2)}`;
                        } else {
                            labelVigente.textContent = 'Sin precio vigente';
                        }
                    }

                    if (precios.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No se registran precios base para este sector.</td></tr>';
                        return;
                    }

                    let html = '';
                    precios.forEach(p => {
                        const esVigente = p.fecha_fin === null;
                        const badgeEstado = esVigente
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle f-s-11">Vigente</span>'
                            : '<span class="badge bg-secondary-subtle text-secondary f-s-11">Histórico</span>';

                        html += `
                            <tr>
                                <td class="font-monospace f-s-12">${escaparHtml(p.fecha_inicio)}</td>
                                <td class="font-monospace f-s-12">${p.fecha_fin ? escaparHtml(p.fecha_fin) : '<span class="text-success f-w-600">Actual</span>'}</td>
                                <td class="text-end f-w-600 f-s-13 ${esVigente ? 'text-primary' : 'text-dark'}">${escaparHtml(p.moneda)} ${formatearNumero(p.precio_m2_base, 2)}</td>
                                <td class="f-s-12">${escaparHtml(p.motivo || '—')}</td>
                                <td class="text-center">${badgeEstado}</td>
                            </tr>
                        `;
                    });

                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Error al cargar historial.</td></tr>';
                }
            } catch (err) {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Fallo de comunicación con el servidor.</td></tr>';
            }
        },

        guardarNuevoPrecio: async function (e) {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const sectorId = parseInt(document.getElementById('precioSectorId').value, 10);
            const btn = document.getElementById('btnGuardarPrecio');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            const payload = {
                precio_m2_base: parseFloat(form.precio_m2_base.value),
                fecha_inicio: form.fecha_inicio.value,
                motivo: form.motivo.value.trim()
            };

            try {
                const response = await fetch(`/api/sectores/${sectorId}/precios`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.tokenCsrf
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.status === 201 && data.estado === 'exito') {
                    form.reset();
                    form.classList.remove('was-validated');
                    document.getElementById('nuevoPrecioFechaInicio').value = new Date().toISOString().slice(0, 10);

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Precio Actualizado',
                            text: 'El nuevo precio base por m² fue fijado exitosamente.',
                            timer: 1800,
                            showConfirmButton: false
                        });
                    }

                    await this.cargarHistorialPrecios(sectorId);
                    await this.recargarSectoresYBalance();
                } else {
                    const msj = data.mensaje || 'No se pudo actualizar el precio.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: msj });
                    } else {
                        alert(msj);
                    }
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Fijar';
            }
        },

        abrirModalEstado: function (sectorId, codigo, estadoActual) {
            document.getElementById('estadoSectorId').value = sectorId;
            document.getElementById('textoCambiarEstadoSectorCodigo').textContent = `Sector: ${codigo} (Actual: ${estadoActual})`;

            const select = document.getElementById('nuevoEstadoSectorSelect');
            if (select) {
                select.value = estadoActual;
            }

            const form = document.getElementById('formCambiarEstadoSector');
            if (form) {
                form.motivo.value = '';
                form.classList.remove('was-validated');
            }

            if (this.modalCambiarEstadoSectorBs) {
                this.modalCambiarEstadoSectorBs.show();
            }
        },

        confirmarCambioEstado: async function (e) {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const sectorId = parseInt(document.getElementById('estadoSectorId').value, 10);
            const btn = document.getElementById('btnConfirmarCambioEstadoSector');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';

            const payload = {
                estado: form.estado.value,
                motivo: form.motivo.value.trim()
            };

            try {
                const response = await fetch(`/api/sectores/${sectorId}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.tokenCsrf
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.status === 200 && data.estado === 'exito') {
                    if (this.modalCambiarEstadoSectorBs) {
                        this.modalCambiarEstadoSectorBs.hide();
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Estado Actualizado',
                            text: data.mensaje || 'Estado conmutado exitosamente.',
                            timer: 1800,
                            showConfirmButton: false
                        });
                    }

                    await this.recargarSectoresYBalance();
                } else {
                    const msj = data.mensaje || 'No se pudo conmutar el estado del sector.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: msj });
                    } else {
                        alert(msj);
                    }
                }
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Confirmar Cambio';
            }
        },

        recargarSectoresYBalance: async function () {
            try {
                const response = await fetch(`/api/proyectos/${this.proyectoId}/sectores`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (data.estado === 'exito' && data.datos) {
                    const sectores = data.datos.sectores || [];
                    const balance = data.datos.balance || {};

                    this.actualizarPanelKPIsBalance(balance);
                    this.renderizarTablaSectores(sectores);

                    const badgeConteo = document.getElementById('conteoBadgeSectores');
                    if (badgeConteo) {
                        badgeConteo.textContent = sectores.length;
                    }

                    const textoRemanente = document.getElementById('textoRemanenteDisponibleModal');
                    if (textoRemanente) {
                        textoRemanente.textContent = `${formatearNumero(balance.area_remanente_matriz_m2, 4)} m²`;
                    }
                }
            } catch (err) {
                console.error('Error al recargar sectores y balance:', err);
            }
        },

        actualizarPanelKPIsBalance: function (b) {
            const kpiRef = document.getElementById('kpiAreaReferenciaMatriz');
            if (kpiRef) kpiRef.innerHTML = `${formatearNumero(b.area_referencia_matriz_m2, 4)} <span class="f-s-12 text-muted">m²</span>`;

            const kpiBruta = document.getElementById('kpiAreaSectoresBruta');
            if (kpiBruta) kpiBruta.innerHTML = `${formatearNumero(b.area_sectores_bruta_m2, 4)} <span class="f-s-12 text-muted">m²</span>`;

            const kpiOcup = document.getElementById('kpiOcupacionMatrizBadge');
            if (kpiOcup) kpiOcup.textContent = `${formatearNumero(b.porcentaje_ocupacion_matriz, 2)}% ocupado`;

            const kpiTotal = document.getElementById('kpiTotalSectores');
            if (kpiTotal) kpiTotal.textContent = b.total_sectores || 0;

            const kpiRemanente = document.getElementById('kpiAreaRemanenteMatriz');
            if (kpiRemanente) {
                kpiRemanente.innerHTML = `${formatearNumero(b.area_remanente_matriz_m2, 4)} <span class="f-s-12 text-muted">m²</span>`;
                if (parseFloat(b.area_remanente_matriz_m2 || 0) < 0) {
                    kpiRemanente.classList.add('text-danger');
                } else {
                    kpiRemanente.classList.remove('text-danger');
                }
            }

            const kpiUtil = document.getElementById('kpiAreaSectoresUtil');
            if (kpiUtil) kpiUtil.innerHTML = `${formatearNumero(b.area_sectores_util_m2, 4)} <span class="f-s-12 text-muted">m²</span>`;

            // Banner Semáforo
            const banner = document.getElementById('bannerAlertaBalanceSectores');
            const icono = document.getElementById('iconoAlertaBalanceSectores');
            const mensaje = document.getElementById('mensajeAlertaBalanceSectores');

            if (banner && icono && mensaje) {
                banner.className = 'alert d-flex align-items-center justify-content-between py-2 px-3 mb-4';
                icono.className = 'fa-solid fs-5 me-2';

                switch (b.estado_balance) {
                    case 'BALANCE_CONCILIADO':
                        banner.classList.add('alert-success', 'border-success-subtle');
                        icono.classList.add('fa-circle-check', 'text-success');
                        break;
                    case 'EXCEDE_AREA_MATRIZ':
                        banner.classList.add('alert-danger', 'border-danger-subtle');
                        icono.classList.add('fa-circle-xmark', 'text-danger');
                        break;
                    case 'SECTOR_INCONSISTENTE':
                        banner.classList.add('alert-warning', 'border-warning-subtle');
                        icono.classList.add('fa-triangle-exclamation', 'text-warning');
                        break;
                    default:
                        banner.classList.add('alert-info', 'border-info-subtle');
                        icono.classList.add('fa-circle-info', 'text-info');
                        break;
                }
                mensaje.textContent = b.mensaje_balance || '';
            }
        },

        renderizarTablaSectores: function (sectores) {
            const tbody = document.getElementById('tbodySectores');
            if (!tbody) return;

            if (sectores.length === 0) {
                tbody.innerHTML = `
                    <tr id="trSectoresVacio">
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-map-location-dot fa-2x mb-2 d-block text-secondary opacity-50"></i>
                            <span>No hay sectores urbanísticos registrados en este proyecto.</span><br>
                            <small class="text-secondary">Haga clic en <strong>"Agregar Sector"</strong> para subdividir el terreno matriz e inicializar precios base.</small>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            sectores.forEach(sec => {
                let badgeEstado = 'bg-info-subtle text-info border border-info-subtle';
                if (sec.estado === 'EN_VENTA') badgeEstado = 'bg-success-subtle text-success border border-success-subtle';
                else if (sec.estado === 'CONSOLIDADO') badgeEstado = 'bg-primary-subtle text-primary border border-primary-subtle';
                else if (sec.estado === 'CERRADO') badgeEstado = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                else if (sec.estado === 'INACTIVO') badgeEstado = 'bg-danger-subtle text-danger border border-danger-subtle';

                const precioActual = sec.precio_m2_actual !== null ? parseFloat(sec.precio_m2_actual) : null;
                const monedaSec = sec.precio_moneda || this.monedaProyecto;

                html += `
                    <tr id="fila-sector-${sec.id}">
                        <td class="text-center f-w-600 text-muted f-s-13">${sec.orden}</td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace f-s-12 mb-1">${escaparHtml(sec.codigo)}</span>
                            <div class="f-w-600 text-dark f-s-14">${escaparHtml(sec.nombre)}</div>
                            ${sec.descripcion ? `<small class="text-muted text-truncate d-block" style="max-width: 250px;">${escaparHtml(sec.descripcion)}</small>` : ''}
                        </td>
                        <td class="text-end f-w-600 text-dark f-s-13">
                            ${formatearNumero(sec.area_bruta_m2, 4)} <span class="f-s-11 text-muted">m²</span>
                        </td>
                        <td>
                            <div class="f-s-11">
                                <span class="text-success f-w-500 me-2" title="Área vendible/útil">
                                    <i class="fa-solid fa-shapes me-1"></i>Útil: ${formatearNumero(sec.area_util_m2, 2)} m²
                                </span>
                                <span class="text-warning-emphasis f-w-500 me-2" title="Vías y aportes">
                                    <i class="fa-solid fa-road me-1"></i>Cesión: ${formatearNumero(sec.area_cesion_m2, 2)} m²
                                </span>
                                <span class="text-secondary f-w-500" title="Áreas comunes">
                                    <i class="fa-solid fa-tree me-1"></i>Común: ${formatearNumero(sec.area_comun_m2, 2)} m²
                                </span>
                            </div>
                        </td>
                        <td class="text-end">
                            ${precioActual !== null
                                ? `<span class="badge bg-primary text-white f-s-12">${escaparHtml(monedaSec)} ${formatearNumero(precioActual, 2)} / m²</span>`
                                : '<span class="badge bg-secondary-subtle text-secondary f-s-11">Sin precio</span>'
                            }
                            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 d-block ms-auto f-s-11 btn-ver-precios" data-id="${sec.id}" data-codigo="${escaparHtml(sec.codigo)}" data-nombre="${escaparHtml(sec.nombre)}">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i>Historial
                            </button>
                        </td>
                        <td class="text-center">
                            <span class="badge ${badgeEstado} f-s-11 px-2 py-1">${escaparHtml(sec.estado)}</span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-editar-sector" data-id="${sec.id}" title="Editar sector">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-outline-success btn-ajustar-precio" data-id="${sec.id}" data-codigo="${escaparHtml(sec.codigo)}" title="Actualizar precio m²">
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-estado-sector" data-id="${sec.id}" data-codigo="${escaparHtml(sec.codigo)}" data-estado="${escaparHtml(sec.estado)}" title="Cambiar estado">
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        MODULO.init();
    });

    window.CasaProGestionSectores = MODULO;
})();
