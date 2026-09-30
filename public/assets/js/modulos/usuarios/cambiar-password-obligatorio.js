/**
 * cambiar-password-obligatorio.js — Lógica de cambio obligatorio o voluntario de contraseña.
 * Microfase 1G-2 — CasaPRO.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('formCambiarPasswordObligatorio');
        if (!form) return;

        const inputActual = document.getElementById('password_actual');
        const inputNuevo = document.getElementById('nuevo_password');
        const inputConfirmar = document.getElementById('confirmar_password');
        const btnActualizar = document.getElementById('btnActualizarPassword');
        const spinner = document.getElementById('spinnerCarga');
        const icono = document.getElementById('iconoActualizar');
        const alertaContenedor = document.getElementById('alertaContenedor');
        const tokenCsrf = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

        // Elementos de requisitos
        const reqLongitud = document.getElementById('req-longitud');
        const reqMayus = document.getElementById('req-mayus');
        const reqMinus = document.getElementById('req-minus');
        const reqNumero = document.getElementById('req-numero');
        const reqSimbolo = document.getElementById('req-simbolo');

        function actualizarIconoReq(elem, valido) {
            if (!elem) return;
            if (valido) {
                elem.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i> ' + elem.textContent.trim();
                elem.className = 'text-success f-w-600';
            } else {
                elem.innerHTML = '<i class="fa-solid fa-circle-xmark text-danger me-1"></i> ' + elem.textContent.trim();
                elem.className = 'text-muted';
            }
        }

        inputNuevo.addEventListener('input', function () {
            const val = inputNuevo.value;
            actualizarIconoReq(reqLongitud, val.length >= 8 && val.length <= 128);
            actualizarIconoReq(reqMayus, /[A-Z]/.test(val));
            actualizarIconoReq(reqMinus, /[a-z]/.test(val));
            actualizarIconoReq(reqNumero, /[0-9]/.test(val));
            actualizarIconoReq(reqSimbolo, /[^a-zA-Z0-9\s]/.test(val));
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            alertaContenedor.innerHTML = '';

            const passwordActual = inputActual.value.trim();
            const nuevoPassword = inputNuevo.value;
            const confirmarPassword = inputConfirmar.value;

            if (!passwordActual || !nuevoPassword || !confirmarPassword) {
                alertaContenedor.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <div>Todos los campos son obligatorios.</div>
                    </div>`;
                return;
            }

            if (nuevoPassword !== confirmarPassword) {
                alertaContenedor.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <div>La nueva contraseña y su confirmación no coinciden.</div>
                    </div>`;
                return;
            }

            btnActualizar.disabled = true;
            spinner.classList.remove('d-none');
            icono.classList.add('d-none');

            try {
                const respuesta = await fetch('/api/mi-cuenta/cambiar-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': tokenCsrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        password_actual: passwordActual,
                        nuevo_password: nuevoPassword,
                        confirmar_password: confirmarPassword
                    })
                });

                const json = await respuesta.json();

                if (respuesta.ok && json.estado === 'exito') {
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: '¡Contraseña Actualizada!',
                            text: 'Su contraseña ha sido renovada exitosamente. Redirigiendo a CasaPRO...',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    window.location.href = '/inicio';
                } else {
                    let mensajeError = json.mensaje || 'No fue posible actualizar su contraseña.';
                    if (json.errores) {
                        const lista = Object.values(json.errores).flat().join('<br>');
                        mensajeError += '<br>' + lista;
                    }
                    alertaContenedor.innerHTML = `
                        <div class="alert alert-danger d-flex align-items-center mb-3">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <div>${mensajeError}</div>
                        </div>`;
                }
            } catch (err) {
                alertaContenedor.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center mb-3">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <div>Error de conexión con el servidor. Intente nuevamente.</div>
                    </div>`;
            } finally {
                btnActualizar.disabled = false;
                spinner.classList.add('d-none');
                icono.classList.remove('d-none');
            }
        });
    });
})();
