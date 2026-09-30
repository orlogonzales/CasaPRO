/**
 * listado-usuarios.js — Módulo JavaScript para el listado interactivo y gestión de Usuarios.
 * Microfase 1G-2 — CasaPRO.
 */
(function () {
    'use strict';

    if (window.CasaProUsuariosListado) {
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

    function formatearFecha(fechaIso) {
        if (!fechaIso) return '—';
        try {
            const fecha = new Date(fechaIso);
            if (isNaN(fecha.getTime())) return escaparHtml(fechaIso);
            const dia = String(fecha.getDate()).padStart(2, '0');
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const anio = fecha.getFullYear();
            const horas = String(fecha.getHours()).padStart(2, '0');
            const minutos = String(fecha.getMinutes()).padStart(2, '0');
            return `${dia}/${mes}/${anio} ${horas}:${minutos}`;
        } catch {
            return escaparHtml(fechaIso);
        }
    }

    function generarPasswordSegura(longitud = 12) {
        const mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const minus = 'abcdefghijkmnopqrstuvwxyz';
        const num = '23456789';
        const sim = '!@#$%^&*_-+=';
        const todos = mayus + minus + num + sim;

        let pass = '';
        pass += mayus.charAt(Math.floor(Math.random() * mayus.length));
        pass += minus.charAt(Math.floor(Math.random() * minus.length));
        pass += num.charAt(Math.floor(Math.random() * num.length));
        pass += sim.charAt(Math.floor(Math.random() * sim.length));

        for (let i = 4; i < longitud; i++) {
            pass += todos.charAt(Math.floor(Math.random() * todos.length));
        }

        return pass.split('').sort(() => 0.5 - Math.random()).join('');
    }

    const MAPA_ORDEN_COLUMNAS = {
        0: 'u.nombre_usuario',
        1: 'persona_nombre',
        3: 'u.estado',
        4: 'u.bloqueado_hasta',
        5: 'u.ultimo_login_en'
    };

    const MODULO = {
        tabla: null,
        apiUrl: '',
        personasUrl: '',
        rolesUrl: '',
        tokenCsrf: '',
        debounceTimeout: null,

        init: function () {
            const tablaEl = document.getElementById('tablaUsuarios');
            if (!tablaEl) return;

            this.apiUrl = tablaEl.getAttribute('data-api-usuarios-url') || '/api/usuarios';
            this.personasUrl = tablaEl.getAttribute('data-api-personas-disponibles-url') || '/api/usuarios/personas-disponibles';
            this.rolesUrl = tablaEl.getAttribute('data-api-roles-url') || '/api/usuarios/roles';
            this.tokenCsrf = tablaEl.getAttribute('data-csrf-token') || '';

            this.inicializarDataTables(tablaEl);
            this.vincularFiltros();
            this.vincularAcciones();
            this.vincularGeneradoresPassword();
        },

        inicializarDataTables: function (tablaEl) {
            const self = this;

            this.tabla = $(tablaEl).DataTable({
                serverSide: true,
                processing: true,
                searchDelay: 400,
                ajax: function (data, callback, settings) {
                    const params = new URLSearchParams();
                    params.append('draw', data.draw);
                    params.append('start', data.start);
                    params.append('length', data.length);
                    params.append('search', data.search ? data.search.value : '');

                    if (data.order && data.order.length > 0) {
                        const colIdx = data.order[0].column;
                        const colDir = data.order[0].dir;
                        const campoDb = MAPA_ORDEN_COLUMNAS[colIdx] || 'u.id';
                        params.append('order_column', campoDb);
                        params.append('order_dir', colDir);
                    }

                    const estado = document.getElementById('filtroEstado')?.value || '';
                    if (estado) params.append('estado', estado);

                    const bloqueo = document.getElementById('filtroBloqueo')?.value || '';
                    if (bloqueo) params.append('bloqueo', bloqueo);

                    const rolId = document.getElementById('filtroRol')?.value || '';
                    if (rolId) params.append('rol_id', rolId);

                    fetch(self.apiUrl + '?' + params.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.json())
                    .then(json => {
                        callback({
                            draw: json.draw || data.draw,
                            recordsTotal: json.recordsTotal || 0,
                            recordsFiltered: json.recordsFiltered || 0,
                            data: json.data || []
                        });
                    })
                    .catch(err => {
                        console.error('Error al cargar usuarios:', err);
                        callback({
                            draw: data.draw,
                            recordsTotal: 0,
                            recordsFiltered: 0,
                            data: []
                        });
                    });
                },
                columns: [
                    {
                        data: null,
                        render: function (data, type, row) {
                            return `
                                <div>
                                    <strong class="text-dark d-block f-s-14">${escaparHtml(row.nombre_usuario)}</strong>
                                    <span class="text-secondary f-s-12">${escaparHtml(row.email)}</span>
                                </div>`;
                        }
                    },
                    {
                        data: null,
                        render: function (data, type, row) {
                            const nombre = row.persona_nombre ? escaparHtml(row.persona_nombre) : '—';
                            const doc = row.numero_documento ? `${escaparHtml(row.documento_tipo || 'DOC')}: ${escaparHtml(row.numero_documento)}` : '';
                            return `
                                <div>
                                    <span class="d-block f-s-13">${nombre}</span>
                                    ${doc ? `<span class="text-muted f-s-11">${doc}</span>` : ''}
                                </div>`;
                        }
                    },
                    {
                        data: 'roles',
                        orderable: false,
                        render: function (data) {
                            if (!data) return '<span class="text-muted f-s-12">Sin roles</span>';
                            const codigos = String(data).split(',').map(c => c.trim()).filter(Boolean);
                            return codigos.map(c => `<span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">${escaparHtml(c)}</span>`).join('');
                        }
                    },
                    {
                        data: 'estado',
                        className: 'text-center',
                        render: function (data) {
                            if (data === 'ACTIVO') {
                                return '<span class="badge bg-success-subtle text-success border border-success-subtle">ACTIVO</span>';
                            }
                            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">INACTIVO</span>';
                        }
                    },
                    {
                        data: null,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const bloqueado = row.bloqueado_hasta && (new Date(row.bloqueado_hasta) > new Date());
                            if (bloqueado) {
                                return `<span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Bloqueado hasta ${escaparHtml(row.bloqueado_hasta)}"><i class="fa-solid fa-lock me-1"></i>Bloqueado</span>`;
                            }
                            const intentos = parseInt(row.intentos_fallidos, 10) || 0;
                            if (intentos > 0) {
                                return `<span class="badge bg-light-warning text-dark border" title="${intentos} intentos fallidos">${intentos} fallos</span>`;
                            }
                            return '<span class="badge bg-light-secondary text-secondary border">Normal</span>';
                        }
                    },
                    {
                        data: 'ultimo_login_en',
                        render: function (data) {
                            return `<span class="f-s-12 text-secondary">${formatearFecha(data)}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const bloqueado = row.bloqueado_hasta && (new Date(row.bloqueado_hasta) > new Date());
                            const jsonRow = escaparHtml(JSON.stringify(row));

                            return `
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm f-s-13">
                                        <li>
                                            <a class="dropdown-item" href="/usuarios/${row.id}">
                                                <i class="fa-solid fa-eye me-2 text-primary"></i> Ver Ficha
                                            </a>
                                        </li>
                                        <li>
                                            <button class="dropdown-item btn-accion-editar" data-id="${row.id}" data-usuario='${jsonRow}'>
                                                <i class="fa-solid fa-pen-to-square me-2 text-info"></i> Editar
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item btn-accion-roles" data-id="${row.id}" data-nombre="${escaparHtml(row.nombre_usuario)}">
                                                <i class="fa-solid fa-users-gear me-2 text-secondary"></i> Asignar Roles
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item btn-accion-estado" data-id="${row.id}" data-nombre="${escaparHtml(row.nombre_usuario)}" data-estado="${escaparHtml(row.estado)}">
                                                <i class="fa-solid fa-power-off me-2 text-warning"></i> Cambiar Estado
                                            </button>
                                        </li>
                                        ${bloqueado ? `
                                        <li>
                                            <button class="dropdown-item btn-accion-desbloquear text-success" data-id="${row.id}" data-nombre="${escaparHtml(row.nombre_usuario)}">
                                                <i class="fa-solid fa-unlock me-2"></i> Desbloquear Cuenta
                                            </button>
                                        </li>` : ''}
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item btn-accion-reset text-danger" data-id="${row.id}" data-nombre="${escaparHtml(row.nombre_usuario)}">
                                                <i class="fa-solid fa-key me-2"></i> Resetear Contraseña
                                            </button>
                                        </li>
                                    </ul>
                                </div>`;
                        }
                    }
                ],
                language: {
                    processing: 'Procesando usuarios...',
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
                    infoEmpty: 'Mostrando 0 a 0 de 0 usuarios',
                    infoFiltered: '(filtrado de _MAX_ usuarios totales)',
                    loadingRecords: 'Cargando registros...',
                    zeroRecords: 'No se encontraron usuarios coincidentes',
                    emptyTable: 'No existen usuarios registrados',
                    paginate: {
                        first: '«',
                        previous: '‹',
                        next: '›',
                        last: '»'
                    }
                }
            });
        },

        vincularFiltros: function () {
            const self = this;
            ['filtroEstado', 'filtroBloqueo', 'filtroRol'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', () => self.tabla.ajax.reload());
                }
            });

            const btnLimpiar = document.getElementById('btnLimpiarFiltros');
            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', () => {
                    ['filtroEstado', 'filtroBloqueo', 'filtroRol'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                    self.tabla.search('').draw();
                });
            }
        },

        vincularGeneradoresPassword: function () {
            const btnGen = document.getElementById('btnGenerarPasswordTemporal');
            if (btnGen) {
                btnGen.addEventListener('click', () => {
                    const pass = generarPasswordSegura(12);
                    const input = document.getElementById('crearPassword');
                    if (input) input.value = pass;
                });
            }

            const btnCopiar = document.getElementById('btnCopiarPassword');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', () => {
                    const input = document.getElementById('crearPassword');
                    if (input && input.value) {
                        navigator.clipboard.writeText(input.value);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Contraseña copiada al portapapeles',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }

            const btnResetGen = document.getElementById('btnGenerarResetTemporal');
            if (btnResetGen) {
                btnResetGen.addEventListener('click', () => {
                    const pass = generarPasswordSegura(12);
                    const input = document.getElementById('resetPasswordTemporal');
                    if (input) input.value = pass;
                });
            }

            const btnResetCopiar = document.getElementById('btnCopiarResetPassword');
            if (btnResetCopiar) {
                btnResetCopiar.addEventListener('click', () => {
                    const input = document.getElementById('resetPasswordTemporal');
                    if (input && input.value) {
                        navigator.clipboard.writeText(input.value);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Contraseña copiada al portapapeles',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                });
            }
        },

        vincularAcciones: function () {
            const self = this;

            // Abrir Modal Crear Usuario
            const btnNuevo = document.getElementById('btnNuevoUsuario');
            if (btnNuevo) {
                btnNuevo.addEventListener('click', async () => {
                    const form = document.getElementById('formCrearUsuario');
                    if (form) form.reset();

                    // Cargar personas naturales disponibles
                    const selectPersona = document.getElementById('crearPersonaId');
                    selectPersona.innerHTML = '<option value="">Cargando personas disponibles...</option>';

                    try {
                        const res = await fetch(self.personasUrl, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            selectPersona.innerHTML = '<option value="">Seleccione una Persona Natural...</option>';
                            json.datos.forEach(p => {
                                const doc = p.numero_documento ? ` (${p.tipo_documento}: ${p.numero_documento})` : '';
                                selectPersona.innerHTML += `<option value="${p.id}">${escaparHtml(p.nombre_completo)}${doc}</option>`;
                            });
                        }
                    } catch (e) {
                        selectPersona.innerHTML = '<option value="">Error al cargar personas</option>';
                    }

                    // Auto-generar password temporal
                    const inputPass = document.getElementById('crearPassword');
                    if (inputPass) inputPass.value = generarPasswordSegura(12);

                    const modal = new bootstrap.Modal(document.getElementById('modalCrearUsuario'));
                    modal.show();
                });
            }

            // Submit Form Crear Usuario
            const formCrear = document.getElementById('formCrearUsuario');
            if (formCrear) {
                formCrear.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const personaId = document.getElementById('crearPersonaId').value;
                    const nombreUsuario = document.getElementById('crearNombreUsuario').value.trim();
                    const email = document.getElementById('crearEmail').value.trim();
                    const password = document.getElementById('crearPassword').value.trim();
                    const rolesCheckboxes = document.querySelectorAll('.check-rol-crear:checked');
                    const roles = Array.from(rolesCheckboxes).map(cb => parseInt(cb.value, 10));

                    const errorRoles = document.getElementById('errorRolesCrear');
                    if (roles.length === 0) {
                        errorRoles.classList.remove('d-none');
                        return;
                    }
                    errorRoles.classList.add('d-none');

                    const btnGuardar = document.getElementById('btnGuardarCrearUsuario');
                    btnGuardar.disabled = true;

                    try {
                        const res = await fetch(self.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                persona_id: parseInt(personaId, 10),
                                nombre_usuario: nombreUsuario,
                                email: email,
                                password: password,
                                roles: roles
                            })
                        });

                        const json = await res.json();

                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalCrearUsuario')).hide();
                            await Swal.fire({
                                icon: 'success',
                                title: '¡Usuario Creado!',
                                html: `La cuenta de <strong>${escaparHtml(nombreUsuario)}</strong> fue creada exitosamente.<br><br>
                                       <strong>Contraseña temporal:</strong> <code>${escaparHtml(password)}</code><br><br>
                                       <small class="text-muted">Proporcione esta contraseña al usuario. Se le exigirá cambiarla en su primer acceso.</small>`
                            });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            let msg = json.mensaje || 'Error al crear el usuario.';
                            if (json.errores) {
                                msg += '<br>' + Object.values(json.errores).flat().join('<br>');
                            }
                            Swal.fire({ icon: 'error', title: 'Error', html: msg });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                    } finally {
                        btnGuardar.disabled = false;
                    }
                });
            }

            // Delegación de eventos en tabla: Editar
            $(document).on('click', '.btn-accion-editar', function () {
                const usuario = JSON.parse($(this).attr('data-usuario'));
                document.getElementById('editarUsuarioId').value = usuario.id;
                document.getElementById('editarNombreUsuario').value = usuario.nombre_usuario;
                document.getElementById('editarEmail').value = usuario.email;

                const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
                modal.show();
            });

            // Submit Form Editar
            const formEditar = document.getElementById('formEditarUsuario');
            if (formEditar) {
                formEditar.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = document.getElementById('editarUsuarioId').value;
                    const nombreUsuario = document.getElementById('editarNombreUsuario').value.trim();
                    const email = document.getElementById('editarEmail').value.trim();

                    const btn = document.getElementById('btnGuardarEditarUsuario');
                    btn.disabled = true;

                    try {
                        const res = await fetch(`${self.apiUrl}/${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nombre_usuario: nombreUsuario, email: email })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
                            Swal.fire({ icon: 'success', title: 'Actualizado', text: 'Usuario actualizado correctamente.', timer: 1500, showConfirmButton: false });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo actualizar.' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
                    } finally {
                        btn.disabled = false;
                    }
                });
            }

            // Delegación: Cambiar Estado
            $(document).on('click', '.btn-accion-estado', function () {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');
                const estado = $(this).data('estado');

                document.getElementById('estadoUsuarioId').value = id;
                document.getElementById('estadoNombreUsuario').textContent = nombre;
                document.getElementById('nuevoEstadoUsuario').value = estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
                document.getElementById('motivoCambioEstado').value = '';

                const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
                modal.show();
            });

            // Submit Cambiar Estado
            const formEstado = document.getElementById('formCambiarEstado');
            if (formEstado) {
                formEstado.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = document.getElementById('estadoUsuarioId').value;
                    const nuevoEstado = document.getElementById('nuevoEstadoUsuario').value;
                    const motivo = document.getElementById('motivoCambioEstado').value.trim();

                    if (!motivo || motivo.length < 5) {
                        Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'El motivo debe tener al menos 5 caracteres.' });
                        return;
                    }

                    const btn = document.getElementById('btnGuardarCambioEstado');
                    btn.disabled = true;

                    try {
                        const res = await fetch(`${self.apiUrl}/${id}/estado`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ nuevo_estado: nuevoEstado, motivo: motivo })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalCambiarEstado')).hide();
                            Swal.fire({ icon: 'success', title: 'Estado Actualizado', text: 'El estado administrativo ha sido modificado.', timer: 1500, showConfirmButton: false });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo cambiar el estado.' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
                    } finally {
                        btn.disabled = false;
                    }
                });
            }

            // Delegación: Desbloquear
            $(document).on('click', '.btn-accion-desbloquear', function () {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');

                document.getElementById('desbloquearUsuarioId').value = id;
                document.getElementById('desbloquearNombreUsuario').textContent = nombre;
                document.getElementById('motivoDesbloqueo').value = '';

                const modal = new bootstrap.Modal(document.getElementById('modalDesbloquear'));
                modal.show();
            });

            // Submit Desbloquear
            const formDesbloquear = document.getElementById('formDesbloquear');
            if (formDesbloquear) {
                formDesbloquear.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = document.getElementById('desbloquearUsuarioId').value;
                    const motivo = document.getElementById('motivoDesbloqueo').value.trim();

                    if (!motivo || motivo.length < 5) {
                        Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'El motivo debe tener al menos 5 caracteres.' });
                        return;
                    }

                    const btn = document.getElementById('btnGuardarDesbloqueo');
                    btn.disabled = true;

                    try {
                        const res = await fetch(`${self.apiUrl}/${id}/desbloquear`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ motivo: motivo })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalDesbloquear')).hide();
                            Swal.fire({ icon: 'success', title: 'Cuenta Desbloqueada', text: 'El bloqueo de seguridad ha sido cancelado.', timer: 1500, showConfirmButton: false });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No fue posible desbloquear.' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
                    } finally {
                        btn.disabled = false;
                    }
                });
            }

            // Delegación: Asignar Roles
            $(document).on('click', '.btn-accion-roles', async function () {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');

                document.getElementById('rolesUsuarioId').value = id;
                document.getElementById('rolesNombreUsuario').textContent = nombre;

                // Desmarcar todos primero
                document.querySelectorAll('.check-rol-asignar').forEach(cb => cb.checked = false);

                try {
                    const res = await fetch(`${self.apiUrl}/${id}`, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    if (res.ok && json.estado === 'exito' && json.datos.roles) {
                        json.datos.roles.forEach(r => {
                            const cb = document.getElementById(`rol_asig_${r.id}`);
                            if (cb) cb.checked = true;
                        });
                    }
                } catch (e) {}

                const modal = new bootstrap.Modal(document.getElementById('modalAsignarRoles'));
                modal.show();
            });

            // Submit Roles
            const formRoles = document.getElementById('formAsignarRoles');
            if (formRoles) {
                formRoles.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = document.getElementById('rolesUsuarioId').value;
                    const cbs = document.querySelectorAll('.check-rol-asignar:checked');
                    const roles = Array.from(cbs).map(cb => parseInt(cb.value, 10));

                    const errDiv = document.getElementById('errorRolesAsignar');
                    if (roles.length === 0) {
                        errDiv.classList.remove('d-none');
                        return;
                    }
                    errDiv.classList.add('d-none');

                    const btn = document.getElementById('btnGuardarAsignarRoles');
                    btn.disabled = true;

                    try {
                        const res = await fetch(`${self.apiUrl}/${id}/roles`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ roles: roles })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalAsignarRoles')).hide();
                            Swal.fire({ icon: 'success', title: 'Roles Sincronizados', text: 'Los roles fueron asignados correctamente.', timer: 1500, showConfirmButton: false });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudieron actualizar los roles.' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
                    } finally {
                        btn.disabled = false;
                    }
                });
            }

            // Delegación: Resetear Contraseña
            $(document).on('click', '.btn-accion-reset', function () {
                const id = $(this).data('id');
                const nombre = $(this).data('nombre');

                document.getElementById('resetUsuarioId').value = id;
                document.getElementById('resetNombreUsuario').textContent = nombre;
                document.getElementById('resetPasswordTemporal').value = generarPasswordSegura(12);
                document.getElementById('motivoResetPassword').value = '';

                const modal = new bootstrap.Modal(document.getElementById('modalResetearPassword'));
                modal.show();
            });

            // Submit Resetear Contraseña
            const formReset = document.getElementById('formResetearPassword');
            if (formReset) {
                formReset.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = document.getElementById('resetUsuarioId').value;
                    const pass = document.getElementById('resetPasswordTemporal').value.trim();
                    const motivo = document.getElementById('motivoResetPassword').value.trim();

                    if (!motivo || motivo.length < 5) {
                        Swal.fire({ icon: 'warning', title: 'Motivo requerido', text: 'El motivo debe tener al menos 5 caracteres.' });
                        return;
                    }

                    const btn = document.getElementById('btnGuardarResetPassword');
                    btn.disabled = true;

                    try {
                        const res = await fetch(`${self.apiUrl}/${id}/reset-password`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ password_temporal: pass, motivo: motivo })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            bootstrap.Modal.getInstance(document.getElementById('modalResetearPassword')).hide();
                            await Swal.fire({
                                icon: 'success',
                                title: 'Contraseña Reseteada',
                                html: `La contraseña temporal ha sido asignada:<br><br>
                                       <code>${escaparHtml(pass)}</code><br><br>
                                       <small class="text-muted">El usuario deberá ingresar con esta clave y será obligado a renovarla.</small>`
                            });
                            self.tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo resetear la contraseña.' });
                        }
                    } catch (err) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
                    } finally {
                        btn.disabled = false;
                    }
                });
            }
        }
    };

    window.CasaProUsuariosListado = MODULO;

    document.addEventListener('DOMContentLoaded', function () {
        MODULO.init();
    });
})();
