/**
 * gestion-menu.js — Módulo interactivo de administración y reordenamiento del Menú.
 * Microfase 1G-3 — CasaPRO.
 * Reutiliza SortableJS oficial de Alina sin dependencias jQuery propias.
 */
(function () {
    'use strict';

    if (window.CasaProGestionMenu) {
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

    const ModuloMenu = {
        contenedor: null,
        apiUrl: '',
        csrfToken: '',
        modalEl: null,
        modalInstancia: null,
        sortableInstancias: [],
        haCambiadoOrden: false,

        iniciar: function () {
            this.contenedor = document.getElementById('contenedorArbolMenu');
            if (!this.contenedor) {
                return;
            }

            this.apiUrl = this.contenedor.getAttribute('data-api-menu-url') || '/api/menu';
            this.csrfToken = this.contenedor.getAttribute('data-csrf-token') || '';

            this.modalEl = document.getElementById('modalMenuOpcion');
            if (this.modalEl && window.bootstrap && window.bootstrap.Modal) {
                this.modalInstancia = new bootstrap.Modal(this.modalEl);
            }

            this.inicializarSortables();
            this.vincularEventos();
        },

        inicializarSortables: function () {
            const self = this;
            if (typeof window.Sortable === 'undefined') {
                console.warn('SortableJS no está cargado.');
                return;
            }

            // Destruir instancias previas si existen
            this.sortableInstancias.forEach(s => {
                try { s.destroy(); } catch (e) {}
            });
            this.sortableInstancias = [];

            const listas = document.querySelectorAll('.nested-sortable');
            listas.forEach(lista => {
                const sortable = new window.Sortable(lista, {
                    group: 'nested-menu',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    handle: '.drag-handle',
                    ghostClass: 'bg-light-primary',
                    chosenClass: 'border-primary',
                    onEnd: function (evt) {
                        self.validarYNotificarMovimiento(evt);
                    }
                });
                self.sortableInstancias.push(sortable);
            });
        },

        validarYNotificarMovimiento: function (evt) {
            // Verificar profundidad máxima permitida (máx 3 niveles: 0, 1 y 2)
            const itemMovido = evt.item;
            const listaDestino = evt.to;
            const nuevoPadreId = listaDestino.getAttribute('data-padre-id') || null;
            const nuevoNivel = parseInt(listaDestino.getAttribute('data-nivel') || '0', 10);

            // Si el item tiene hijos, su profundidad total = nuevoNivel + profundidad relativa de hijos
            const tieneSubListas = itemMovido.querySelectorAll('.nested-sortable .item-menu').length > 0;
            if (nuevoNivel > 2 || (nuevoNivel >= 2 && tieneSubListas)) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Profundidad Excedida',
                        text: 'El sistema permite un máximo de 3 niveles jerárquicos (Raíz 0, Agrupador 1 y Enlace 2). No es posible anidar más profundamente.'
                    });
                }
                // Revertir movimiento
                if (evt.from && evt.item) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                }
                return;
            }

            // Limpiar placeholders vacíos en destino si existen
            const placeholder = listaDestino.querySelector('.placeholder-vacio');
            if (placeholder) {
                placeholder.remove();
            }

            // Habilitar botón de guardar
            this.haCambiadoOrden = true;
            const btnGuardar = document.getElementById('btnGuardarOrden');
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.classList.remove('btn-outline-primary');
                btnGuardar.classList.add('btn-primary');
            }
        },

        serializarArbol: function () {
            const opciones = [];

            function recorrerContenedor(contenedor, padreId) {
                const items = Array.from(contenedor.children).filter(el => el.classList.contains('item-menu'));
                items.forEach((item, index) => {
                    const id = parseInt(item.getAttribute('data-id'), 10);
                    const orden = index + 1;

                    opciones.push({
                        id: id,
                        nuevo_padre_id: padreId,
                        orden: orden
                    });

                    // Buscar contenedor de hijos directos
                    const subContenedor = item.querySelector(':scope > .nested-sortable');
                    if (subContenedor) {
                        recorrerContenedor(subContenedor, id);
                    }
                });
            }

            const contenedorRaiz = document.getElementById('contenedorArbolMenu');
            if (contenedorRaiz) {
                recorrerContenedor(contenedorRaiz, null);
            }

            return opciones;
        },

        guardarReordenamiento: async function () {
            const self = this;
            const btnGuardar = document.getElementById('btnGuardarOrden');
            if (!this.haCambiadoOrden) {
                return;
            }

            const payload = {
                opciones: this.serializarArbol()
            };

            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
            }

            try {
                const res = await fetch(`${self.apiUrl}/reordenar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': self.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();

                if (res.ok && json.estado === 'exito') {
                    self.haCambiadoOrden = false;
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Estructura Actualizada!',
                            text: 'El nuevo orden jerárquico del menú se guardó exitosamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    const msg = json.mensaje || 'Error al guardar el reordenamiento.';
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error de Reordenamiento', text: msg });
                    } else {
                        alert(msg);
                    }
                }
            } catch (err) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo comunicar con el servidor para guardar el orden.' });
                } else {
                    alert('Error de conexión.');
                }
            } finally {
                if (btnGuardar) {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Reordenamiento';
                }
            }
        },

        abrirModalCrear: function (padreId = null) {
            const form = document.getElementById('formMenuOpcion');
            if (form) {
                form.reset();
            }

            document.getElementById('menuOpcionId').value = '';
            document.getElementById('tituloModalMenuOpcion').innerHTML = '<i class="fa-solid fa-bars-staggered me-2"></i>Nueva Opción de Menú';

            // Habilitar campos bloqueados en edición
            const tipoSelect = document.getElementById('tipoSelect');
            tipoSelect.disabled = false;

            const codigoInput = document.getElementById('codigoInput');
            codigoInput.readOnly = false;

            if (padreId !== null) {
                document.getElementById('padreIdSelect').value = String(padreId);
                tipoSelect.value = 'ENLACE';
            } else {
                document.getElementById('padreIdSelect').value = '';
                tipoSelect.value = 'AGRUPADOR';
            }

            this.actualizarCamposPorTipo();
            this.actualizarPreviewIcono();

            if (this.modalInstancia) {
                this.modalInstancia.show();
            }
        },

        abrirModalEditar: async function (id) {
            const self = this;
            try {
                const res = await fetch(`${self.apiUrl}/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();

                if (!res.ok || json.estado !== 'exito') {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo cargar la opción.' });
                    }
                    return;
                }

                const op = json.datos;
                document.getElementById('menuOpcionId').value = op.id;
                document.getElementById('padreIdSelect').value = op.padre_id ? String(op.padre_id) : '';

                const tipoSelect = document.getElementById('tipoSelect');
                tipoSelect.value = op.tipo;
                tipoSelect.disabled = true; // Tipo inmutable en edición para preservar integridad

                const codigoInput = document.getElementById('codigoInput');
                codigoInput.value = op.codigo;
                codigoInput.readOnly = true; // Código inmutable en edición

                document.getElementById('tituloInput').value = op.titulo;
                document.getElementById('iconoInput').value = op.icono || '';
                document.getElementById('rutaInput').value = op.ruta || '';
                document.getElementById('privilegioIdSelect').value = op.privilegio_id ? String(op.privilegio_id) : '';
                document.getElementById('estadoSelect').value = op.estado;
                document.getElementById('ordenInput').value = op.orden;

                document.getElementById('tituloModalMenuOpcion').innerHTML = `<i class="fa-solid fa-pen-to-square me-2"></i>Editar Opción [${escaparHtml(op.codigo)}]`;

                self.actualizarCamposPorTipo();
                self.actualizarPreviewIcono();

                if (self.modalInstancia) {
                    self.modalInstancia.show();
                }
            } catch (e) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al consultar la opción de menú.' });
                }
            }
        },

        actualizarCamposPorTipo: function () {
            const tipo = document.getElementById('tipoSelect').value;
            const contenedorRuta = document.getElementById('contenedorRuta');
            const rutaInput = document.getElementById('rutaInput');

            if (tipo === 'AGRUPADOR') {
                if (contenedorRuta) contenedorRuta.classList.add('d-none');
                if (rutaInput) {
                    rutaInput.removeAttribute('required');
                    rutaInput.value = '';
                }
            } else {
                if (contenedorRuta) contenedorRuta.classList.remove('d-none');
                if (rutaInput) rutaInput.setAttribute('required', 'required');
            }
        },

        actualizarPreviewIcono: function () {
            const preview = document.getElementById('previewIcono');
            const valor = document.getElementById('iconoInput').value.trim();
            if (preview) {
                preview.innerHTML = `<i class="${valor || 'fa-solid fa-folder'} text-primary"></i>`;
            }
        },

        guardarFormulario: async function (e) {
            e.preventDefault();
            const self = this;
            const form = document.getElementById('formMenuOpcion');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const id = document.getElementById('menuOpcionId').value;
            const esEdicion = Boolean(id);

            const btnGuardar = document.getElementById('btnGuardarModal');
            if (btnGuardar) {
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
            }

            const padreIdVal = document.getElementById('padreIdSelect').value;
            const privIdVal = document.getElementById('privilegioIdSelect').value;

            const payload = {
                padre_id: padreIdVal ? parseInt(padreIdVal, 10) : null,
                titulo: document.getElementById('tituloInput').value.trim(),
                icono: document.getElementById('iconoInput').value.trim() || null,
                ruta: document.getElementById('rutaInput').value.trim() || null,
                privilegio_id: privIdVal ? parseInt(privIdVal, 10) : null,
                estado: document.getElementById('estadoSelect').value,
                orden: parseInt(document.getElementById('ordenInput').value || '1', 10)
            };

            if (!esEdicion) {
                payload.codigo = document.getElementById('codigoInput').value.trim().toUpperCase();
                payload.tipo = document.getElementById('tipoSelect').value;
            }

            const url = esEdicion ? `${self.apiUrl}/${id}` : self.apiUrl;
            const metodo = esEdicion ? 'PUT' : 'POST';

            try {
                const res = await fetch(url, {
                    method: metodo,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': self.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();

                if (res.ok && (json.estado === 'exito' || json.codigo === 200 || json.codigo === 201)) {
                    if (self.modalInstancia) {
                        self.modalInstancia.hide();
                    }
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Operación Exitosa!',
                            text: json.mensaje || 'Opción guardada correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    let msg = json.mensaje || 'Error al guardar la opción.';
                    if (json.errores && typeof json.errores === 'object') {
                        msg += '<br>' + Object.values(json.errores).flat().join('<br>');
                    }
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error de Validación', html: msg });
                    } else {
                        alert(msg);
                    }
                }
            } catch (err) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                } else {
                    alert('Error de comunicación con el servidor.');
                }
            } finally {
                if (btnGuardar) {
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Opción';
                }
            }
        },

        cambiarEstado: async function (id, estadoActual) {
            const self = this;
            const nuevoEstado = (estadoActual === 'ACTIVO') ? 'INACTIVO' : 'ACTIVO';
            const accionTexto = (nuevoEstado === 'ACTIVO') ? 'activar' : 'desactivar';

            if (window.Swal) {
                const confirmacion = await Swal.fire({
                    title: `¿Desea ${accionTexto} esta opción?`,
                    text: `La opción pasará al estado ${nuevoEstado}.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: `Sí, ${accionTexto}`,
                    cancelButtonText: 'Cancelar'
                });
                if (!confirmacion.isConfirmed) {
                    return;
                }
            }

            try {
                const res = await fetch(`${self.apiUrl}/${id}/estado`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': self.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ estado: nuevoEstado })
                });

                const json = await res.json();
                if (res.ok && json.estado === 'exito') {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Estado Actualizado',
                            text: json.mensaje,
                            timer: 1200,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'Error al cambiar estado.' });
                    }
                }
            } catch (e) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión con el servidor.' });
                }
            }
        },

        eliminarOpcion: async function (id, titulo, cantidadHijos) {
            const self = this;
            if (cantidadHijos > 0) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Eliminación No Permitida',
                        text: `No es posible eliminar "${titulo}" porque contiene ${cantidadHijos} sub-opciones activas. Reubique o elimine primero sus sub-opciones.`
                    });
                } else {
                    alert('No es posible eliminar un elemento que contiene sub-opciones.');
                }
                return;
            }

            if (window.Swal) {
                const confirmacion = await Swal.fire({
                    title: `¿Eliminar opción "${titulo}"?`,
                    text: 'Esta acción eliminará el registro y se registrará un evento en la auditoría del sistema.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                });
                if (!confirmacion.isConfirmed) {
                    return;
                }
            }

            try {
                const res = await fetch(`${self.apiUrl}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': self.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const json = await res.json();
                if (res.ok && json.estado === 'exito') {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Opción Eliminada',
                            text: json.mensaje,
                            timer: 1200,
                            showConfirmButton: false
                        });
                    }
                    window.location.reload();
                } else {
                    if (window.Swal) {
                        Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: json.mensaje || 'Error al eliminar.' });
                    }
                }
            } catch (e) {
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                }
            }
        },

        vincularEventos: function () {
            const self = this;

            // Botón Crear Raíz
            const btnNueva = document.getElementById('btnNuevaOpcion');
            if (btnNueva) {
                btnNueva.addEventListener('click', () => self.abrirModalCrear(null));
            }

            const btnNuevaVacio = document.getElementById('btnNuevaOpcionVacio');
            if (btnNuevaVacio) {
                btnNuevaVacio.addEventListener('click', () => self.abrirModalCrear(null));
            }

            // Botón Refrescar
            const btnRecargar = document.getElementById('btnRecargarArbol');
            if (btnRecargar) {
                btnRecargar.addEventListener('click', () => window.location.reload());
            }

            // Botón Guardar Reordenamiento
            const btnGuardar = document.getElementById('btnGuardarOrden');
            if (btnGuardar) {
                btnGuardar.addEventListener('click', () => self.guardarReordenamiento());
            }

            // Eventos del formulario Modal
            const form = document.getElementById('formMenuOpcion');
            if (form) {
                form.addEventListener('submit', (e) => self.guardarFormulario(e));
            }

            const tipoSelect = document.getElementById('tipoSelect');
            if (tipoSelect) {
                tipoSelect.addEventListener('change', () => self.actualizarCamposPorTipo());
            }

            const iconoInput = document.getElementById('iconoInput');
            if (iconoInput) {
                iconoInput.addEventListener('input', () => self.actualizarPreviewIcono());
            }

            // Delegación de eventos en el árbol de opciones
            if (this.contenedor) {
                this.contenedor.addEventListener('click', (e) => {
                    const btnHijo = e.target.closest('.btn-agregar-hijo');
                    if (btnHijo) {
                        const padreId = parseInt(btnHijo.getAttribute('data-padre-id'), 10);
                        self.abrirModalCrear(padreId);
                        return;
                    }

                    const btnEditar = e.target.closest('.btn-editar');
                    if (btnEditar) {
                        const id = parseInt(btnEditar.getAttribute('data-id'), 10);
                        self.abrirModalEditar(id);
                        return;
                    }

                    const btnEstado = e.target.closest('.btn-cambiar-estado');
                    if (btnEstado) {
                        const id = parseInt(btnEstado.getAttribute('data-id'), 10);
                        const estadoActual = btnEstado.getAttribute('data-estado');
                        self.cambiarEstado(id, estadoActual);
                        return;
                    }

                    const btnEliminar = e.target.closest('.btn-eliminar');
                    if (btnEliminar) {
                        const id = parseInt(btnEliminar.getAttribute('data-id'), 10);
                        const titulo = btnEliminar.getAttribute('data-titulo') || 'la opción';
                        const hijos = parseInt(btnEliminar.getAttribute('data-hijos') || '0', 10);
                        self.eliminarOpcion(id, titulo, hijos);
                        return;
                    }
                });
            }
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        ModuloMenu.iniciar();
    });

    window.CasaProGestionMenu = ModuloMenu;
})();
