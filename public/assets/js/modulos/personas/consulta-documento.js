/**
 * consulta-documento.js — Módulo cliente reutilizable para consulta documental asistida (DNI / RUC).
 *
 * Microfase 1F — CasaPRO Inmobiliario.
 * Estándares:
 * - JavaScript ES6+ Vanilla, cero dependencias de jQuery para lógica de negocio.
 * - Desacoplamiento total: se comunica exclusivamente con el backend de CasaPRO vía POST.
 * - Anti-duplicidad primero: procesa advertencias cuando la identidad ya existe localmente.
 * - No consulta por keyup: se activa exclusivamente por intención explícita (clic en botón).
 * - Control de abuso y caché en memoria por sesión de formulario para evitar consultas idénticas redundantes.
 * - Reutilizable desde Personas, Clientes, Proveedores, Personal y Titulares.
 */

(function () {
    'use strict';

    if (window.CasaProConsultaDocumento) {
        return;
    }

    /**
     * Memoria caché volátil para evitar re-consultar el mismo documento en la misma sesión.
     * @type {Map<string, object>}
     */
    const cacheConsultas = new Map();

    /**
     * Estado de ejecución en curso para prevenir peticiones simultáneas.
     * @type {boolean}
     */
    let ejecutandoConsulta = false;

    window.CasaProConsultaDocumento = {
        /**
         * Realiza la consulta asistida de un documento al backend de CasaPRO.
         *
         * @param {object} opciones
         * @param {number} opciones.tipoDocumentoId ID del tipo de documento según catálogo.
         * @param {string} opciones.numeroDocumento Número de documento limpio.
         * @param {string} opciones.urlEndpoint URL del endpoint backend (ej. /api/personas/consultar-documento).
         * @param {string} [opciones.csrfToken] Token CSRF (si se omite, se lee del meta tag o input).
         * @returns {Promise<{exito: boolean, estadoConsulta: string, mensaje: string, datos?: object, personaExistente?: object}>}
         */
        consultar: async function (opciones) {
            const { tipoDocumentoId, numeroDocumento, urlEndpoint } = opciones;

            if (!tipoDocumentoId || !numeroDocumento) {
                return {
                    exito: false,
                    estadoConsulta: 'PARAMETROS_INVALIDOS',
                    mensaje: 'Debe especificar el tipo y número de documento para consultar.'
                };
            }

            const docLimpio = String(numeroDocumento).trim().toUpperCase();
            const cacheKey = `${tipoDocumentoId}_${docLimpio}`;

            // 1. Control de duplicación en sesión de formulario
            if (cacheConsultas.has(cacheKey)) {
                return cacheConsultas.get(cacheKey);
            }

            // 2. Control de concurrencia
            if (ejecutandoConsulta) {
                return {
                    exito: false,
                    estadoConsulta: 'OCUPADO',
                    mensaje: 'Ya existe una consulta en procesamiento. Por favor espere.'
                };
            }

            // 3. Resolver token CSRF
            const csrfToken = opciones.csrfToken ||
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                document.querySelector('input[name="_token_csrf"]')?.value ||
                '';

            ejecutandoConsulta = true;

            try {
                const respuesta = await fetch(urlEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        tipo_documento_id: Number(tipoDocumentoId),
                        numero_documento: docLimpio
                    })
                });

                const json = await respuesta.json();

                if (!respuesta.ok) {
                    const mensajeError = json.mensaje || 'Error al procesar la consulta documental.';
                    return {
                        exito: false,
                        estadoConsulta: 'ERROR_SERVIDOR',
                        mensaje: mensajeError,
                        errores: json.errores || null
                    };
                }

                const resultado = json.datos || {};
                const salida = {
                    exito: true,
                    estadoConsulta: resultado.estado_consulta || 'RESPUESTA_OK',
                    mensaje: json.mensaje || resultado.mensaje || 'Consulta finalizada.',
                    datos: resultado.datos || null,
                    personaExistente: resultado.persona_existente || null
                };

                // Almacenar en caché si fue exitosa o duplicado local
                cacheConsultas.set(cacheKey, salida);
                return salida;
            } catch (err) {
                return {
                    exito: false,
                    estadoConsulta: 'ERROR_CONEXION',
                    mensaje: 'No se pudo conectar con el servidor local para realizar la consulta.'
                };
            } finally {
                ejecutandoConsulta = false;
            }
        },

        /**
         * Limpia la memoria caché de consultas (por ejemplo al cerrar o reiniciar un formulario).
         */
        limpiarCache: function () {
            cacheConsultas.clear();
        }
    };
})();
