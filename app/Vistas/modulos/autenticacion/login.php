<!DOCTYPE html>
<html lang="es">
<head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="CasaPRO - Plataforma Inmobiliaria Integral" name="description">
    <link href="/assets/images/logo/favicon.png" rel="icon" type="image/x-icon">
    <link href="/assets/images/logo/favicon.png" rel="shortcut icon" type="image/x-icon">
    <title><?= htmlspecialchars($tituloPagina ?? 'Iniciar Sesión | CasaPRO', ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Font Awesome Free 6.3.0 -->
    <link href="/assets/vendor/fontawesome/css/all.css" rel="stylesheet">
    <!-- Bootstrap css -->
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet" type="text/css">
    <!-- App css -->
    <link href="/assets/css/style.css" rel="stylesheet" type="text/css">
    <!-- Responsive css -->
    <link href="/assets/css/responsive.css" rel="stylesheet" type="text/css">
</head>
<body>

<div class="sign-bg-wrapper">
    <div class="container main-container">
        <div class="row main-content-box">
            <div class="col-lg-5 form-content-box p-0">
                <div class="form-container">
                    <form class="app-form needs-validation" id="formLogin" method="POST" action="/login" novalidate>
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($tokenCsrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-sm-4 mb-3 text-center text-lg-start">
                                    <div class="d-flex align-items-center justify-content-center justify-content-lg-start mb-3">
                                        <i class="fa-solid fa-building-shield text-primary f-s-32 me-2"></i>
                                        <h2 class="text-blue f-w-600 mb-0">Casa<span class="text-primary">PRO</span></h2>
                                    </div>
                                    <p class="f-s-16 text-secondary">Ingrese sus credenciales de acceso institucional</p>
                                </div>
                            </div>

                            <div class="col-12" id="alertaContenedor">
                                <?php if (!empty($mensajeError)): ?>
                                    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                                        <i class="fa-solid fa-triangle-exclamation me-2 f-s-18"></i>
                                        <div><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($mensajeExito)): ?>
                                    <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
                                        <i class="fa-solid fa-circle-check me-2 f-s-18"></i>
                                        <div><?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="identificador" name="identificador" placeholder="Usuario o Correo"
                                           type="text" required autofocus autocomplete="username">
                                    <label for="identificador"><i class="fa-solid fa-user me-2 text-muted"></i>Usuario o Correo Electrónico</label>
                                    <div class="invalid-feedback">Por favor, ingrese su usuario o correo electrónico.</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="password" name="password" placeholder="Contraseña"
                                           type="password" required autocomplete="current-password">
                                    <label for="password"><i class="fa-solid fa-lock me-2 text-muted"></i>Contraseña</label>
                                    <div class="invalid-feedback">Por favor, ingrese su contraseña.</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check gap-3 flex-wrap d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <input class="form-check-input w-25 h-25" id="recordarme" name="recordarme" type="checkbox" value="1">
                                        <label class="form-check-label mt-1 pa-s-5 f-s-16 text-dark" for="recordarme">
                                            Recordar sesión
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-2">
                                <button class="btn bg-gradient-primary btn-lg b-r-16 w-100" id="btnIngresar" type="submit">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spinnerCarga" role="status" aria-hidden="true"></span>
                                    <i class="fa-solid fa-right-to-bracket me-2" id="iconoIngresar"></i>
                                    <span>Iniciar Sesión</span>
                                </button>
                            </div>

                            <div class="col-12 mt-4 text-center">
                                <small class="text-muted">
                                    <i class="fa-solid fa-shield-halved me-1 text-primary"></i>
                                    Sistema protegido con control estricto de accesos y trazabilidad forense
                                </small>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-7 image-content-box d-none d-lg-block p-0">
                <img alt="CasaPRO Inmobiliaria" class="img-fluid bg-img-cls" src="/assets/images/login/01.jpg">
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap js -->
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<!-- Pristine Validation -->
<script src="/assets/vendor/pristine/pristine.min.js"></script>
<!-- Login JS -->
<script src="/assets/js/modulos/autenticacion/login.js"></script>

</body>
</html>
