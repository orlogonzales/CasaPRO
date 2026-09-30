/**
 * formulario-persona.js — Controlador del modal de Alta y Edición de Personas.
 *
 * Microfase 1F — CasaPRO Inmobiliario.
 * Estándares:
 * - JavaScript ES6+ Vanilla, sin dependencias de jQuery para lógica propia.
 * - Validación frontend declarativa con PristineJS (ADR-009).
 * - Máscaras visuales con CleaveJS (ADR-009).
 * - Componente de fechas Flatpickr nativo de Alina.
 * - Prevención estricta de doble submit con spinner Font Awesome.
 * - En errores HTTP 422: el modal permanece abierto, los datos no se borran y se enfoca el primer error.
 * - Anti-duplicidad integrada mediante CasaProConsultaDocumento.
 */

(function () {
    'use strict';

    if (window.CasaProFormularioPersona) {
        return;
    }

    let modalBs = null;
    let validadorPristine = null;
    let cleaveNumeroDoc = null;
    let catalogos = {};
    let modoOperacion = 'CREAR'; // 'CREAR' | 'EDITAR'
    let personaIdEdicion = null;
    let callbackExito = null;

    // Instancias de Flatpickr
    let fpNacimiento = null;
    let fpConstitucion = null;
    let fpInicioRep = null;

    /**
     * Utilidad para escapar texto HTML.
     */
    function escaparHtml(val) {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Obtiene el token CSRF activo del documento.
     */
    function obtenerCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
               document.querySelector('input[name="_token_csrf"]')?.value || '';
    }

    const MODULO = {
        /**
         * Inicializa componentes, listeners y catálogos.
         */
        init: function (opciones = {}) {
            callbackExito = opciones.onExito || null;

            const modalEl = document.getElementById('modalFormularioPersona');
            if (!modalEl) return;

            modalBs = new bootstrap.Modal(modalEl);

            // 1. Cargar catálogos desde el bloque JSON seguro
            const scriptCat = document.getElementById('datosCatalogosCasaPro');
            if (scriptCat) {
                try {
                    catalogos = JSON.parse(scriptCat.textContent || '{}');
                } catch {
                    catalogos = {};
                }
            }

            // 2. Inicializar Flatpickr en campos de fecha si está disponible
            this.inicializarFlatpickr();

            // 3. Inicializar PristineJS
            this.inicializarPristine();

            // 4. Vincular eventos de UI
            this.vincularConmutadorTipoPersona();
            this.vincularTipoDocumento();
            this.vincularConsultaDocumental();
            this.vincularCascadaUbigeo();
            this.vincularSubmitFormulario();
        },

        /**
         * Inicializa Flatpickr en los inputs de fecha.
         */
        inicializarFlatpickr: function () {
            if (typeof window.flatpickr === 'function') {
                const config = {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                };

                const elNac = document.getElementById('campo_fecha_nacimiento');
                if (elNac) fpNacimiento = window.flatpickr(elNac, config);

                const elConst = document.getElementById('campo_fecha_constitucion');
                if (elConst) fpConstitucion = window.flatpickr(elConst, config);

                const elRep = document.getElementById('campo_rep_fecha_inicio');
                if (elRep) fpInicioRep = window.flatpickr(elRep, config);
            }
        },

        /**
         * Inicializa PristineJS para validación declarativa frontend.
         */
        inicializarPristine: function () {
            const formEl = document.getElementById('formPersona');
            if (!formEl || typeof window.Pristine !== 'function') return;

            validadorPristine = new window.Pristine(formEl, {
                classTo: 'col-md-3, .col-md-4, .col-md-5, .col-md-7, .col-md-8, .col-12',
                errorClass: 'is-invalid',
                successClass: 'is-valid',
                errorTextParent: 'col-md-3, .col-md-4, .col-md-5, .col-md-7, .col-md-8, .col-12',
                errorTextTag: 'div',
                errorTextClass: 'invalid-feedback'
            });
        },

        /**
         * Conmuta dinámicamente los campos visibles según sea Persona Natural o Jurídica.
         */
        vincularConmutadorTipoPersona: function () {
            const self = this;
            const radios = document.querySelectorAll('input[name="selector_tipo_persona"]');
            radios.forEach(radio => {
                radio.addEventListener('change', function () {
                    self.aplicarTipoPersona(this.value);
                });
            });
        },

        /**
         * Aplica los cambios visuales y de obligatoriedad según el tipo de persona.
         */
        aplicarTipoPersona: function (tipo) {
            const tipoUpper = tipo === 'JURIDICA' ? 'JURIDICA' : 'NATURAL';
            document.getElementById('tipo_persona').value = tipoUpper;

            const secNat = document.getElementById('seccionCamposNatural');
            const secJur = document.getElementById('seccionCamposJuridica');
            const tabRep = document.getElementById('tabItemFormRepresentacion');

            const campoNombres = document.getElementById('campo_nombres');
            const campoApPaterno = document.getElementById('campo_apellido_paterno');
            const campoRazonSocial = document.getElementById('campo_razon_social');

            if (tipoUpper === 'NATURAL') {
                secNat.style.display = '';
                secJur.style.display = 'none';
                tabRep.style.display = 'none';

                campoNombres.setAttribute('required', 'required');
                campoApPaterno.setAttribute('required', 'required');
                campoRazonSocial.removeAttribute('required');

                // Si la pestaña activa era Representante, regresar a Datos Principales
                const tabRepBtn = document.getElementById('tab-form-representantes-btn');
                if (tabRepBtn && tabRepBtn.classList.contains('active')) {
                    document.getElementById('tab-form-principales-btn').click();
                }
            } else {
                secNat.style.display = 'none';
                secJur.style.display = '';
                tabRep.style.display = '';

                campoNombres.removeAttribute('required');
                campoApPaterno.removeAttribute('required');
                campoRazonSocial.setAttribute('required', 'required');
            }

            this.limpiarErroresValidacion();
            this.actualizarOpcionesTipoDocumento(tipoUpper);
        },

        /**
         * Filtra o pre-selecciona el tipo de documento idóneo según el tipo de persona.
         */
        actualizarOpcionesTipoDocumento: function (tipoPersona) {
            const selectDoc = document.getElementById('campo_tipo_documento_id');
            if (!selectDoc) return;

            const valorActual = selectDoc.value;
            let primeraOpcionValida = '';

            Array.from(selectDoc.options).forEach(opt => {
                if (!opt.value) return;
                const aplicaA = opt.getAttribute('data-tipo-persona') || 'AMBOS';
                const esCompatible = (aplicaA === 'AMBOS' || aplicaA === tipoPersona);

                opt.style.display = esCompatible ? '' : 'none';
                opt.disabled = !esCompatible;

                if (esCompatible && !primeraOpcionValida) {
                    primeraOpcionValida = opt.value;
                }
            });

            // Si la opción seleccionada ya no es compatible, cambiar a la primera compatible
            const optSeleccionada = selectDoc.selectedOptions[0];
            if (optSeleccionada && optSeleccionada.disabled) {
                selectDoc.value = primeraOpcionValida;
            }

            this.aplicarMascaraDocumento();
        },

        /**
         * Vincula el cambio en el selector de tipo de documento para actualizar CleaveJS y botón Consultar.
         */
        vincularTipoDocumento: function () {
            const selectDoc = document.getElementById('campo_tipo_documento_id');
            if (!selectDoc) return;

            selectDoc.addEventListener('change', () => {
                this.aplicarMascaraDocumento();
            });
        },

        /**
         * Aplica la máscara CleaveJS apropiada al número de documento según el catálogo.
         */
        aplicarMascaraDocumento: function () {
            const selectDoc = document.getElementById('campo_tipo_documento_id');
            const inputDoc = document.getElementById('campo_numero_documento');
            const btnConsultar = document.getElementById('btnConsultarDocumento');
            if (!selectDoc || !inputDoc) return;

            const opt = selectDoc.selectedOptions[0];
            const codigo = opt ? (opt.getAttribute('data-codigo') || '').toUpperCase() : '';

            // Destruir instancia previa de CleaveJS si existe
            if (cleaveNumeroDoc) {
                cleaveNumeroDoc.destroy();
                cleaveNumeroDoc = null;
            }

            if (typeof window.Cleave === 'function') {
                if (codigo === 'DNI') {
                    cleaveNumeroDoc = new window.Cleave(inputDoc, {
                        numericOnly: true,
                        blocks: [8]
                    });
                } else if (codigo === 'RUC') {
                    cleaveNumeroDoc = new window.Cleave(inputDoc, {
                        numericOnly: true,
                        blocks: [11]
                    });
                }
            }

            // Habilitar botón consultar solo para DNI y RUC
            if (btnConsultar) {
                const soportaConsulta = (codigo === 'DNI' || codigo === 'RUC');
                btnConsultar.disabled = !soportaConsulta;
                btnConsultar.title = soportaConsulta ? `Consultar ${codigo} en padrón oficial` : 'Consulta asistida no disponible para este tipo';
            }
        },

        /**
         * Vincula la consulta documental asistida (botón Consultar).
         */
        vincularConsultaDocumental: function () {
            const self = this;
            const btnConsultar = document.getElementById('btnConsultarDocumento');
            if (!btnConsultar) return;

            btnConsultar.addEventListener('click', async function () {
                const selectDoc = document.getElementById('campo_tipo_documento_id');
                const inputDoc = document.getElementById('campo_numero_documento');
                const spinner = document.getElementById('spinnerConsultarDoc');
                const icono = document.getElementById('iconoConsultarDoc');
                const alerta = document.getElementById('alertaConsultaDocumento');

                const tipoDocId = selectDoc ? Number(selectDoc.value) : 0;
                const numeroDoc = inputDoc ? inputDoc.value.trim() : '';

                if (!tipoDocId || !numeroDoc) {
                    self.mostrarAlertaDoc('warning', 'Seleccione un tipo de documento e ingrese el número para consultar.');
                    if (inputDoc) inputDoc.focus();
                    return;
                }

                // Resolver URL de consulta
                const tablaEl = document.getElementById('tablaPersonas');
                const urlConsulta = tablaEl?.getAttribute('data-api-consultar-doc-url') || '/api/personas/consultar-documento';

                // Estado cargando
                btnConsultar.disabled = true;
                if (spinner) spinner.classList.remove('d-none');
                if (icono) icono.classList.add('d-none');
                if (alerta) alerta.classList.add('d-none');

                try {
                    const res = await window.CasaProConsultaDocumento.consultar({
                        tipoDocumentoId: tipoDocId,
                        numeroDocumento: numeroDoc,
                        urlEndpoint: urlConsulta,
                        csrfToken: obtenerCsrfToken()
                    });

                    // Caso 1: DUPLICADO LOCAL (Regla de Oro Anti-Duplicidad)
                    if (res.estadoConsulta === 'DUPLICADO_LOCAL' && res.personaExistente) {
                        const persona = res.personaExistente;
                        self.mostrarAlertaDoc('danger', `<strong>Atención:</strong> ${escaparHtml(res.mensaje)}`);

                        if (typeof window.Swal === 'function') {
                            window.Swal.fire({
                                icon: 'warning',
                                title: 'Persona ya Registrada',
                                html: `El documento consultado ya pertenece a:<br><strong>${escaparHtml(persona.nombre_completo)}</strong><br><span class="badge ${persona.estado === 'ACTIVO' ? 'bg-success' : 'bg-secondary'} mt-2">${escaparHtml(persona.estado)}</span>`,
                                showCancelButton: true,
                                confirmButtonText: '<i class="fa-solid fa-eye me-1"></i> Ver Ficha',
                                cancelButtonText: 'Cerrar',
                                confirmButtonColor: '#0d6efd'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    modalBs.hide();
                                    const evt = new CustomEvent('casapro:ver-ficha', { detail: { id: persona.id } });
                                    document.dispatchEvent(evt);
                                }
                            });
                        }
                        return;
                    }

                    // Caso 2: ENCONTRADO EN PADRÓN
                    if (res.estadoConsulta === 'ENCONTRADO' && res.datos) {
                        self.autocompletarDesdePadrón(res.datos);
                        self.mostrarAlertaDoc('success', `<i class="fa-solid fa-circle-check me-1"></i> ${escaparHtml(res.mensaje)}`);
                        return;
                    }

                    // Caso 3: NO ENCONTRADO o NO DISPONIBLE (Fallback manual)
                    if (res.estadoConsulta === 'NO_ENCONTRADO') {
                        self.mostrarAlertaDoc('warning', `<i class="fa-solid fa-triangle-exclamation me-1"></i> ${escaparHtml(res.mensaje)} Ingrese los datos manualmente.`);
                    } else {
                        self.mostrarAlertaDoc('info', `<i class="fa-solid fa-info-circle me-1"></i> ${escaparHtml(res.mensaje)}`);
                    }
                } finally {
                    btnConsultar.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                    if (icono) icono.classList.remove('d-none');
                }
            });
        },

        /**
         * Muestra una alerta en el panel de documentos.
         */
        mostrarAlertaDoc: function (tipo, htmlMensaje) {
            const alerta = document.getElementById('alertaConsultaDocumento');
            if (!alerta) return;

            alerta.className = `alert alert-${tipo} py-2 px-3 f-s-13 mb-4`;
            alerta.innerHTML = htmlMensaje;
            alerta.classList.remove('d-none');
        },

        /**
         * Autocompleta los campos del formulario con los datos recuperados de la consulta oficial.
         */
        autocompletarDesdePadrón: function (datos) {
            const tipoActual = document.getElementById('tipo_persona').value;

            if (tipoActual === 'NATURAL') {
                if (datos.nombres) document.getElementById('campo_nombres').value = datos.nombres;
                if (datos.apellido_paterno) document.getElementById('campo_apellido_paterno').value = datos.apellido_paterno;
                if (datos.apellido_materno) document.getElementById('campo_apellido_materno').value = datos.apellido_materno;
            } else {
                if (datos.razon_social) document.getElementById('campo_razon_social').value = datos.razon_social;
                if (datos.direccion) document.getElementById('campo_direccion').value = datos.direccion;

                // Correlacionar UBIGEO si vino desde el registro tributario
                if (datos.ubigeo_local) {
                    const u = datos.ubigeo_local;
                    this.seleccionarJerarquiaUbigeo(u.departamento_id, u.provincia_id, u.distrito_id);
                }
            }
        },

        /**
         * Cascada geográfica dependiente: Departamento -> Provincia -> Distrito.
         */
        vincularCascadaUbigeo: function () {
            const self = this;
            const selDep = document.getElementById('campo_departamento_id');
            const selProv = document.getElementById('campo_provincia_id');
            const selDist = document.getElementById('campo_distrito_id');

            if (!selDep || !selProv || !selDist) return;

            const tablaEl = document.getElementById('tablaPersonas');
            const urlProvincias = tablaEl?.getAttribute('data-api-provincias-url') || '/api/ubigeo/provincias';
            const urlDistritos = tablaEl?.getAttribute('data-api-distritos-url') || '/api/ubigeo/distritos';

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
                    const res = await fetch(`${urlProvincias}?departamento_id=${depId}`);
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
                    const res = await fetch(`${urlDistritos}?provincia_id=${provId}`);
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
         * Selecciona programáticamente la cascada UBIGEO (útil para edición y autocompletado de RUC).
         */
        seleccionarJerarquiaUbigeo: async function (depId, provId, distId) {
            const selDep = document.getElementById('campo_departamento_id');
            const selProv = document.getElementById('campo_provincia_id');
            const selDist = document.getElementById('campo_distrito_id');
            if (!selDep || !selProv || !selDist || !depId) return;

            selDep.value = depId;

            const tablaEl = document.getElementById('tablaPersonas');
            const urlProvincias = tablaEl?.getAttribute('data-api-provincias-url') || '/api/ubigeo/provincias';
            const urlDistritos = tablaEl?.getAttribute('data-api-distritos-url') || '/api/ubigeo/distritos';

            try {
                const resProv = await fetch(`${urlProvincias}?departamento_id=${depId}`);
                const jsonProv = await resProv.json();
                let optsProv = '<option value="">Seleccione provincia...</option>';
                (jsonProv.datos || []).forEach(p => {
                    optsProv += `<option value="${p.id}">${escaparHtml(p.nombre)}</option>`;
                });
                selProv.innerHTML = optsProv;
                selProv.disabled = false;

                if (provId) {
                    selProv.value = provId;
                    const resDist = await fetch(`${urlDistritos}?provincia_id=${provId}`);
                    const jsonDist = await resDist.json();
                    let optsDist = '<option value="">Seleccione distrito...</option>';
                    (jsonDist.datos || []).forEach(d => {
                        optsDist += `<option value="${d.id}">${escaparHtml(d.nombre)}</option>`;
                    });
                    selDist.innerHTML = optsDist;
                    selDist.disabled = false;

                    if (distId) {
                        selDist.value = distId;
                    }
                }
            } catch (e) {
                // Falla silenciosa si no responde el endpoint auxiliar
            }
        },

        /**
         * Vincula el evento submit del formulario asíncrono.
         */
        vincularSubmitFormulario: function () {
            const self = this;
            const formEl = document.getElementById('formPersona');
            if (!formEl) return;

            formEl.addEventListener('submit', async function (e) {
                e.preventDefault();

                // 1. Validación frontend PristineJS
                if (validadorPristine && !validadorPristine.validate()) {
                    self.enfocarPrimerError();
                    return;
                }

                // 2. Estado de carga y prevención de doble submit
                const btnSubmit = document.getElementById('btnGuardarPersona');
                const spinner = document.getElementById('spinnerGuardarPersona');
                const icono = document.getElementById('iconoGuardarPersona');
                const texto = document.getElementById('textoBtnGuardarPersona');

                btnSubmit.disabled = true;
                if (spinner) spinner.classList.remove('d-none');
                if (icono) icono.classList.add('d-none');
                if (texto) texto.textContent = ' Guardando...';

                // 3. Ensamblar payload estricto alineado a los DTOs de CasaPRO
                const payload = self.construirPayload();
                const metodo = modoOperacion === 'CREAR' ? 'POST' : 'PUT';

                const tablaEl = document.getElementById('tablaPersonas');
                const urlBase = tablaEl?.getAttribute('data-api-personas-url') || '/api/personas';
                const urlEndpoint = modoOperacion === 'CREAR' ? urlBase : `${urlBase}/${personaIdEdicion}`;

                try {
                    const respuesta = await fetch(urlEndpoint, {
                        method: metodo,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': obtenerCsrfToken()
                        },
                        body: JSON.stringify(payload)
                    });

                    const json = await respuesta.json();

                    if (!respuesta.ok) {
                        // REGLA INVIOLABLE: ANTE HTTP 422 EL MODAL PERMANECE ABIERTO
                        if (respuesta.status === 422 && json.errores) {
                            self.mapearErroresBackend(json.errores);
                        } else {
                            const msg = json.mensaje || 'Ocurrió un error al procesar la operación.';
                            if (typeof window.Swal === 'function') {
                                window.Swal.fire({ icon: 'error', title: 'Error', text: msg });
                            } else {
                                alert(msg);
                            }
                        }
                        return;
                    }

                    // ÉXITO: cerrar modal, resetear y notificar
                    modalBs.hide();
                    self.limpiarFormulario();

                    const mensajeExito = json.mensaje || (modoOperacion === 'CREAR' ? 'Persona creada exitosamente.' : 'Persona actualizada exitosamente.');
                    if (typeof window.Swal === 'function') {
                        window.Swal.fire({
                            icon: 'success',
                            title: '¡Operación Exitosa!',
                            text: mensajeExito,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }

                    if (typeof callbackExito === 'function') {
                        callbackExito(json.datos);
                    }
                } catch (err) {
                    if (typeof window.Swal === 'function') {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Error de Conexión',
                            text: 'No se pudo establecer comunicación con el servidor. Intente nuevamente.'
                        });
                    }
                } finally {
                    btnSubmit.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                    if (icono) icono.classList.remove('d-none');
                    if (texto) texto.textContent = (modoOperacion === 'CREAR' ? 'Guardar Persona' : 'Actualizar Persona');
                }
            });
        },

        /**
         * Construye el payload exacto según CrearPersonaDTO y ActualizarPersonaDTO.
         */
        construirPayload: function () {
            const tipo = document.getElementById('tipo_persona').value;
            const notas = document.getElementById('campo_notas').value.trim();

            const payload = {};
            if (modoOperacion === 'CREAR') {
                payload.tipo_persona = tipo;
            }
            payload.notas = notas !== '' ? notas : null;

            if (tipo === 'NATURAL') {
                payload.nombres = document.getElementById('campo_nombres').value.trim();
                payload.apellido_paterno = document.getElementById('campo_apellido_paterno').value.trim();
                const apMat = document.getElementById('campo_apellido_materno').value.trim();
                payload.apellido_materno = apMat !== '' ? apMat : null;

                const fn = document.getElementById('campo_fecha_nacimiento').value.trim();
                payload.fecha_nacimiento = fn !== '' ? fn : null;

                const sexoId = document.getElementById('campo_sexo_id').value;
                payload.sexo_id = sexoId ? Number(sexoId) : null;

                const ecId = document.getElementById('campo_estado_civil_id').value;
                payload.estado_civil_id = ecId ? Number(ecId) : null;

                const prof = document.getElementById('campo_profesion_ocupacion').value.trim();
                payload.profesion_ocupacion = prof !== '' ? prof : null;
            } else {
                payload.razon_social = document.getElementById('campo_razon_social').value.trim();
                const nc = document.getElementById('campo_nombre_comercial').value.trim();
                payload.nombre_comercial = nc !== '' ? nc : null;

                const fc = document.getElementById('campo_fecha_constitucion').value.trim();
                payload.fecha_constitucion = fc !== '' ? fc : null;

                const os = document.getElementById('campo_objeto_social').value.trim();
                payload.objeto_social = os !== '' ? os : null;
            }

            // Documento Principal
            const tipoDocId = document.getElementById('campo_tipo_documento_id').value;
            const numDoc = document.getElementById('campo_numero_documento').value.trim();
            if (tipoDocId && numDoc) {
                payload.documentos = [{
                    tipo_documento_id: Number(tipoDocId),
                    numero_documento: numDoc,
                    es_principal: document.getElementById('campo_doc_es_principal').checked ? 1 : 0
                }];
            } else if (modoOperacion === 'EDITAR') {
                // En edición si no se modificó, no sobreescribir la colección
                // payload.documentos se omite para preservar
            }

            // Contacto Principal
            const tipoConId = document.getElementById('campo_tipo_contacto_id').value;
            const valorCon = document.getElementById('campo_contacto_valor').value.trim();
            if (tipoConId && valorCon) {
                payload.contactos = [{
                    tipo_contacto_id: Number(tipoConId),
                    valor: valorCon,
                    etiqueta: document.getElementById('campo_contacto_etiqueta').value.trim() || null,
                    es_principal: 1
                }];
            }

            // Domicilio Principal
            const tipoDirId = document.getElementById('campo_tipo_direccion_id').value;
            const dirTexto = document.getElementById('campo_direccion').value.trim();
            if (tipoDirId && dirTexto) {
                const distId = document.getElementById('campo_distrito_id').value;
                payload.direcciones = [{
                    tipo_direccion_id: Number(tipoDirId),
                    distrito_id: distId ? Number(distId) : null,
                    direccion: dirTexto,
                    referencia: document.getElementById('campo_referencia').value.trim() || null,
                    codigo_postal: document.getElementById('campo_codigo_postal').value.trim() || null,
                    es_principal: 1
                }];
            }

            // Representante Legal (Solo Jurídicas)
            if (tipo === 'JURIDICA') {
                const repPersonaId = document.getElementById('campo_rep_persona_id').value;
                const repCargo = document.getElementById('campo_rep_cargo').value.trim();
                if (repPersonaId && repCargo) {
                    payload.representantes = [{
                        persona_natural_id: Number(repPersonaId),
                        cargo: repCargo,
                        partida_registral: document.getElementById('campo_rep_partida').value.trim() || null,
                        fecha_inicio: document.getElementById('campo_rep_fecha_inicio').value.trim() || new Date().toISOString().slice(0, 10),
                        es_representante_actual: 1
                    }];
                }
            }

            return payload;
        },

        /**
         * Mapea los errores devueltos por el backend (HTTP 422) a los campos del formulario.
         */
        mapearErroresBackend: function (errores) {
            this.limpiarErroresValidacion();

            let primerCampoInvalido = null;

            for (const [campo, mensajes] of Object.entries(errores)) {
                const textoError = Array.isArray(mensajes) ? mensajes.join(' ') : String(mensajes);
                let inputEl = document.querySelector(`[name="${campo}"]`) ||
                              document.getElementById(`campo_${campo}`);

                // Mapeo para colecciones o nombres alternativos
                if (!inputEl) {
                    if (campo.includes('documentos')) inputEl = document.getElementById('campo_numero_documento');
                    else if (campo.includes('contactos')) inputEl = document.getElementById('campo_contacto_valor');
                    else if (campo.includes('direcciones')) inputEl = document.getElementById('campo_direccion');
                    else if (campo.includes('representantes')) inputEl = document.getElementById('campo_rep_cargo');
                }

                if (inputEl) {
                    inputEl.classList.add('is-invalid');
                    let parent = inputEl.closest('.col-md-3, .col-md-4, .col-md-5, .col-md-7, .col-md-8, .col-12');
                    let feedback = parent ? parent.querySelector('.invalid-feedback') : null;
                    if (feedback) {
                        feedback.textContent = textoError;
                        feedback.style.display = 'block';
                    }
                    if (!primerCampoInvalido) {
                        primerCampoInvalido = inputEl;
                    }
                }
            }

            if (primerCampoInvalido) {
                this.activarPestanaDeElemento(primerCampoInvalido);
                primerCampoInvalido.focus();
            }
        },

        /**
         * Activa automáticamente la pestaña del modal donde reside un elemento.
         */
        activarPestanaDeElemento: function (el) {
            const pane = el.closest('.tab-pane');
            if (pane && !pane.classList.contains('active')) {
                const paneId = pane.id;
                const tabBtn = document.querySelector(`[data-bs-target="#${paneId}"]`);
                if (tabBtn) tabBtn.click();
            }
        },

        /**
         * Enfoca el primer campo inválido tras validación PristineJS.
         */
        enfocarPrimerError: function () {
            const primerInvalido = document.querySelector('#formPersona .is-invalid');
            if (primerInvalido) {
                this.activarPestanaDeElemento(primerInvalido);
                primerInvalido.focus();
            }
        },

        /**
         * Limpia las clases y mensajes de validación.
         */
        limpiarErroresValidacion: function () {
            document.querySelectorAll('#formPersona .is-invalid').forEach(el => el.classList.remove('is-invalid'));
            document.querySelectorAll('#formPersona .is-valid').forEach(el => el.classList.remove('is-valid'));
            document.querySelectorAll('#formPersona .invalid-feedback').forEach(el => {
                el.style.display = '';
            });
            const alerta = document.getElementById('alertaConsultaDocumento');
            if (alerta) alerta.classList.add('d-none');
        },

        /**
         * Limpia el formulario completo.
         */
        limpiarFormulario: function () {
            const form = document.getElementById('formPersona');
            if (form) form.reset();

            this.limpiarErroresValidacion();

            if (fpNacimiento) fpNacimiento.clear();
            if (fpConstitucion) fpConstitucion.clear();
            if (fpInicioRep) fpInicioRep.clear();

            document.getElementById('persona_id').value = '';
            document.getElementById('campo_departamento_id').value = '';
            document.getElementById('campo_provincia_id').innerHTML = '<option value="">Seleccione depto...</option>';
            document.getElementById('campo_provincia_id').disabled = true;
            document.getElementById('campo_distrito_id').innerHTML = '<option value="">Seleccione prov...</option>';
            document.getElementById('campo_distrito_id').disabled = true;

            // Restablecer a Pestaña 1
            const btnTab1 = document.getElementById('tab-form-principales-btn');
            if (btnTab1) btnTab1.click();
        },

        /**
         * Abre el modal en modo Creación.
         */
        abrirCrear: function () {
            modoOperacion = 'CREAR';
            personaIdEdicion = null;

            this.limpiarFormulario();

            // Configurar títulos y visibilidad
            document.getElementById('textoTituloModal').textContent = 'Registrar Nueva Persona';
            document.getElementById('grupoSelectorTipoPersona').classList.remove('d-none');
            document.getElementById('badgeTipoPersonaEdicion').classList.add('d-none');
            document.getElementById('tipoRadioNatural').checked = true;

            this.aplicarTipoPersona('NATURAL');
            modalBs.show();
        },

        /**
         * Abre el modal en modo Edición cargando los datos 360 desde el servidor.
         */
        abrirEditar: async function (id) {
            modoOperacion = 'EDITAR';
            personaIdEdicion = id;

            this.limpiarFormulario();

            document.getElementById('textoTituloModal').textContent = 'Editar Persona';
            document.getElementById('grupoSelectorTipoPersona').classList.add('d-none');
            const badgeEdicion = document.getElementById('badgeTipoPersonaEdicion');
            badgeEdicion.classList.remove('d-none');
            badgeEdicion.textContent = 'Cargando...';

            modalBs.show();

            const tablaEl = document.getElementById('tablaPersonas');
            const urlBase = tablaEl?.getAttribute('data-api-personas-url') || '/api/personas';

            try {
                const res = await fetch(`${urlBase}/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                if (!res.ok) {
                    alert('No se pudo cargar la información de la persona.');
                    modalBs.hide();
                    return;
                }

                const d = json.datos || {};
                const p = d.persona || {};
                const tipo = p.tipo_persona || 'NATURAL';

                badgeEdicion.textContent = tipo === 'NATURAL' ? 'PERSONA NATURAL' : 'PERSONA JURÍDICA';
                badgeEdicion.className = `chip ${tipo === 'NATURAL' ? 'bg-light-primary text-primary' : 'bg-light-info text-info'}`;

                document.getElementById('persona_id').value = p.id;
                document.getElementById('campo_notas').value = p.notas || '';

                this.aplicarTipoPersona(tipo);

                // Poblar según tipo
                if (tipo === 'NATURAL' && d.natural) {
                    const nat = d.natural;
                    document.getElementById('campo_nombres').value = nat.nombres || '';
                    document.getElementById('campo_apellido_paterno').value = nat.apellido_paterno || '';
                    document.getElementById('campo_apellido_materno').value = nat.apellido_materno || '';
                    if (fpNacimiento && nat.fecha_nacimiento) fpNacimiento.setDate(nat.fecha_nacimiento);
                    if (nat.sexo_id) document.getElementById('campo_sexo_id').value = nat.sexo_id;
                    if (nat.estado_civil_id) document.getElementById('campo_estado_civil_id').value = nat.estado_civil_id;
                    document.getElementById('campo_profesion_ocupacion').value = nat.profesion_ocupacion || '';
                } else if (tipo === 'JURIDICA' && d.juridica) {
                    const jur = d.juridica;
                    document.getElementById('campo_razon_social').value = jur.razon_social || '';
                    document.getElementById('campo_nombre_comercial').value = jur.nombre_comercial || '';
                    if (fpConstitucion && jur.fecha_constitucion) fpConstitucion.setDate(jur.fecha_constitucion);
                    document.getElementById('campo_objeto_social').value = jur.objeto_social || '';
                }

                // Poblar Documento principal si existe
                if (Array.isArray(d.documentos) && d.documentos.length > 0) {
                    const docPrinc = d.documentos.find(doc => Number(doc.es_principal) === 1) || d.documentos[0];
                    if (docPrinc) {
                        document.getElementById('campo_tipo_documento_id').value = docPrinc.tipo_documento_id;
                        document.getElementById('campo_numero_documento').value = docPrinc.numero_documento;
                        document.getElementById('campo_doc_es_principal').checked = Number(docPrinc.es_principal) === 1;
                        this.aplicarMascaraDocumento();
                    }
                }

                // Poblar Contacto principal si existe
                if (Array.isArray(d.contactos) && d.contactos.length > 0) {
                    const conPrinc = d.contactos.find(c => Number(c.es_principal) === 1) || d.contactos[0];
                    if (conPrinc) {
                        document.getElementById('campo_tipo_contacto_id').value = conPrinc.tipo_contacto_id;
                        document.getElementById('campo_contacto_valor').value = conPrinc.valor;
                        document.getElementById('campo_contacto_etiqueta').value = conPrinc.etiqueta || '';
                    }
                }

                // Poblar Domicilio principal si existe
                if (Array.isArray(d.direcciones) && d.direcciones.length > 0) {
                    const dirPrinc = d.direcciones.find(dir => Number(dir.es_principal) === 1) || d.direcciones[0];
                    if (dirPrinc) {
                        document.getElementById('campo_tipo_direccion_id').value = dirPrinc.tipo_direccion_id;
                        document.getElementById('campo_direccion').value = dirPrinc.direccion;
                        document.getElementById('campo_referencia').value = dirPrinc.referencia || '';
                        document.getElementById('campo_codigo_postal').value = dirPrinc.codigo_postal || '';

                        // Si tiene distrito_id, cargar jerarquía
                        if (dirPrinc.distrito_id) {
                            const jerarquia = dirPrinc.jerarquia_ubigeo;
                            if (jerarquia) {
                                this.seleccionarJerarquiaUbigeo(jerarquia.departamento_id, jerarquia.provincia_id, jerarquia.distrito_id);
                            }
                        }
                    }
                }

                // Poblar Representante si aplica
                if (tipo === 'JURIDICA' && Array.isArray(d.representantes) && d.representantes.length > 0) {
                    const repPrinc = d.representantes[0];
                    if (repPrinc) {
                        document.getElementById('campo_rep_persona_id').value = repPrinc.persona_natural_id;
                        document.getElementById('campo_rep_cargo').value = repPrinc.cargo || '';
                        document.getElementById('campo_rep_partida').value = repPrinc.partida_registral || '';
                        if (fpInicioRep && repPrinc.fecha_inicio) fpInicioRep.setDate(repPrinc.fecha_inicio);
                    }
                }
            } catch (err) {
                alert('Ocurrió un error al cargar la información de la persona.');
                modalBs.hide();
            }
        }
    };

    window.CasaProFormularioPersona = MODULO;
})();
