/**
 * gestion-empresas.js — Módulo JavaScript Vanilla ES6+ para la administración interactiva de Empresas Corporativas.
 *
 * Microfase 2C — CasaPRO Inmobiliario.
 * Reglas Inviolables:
 * - Cero window.location.reload() / location.reload() — Todas las mutaciones son 100% asíncronas.
 * - Cero llamadas a $.ajax, $.get, $.post, $.getJSON en código propio de CasaPRO.
 * - Reutilización de jQuery exclusivamente como dependencia técnica interna de DataTables y Select2.
 * - Recarga asíncrona de DataTables con self.tabla.ajax.reload(null, false) conservando paginación y orden.
 * - Reutilización del contrato íntegro de CrearPersonaDTO en alta orquestada (sin duplicidad civil).
 * - Despliegue veraz de la Ficha 360° sin inventar atributos.
 * - Inmunidad XSS en manipulación de DOM y escape de datos.
 */

(function () {
    'use strict';

    if (window.CasaProEmpresas) {
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
     * Formateador de fechas ISO a formato legible dd/mm/aaaa hh:mm.
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
     * Mapa de ordenamiento para DataTables server-side whitelist.
     */
    const MAPA_ORDEN_COLUMNAS = {
        0: 'codigo',
        1: 'ruc',
        2: 'razon_social',
        3: 'nombre_corto',
        4: 'estado',
        5: 'creado_en'
    };

    const MODULO = {
        tabla: null,
        apiUrl: '',
        personasUrl: '',
        provinciasUrl: '',
        distritosUrl: '',
        consultarDocUrl: '',
        tokenCsrf: '',
        rucTipoId: 0,
        modalCrearBs: null,
        modalEditarBs: null,
        modalFichaBs: null,
        pristineVinculada: null,
        pristineOrquestada: null,
        pristineEditar: null,

        /**
         * Inicialización del módulo al cargar la página.
         */
        init: function () {
            const tablaEl = document.getElementById('tablaEmpresas');
            if (!tablaEl) return;

            this.apiUrl = tablaEl.getAttribute('data-api-url') || '/api/empresas';
            this.personasUrl = tablaEl.getAttribute('data-api-personas-disponibles-url') || '/api/empresas/personas-juridicas-disponibles';
            this.provinciasUrl = tablaEl.getAttribute('data-api-provincias-url') || '/api/ubigeo/provincias';
            this.distritosUrl = tablaEl.getAttribute('data-api-distritos-url') || '/api/ubigeo/distritos';
            this.consultarDocUrl = tablaEl.getAttribute('data-api-consultar-documento-url') || '/api/personas/consultar-documento';
            this.tokenCsrf = tablaEl.getAttribute('data-csrf-token') || '';
            this.rucTipoId = parseInt(tablaEl.getAttribute('data-ruc-tipo-id') || '0', 10);

            // Modales Bootstrap 5
            const modalCrearEl = document.getElementById('modalCrearEmpresa');
            if (modalCrearEl) this.modalCrearBs = bootstrap.Modal.getOrCreateInstance(modalCrearEl);

            const modalEditarEl = document.getElementById('modalEditarEmpresa');
            if (modalEditarEl) this.modalEditarBs = bootstrap.Modal.getOrCreateInstance(modalEditarEl);

            const modalFichaEl = document.getElementById('modalFichaEmpresa');
            if (modalFichaEl) this.modalFichaBs = bootstrap.Modal.getOrCreateInstance(modalFichaEl);

            this.inicializarDataTables(tablaEl);
            this.vincularFiltros();
            this.vincularSelect2Personas();
            this.vincularCascadaUbigeo();
            this.vincularConsultaSunat();
            this.vincularFormularios();
            this.vincularMascaras();
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
                language: {
                    search: "Buscar:",
                    lengthMenu: "Mostrar _MENU_ registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ empresas",
                    infoEmpty: "Mostrando 0 a 0 de 0 empresas",
                    infoFiltered: "(filtrado de _MAX_ registros totales)",
                    loadingRecords: "Cargando...",
                    zeroRecords: "No se encontraron empresas coincidentes",
                    emptyTable: "No hay empresas registradas en el sistema",
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
                            self.notificarAlerta('error', 'Acceso denegado', 'No cuenta con el privilegio requerido (empresas.ver).');
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
                    // Columna 1: RUC
                    {
                        data: 'ruc',
                        render: function (data) {
                            return data ? `<span class="text-dark f-w-500 font-monospace">${escaparHtml(data)}</span>` : '<span class="badge text-light-secondary">S/RUC</span>';
                        }
                    },
                    // Columna 2: Razón Social
                    {
                        data: 'razon_social',
                        render: function (data) {
                            return `<span class="text-dark f-w-600">${escaparHtml(data || '—')}</span>`;
                        }
                    },
                    // Columna 3: Nombre Corto / Comercial
                    {
                        data: null,
                        render: function (data, type, row) {
                            let html = `<span class="text-dark f-w-500">${escaparHtml(row.nombre_corto)}</span>`;
                            if (row.nombre_comercial && row.nombre_comercial !== row.nombre_corto) {
                                html += `<br><small class="text-muted f-s-11"><i class="fa-solid fa-store me-1"></i>${escaparHtml(row.nombre_comercial)}</small>`;
                            }
                            return html;
                        }
                    },
                    // Columna 4: Estado
                    {
                        data: 'estado',
                        className: 'text-center',
                        render: function (data) {
                            if (data === 'ACTIVO') {
                                return '<span class="badge text-light-success"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>';
                            }
                            return '<span class="badge text-light-secondary"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo</span>';
                        }
                    },
                    // Columna 5: Creado en
                    {
                        data: 'creado_en',
                        render: function (data) {
                            return `<span class="f-s-12 text-secondary">${formatearFecha(data)}</span>`;
                        }
                    },
                    // Columna 6: Acciones
                    {
                        data: null,
                        className: 'text-center',
                        orderable: false,
                        render: function (data, type, row) {
                            const esActivo = row.estado === 'ACTIVO';
                            const iconoToggle = esActivo ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary';
                            const tituloToggle = esActivo ? 'Inactivar Empresa' : 'Activar Empresa';

                            return `
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-info btn-ver-ficha" data-id="${row.id}" title="Ver Ficha 360°">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-editar-empresa" data-id="${row.id}" data-codigo="${escaparHtml(row.codigo)}" data-nombre-corto="${escaparHtml(row.nombre_corto)}" data-razon-social="${escaparHtml(row.razon_social || '')}" data-ruc="${escaparHtml(row.ruc || '')}" data-estado="${row.estado}" title="Editar Empresa">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-toggle-estado" data-id="${row.id}" data-nombre="${escaparHtml(row.nombre_corto)}" data-estado="${row.estado}" title="${tituloToggle}">
                                        <i class="fa-solid ${iconoToggle}"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ],
                order: [[0, 'asc']]
            });
        },

        /**
         * Vincula los filtros de la barra superior.
         */
        vincularFiltros: function () {
            const self = this;

            const filtroEstado = document.getElementById('filtroEstado');
            if (filtroEstado) {
                filtroEstado.addEventListener('change', function () {
                    self.tabla.ajax.reload(null, false);
                });
            }

            const btnLimpiar = document.getElementById('btnLimpiarFiltros');
            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', function () {
                    if (filtroEstado) filtroEstado.value = '';
                    if (self.tabla) {
                        self.tabla.search('').draw();
                    }
                });
            }

            const btnNueva = document.getElementById('btnNuevaEmpresa');
            if (btnNueva) {
                btnNueva.addEventListener('click', function () {
                    self.abrirModalCrear();
                });
            }
        },

        /**
         * Inicializa Select2 para búsqueda de personas jurídicas disponibles.
         */
        vincularSelect2Personas: function () {
            const self = this;
            const $select = $('#crearPersonaId');
            if (!$select.length) return;

            $select.select2({
                dropdownParent: $('#modalCrearEmpresa'),
                placeholder: 'Buscar por RUC o Razón Social...',
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: self.personasUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            busqueda: params.term || '',
                            limite: 20
                        };
                    },
                    processResults: function (data) {
                        const items = (data && data.datos) ? data.datos : [];
                        return {
                            results: items.map(function (p) {
                                const rucTxt = p.ruc ? `[${p.ruc}] ` : '';
                                const comTxt = p.nombre_comercial ? ` (${p.nombre_comercial})` : '';
                                return {
                                    id: p.id,
                                    text: `${rucTxt}${p.razon_social}${comTxt}`,
                                    personaData: p
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // Reactividad al seleccionar persona jurídica
            $select.on('select2:select', function (e) {
                const data = e.params.data;
                const persona = data.personaData || {};

                const cardPreview = document.getElementById('cardPreviewPersonaVinculada');
                const prevRuc = document.getElementById('previewVincularRuc');
                const prevRazon = document.getElementById('previewVincularRazonSocial');
                const prevComercial = document.getElementById('previewVincularNombreComercial');
                const contComercial = document.getElementById('previewVincularContenedorComercial');

                if (cardPreview) cardPreview.classList.remove('d-none');
                if (prevRuc) prevRuc.textContent = persona.ruc || 'S/D';
                if (prevRazon) prevRazon.textContent = persona.razon_social || '—';

                if (persona.nombre_comercial) {
                    if (prevComercial) prevComercial.textContent = persona.nombre_comercial;
                    if (contComercial) contComercial.classList.remove('d-none');
                } else {
                    if (contComercial) contComercial.classList.add('d-none');
                }

                // Autocompletar sugerencia de nombre corto si está vacío
                const inputNombreCorto = document.getElementById('crearNombreCorto');
                if (inputNombreCorto && !inputNombreCorto.value) {
                    inputNombreCorto.value = persona.nombre_comercial || persona.razon_social || '';
                }
            });

            $select.on('select2:clear', function () {
                const cardPreview = document.getElementById('cardPreviewPersonaVinculada');
                if (cardPreview) cardPreview.classList.add('d-none');
            });
        },

        /**
         * Cascada geográfica UBIGEO de 3 niveles para la pestaña de alta orquestada.
         */
        vincularCascadaUbigeo: function () {
            const self = this;
            const selDep = document.getElementById('orqDepartamentoId');
            const selProv = document.getElementById('orqProvinciaId');
            const selDist = document.getElementById('orqDistritoId');

            if (!selDep || !selProv || !selDist) return;

            selDep.addEventListener('change', async function () {
                const depId = this.value;
                selProv.innerHTML = '<option value="">Cargando provincias...</option>';
                selProv.disabled = true;
                selDist.innerHTML = '<option value="">Seleccione prov...</option>';
                selDist.disabled = true;

                if (!depId) {
                    selProv.innerHTML = '<option value="">Seleccione depto...</option>';
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
                    selProv.innerHTML = opts;
                    selProv.disabled = false;
                } catch {
                    selProv.innerHTML = '<option value="">Error al cargar</option>';
                }
            });

            selProv.addEventListener('change', async function () {
                const provId = this.value;
                selDist.innerHTML = '<option value="">Cargando distritos...</option>';
                selDist.disabled = true;

                if (!provId) {
                    selDist.innerHTML = '<option value="">Seleccione prov...</option>';
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
                    selDist.innerHTML = opts;
                    selDist.disabled = false;
                } catch {
                    selDist.innerHTML = '<option value="">Error al cargar</option>';
                }
            });
        },

        /**
         * Selecciona programáticamente la cascada geográfica UBIGEO por IDs.
         */
        seleccionarJerarquiaUbigeo: async function (depId, provId, distId) {
            const self = this;
            const selDep = document.getElementById('orqDepartamentoId');
            const selProv = document.getElementById('orqProvinciaId');
            const selDist = document.getElementById('orqDistritoId');
            if (!selDep || !selProv || !selDist || !depId) return;

            selDep.value = depId;

            try {
                const resProv = await window.fetch(`${self.provinciasUrl}?departamento_id=${depId}`);
                const jsonProv = await resProv.json();
                const listaProv = jsonProv.datos || [];

                let optsProv = '<option value="">Seleccione provincia...</option>';
                listaProv.forEach(p => {
                    const sel = (String(p.id) === String(provId)) ? 'selected' : '';
                    optsProv += `<option value="${p.id}" ${sel}>${escaparHtml(p.nombre)}</option>`;
                });
                selProv.innerHTML = optsProv;
                selProv.disabled = false;

                if (provId) {
                    const resDist = await window.fetch(`${self.distritosUrl}?provincia_id=${provId}`);
                    const jsonDist = await resDist.json();
                    const listaDist = jsonDist.datos || [];

                    let optsDist = '<option value="">Seleccione distrito...</option>';
                    listaDist.forEach(d => {
                        const sel = (String(d.id) === String(distId)) ? 'selected' : '';
                        optsDist += `<option value="${d.id}" ${sel}>${escaparHtml(d.nombre)}</option>`;
                    });
                    selDist.innerHTML = optsDist;
                    selDist.disabled = false;
                }
            } catch (err) {
                console.warn('[CasaPRO Empresas] Error al seleccionar UBIGEO programático:', err);
            }
        },

        /**
         * Conecta el botón de consulta SUNAT asistida en la modalidad orquestada.
         */
        vincularConsultaSunat: function () {
            const self = this;
            const btn = document.getElementById('btnConsultarSunat');
            const inputRuc = document.getElementById('orqRuc');
            const alerta = document.getElementById('alertaConsultaSunat');
            const spinner = document.getElementById('spinnerConsultarSunat');
            const icono = document.getElementById('iconoConsultarSunat');

            if (!btn || !inputRuc) return;

            btn.addEventListener('click', async function () {
                const ruc = inputRuc.value.trim();
                if (!ruc || ruc.length !== 11) {
                    self.mostrarAlertaLocal(alerta, 'warning', 'Debe ingresar un número de RUC de 11 dígitos.');
                    inputRuc.focus();
                    return;
                }

                if (spinner) spinner.classList.remove('d-none');
                if (icono) icono.classList.add('d-none');
                btn.disabled = true;

                try {
                    let resultado = null;

                    // Si el módulo compartido de consulta existe, usarlo; de lo contrario, fetch nativo
                    if (window.CasaProConsultaDocumento) {
                        resultado = await window.CasaProConsultaDocumento.consultar({
                            tipoDocumentoId: self.rucTipoId,
                            numeroDocumento: ruc,
                            urlEndpoint: self.consultarDocUrl,
                            csrfToken: self.tokenCsrf
                        });
                    } else {
                        const res = await window.fetch(self.consultarDocUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify({
                                tipo_documento_id: self.rucTipoId,
                                numero_documento: ruc
                            })
                        });
                        const json = await res.json();
                        resultado = {
                            exito: res.ok,
                            mensaje: json.mensaje || '',
                            datos: json.datos?.datos || null,
                            personaExistente: json.datos?.persona_existente || null
                        };
                    }

                    if (!resultado.exito) {
                        self.mostrarAlertaLocal(alerta, 'danger', resultado.mensaje || 'No se pudo consultar el RUC.');
                        return;
                    }

                    // Caso A: La persona ya existe registrada en el padrón central
                    if (resultado.personaExistente) {
                        const pExist = resultado.personaExistente;
                        self.mostrarAlertaLocal(
                            alerta,
                            'warning',
                            `La persona jurídica ya existe en el padrón (#${pExist.id}: ${pExist.razon_social || pExist.nombre_completo}). Le recomendamos usar la pestaña "Vincular Persona Existente".`
                        );
                        return;
                    }

                    // Caso B: Consulta SUNAT exitosa con datos civiles
                    const d = resultado.datos;
                    if (d) {
                        const razonInput = document.getElementById('orqRazonSocial');
                        const comInput = document.getElementById('orqNombreComercial');
                        const dirInput = document.getElementById('orqDireccion');
                        const codInput = document.getElementById('orqCodigo');
                        const cortoInput = document.getElementById('orqNombreCorto');

                        if (razonInput && d.razon_social) razonInput.value = d.razon_social;
                        if (comInput && d.nombre_comercial) comInput.value = d.nombre_comercial;
                        if (dirInput && d.direccion) dirInput.value = d.direccion;

                        // Sugerir código corporativo a partir de la razón social o RUC
                        if (codInput && !codInput.value && d.razon_social) {
                            const sugerencia = d.razon_social
                                .toUpperCase()
                                .replace(/[^A-Z0-9]/g, '_')
                                .replace(/_+/g, '_')
                                .substring(0, 20)
                                .replace(/_$/, '');
                            codInput.value = sugerencia;
                        }

                        // Sugerir nombre corto
                        if (cortoInput && !cortoInput.value) {
                            cortoInput.value = d.nombre_comercial || (d.razon_social ? d.razon_social.substring(0, 30) : '');
                        }

                        // Autoseleccionar UBIGEO si vino en los datos
                        if (d.departamento_id) {
                            await self.seleccionarJerarquiaUbigeo(d.departamento_id, d.provincia_id, d.distrito_id);
                        }

                        self.mostrarAlertaLocal(alerta, 'success', `Datos de SUNAT cargados exitosamente: ${d.razon_social || ''}`);
                    } else {
                        self.mostrarAlertaLocal(alerta, 'info', resultado.mensaje || 'Consulta finalizada sin datos adicionales.');
                    }
                } catch (err) {
                    self.mostrarAlertaLocal(alerta, 'danger', 'Ocurrió un error al consultar el servicio de identidad.');
                } finally {
                    if (spinner) spinner.classList.add('d-none');
                    if (icono) icono.classList.remove('d-none');
                    btn.disabled = false;
                }
            });
        },

        /**
         * Muestra una alerta visual dentro de un contenedor local en el modal.
         */
        mostrarAlertaLocal: function (elemento, tipo, mensaje) {
            if (!elemento) return;
            elemento.className = `alert alert-${tipo} py-2 px-3 f-s-12 mt-2`;
            elemento.innerHTML = `<i class="fa-solid fa-${tipo === 'success' ? 'circle-check' : (tipo === 'warning' ? 'triangle-exclamation' : 'circle-exclamation')} me-1"></i> ${escaparHtml(mensaje)}`;
            elemento.classList.remove('d-none');
        },

        /**
         * Vincula máscaras CleaveJS para RUC y Códigos Corporativos.
         */
        vincularMascaras: function () {
            if (typeof Cleave === 'undefined') return;

            const rucEl = document.getElementById('orqRuc');
            if (rucEl) {
                new Cleave(rucEl, {
                    numericOnly: true,
                    blocks: [11]
                });
            }

            const formatCodigo = function (el) {
                el.addEventListener('input', function () {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
                });
            };

            const codCrear = document.getElementById('crearCodigo');
            if (codCrear) formatCodigo(codCrear);

            const codOrq = document.getElementById('orqCodigo');
            if (codOrq) formatCodigo(codOrq);
        },

        /**
         * Configura el envío de los formularios de alta y edición.
         */
        vincularFormularios: function () {
            const self = this;

            // Formulario 1: Alta Vinculada
            const formVinculada = document.getElementById('formCrearEmpresaVinculada');
            if (formVinculada) {
                formVinculada.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const personaId = $('#crearPersonaId').val();
                    const codigo = document.getElementById('crearCodigo')?.value.trim().toUpperCase() || '';
                    const nombreCorto = document.getElementById('crearNombreCorto')?.value.trim() || '';

                    if (!personaId) {
                        self.notificarAlerta('warning', 'Validación requerida', 'Debe seleccionar una persona jurídica activa.');
                        return;
                    }

                    if (!codigo || codigo.length < 3 || codigo.length > 32) {
                        self.notificarAlerta('warning', 'Validación requerida', 'El código de empresa debe tener entre 3 y 32 caracteres.');
                        return;
                    }

                    if (!nombreCorto || nombreCorto.length < 2 || nombreCorto.length > 64) {
                        self.notificarAlerta('warning', 'Validación requerida', 'El nombre corto debe tener entre 2 y 64 caracteres.');
                        return;
                    }

                    const btnSubmit = document.getElementById('btnGuardarVinculada');
                    const spinner = document.getElementById('spinnerGuardarVinculada');
                    const icono = document.getElementById('iconoGuardarVinculada');

                    if (btnSubmit) btnSubmit.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');
                    if (icono) icono.classList.add('d-none');

                    try {
                        const payload = {
                            persona_id: parseInt(personaId, 10),
                            codigo: codigo,
                            nombre_corto: nombreCorto
                        };

                        const res = await window.fetch(self.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify(payload)
                        });

                        const json = await res.json();

                        if (!res.ok) {
                            const errMsj = json.mensaje || 'Error al constituir la empresa.';
                            self.notificarAlerta('error', 'Error en constitución', errMsj);
                            return;
                        }

                        // Cierre de modal y refresco DataTables sin reload de página
                        if (self.modalCrearBs) self.modalCrearBs.hide();
                        formVinculada.reset();
                        $('#crearPersonaId').val(null).trigger('change');
                        document.getElementById('cardPreviewPersonaVinculada')?.classList.add('d-none');

                        self.notificarAlerta('success', 'Empresa constituida', json.mensaje || 'Empresa vinculada exitosamente.', 2000);
                        self.tabla.ajax.reload(null, false);
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnSubmit) btnSubmit.disabled = false;
                        if (spinner) spinner.classList.add('d-none');
                        if (icono) icono.classList.remove('d-none');
                    }
                });
            }

            // Formulario 2: Alta Orquestada
            const formOrquestada = document.getElementById('formCrearEmpresaOrquestada');
            if (formOrquestada) {
                formOrquestada.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const ruc = document.getElementById('orqRuc')?.value.trim() || '';
                    const razonSocial = document.getElementById('orqRazonSocial')?.value.trim() || '';
                    const nombreComercial = document.getElementById('orqNombreComercial')?.value.trim() || null;
                    const fechaConstitucion = document.getElementById('orqFechaConstitucion')?.value || null;
                    const objetoSocial = document.getElementById('orqObjetoSocial')?.value.trim() || null;

                    const tipoDirId = document.getElementById('orqTipoDireccionId')?.value || '';
                    const distId = document.getElementById('orqDistritoId')?.value || '';
                    const direccion = document.getElementById('orqDireccion')?.value.trim() || '';
                    const referencia = document.getElementById('orqReferencia')?.value.trim() || null;
                    const codigoPostal = document.getElementById('orqCodigoPostal')?.value.trim() || null;

                    const tipoContId = document.getElementById('orqTipoContactoId')?.value || '';
                    const valorContacto = document.getElementById('orqContactoValor')?.value.trim() || '';
                    const etiquetaContacto = document.getElementById('orqContactoEtiqueta')?.value.trim() || null;

                    const codigo = document.getElementById('orqCodigo')?.value.trim().toUpperCase() || '';
                    const nombreCorto = document.getElementById('orqNombreCorto')?.value.trim() || '';

                    // Validaciones básicas requeridas
                    if (!ruc || ruc.length !== 11) {
                        self.notificarAlerta('warning', 'Validación', 'El RUC debe tener 11 dígitos.');
                        return;
                    }

                    if (!razonSocial) {
                        self.notificarAlerta('warning', 'Validación', 'La razón social es obligatoria.');
                        return;
                    }

                    if (!codigo || codigo.length < 3 || codigo.length > 32) {
                        self.notificarAlerta('warning', 'Validación', 'El código de empresa debe tener entre 3 y 32 caracteres.');
                        return;
                    }

                    if (!nombreCorto || nombreCorto.length < 2 || nombreCorto.length > 64) {
                        self.notificarAlerta('warning', 'Validación', 'El nombre corto debe tener entre 2 y 64 caracteres.');
                        return;
                    }

                    // Construir payload soberano que cumple con CrearPersonaDTO + CrearEmpresaDTO
                    const documentos = [
                        {
                            tipo_documento_id: self.rucTipoId,
                            numero_documento: ruc,
                            es_principal: 1
                        }
                    ];

                    const direcciones = [];
                    if (direccion) {
                        direcciones.push({
                            tipo_direccion_id: parseInt(tipoDirId, 10) || 1,
                            distrito_id: distId ? parseInt(distId, 10) : null,
                            direccion: direccion,
                            referencia: referencia,
                            codigo_postal: codigoPostal,
                            es_principal: 1
                        });
                    }

                    const contactos = [];
                    if (valorContacto) {
                        contactos.push({
                            tipo_contacto_id: parseInt(tipoContId, 10) || 1,
                            valor: valorContacto,
                            etiqueta: etiquetaContacto,
                            es_principal: 1
                        });
                    }

                    const payload = {
                        codigo: codigo,
                        nombre_corto: nombreCorto,
                        datos_persona: {
                            tipo_persona: 'JURIDICA',
                            razon_social: razonSocial,
                            nombre_comercial: nombreComercial,
                            fecha_constitucion: fechaConstitucion,
                            objeto_social: objetoSocial,
                            documentos: documentos,
                            direcciones: direcciones,
                            contactos: contactos
                        }
                    };

                    const btnSubmit = document.getElementById('btnGuardarOrquestada');
                    const spinner = document.getElementById('spinnerGuardarOrquestada');
                    const icono = document.getElementById('iconoGuardarOrquestada');

                    if (btnSubmit) btnSubmit.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');
                    if (icono) icono.classList.add('d-none');

                    try {
                        const res = await window.fetch(self.apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify(payload)
                        });

                        const json = await res.json();

                        if (!res.ok) {
                            const errMsj = json.mensaje || 'Error al realizar el alta orquestada.';
                            self.notificarAlerta('error', 'Error en alta orquestada', errMsj);
                            return;
                        }

                        // Cierre de modal y refresco DataTables
                        if (self.modalCrearBs) self.modalCrearBs.hide();
                        formOrquestada.reset();
                        document.getElementById('alertaConsultaSunat')?.classList.add('d-none');

                        self.notificarAlerta('success', 'Empresa constituida', json.mensaje || 'Persona Jurídica y Empresa creadas exitosamente.', 2000);
                        self.tabla.ajax.reload(null, false);
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnSubmit) btnSubmit.disabled = false;
                        if (spinner) spinner.classList.add('d-none');
                        if (icono) icono.classList.remove('d-none');
                    }
                });
            }

            // Formulario 3: Edición Inmutable (Solo Nombre Corto)
            const formEditar = document.getElementById('formEditarEmpresa');
            if (formEditar) {
                formEditar.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const empresaId = document.getElementById('editarEmpresaId')?.value;
                    const nombreCorto = document.getElementById('editarNombreCorto')?.value.trim() || '';

                    if (!empresaId) return;

                    if (!nombreCorto || nombreCorto.length < 2 || nombreCorto.length > 64) {
                        self.notificarAlerta('warning', 'Validación', 'El nombre corto debe tener entre 2 y 64 caracteres.');
                        return;
                    }

                    const btnSubmit = document.getElementById('btnActualizarEmpresa');
                    const spinner = document.getElementById('spinnerActualizarEmpresa');
                    const icono = document.getElementById('iconoActualizarEmpresa');

                    if (btnSubmit) btnSubmit.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');
                    if (icono) icono.classList.add('d-none');

                    try {
                        // En estricto apego al principio de inmutabilidad, solo se envía nombre_corto
                        const payload = {
                            nombre_corto: nombreCorto
                        };

                        const urlEditar = `${self.apiUrl}/${encodeURIComponent(empresaId)}`;
                        const res = await window.fetch(urlEditar, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-Token': self.tokenCsrf
                            },
                            body: JSON.stringify(payload)
                        });

                        const json = await res.json();

                        if (!res.ok) {
                            const errMsj = json.mensaje || 'Error al actualizar la empresa.';
                            self.notificarAlerta('error', 'Error en edición', errMsj);
                            return;
                        }

                        if (self.modalEditarBs) self.modalEditarBs.hide();
                        self.notificarAlerta('success', 'Empresa actualizada', json.mensaje || 'Denominación operativa actualizada exitosamente.', 2000);
                        self.tabla.ajax.reload(null, false);
                    } catch (err) {
                        self.notificarAlerta('error', 'Error de red', 'No se pudo comunicar con el servidor.');
                    } finally {
                        if (btnSubmit) btnSubmit.disabled = false;
                        if (spinner) spinner.classList.add('d-none');
                        if (icono) icono.classList.remove('d-none');
                    }
                });
            }
        },

        /**
         * Vincula los botones dinámicos dentro de las filas de DataTables.
         */
        vincularEventosTabla: function () {
            const self = this;
            const tablaEl = document.getElementById('tablaEmpresas');
            if (!tablaEl) return;

            // Delegación de eventos en el cuerpo de la tabla
            tablaEl.addEventListener('click', function (e) {
                // 1. Ver Ficha 360°
                const btnFicha = e.target.closest('.btn-ver-ficha');
                if (btnFicha) {
                    const id = btnFicha.getAttribute('data-id');
                    if (id) self.abrirFichaEmpresa(id);
                    return;
                }

                // 2. Editar Empresa
                const btnEditar = e.target.closest('.btn-editar-empresa');
                if (btnEditar) {
                    const id = btnEditar.getAttribute('data-id');
                    const codigo = btnEditar.getAttribute('data-codigo');
                    const nombreCorto = btnEditar.getAttribute('data-nombre-corto');
                    const razonSocial = btnEditar.getAttribute('data-razon-social');
                    const ruc = btnEditar.getAttribute('data-ruc');
                    const estado = btnEditar.getAttribute('data-estado');

                    self.abrirModalEditar({
                        id: id,
                        codigo: codigo,
                        nombre_corto: nombreCorto,
                        razon_social: razonSocial,
                        ruc: ruc,
                        estado: estado
                    });
                    return;
                }

                // 3. Conmutar Estado (Activar / Inactivar)
                const btnToggle = e.target.closest('.btn-toggle-estado');
                if (btnToggle) {
                    const id = btnToggle.getAttribute('data-id');
                    const nombre = btnToggle.getAttribute('data-nombre');
                    const estadoActual = btnToggle.getAttribute('data-estado');

                    self.conmutarEstadoEmpresa(id, nombre, estadoActual);
                    return;
                }
            });
        },

        /**
         * Abre el modal de creación y resetea pestañas y formularios.
         */
        abrirModalCrear: function () {
            document.getElementById('formCrearEmpresaVinculada')?.reset();
            document.getElementById('formCrearEmpresaOrquestada')?.reset();
            $('#crearPersonaId').val(null).trigger('change');
            document.getElementById('cardPreviewPersonaVinculada')?.classList.add('d-none');
            document.getElementById('alertaConsultaSunat')?.classList.add('d-none');

            // Resetear a primera pestaña
            const tabVincularBtn = document.getElementById('tab-vincular-btn');
            if (tabVincularBtn) {
                bootstrap.Tab.getOrCreateInstance(tabVincularBtn).show();
            }

            if (this.modalCrearBs) {
                this.modalCrearBs.show();
            }
        },

        /**
         * Abre y puebla el modal de edición inmutable.
         */
        abrirModalEditar: function (empresa) {
            const inputId = document.getElementById('editarEmpresaId');
            const inputRuc = document.getElementById('editarRuc');
            const inputRazon = document.getElementById('editarRazonSocial');
            const inputCodigo = document.getElementById('editarCodigo');
            const inputNombreCorto = document.getElementById('editarNombreCorto');
            const divEstado = document.getElementById('editarEstadoPreview');

            if (inputId) inputId.value = empresa.id || '';
            if (inputRuc) inputRuc.value = empresa.ruc || 'S/RUC';
            if (inputRazon) inputRazon.value = empresa.razon_social || '—';
            if (inputCodigo) inputCodigo.value = empresa.codigo || '';
            if (inputNombreCorto) inputNombreCorto.value = empresa.nombre_corto || '';

            if (divEstado) {
                divEstado.innerHTML = (empresa.estado === 'ACTIVO')
                    ? '<span class="badge text-light-success"><i class="fa-solid fa-circle-check me-1"></i>ACTIVO</span>'
                    : '<span class="badge text-light-secondary"><i class="fa-solid fa-circle-xmark me-1"></i>INACTIVO</span>';
            }

            if (this.modalEditarBs) {
                this.modalEditarBs.show();
            }
        },

        /**
         * Conmuta el estado de una empresa mediante PATCH asíncrono con confirmación SweetAlert2.
         */
        conmutarEstadoEmpresa: async function (id, nombre, estadoActual) {
            const self = this;
            const nuevoEstado = (estadoActual === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
            const accionTexto = (nuevoEstado === 'ACTIVO') ? 'activar' : 'inactivar';
            const confirmBtnColor = (nuevoEstado === 'ACTIVO') ? '#0d6efd' : '#dc3545';

            const confirmacion = await Swal.fire({
                title: `¿Desea ${accionTexto} la empresa?`,
                html: `La empresa <strong>${escaparHtml(nombre)}</strong> pasará al estado <strong>${nuevoEstado}</strong>.`,
                icon: nuevoEstado === 'ACTIVO' ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: `Sí, ${accionTexto}`,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: confirmBtnColor,
                cancelButtonColor: '#6c757d'
            });

            if (!confirmacion.isConfirmed) return;

            try {
                const url = `${self.apiUrl}/${encodeURIComponent(id)}/estado`;
                const res = await window.fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': self.tokenCsrf
                    },
                    body: JSON.stringify({ estado: nuevoEstado })
                });

                const json = await res.json();

                if (!res.ok) {
                    const errMsj = json.mensaje || 'No se pudo cambiar el estado de la empresa.';
                    self.notificarAlerta('error', 'Error al cambiar estado', errMsj);
                    return;
                }

                self.notificarAlerta('success', 'Estado modificado', json.mensaje || `Empresa ${nuevoEstado.toLowerCase()} exitosamente.`, 2000);
                self.tabla.ajax.reload(null, false);
            } catch (err) {
                self.notificarAlerta('error', 'Error de red', 'No se pudo comunicar con el servidor.');
            }
        },

        /**
         * Abre la Ficha 360° Integral de la Empresa.
         */
        abrirFichaEmpresa: async function (empresaId) {
            const self = this;
            const spinner = document.getElementById('fichaSpinnerCarga');
            const contenido = document.getElementById('fichaContenidoDetalle');

            if (spinner) spinner.classList.remove('d-none');
            if (contenido) contenido.classList.add('d-none');

            if (this.modalFichaBs) {
                this.modalFichaBs.show();
            }

            try {
                const urlFicha = `${self.apiUrl}/${encodeURIComponent(empresaId)}`;
                const urlColabs = `/api/empresas/${encodeURIComponent(empresaId)}/colaboradores`;

                const [resFicha, resColabs] = await Promise.all([
                    window.fetch(urlFicha, { method: 'GET', headers: { 'Accept': 'application/json' } }),
                    window.fetch(urlColabs, { method: 'GET', headers: { 'Accept': 'application/json' } })
                ]);

                if (!resFicha.ok) {
                    throw new Error(`No se pudo obtener la ficha (HTTP ${resFicha.status}).`);
                }

                const jsonFicha = await resFicha.json();
                const datos = jsonFicha.datos || {};
                self.poblarFichaEmpresa(datos);

                if (resColabs.ok) {
                    const jsonColabs = await resColabs.json();
                    self.poblarColaboradoresEmpresa(jsonColabs.datos?.colaboradores || []);
                } else {
                    self.poblarColaboradoresEmpresa([]);
                }

                if (spinner) spinner.classList.add('d-none');
                if (contenido) contenido.classList.remove('d-none');
            } catch (err) {
                if (spinner) spinner.classList.add('d-none');
                self.notificarAlerta('error', 'Error', err.message || 'Error al cargar la ficha 360°.');
                if (self.modalFichaBs) self.modalFichaBs.hide();
            }
        },

        /**
         * Puebla los paneles de la Ficha 360° de forma veraz y segura.
         */
        poblarFichaEmpresa: function (datos) {
            const empresa = datos.empresa || {};
            const detallePersona = datos.detalle_persona || {};
            const persona = detallePersona.persona || {};
            const juridica = detallePersona.juridica || {};
            const documentos = Array.isArray(detallePersona.documentos) ? detallePersona.documentos : [];
            const direcciones = Array.isArray(detallePersona.direcciones) ? detallePersona.direcciones : [];
            const contactos = Array.isArray(detallePersona.contactos) ? detallePersona.contactos : [];
            const representantes = Array.isArray(detallePersona.representantes) ? detallePersona.representantes : [];

            // 1. Cabecera del modal
            const badgeCod = document.getElementById('fichaBadgeCodigo');
            const badgeEst = document.getElementById('fichaBadgeEstado');
            const txtId = document.getElementById('fichaTextoId');

            if (badgeCod) badgeCod.textContent = empresa.codigo || '—';
            if (badgeEst) {
                badgeEst.className = (empresa.estado === 'ACTIVO') ? 'badge text-light-success' : 'badge text-light-secondary';
                badgeEst.textContent = empresa.estado || '—';
            }
            if (txtId) txtId.textContent = `Empresa ID: #${empresa.id || '—'} | Persona ID: #${empresa.persona_id || '—'}`;

            // 2. Panel 1: Atributos de Empresa
            const gridEmpresa = document.getElementById('fichaGridEmpresa');
            if (gridEmpresa) {
                gridEmpresa.innerHTML = '';
                this.agregarCampoDetalle(gridEmpresa, 'Código Corporativo', empresa.codigo, 4);
                this.agregarCampoDetalle(gridEmpresa, 'Nombre Corto Operativo', empresa.nombre_corto, 4);
                this.agregarCampoDetalle(gridEmpresa, 'Estado de Operación', empresa.estado, 4);
                this.agregarCampoDetalle(gridEmpresa, 'Fecha de Creación', formatearFecha(empresa.creado_en), 6);
                this.agregarCampoDetalle(gridEmpresa, 'Última Actualización', formatearFecha(empresa.actualizado_en), 6);
            }

            // 3. Panel 2: Persona Jurídica
            const gridJuridica = document.getElementById('fichaGridJuridica');
            if (gridJuridica) {
                gridJuridica.innerHTML = '';
                this.agregarCampoDetalle(gridJuridica, 'Razón Social Oficial', juridica.razon_social, 8);
                this.agregarCampoDetalle(gridJuridica, 'Nombre Comercial', juridica.nombre_comercial, 4);
                this.agregarCampoDetalle(gridJuridica, 'Fecha de Constitución', juridica.fecha_constitucion || '—', 4);
                this.agregarCampoDetalle(gridJuridica, 'Estado en Padrón Central', persona.estado, 4);
                this.agregarCampoDetalle(gridJuridica, 'Tipo de Persona', persona.tipo_persona, 4);
                this.agregarCampoDetalle(gridJuridica, 'Objeto Social / Giro', juridica.objeto_social, 12);
            }

            // Documentos
            const contDocs = document.getElementById('fichaContenedorDocumentos');
            if (contDocs) {
                contDocs.innerHTML = '';
                if (documentos.length === 0) {
                    contDocs.innerHTML = '<p class="text-muted f-s-13 mb-0">Sin documentos registrados.</p>';
                } else {
                    let htmlDocs = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Número de Documento</th><th>Principal</th><th>Estado</th></tr></thead><tbody>';
                    documentos.forEach(d => {
                        htmlDocs += `<tr>
                            <td>${escaparHtml(d.tipo_documento_nombre || d.tipo_documento_abreviatura || '—')}</td>
                            <td class="font-monospace f-w-600 text-dark">${escaparHtml(d.numero_documento)}</td>
                            <td>${d.es_principal ? '<span class="badge text-light-success">Sí</span>' : '<span class="badge text-light-secondary">No</span>'}</td>
                            <td>${escaparHtml(d.estado || 'ACTIVO')}</td>
                        </tr>`;
                    });
                    htmlDocs += '</tbody></table>';
                    contDocs.innerHTML = htmlDocs;
                }
            }

            // 4. Panel 3: Domicilios
            const contDir = document.getElementById('fichaContenedorDirecciones');
            if (contDir) {
                contDir.innerHTML = '';
                if (direcciones.length === 0) {
                    contDir.innerHTML = '<p class="text-muted f-s-13 mb-0">No registra domicilios o direcciones fiscales.</p>';
                } else {
                    let htmlDir = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Ubicación Territorial (UBIGEO)</th><th>Dirección Física</th><th>Referencia</th><th>Principal</th></tr></thead><tbody>';
                    direcciones.forEach(d => {
                        const ubigeoTxt = d.ubigeo ? `${d.ubigeo.departamento} / ${d.ubigeo.provincia} / ${d.ubigeo.distrito}` : '—';
                        htmlDir += `<tr>
                            <td>${escaparHtml(d.tipo_direccion ? d.tipo_direccion.nombre : '—')}</td>
                            <td class="f-s-12">${escaparHtml(ubigeoTxt)}</td>
                            <td class="text-dark">${escaparHtml(d.direccion)}</td>
                            <td class="text-muted f-s-12">${escaparHtml(d.referencia || '—')}</td>
                            <td>${d.es_principal ? '<span class="badge text-light-success">Sí</span>' : '<span class="badge text-light-secondary">No</span>'}</td>
                        </tr>`;
                    });
                    htmlDir += '</tbody></table>';
                    contDir.innerHTML = htmlDir;
                }
            }

            // 5. Panel 4: Contactos
            const contContactos = document.getElementById('fichaContenedorContactos');
            if (contContactos) {
                contContactos.innerHTML = '';
                if (contactos.length === 0) {
                    contContactos.innerHTML = '<p class="text-muted f-s-13 mb-0">No registra medios de contacto institucionales.</p>';
                } else {
                    let htmlCont = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Dato de Contacto</th><th>Etiqueta</th><th>Principal</th></tr></thead><tbody>';
                    contactos.forEach(c => {
                        htmlCont += `<tr>
                            <td>${escaparHtml(c.tipo_contacto ? c.tipo_contacto.nombre : '—')}</td>
                            <td class="text-dark f-w-500">${escaparHtml(c.valor)}</td>
                            <td>${escaparHtml(c.etiqueta || '—')}</td>
                            <td>${c.es_principal ? '<span class="badge text-light-success">Sí</span>' : '<span class="badge text-light-secondary">No</span>'}</td>
                        </tr>`;
                    });
                    htmlCont += '</tbody></table>';
                    contContactos.innerHTML = htmlCont;
                }
            }

            // 6. Panel 5: Representantes Legales
            const tabItemRep = document.getElementById('tabItemFichaRepresentacion');
            const contRep = document.getElementById('fichaContenedorRepresentantes');

            if (representantes.length > 0) {
                if (tabItemRep) tabItemRep.style.display = '';
                if (contRep) {
                    contRep.innerHTML = '';
                    let htmlRep = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Representante</th><th>Cargo</th><th>Partida Registral</th><th>Periodo</th><th>Vigente</th></tr></thead><tbody>';
                    representantes.forEach(r => {
                        const periodo = `${r.fecha_inicio || '—'} al ${r.fecha_fin || 'Indefinido'}`;
                        htmlRep += `<tr>
                            <td class="f-w-600 text-dark">${escaparHtml(r.nombre_representante || `Persona #${r.persona_natural_id}`)}</td>
                            <td>${escaparHtml(r.cargo || '—')}</td>
                            <td class="font-monospace">${escaparHtml(r.partida_registral || '—')}</td>
                            <td class="f-s-12">${escaparHtml(periodo)}</td>
                            <td>${r.es_representante_actual ? '<span class="badge text-light-success">Sí</span>' : '<span class="badge text-light-secondary">No</span>'}</td>
                        </tr>`;
                    });
                    htmlRep += '</tbody></table>';
                    contRep.innerHTML = htmlRep;
                }
            } else {
                if (tabItemRep) tabItemRep.style.display = 'none';
            }

            // Activar primera pestaña de la ficha
            const tabEmpresaBtn = document.getElementById('tab-ficha-empresa-btn');
            if (tabEmpresaBtn) {
                bootstrap.Tab.getOrCreateInstance(tabEmpresaBtn).show();
            }
        },

        /**
         * Crea un bloque clave-valor seguro dentro de un contenedor grid.
         */
        agregarCampoDetalle: function (padre, etiqueta, valor, columnas) {
            const col = document.createElement('div');
            col.className = `col-md-${columnas}`;

            const lbl = document.createElement('span');
            lbl.className = 'text-secondary f-s-11 d-block text-uppercase tracking-wider';
            lbl.textContent = etiqueta;

            const val = document.createElement('span');
            val.className = 'text-dark f-w-500 d-block';
            val.textContent = valor || '—';

            col.appendChild(lbl);
            col.appendChild(val);
            padre.appendChild(col);
        },

        /**
         * Despliega la lista de colaboradores asignados a la empresa (vista de consulta).
         */
        poblarColaboradoresEmpresa: function (colaboradores) {
            const tbody = document.getElementById('tbodyFichaColaboradores');
            const badgeTotal = document.getElementById('badgeTotalColaboradoresEmpresa');
            if (!tbody) return;

            const lista = Array.isArray(colaboradores) ? colaboradores : [];
            if (badgeTotal) {
                badgeTotal.textContent = `${lista.length} Asignaciones`;
            }

            if (lista.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted f-s-12">Sin colaboradores asignados a esta empresa.</td></tr>';
                return;
            }

            tbody.innerHTML = '';
            lista.forEach(c => {
                const tr = document.createElement('tr');
                const esActivo = c.estado === 'ACTIVO';
                const badgeEstado = esActivo
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">ACTIVO</span>'
                    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">INACTIVO</span>';

                tr.innerHTML = `
                    <td>
                        <strong class="text-dark f-s-12">${escaparHtml(c.nombre_usuario || '—')}</strong>
                    </td>
                    <td class="text-muted f-s-12">${escaparHtml(c.email || '—')}</td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">${escaparHtml(c.rol_codigo || '')}</span>
                        <span class="text-dark f-s-12 ms-1">${escaparHtml(c.rol_nombre || '')}</span>
                    </td>
                    <td>${badgeEstado}</td>
                    <td class="text-muted f-s-12">${escaparHtml(c.asignado_en || '—')}</td>
                `;
                tbody.appendChild(tr);
            });
        },

        /**
         * Despliega notificaciones SweetAlert2 estándar.
         */
        notificarAlerta: function (icono, titulo, texto, timer = null) {
            if (typeof Swal !== 'undefined') {
                const conf = {
                    icon: icono,
                    title: titulo,
                    text: texto
                };
                if (timer) {
                    conf.timer = timer;
                    conf.showConfirmButton = false;
                }
                Swal.fire(conf);
            } else {
                alert(`${titulo}: ${texto}`);
            }
        }
    };

    window.CasaProEmpresas = MODULO;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            MODULO.init();
        });
    } else {
        MODULO.init();
    }
})();
