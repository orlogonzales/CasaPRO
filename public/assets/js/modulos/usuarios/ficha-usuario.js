/**
 * ficha-usuario.js — Lógica de interacciones en la Ficha de Seguridad y Acceso.
 * Microfase 1G-2 — CasaPRO.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Desbloquear desde Ficha
        const btnDesbloquear = document.getElementById('btnFichaDesbloquear');
        if (btnDesbloquear) {
            btnDesbloquear.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');

                const { value: motivo } = await Swal.fire({
                    title: 'Desbloquear Cuenta',
                    input: 'textarea',
                    inputLabel: 'Motivo del desbloqueo (obligatorio, mín. 5 caracteres)',
                    inputPlaceholder: 'Ingrese la justificación para auditoría...',
                    inputAttributes: { minlength: '5' },
                    showCancelButton: true,
                    confirmButtonText: 'Desbloquear',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#28a745',
                    inputValidator: (val) => {
                        if (!val || val.trim().length < 5) {
                            return 'Debe ingresar un motivo de al menos 5 caracteres.';
                        }
                    }
                });

                if (motivo) {
                    try {
                        const res = await fetch(`/api/usuarios/${id}/desbloquear`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ motivo: motivo.trim() })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            await Swal.fire({ icon: 'success', title: 'Cuenta Desbloqueada', text: 'El usuario ha sido desbloqueado exitosamente.' });
                            window.location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No fue posible desbloquear.' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                    }
                }
            });
        }

        // Cambiar Estado desde Ficha
        const btnEstado = document.getElementById('btnFichaCambiarEstado');
        if (btnEstado) {
            btnEstado.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');
                const estadoActual = this.getAttribute('data-estado');
                const nuevoEstado = estadoActual === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';

                const { value: formValues } = await Swal.fire({
                    title: `Cambiar estado a ${nuevoEstado}`,
                    html: `
                        <p class="text-secondary f-s-13">Esta acción modificará el estado administrativo y revocará las sesiones concurrentes.</p>
                        <textarea id="swalMotivoEstado" class="swal2-textarea" placeholder="Motivo obligatorio (mínimo 5 caracteres)..."></textarea>
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ffc107',
                    preConfirm: () => {
                        const motivo = document.getElementById('swalMotivoEstado').value.trim();
                        if (!motivo || motivo.length < 5) {
                            Swal.showValidationMessage('Debe ingresar un motivo de al menos 5 caracteres.');
                            return false;
                        }
                        return { nuevo_estado: nuevoEstado, motivo: motivo };
                    }
                });

                if (formValues) {
                    try {
                        const res = await fetch(`/api/usuarios/${id}/estado`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(formValues)
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            await Swal.fire({ icon: 'success', title: 'Estado Actualizado', text: 'El estado administrativo ha sido modificado.' });
                            window.location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo cambiar el estado.' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                    }
                }
            });
        }

        // Resetear Contraseña desde Ficha
        const btnReset = document.getElementById('btnFichaResetearPassword');
        if (btnReset) {
            btnReset.addEventListener('click', async function () {
                const id = this.getAttribute('data-id');

                // Generar password temporal
                const mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                const minus = 'abcdefghijkmnopqrstuvwxyz';
                const num = '23456789';
                const sim = '!@#$%^&*_-+=';
                let passTemp = 'Cp!' + Math.floor(1000 + Math.random() * 9000) + 'Xy$';

                const { value: motivo } = await Swal.fire({
                    title: 'Resetear Contraseña',
                    html: `
                        <p class="text-secondary f-s-13">Se asignará la clave temporal: <code>${passTemp}</code><br>Se forzará el cambio obligatorio en el siguiente acceso y se revocarán sesiones activas.</p>
                        <textarea id="swalMotivoReset" class="swal2-textarea" placeholder="Motivo obligatorio del reseteo (mínimo 5 caracteres)..."></textarea>
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Resetear Clave',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545',
                    preConfirm: () => {
                        const m = document.getElementById('swalMotivoReset').value.trim();
                        if (!m || m.length < 5) {
                            Swal.showValidationMessage('Debe ingresar un motivo de al menos 5 caracteres.');
                            return false;
                        }
                        return m;
                    }
                });

                if (motivo) {
                    try {
                        const res = await fetch(`/api/usuarios/${id}/reset-password`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': tokenCsrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ password_temporal: passTemp, motivo: motivo })
                        });
                        const json = await res.json();
                        if (res.ok && json.estado === 'exito') {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Contraseña Reseteada',
                                html: `Contraseña temporal asignada:<br><br><code>${passTemp}</code><br><br><small class="text-muted">Entregue esta clave al usuario de forma segura.</small>`
                            });
                            window.location.reload();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo resetear la contraseña.' });
                        }
                    } catch (e) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de comunicación con el servidor.' });
                    }
                }
            });
        }
    });
})();
