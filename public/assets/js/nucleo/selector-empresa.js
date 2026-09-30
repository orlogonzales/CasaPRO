/**
 * CasaPRO — Selector Corporativo de Empresa Activa (Microfase 2D)
 *
 * Excepción Arquitectónica Soberana: Conmutación Global de Ámbito Territorial.
 * - Cero jQuery en código propio (Fetch nativo ES6+).
 * - Protección CSRF mediante cabecera X-CSRF-Token.
 * - Navegación / recarga limpia controlada tras Scope Shift.
 */
document.addEventListener('DOMContentLoaded', () => {
    const contenedor = document.getElementById('contenedorSelectorEmpresa');
    if (!contenedor) return;

    const botonesConmutar = contenedor.querySelectorAll('.btn-conmutar-empresa');
    const inputBuscador = document.getElementById('buscadorEmpresasTopbar');

    // 1. Buscador en caliente en el menú desplegable (> 5 empresas)
    if (inputBuscador) {
        inputBuscador.addEventListener('input', (e) => {
            const termino = e.target.value.toLowerCase().trim();
            const items = contenedor.querySelectorAll('.item-empresa-wrapper');
            items.forEach((item) => {
                const texto = item.textContent.toLowerCase();
                item.style.display = texto.includes(termino) ? '' : 'none';
            });
        });
        inputBuscador.addEventListener('click', (e) => e.stopPropagation());
    }

    // 2. Conmutación en caliente de empresa activa
    botonesConmutar.forEach((boton) => {
        boton.addEventListener('click', async (e) => {
            e.preventDefault();

            // Si ya es la empresa activa, evitar petición redundante
            if (boton.classList.contains('active')) {
                return;
            }

            const nuevaEmpresaId = parseInt(boton.getAttribute('data-empresa-id'), 10);
            const nombreEmpresa = boton.getAttribute('data-empresa-nombre') || 'la empresa seleccionada';

            if (!nuevaEmpresaId || nuevaEmpresaId <= 0) return;

            // Extraer token CSRF del meta tag o input oculto
            let csrfToken = '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                csrfToken = csrfMeta.getAttribute('content') || '';
            }
            if (!csrfToken) {
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                    csrfToken = csrfInput.value;
                }
            }

            // Feedback visual en el botón de cabecera
            const botonDropdown = document.getElementById('dropdownBotonEmpresa');
            const htmlOriginal = botonDropdown ? botonDropdown.innerHTML : '';
            if (botonDropdown) {
                botonDropdown.style.pointerEvents = 'none';
                botonDropdown.innerHTML = `<span class="spinner-border spinner-border-sm text-primary" role="status"></span> <span class="d-none d-md-inline f-s-12 ms-1">Cambiando...</span>`;
            }

            try {
                const respuesta = await window.fetch('/api/contexto/cambiar-empresa', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ empresa_id: nuevaEmpresaId })
                });

                const resultado = await respuesta.json();

                if (respuesta.ok && resultado.estado === 'exito') {
                    // Excepción de Ámbito: Navegación controlada tras cambio global de scope
                    const rutaActual = window.location.pathname;
                    const esRutaInicio = rutaActual === '/' || rutaActual === '/inicio' || rutaActual === '';
                    const destinoNavegacion = esRutaInicio ? null : '/inicio';

                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'success',
                            title: 'Contexto Actualizado',
                            text: `Cambiando a ${nombreEmpresa}...`,
                            timer: 900,
                            showConfirmButton: false,
                            willClose: () => {
                                if (destinoNavegacion) {
                                    window.location.href = destinoNavegacion;
                                } else {
                                    window.location.reload();
                                }
                            }
                        });
                    } else {
                        if (destinoNavegacion) {
                            window.location.href = destinoNavegacion;
                        } else {
                            window.location.reload();
                        }
                    }
                } else {
                    if (botonDropdown) {
                        botonDropdown.style.pointerEvents = '';
                        botonDropdown.innerHTML = htmlOriginal;
                    }

                    const mensajeError = resultado.mensaje || 'No se pudo conmutar el contexto empresarial.';
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'error',
                            title: 'Operación Denegada',
                            text: mensajeError,
                            confirmButtonColor: '#6366f1'
                        }).then(() => {
                            if (respuesta.status === 409 || respuesta.status === 403) {
                                window.location.reload();
                            }
                        });
                    } else {
                        alert(mensajeError);
                    }
                }
            } catch (error) {
                if (botonDropdown) {
                    botonDropdown.style.pointerEvents = '';
                    botonDropdown.innerHTML = htmlOriginal;
                }
                console.error('[CasaPRO] Error al conmutar empresa:', error);
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'No se pudo conectar con el servidor para conmutar la empresa.',
                        confirmButtonColor: '#6366f1'
                    });
                }
            }
        });
    });
});
