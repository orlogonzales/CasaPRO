/**
 * listado-personas.js — Módulo JavaScript para el listado interactivo y consulta de Personas.
 *
 * Microfase 1E — CasaPRO Inmobiliario.
 * Estándares:
 * - JavaScript ES6+ Vanilla para toda la lógica de negocio y comunicación con API.
 * - Cero uso de jQuery AJAX o llamadas asíncronas heredadas en la lógica propia.
 * - Consumo de DataTables 1.13.3 oficial de Alina con adaptador asíncrono sobre window.fetch().
 * - Inmunidad contra XSS mediante escapeHTML estricto y asignación vía textContent.
 * - Resolución de URLs dinámicas basada en la infraestructura de CasaPRO.
 */

(function () {
    'use strict';

    // Evitar inicialización redundante
    if (window.CasaProPersonasListado) {
        return;
    }

    /**
     * Utilidad centralizada para escape contextual HTML (Protección contra XSS).
     * @param {any} valor
     * @returns {string}
     */
    function escaparHtml(valor) {
        if (valor === null || valor === undefined) {
            return '';
        }
        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Formatea fechas ISO a formato legible local DD/MM/AAAA HH:mm.
     * @param {string|null} fechaIso
     * @returns {string}
     */
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

    /**
     * Mapeo de columnas visibles a campos permitidos en ConsultaDataTablesDTO::MAPA_COLUMNAS.
     */
    const MAPA_ORDEN_COLUMNAS = {
        0: 'documento_principal',
        1: 'nombre_completo',
        2: 'tipo_persona',
        4: 'estado',
        5: 'creado_en'
    };

    const MODULO = {
        tabla: null,
        apiUrl: '',
        debounceTimeout: null,
        tiempoDebounceMs: 400,

        /**
         * Inicializa la pantalla y sus componentes.
         */
        init: function () {
            const tablaEl = document.getElementById('tablaPersonas');
            if (!tablaEl) {
                return;
            }

            // Resolver URL base oficial desde atributo data
            this.apiUrl = tablaEl.getAttribute('data-api-personas-url') || '/api/personas';

            this.inicializarDataTables(tablaEl);
            this.vincularEventosFiltros();
            this.vincularEventosAcciones();
        },

        /**
         * Inicializa la instancia de DataTables 1.13.3 utilizando fetch nativo.
         * @param {HTMLElement} tablaEl
         */
        inicializarDataTables: function (tablaEl) {
            const self = this;

            // Invocación mínima a DataTables (plugin jQuery heredado de Alina)
            // sin usar jQuery para llamadas AJAX o lógica propia.
            this.tabla = $(tablaEl).DataTable({
                serverSide: true,
                processing: true,
                responsive: true,
                order: [[5, 'desc']], // Orden inicial por fecha de registro descendente
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    processing: '<div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div> Procesando consulta...',
                    search: 'Buscar:',
                    searchPlaceholder: 'Nombre, documento, teléfono...',
                    lengthMenu: 'Mostrar _MENU_ registros por página',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                    infoFiltered: '(filtrado de _MAX_ registros en total)',
                    zeroRecords: 'No se encontraron personas con los criterios de búsqueda seleccionados.',
                    emptyTable: 'No hay personas registradas en el sistema.',
                    paginate: {
                        first: 'Primero',
                        previous: 'Anterior',
                        next: 'Siguiente',
                        last: 'Último'
                    }
                },
                ajax: async function (dtParams, callback, settings) {
                    try {
                        const query = new URLSearchParams();

                        // 1. Paginación y control
                        query.set('draw', String(dtParams.draw));
                        query.set('start', String(dtParams.start));
                        query.set('length', String(dtParams.length));

                        // 2. Búsqueda global
                        if (dtParams.search && dtParams.search.value) {
                            const valBusqueda = dtParams.search.value.trim();
                            if (valBusqueda !== '') {
                                query.set('search', valBusqueda);
                            }
                        }

                        // 3. Ordenamiento adaptado a ConsultaDataTablesDTO
                        if (dtParams.order && dtParams.order.length > 0) {
                            const primerOrden = dtParams.order[0];
                            const columnaId = primerOrden.column;
                            const columnaBd = MAPA_ORDEN_COLUMNAS[columnaId];
                            if (columnaBd) {
                                query.set('order_column', columnaBd);
                                query.set('order_dir', primerOrden.dir ? primerOrden.dir.toUpperCase() : 'DESC');
                            }
                        }

                        // 4. Filtros específicos de la interfaz
                        const selectTipo = document.getElementById('filtroTipoPersona');
                        const selectEstado = document.getElementById('filtroEstado');

                        if (selectTipo && selectTipo.value) {
                            query.set('tipo_persona', selectTipo.value);
                        }
                        if (selectEstado && selectEstado.value) {
                            query.set('estado', selectEstado.value);
                        }

                        // 5. Petición HTTP nativa con window.fetch()
                        const urlCompleta = `${self.apiUrl}?${query.toString()}`;
                        const respuesta = await window.fetch(urlCompleta, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (respuesta.status === 401) {
                            self.notificarError('Acceso no autorizado: Se requiere una sesión activa para consultar datos de identidad.');
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        if (respuesta.status === 403) {
                            self.notificarError('No tiene autorización para realizar esta operación.');
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        if (!respuesta.ok) {
                            const errJson = await respuesta.json().catch(() => null);
                            const msj = errJson && errJson.mensaje ? errJson.mensaje : `Error del servidor (HTTP ${respuesta.status})`;
                            self.notificarError(msj);
                            callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                            return;
                        }

                        const json = await respuesta.json();

                        // Validar estructura de respuesta
                        if (typeof json.recordsTotal !== 'number' || !Array.isArray(json.data)) {
                            throw new Error('Respuesta inválida del servidor.');
                        }

                        callback(json);
                    } catch (err) {
                        self.notificarError('No se pudo establecer comunicación con el servidor. Intente nuevamente.');
                        callback({ draw: dtParams.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                    }
                },
                columns: [
                    // Columna 0: Documento Principal
                    {
                        data: null,
                        orderable: true,
                        render: function (data, type, row) {
                            if (row.documento_principal) {
                                const tipoDoc = row.tipo_documento_abreviatura ? escaparHtml(row.tipo_documento_abreviatura) + ' ' : '';
                                return `<span class="f-w-500 text-dark">${tipoDoc}${escaparHtml(row.documento_principal)}</span>`;
                            }
                            return '<span class="badge text-light-secondary">S/D</span>';
                        }
                    },
                    // Columna 1: Nombre Completo / Razón Social
                    {
                        data: 'nombre_completo',
                        orderable: true,
                        render: function (data) {
                            return `<span class="text-dark f-w-600">${escaparHtml(data || '—')}</span>`;
                        }
                    },
                    // Columna 2: Tipo de Persona (Variants of chip Alina)
                    {
                        data: 'tipo_persona',
                        orderable: true,
                        render: function (data) {
                            if (data === 'NATURAL') {
                                return '<span class="chip bg-light-primary text-primary">NATURAL</span>';
                            }
                            return '<span class="chip bg-light-info text-info">JURÍDICA</span>';
                        }
                    },
                    // Columna 3: Contacto Principal
                    {
                        data: 'contacto_principal',
                        orderable: false,
                        render: function (data) {
                            if (data) {
                                return `<span class="text-secondary f-s-13"><i class="ti ti-phone me-1 text-muted"></i>${escaparHtml(data)}</span>`;
                            }
                            return '<span class="text-muted f-s-13">—</span>';
                        }
                    },
                    // Columna 4: Estado (Variants of badge Alina - Nunca dotted)
                    {
                        data: 'estado',
                        orderable: true,
                        render: function (data) {
                            if (data === 'ACTIVO') {
                                return '<span class="badge text-light-success">ACTIVO</span>';
                            }
                            return '<span class="badge text-light-secondary">INACTIVO</span>';
                        }
                    },
                    // Columna 5: Fecha de Registro
                    {
                        data: 'creado_en',
                        orderable: true,
                        render: function (data) {
                            return `<span class="text-secondary f-s-13">${formatearFecha(data)}</span>`;
                        }
                    },
                    // Columna 6: Acciones Funcionales 1E (Solo "Ver Ficha")
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            return `<button type="button" class="btn btn-outline-primary btn-sm icon-btn b-r-4 btn-ver-ficha" data-id="${escaparHtml(row.id)}" title="Ver Ficha de Identidad"><i class="ti ti-eye"></i></button>`;
                        }
                    }
                ]
            });
        },

        /**
         * Vincula eventos de cambio en filtros de servidor y debounce en búsqueda.
         */
        vincularEventosFiltros: function () {
            const self = this;
            const selectTipo = document.getElementById('filtroTipoPersona');
            const selectEstado = document.getElementById('filtroEstado');
            const btnLimpiar = document.getElementById('btnLimpiarFiltros');

            if (selectTipo) {
                selectTipo.addEventListener('change', function () {
                    if (self.tabla) self.tabla.ajax.reload();
                });
            }

            if (selectEstado) {
                selectEstado.addEventListener('change', function () {
                    if (self.tabla) self.tabla.ajax.reload();
                });
            }

            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', function () {
                    if (selectTipo) selectTipo.value = '';
                    if (selectEstado) selectEstado.value = '';
                    if (self.tabla) {
                        self.tabla.search('');
                        self.tabla.ajax.reload();
                    }
                });
            }

            // Debouncing centralizado para el campo de búsqueda de DataTables
            const wrapper = document.querySelector('#tablaPersonas_filter input');
            if (wrapper) {
                wrapper.addEventListener('input', function (e) {
                    clearTimeout(self.debounceTimeout);
                    const valor = e.target.value;
                    self.debounceTimeout = setTimeout(function () {
                        if (self.tabla) {
                            self.tabla.search(valor).draw();
                        }
                    }, self.tiempoDebounceMs);
                });
            }
        },

        /**
         * Vincula eventos de clic en botones de acción dentro de la tabla.
         */
        vincularEventosAcciones: function () {
            const self = this;
            const tablaEl = document.getElementById('tablaPersonas');
            if (!tablaEl) return;

            tablaEl.addEventListener('click', function (evento) {
                const boton = evento.target.closest('.btn-ver-ficha');
                if (!boton) return;

                const personaId = boton.getAttribute('data-id');
                if (personaId) {
                    self.abrirFichaPersona(personaId);
                }
            });
        },

        /**
         * Consulta y despliega la Ficha de Identidad de Persona en diálogo modal.
         * @param {string|number} personaId
         */
        abrirFichaPersona: async function (personaId) {
            const modalEl = document.getElementById('modalFichaPersona');
            if (!modalEl) return;

            const modalBs = bootstrap.Modal.getOrCreateInstance(modalEl);
            const spinner = document.getElementById('fichaSpinnerCarga');
            const contenido = document.getElementById('fichaContenidoDetalle');

            // Mostrar estado de carga y abrir modal
            if (spinner) spinner.classList.remove('d-none');
            if (contenido) contenido.classList.add('d-none');
            modalBs.show();

            try {
                const url = `${this.apiUrl}/${encodeURIComponent(personaId)}`;
                const respuesta = await window.fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });

                if (!respuesta.ok) {
                    throw new Error(`No se pudo obtener el detalle de la persona (HTTP ${respuesta.status}).`);
                }

                const json = await respuesta.json();
                const datos = json.datos || json;

                this.poblarFichaPersona(datos);

                if (spinner) spinner.classList.add('d-none');
                if (contenido) contenido.classList.remove('d-none');
            } catch (err) {
                if (spinner) spinner.classList.add('d-none');
                this.notificarError(err.message || 'Error al cargar los datos de identidad.');
                modalBs.hide();
            }
        },

        /**
         * Puebla la Ficha de Identidad manipulando el DOM de forma segura (Inmunidad XSS).
         * @param {Object} datos
         */
        poblarFichaPersona: function (datos) {
            const persona = datos.persona || {};
            const natural = datos.natural;
            const juridica = datos.juridica;
            const documentos = Array.isArray(datos.documentos) ? datos.documentos : [];
            const contactos = Array.isArray(datos.contactos) ? datos.contactos : [];
            const direcciones = Array.isArray(datos.direcciones) ? datos.direcciones : [];
            const representantes = Array.isArray(datos.representantes) ? datos.representantes : [];

            // 1. Subtítulo y Metadatos de Cabecera
            const badgeTipo = document.getElementById('fichaBadgeTipo');
            const badgeEstado = document.getElementById('fichaBadgeEstado');
            const textoId = document.getElementById('fichaTextoId');

            if (badgeTipo) {
                badgeTipo.className = persona.tipo_persona === 'NATURAL'
                    ? 'chip bg-light-primary text-primary'
                    : 'chip bg-light-info text-info';
                badgeTipo.textContent = persona.tipo_persona === 'NATURAL' ? 'PERSONA NATURAL' : 'PERSONA JURÍDICA';
            }

            if (badgeEstado) {
                badgeEstado.className = persona.estado === 'ACTIVO'
                    ? 'badge text-light-success'
                    : 'badge text-light-secondary';
                badgeEstado.textContent = persona.estado || '—';
            }

            if (textoId) {
                textoId.textContent = `ID Sistema: #${persona.id || '—'}`;
            }

            // 2. Pestaña: Datos Generales
            const contenedorGenerales = document.getElementById('fichaDatosGenerales');
            if (contenedorGenerales) {
                contenedorGenerales.innerHTML = ''; // Limpiar contenedor previo

                const grid = document.createElement('div');
                grid.className = 'row g-3';

                if (persona.tipo_persona === 'NATURAL' && natural) {
                    this.agregarCampoDetalle(grid, 'Nombre Completo', natural.nombre_completo || `${natural.apellido_paterno} ${natural.apellido_materno || ''}, ${natural.nombres}`, 12);
                    this.agregarCampoDetalle(grid, 'Nombres', natural.nombres, 4);
                    this.agregarCampoDetalle(grid, 'Apellido Paterno', natural.apellido_paterno, 4);
                    this.agregarCampoDetalle(grid, 'Apellido Materno', natural.apellido_materno || '—', 4);
                    this.agregarCampoDetalle(grid, 'Fecha de Nacimiento', natural.fecha_nacimiento || '—', 4);
                    this.agregarCampoDetalle(grid, 'Sexo', natural.sexo ? natural.sexo.nombre : '—', 4);
                    this.agregarCampoDetalle(grid, 'Estado Civil', natural.estado_civil ? natural.estado_civil.nombre : '—', 4);
                    this.agregarCampoDetalle(grid, 'País de Nacimiento', natural.pais_nacimiento ? natural.pais_nacimiento.nombre : '—', 6);
                    this.agregarCampoDetalle(grid, 'Profesión u Ocupación', natural.profesion_ocupacion || '—', 6);
                } else if (juridica) {
                    this.agregarCampoDetalle(grid, 'Razón Social', juridica.razon_social, 12);
                    this.agregarCampoDetalle(grid, 'Nombre Comercial', juridica.nombre_comercial || '—', 6);
                    this.agregarCampoDetalle(grid, 'Fecha de Constitución', juridica.fecha_constitucion || '—', 6);
                    this.agregarCampoDetalle(grid, 'Objeto Social', juridica.objeto_social || '—', 12);
                }

                if (persona.notas) {
                    this.agregarCampoDetalle(grid, 'Notas u Observaciones', persona.notas, 12);
                }

                contenedorGenerales.appendChild(grid);
            }

            // 3. Pestaña: Documentos y Contactos
            const contDocs = document.getElementById('fichaContenedorDocumentos');
            if (contDocs) {
                contDocs.innerHTML = '';
                if (documentos.length === 0) {
                    contDocs.innerHTML = '<p class="text-muted f-s-13 mb-0">No registra documentos de identidad adicionales.</p>';
                } else {
                    const tablaDocs = document.createElement('table');
                    tablaDocs.className = 'table table-sm table-bordered align-middle mb-0';
                    tablaDocs.innerHTML = `
                        <thead class="table-light">
                            <tr>
                                <th>Tipo de Documento</th>
                                <th>Número de Documento</th>
                                <th>País Emisión</th>
                                <th>Emisión / Vencimiento</th>
                                <th>Principal</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    const tbody = tablaDocs.querySelector('tbody');
                    documentos.forEach(doc => {
                        const tr = document.createElement('tr');

                        const tdTipo = document.createElement('td');
                        tdTipo.textContent = doc.tipo_documento ? doc.tipo_documento.nombre : '—';

                        const tdNum = document.createElement('td');
                        tdNum.className = 'f-w-600 text-dark';
                        tdNum.textContent = doc.numero_documento;

                        const tdPais = document.createElement('td');
                        tdPais.textContent = doc.pais_emision ? doc.pais_emision.nombre : '—';

                        const tdFechas = document.createElement('td');
                        const emision = doc.fecha_emision || '—';
                        const venc = doc.fecha_vencimiento || '—';
                        tdFechas.textContent = `${emision} al ${venc}`;

                        const tdPrincipal = document.createElement('td');
                        tdPrincipal.innerHTML = doc.es_principal
                            ? '<span class="badge text-light-success">Sí</span>'
                            : '<span class="badge text-light-secondary">No</span>';

                        tr.appendChild(tdTipo);
                        tr.appendChild(tdNum);
                        tr.appendChild(tdPais);
                        tr.appendChild(tdFechas);
                        tr.appendChild(tdPrincipal);
                        tbody.appendChild(tr);
                    });
                    contDocs.appendChild(tablaDocs);
                }
            }

            const contContactos = document.getElementById('fichaContenedorContactos');
            if (contContactos) {
                contContactos.innerHTML = '';
                if (contactos.length === 0) {
                    contContactos.innerHTML = '<p class="text-muted f-s-13 mb-0">No registra información de contacto.</p>';
                } else {
                    const tablaCont = document.createElement('table');
                    tablaCont.className = 'table table-sm table-bordered align-middle mb-0';
                    tablaCont.innerHTML = `
                        <thead class="table-light">
                            <tr>
                                <th>Canal / Tipo</th>
                                <th>Valor / Contacto</th>
                                <th>Etiqueta</th>
                                <th>Principal</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    const tbody = tablaCont.querySelector('tbody');
                    contactos.forEach(c => {
                        const tr = document.createElement('tr');

                        const tdTipo = document.createElement('td');
                        tdTipo.textContent = c.tipo_contacto ? c.tipo_contacto.nombre : '—';

                        const tdVal = document.createElement('td');
                        tdVal.className = 'f-w-600 text-dark';
                        tdVal.textContent = c.valor;

                        const tdEtiq = document.createElement('td');
                        tdEtiq.textContent = c.etiqueta || '—';

                        const tdPrinc = document.createElement('td');
                        tdPrinc.innerHTML = c.es_principal
                            ? '<span class="badge text-light-success">Sí</span>'
                            : '<span class="badge text-light-secondary">No</span>';

                        tr.appendChild(tdTipo);
                        tr.appendChild(tdVal);
                        tr.appendChild(tdEtiq);
                        tr.appendChild(tdPrinc);
                        tbody.appendChild(tr);
                    });
                    contContactos.appendChild(tablaCont);
                }
            }

            // 4. Pestaña: Domicilios
            const contDirecciones = document.getElementById('fichaContenedorDirecciones');
            if (contDirecciones) {
                contDirecciones.innerHTML = '';
                if (direcciones.length === 0) {
                    contDirecciones.innerHTML = '<p class="text-muted f-s-13 mb-0">No registra domicilios o direcciones fiscales.</p>';
                } else {
                    const tablaDir = document.createElement('table');
                    tablaDir.className = 'table table-sm table-bordered align-middle mb-0';
                    tablaDir.innerHTML = `
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Ubicación Territorial (UBIGEO)</th>
                                <th>Dirección</th>
                                <th>Referencia</th>
                                <th>Principal</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    const tbody = tablaDir.querySelector('tbody');
                    direcciones.forEach(d => {
                        const tr = document.createElement('tr');

                        const tdTipo = document.createElement('td');
                        tdTipo.textContent = d.tipo_direccion ? d.tipo_direccion.nombre : '—';

                        const tdUbigeo = document.createElement('td');
                        if (d.ubigeo) {
                            tdUbigeo.textContent = `${d.ubigeo.departamento} / ${d.ubigeo.provincia} / ${d.ubigeo.distrito}`;
                        } else {
                            tdUbigeo.textContent = '—';
                        }

                        const tdDir = document.createElement('td');
                        tdDir.className = 'text-dark';
                        tdDir.textContent = d.direccion;

                        const tdRef = document.createElement('td');
                        tdRef.textContent = d.referencia || '—';

                        const tdPrinc = document.createElement('td');
                        tdPrinc.innerHTML = d.es_principal
                            ? '<span class="badge text-light-success">Sí</span>'
                            : '<span class="badge text-light-secondary">No</span>';

                        tr.appendChild(tdTipo);
                        tr.appendChild(tdUbigeo);
                        tr.appendChild(tdDir);
                        tr.appendChild(tdRef);
                        tr.appendChild(tdPrinc);
                        tbody.appendChild(tr);
                    });
                    contDirecciones.appendChild(tablaDir);
                }
            }

            // 5. Pestaña: Representación (Solo para Jurídicas con representantes o si existen datos)
            const tabItemRep = document.getElementById('tabItemRepresentacion');
            const contRep = document.getElementById('fichaContenedorRepresentantes');

            if (persona.tipo_persona === 'JURIDICA' && representantes.length > 0) {
                if (tabItemRep) tabItemRep.style.display = '';
                if (contRep) {
                    contRep.innerHTML = '';
                    const tablaRep = document.createElement('table');
                    tablaRep.className = 'table table-sm table-bordered align-middle mb-0';
                    tablaRep.innerHTML = `
                        <thead class="table-light">
                            <tr>
                                <th>Representante Legal</th>
                                <th>Cargo</th>
                                <th>Partida Registral</th>
                                <th>Periodo</th>
                                <th>Actual</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    const tbody = tablaRep.querySelector('tbody');
                    representantes.forEach(r => {
                        const tr = document.createElement('tr');

                        const tdNom = document.createElement('td');
                        tdNom.className = 'f-w-600 text-dark';
                        tdNom.textContent = r.nombre_representante || `Persona #${r.persona_natural_id}`;

                        const tdCargo = document.createElement('td');
                        tdCargo.textContent = r.cargo || '—';

                        const tdPartida = document.createElement('td');
                        tdPartida.textContent = r.partida_registral || '—';

                        const tdPeriodo = document.createElement('td');
                        tdPeriodo.textContent = `${r.fecha_inicio || '—'} al ${r.fecha_fin || 'Indefinido'}`;

                        const tdActual = document.createElement('td');
                        tdActual.innerHTML = r.es_representante_actual
                            ? '<span class="badge text-light-success">Sí</span>'
                            : '<span class="badge text-light-secondary">No</span>';

                        tr.appendChild(tdNom);
                        tr.appendChild(tdCargo);
                        tr.appendChild(tdPartida);
                        tr.appendChild(tdPeriodo);
                        tr.appendChild(tdActual);
                        tbody.appendChild(tr);
                    });
                    contRep.appendChild(tablaRep);
                }
            } else {
                if (tabItemRep) tabItemRep.style.display = 'none';
            }

            // Resetear pestaña activa a la primera ("Datos Generales")
            const primerTabBtn = document.getElementById('tab-general-btn');
            if (primerTabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                const tabInstance = bootstrap.Tab.getOrCreateInstance(primerTabBtn);
                tabInstance.show();
            }
        },

        /**
         * Crea un bloque de atributo clave-valor seguro en la cuadrícula de detalles.
         */
        agregarCampoDetalle: function (contenedorPadre, etiqueta, valor, columnasBootstrap) {
            const col = document.createElement('div');
            col.className = `col-md-${columnasBootstrap}`;

            const lbl = document.createElement('span');
            lbl.className = 'text-secondary f-s-12 d-block text-uppercase tracking-wider';
            lbl.textContent = etiqueta;

            const val = document.createElement('span');
            val.className = 'text-dark f-w-500 d-block';
            val.textContent = valor || '—';

            col.appendChild(lbl);
            col.appendChild(val);
            contenedorPadre.appendChild(col);
        },

        /**
         * Despliega una alerta o notificación de error visual segura.
         * @param {string} mensaje
         */
        notificarError: function (mensaje) {
            // Se puede emitir consola y alerta visual no invasiva
            console.error('[CasaPRO Identidad]', mensaje);
            if (typeof alert === 'function') {
                alert(mensaje);
            }
        }
    };

    // Publicar en espacio de nombres global
    window.CasaProPersonasListado = MODULO;

    // Inicializar automáticamente al cargar el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            MODULO.init();
        });
    } else {
        MODULO.init();
    }
})();
