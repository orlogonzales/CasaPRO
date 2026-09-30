/**
 * CasaPRO — Controlador Frontend de Autenticación (Login)
 * Microfase: 1G-1
 *
 * Basado en la anatomía visual de Alina (sign_in.html y login.js)
 * Estricto Vanilla JS (Fetch API, Bootstrap 5 y PristineJS). Cero jQuery propio.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Adaptación oficial de fondo de Alina para .bg-img-cls
    document.querySelectorAll('.bg-img-cls').forEach((el) => {
        const src = el.getAttribute('src');
        const parent = el.parentElement;

        if (src && parent) {
            Object.assign(parent.style, {
                backgroundImage: `url(${src})`,
                backgroundSize: 'cover',
                backgroundRepeat: 'no-repeat',
                backgroundPosition: 'center top',
                display: 'block',
            });
            el.style.display = 'none';
        }
    });

    const form = document.getElementById('formLogin');
    if (!form) return;

    const btnIngresar = document.getElementById('btnIngresar');
    const spinnerCarga = document.getElementById('spinnerCarga');
    const iconoIngresar = document.getElementById('iconoIngresar');
    const alertaContenedor = document.getElementById('alertaContenedor');

    // 2. Inicializar PristineJS para validación declarativa
    const pristine = new Pristine(form, {
        classTo: 'form-floating',
        errorClass: 'has-danger',
        successClass: 'has-success',
        errorTextParent: 'form-floating',
        errorTextTag: 'div',
        errorTextClass: 'text-danger f-s-12 mt-1'
    });

    const mostrarAlerta = (mensaje, tipo = 'danger') => {
        if (!alertaContenedor) return;
        const icono = tipo === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check';
        alertaContenedor.innerHTML = `
            <div class="alert alert-${tipo} d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid ${icono} me-2 f-s-18"></i>
                <div>${mensaje}</div>
            </div>
        `;
    };

    const limpiarAlerta = () => {
        if (alertaContenedor) {
            alertaContenedor.innerHTML = '';
        }
    };

    const alternarCargando = (cargando) => {
        if (!btnIngresar) return;
        btnIngresar.disabled = cargando;
        if (spinnerCarga) spinnerCarga.classList.toggle('d-none', !cargando);
        if (iconoIngresar) iconoIngresar.classList.toggle('d-none', cargando);
    };

    // 3. Manejo de envío asíncrono con Fetch
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        limpiarAlerta();

        const esValido = pristine.validate();
        if (!esValido) {
            form.classList.add('was-validated');
            return;
        }

        alternarCargando(true);

        const formData = new FormData(form);
        const payload = {
            identificador: formData.get('identificador') || '',
            password: formData.get('password') || '',
            recordarme: formData.get('recordarme') === '1',
            csrf_token: formData.get('csrf_token') || ''
        };

        try {
            const respuesta = await fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': payload.csrf_token
                },
                body: JSON.stringify(payload)
            });

            const datos = await respuesta.json();

            if (respuesta.ok && datos.estado === 'exito') {
                mostrarAlerta('Credenciales verificadas. Redirigiendo...', 'success');
                const destino = datos.datos?.redireccion || '/inicio';
                window.location.href = destino;
            } else {
                const mensajeError = datos.mensaje || 'No fue posible iniciar sesión con las credenciales proporcionadas.';
                mostrarAlerta(mensajeError, 'danger');
                alternarCargando(false);
            }
        } catch (error) {
            mostrarAlerta('Error de comunicación con el servidor. Verifique su conexión e intente nuevamente.', 'danger');
            alternarCargando(false);
        }
    });
});
