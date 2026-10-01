/**
 * CasaPRO — Gestión Visual de Asignaciones Territoriales (Usuario ↔ Empresa ↔ Rol)
 * Módulo: public/assets/js/modulos/usuarios/asignaciones-territoriales.js
 *
 * Principios vinculantes:
 * - Vanilla JavaScript ES6+ nativo (window.fetch()). Cero llamadas AJAX con jQuery.
 * - Refresco asíncrono estricto de la grilla (cero recarga de página).
 * - Sanitización estricta contextual contra XSS.
 * - Integración con modales Bootstrap 5, Select2 y SweetAlert2.
 */
(() => {
    'use strict';

    const CasaProAsignaciones = {
        usuarioId: null,
        csrfToken: null,
        catalogosCargados: false,
        modalInstancia: null,

        init() {
            const form = document.getElementById('formAsignarRolEmpresa');
            if (!form) {
                return;
            }

            this.usuarioId = parseInt(form.dataset.usuarioId || '0', 10);
            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || form.dataset.csrfToken
                || '';

            this.inicializarComponentes();
            this.vincularEventos();
        },

        inicializarComponentes() {
            const modalEl = document.getElementById('modalAsignarRolEmpresa');
            if (modalEl && window.bootstrap?.Modal) {
                this.modalInstancia = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            }

            // Inicializar Select2 en modales si jQuery y Select2 existen
            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery('#selectEmpresaAsignar').select2({
                    dropdownParent: window.jQuery('#modalAsignarRolEmpresa'),
                    placeholder: 'Seleccione una empresa activa...',
                    allowClear: true,
                    width: '100%'
                });

                window.jQuery('#selectRolAsignar').select2({
                    dropdownParent: window.jQuery('#modalAsignarRolEmpresa'),
                    placeholder: 'Seleccione un rol asignable...',
                    allowClear: true,
                    width: '100%'
                });
            }
        },

        vincularEventos() {
            // Botón abrir modal
            const btnAbrir = document.getElementById('btnAbrirModalAsignarRol');
            if (btnAbrir) {
                btnAbrir.addEventListener('click', () => {
                    this.abrirModalAsignacion();
                });
            }

            // Envío del formulario
            const form = document.getElementById('formAsignarRolEmpresa');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.guardarAsignacion();
                });
            }

            // Delegación de eventos en tabla de asignaciones para Revocar / Reactivar
            const tbody = document.getElementById('tbodyAsignacionesTerritoriales');
            if (tbody) {
                tbody.addEventListener('click', (e) => {
                    const btn = e.target.closest('.btn-conmutar-asignacion');
                    if (btn) {
                        const id = parseInt(btn.dataset.id || '0', 10);
                        const estadoActual = btn.dataset.estadoActual || '';
                        const empresa = btn.dataset.empresa || 'la empresa';
                        const rol = btn.dataset.rol || 'el rol';
                        this.conmutarEstadoAsignacion(id, estadoActual, empresa, rol);
                    }
                });
            }
        },

        async abrirModalAsignacion() {
            if (!this.catalogosCargados) {
                await this.cargarCatalogos();
            }

            const form = document.getElementById('formAsignarRolEmpresa');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
                if (window.jQuery && window.jQuery.fn.select2) {
                    window.jQuery('#selectEmpresaAsignar').val('').trigger('change');
                    window.jQuery('#selectRolAsignar').val('').trigger('change');
                }
            }

            if (this.modalInstancia) {
                this.modalInstancia.show();
            }
        },

        async cargarCatalogos() {
            try {
                const [respEmpresas, respRoles] = await Promise.all([
                    window.fetch('/api/asignaciones/empresas-disponibles', {
                        headers: { 'Accept': 'application/json' }
                    }),
                    window.fetch('/api/asignaciones/roles-disponibles', {
                        headers: { 'Accept': 'application/json' }
                    })
                ]);

                const dataEmpresas = await respEmpresas.json();
                const dataRoles = await respRoles.json();

                if (dataEmpresas.estado === 'exito' && Array.isArray(dataEmpresas.datos)) {
                    const selectEmp = document.getElementById('selectEmpresaAsignar');
                    if (selectEmp) {
                        selectEmp.innerHTML = '<option value="">Seleccione una empresa activa...</option>';
                        dataEmpresas.datos.forEach(emp => {
                            const opt = document.createElement('option');
                            opt.value = emp.id;
                            opt.textContent = `[${emp.codigo}] ${emp.nombre_corto}`;
                            selectEmp.appendChild(opt);
                        });
                    }
                }

                if (dataRoles.estado === 'exito' && Array.isArray(dataRoles.datos)) {
                    const selectRol = document.getElementById('selectRolAsignar');
                    if (selectRol) {
                        selectRol.innerHTML = '<option value="">Seleccione un rol asignable...</option>';
                        dataRoles.datos.forEach(r => {
                            const opt = document.createElement('option');
                            opt.value = r.id;
                            opt.textContent = `${r.nombre} (${r.codigo})`;
                            selectRol.appendChild(opt);
                        });
                    }
                }

                this.catalogosCargados = true;
            } catch (err) {
                console.error('Error al cargar catálogos de asignación:', err);
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: 'No fue posible cargar el catálogo de empresas o roles disponibles.'
                    });
                }
            }
        },

        async guardarAsignacion() {
            const form = document.getElementById('formAsignarRolEmpresa');
            if (!form) return;

            const empresaId = parseInt(form.elements['empresa_id']?.value || '0', 10);
            const rolId = parseInt(form.elements['rol_id']?.value || '0', 10);
            const motivo = form.elements['motivo']?.value?.trim() || '';

            if (empresaId <= 0 || rolId <= 0) {
                form.classList.add('was-validated');
                return;
            }

            const btnGuardar = document.getElementById('btnGuardarAsignacionRol');
            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
            }

            try {
                const resp = await window.fetch(`/api/usuarios/${this.usuarioId}/asignaciones`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        empresa_id: empresaId,
                        rol_id: rolId,
                        motivo: motivo
                    })
                });

                const data = await resp.json();

                if (!resp.ok || data.estado !== 'exito') {
                    throw new Error(data.mensaje || 'Error al formalizar la asignación territorial.');
                }

                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'success',
                        title: 'Asignación Formalizada',
                        text: data.mensaje || 'El rol territorial ha sido asignado correctamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }

                if (this.modalInstancia) {
                    this.modalInstancia.hide();
                }

                // Refrescar grilla asíncronamente
                await this.refrescarTablaAsignaciones();

            } catch (err) {
                console.error('Error en asignación territorial:', err);
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'No se pudo asignar el rol',
                        text: err.message
                    });
                }
            } finally {
                if (btnGuardar) {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Asignar Rol';
                }
            }
        },

        async conmutarEstadoAsignacion(id, estadoActual, empresa, rol) {
            const esRevocar = estadoActual === 'ACTIVO';
            const accionTitulo = esRevocar ? 'Revocar Asignación' : 'Reactivar Asignación';
            const accionTexto = esRevocar
                ? `¿Confirma revocar el rol "${rol}" en "${empresa}"? El usuario perderá los privilegios territoriales asociados inmediatamente.`
                : `¿Desea reactivar la asignación del rol "${rol}" en "${empresa}"?`;
            const colorBoton = esRevocar ? '#dc3545' : '#198754';
            const textoBoton = esRevocar ? 'Sí, Revocar Rol' : 'Sí, Reactivar Rol';

            if (!window.Swal) {
                if (!confirm(accionTexto)) return;
                this.ejecutarConmutacion(id, esRevocar ? 'INACTIVO' : 'ACTIVO', '');
                return;
            }

            const resultadoSwal = await window.Swal.fire({
                title: accionTitulo,
                text: accionTexto,
                icon: esRevocar ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: colorBoton,
                cancelButtonColor: '#6c757d',
                confirmButtonText: textoBoton,
                cancelButtonText: 'Cancelar'
            });

            if (resultadoSwal.isConfirmed) {
                await this.ejecutarConmutacion(id, esRevocar ? 'INACTIVO' : 'ACTIVO', '');
            }
        },

        async ejecutarConmutacion(id, nuevoEstado, motivo) {
            try {
                const resp = await window.fetch(`/api/asignaciones/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        estado: nuevoEstado,
                        motivo: motivo || (nuevoEstado === 'INACTIVO' ? 'Revocación administrativa' : 'Reactivación administrativa')
                    })
                });

                const data = await resp.json();

                if (!resp.ok || data.estado !== 'exito') {
                    throw new Error(data.mensaje || 'Error al modificar el estado de la asignación.');
                }

                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'success',
                        title: nuevoEstado === 'ACTIVO' ? 'Asignación Reactivada' : 'Asignación Revocada',
                        text: data.mensaje || 'Operación completada exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }

                await this.refrescarTablaAsignaciones();

            } catch (err) {
                console.error('Error al conmutar asignación:', err);
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Error de Operación',
                        text: err.message
                    });
                }
            }
        },

        async refrescarTablaAsignaciones() {
            const tbody = document.getElementById('tbodyAsignacionesTerritoriales');
            if (!tbody) return;

            try {
                const resp = await window.fetch(`/api/usuarios/${this.usuarioId}/asignaciones`, {
                    headers: { 'Accept': 'application/json' }
                });

                const data = await resp.json();
                if (!resp.ok || data.estado !== 'exito') {
                    return;
                }

                const asignaciones = data.datos?.asignaciones || [];
                tbody.innerHTML = '';

                const badgeTotal = document.getElementById('badgeTotalAsignaciones');
                if (badgeTotal) {
                    badgeTotal.textContent = String(asignaciones.length);
                }

                if (asignaciones.length === 0) {
                    tbody.innerHTML = `
                        <tr id="filaSinAsignaciones">
                            <td colspan="6" class="text-center py-4 text-muted f-s-13">
                                <i class="fa-solid fa-building-circle-exclamation d-block f-s-24 mb-2 text-secondary opacity-50"></i>
                                El usuario no cuenta con roles asignados en empresas actualmente.
                            </td>
                        </tr>
                    `;
                    return;
                }

                asignaciones.forEach(asig => {
                    const tr = document.createElement('tr');
                    tr.id = `fila-asig-${asig.id}`;

                    const esActivo = asig.estado === 'ACTIVO';
                    const badgeEstado = esActivo
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">ACTIVO</span>'
                        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">INACTIVO</span>';

                    const botonAccion = esActivo
                        ? `<button type="button" class="btn btn-outline-danger btn-sm btn-conmutar-asignacion" 
                                data-id="${asig.id}" 
                                data-estado-actual="ACTIVO" 
                                data-empresa="${this.escapeHtml(asig.empresa_nombre || '')}"
                                data-rol="${this.escapeHtml(asig.rol_nombre || '')}"
                                title="Revocar asignación de rol">
                                <i class="fa-solid fa-user-xmark me-1"></i>Revocar
                            </button>`
                        : `<button type="button" class="btn btn-outline-success btn-sm btn-conmutar-asignacion" 
                                data-id="${asig.id}" 
                                data-estado-actual="INACTIVO" 
                                data-empresa="${this.escapeHtml(asig.empresa_nombre || '')}"
                                data-rol="${this.escapeHtml(asig.rol_nombre || '')}"
                                title="Reactivar asignación histórica">
                                <i class="fa-solid fa-user-check me-1"></i>Reactivar
                            </button>`;

                    tr.innerHTML = `
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                ${this.escapeHtml(asig.empresa_codigo || '')}
                            </span>
                            <strong class="text-dark f-s-13">${this.escapeHtml(asig.empresa_nombre || '')}</strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                ${this.escapeHtml(asig.rol_codigo || '')}
                            </span>
                            <span class="text-muted f-s-12 ms-1">${this.escapeHtml(asig.rol_nombre || '')}</span>
                        </td>
                        <td>${badgeEstado}</td>
                        <td class="text-dark f-s-12">${this.escapeHtml(asig.asignado_en || '-')}</td>
                        <td class="text-secondary f-s-12">${this.escapeHtml(asig.asignado_por_nombre || 'Sistema')}</td>
                        <td class="text-end">${botonAccion}</td>
                    `;

                    tbody.appendChild(tr);
                });

            } catch (err) {
                console.error('Error al refrescar tabla de asignaciones:', err);
            }
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        CasaProAsignaciones.init();
    });

    window.CasaProAsignaciones = CasaProAsignaciones;
})();
