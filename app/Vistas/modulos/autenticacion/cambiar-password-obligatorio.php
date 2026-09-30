<!DOCTYPE html>
<html lang="es">
<head>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <meta content="IE=edge" http-equiv="X-UA-Compatible">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="CasaPRO - Plataforma Inmobiliaria Integral" name="description">
    <link href="/assets/images/logo/favicon.png" rel="icon" type="image/x-icon">
    <link href="/assets/images/logo/favicon.png" rel="shortcut icon" type="image/x-icon">
    <title><?= htmlspecialchars($tituloPagina ?? 'Cambio Obligatorio de Contraseña | CasaPRO', ENT_QUOTES, 'UTF-8') ?></title>

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
        <div class="row main-content-box justify-content-center">
            <div class="col-lg-6 col-md-8 form-content-box p-0">
                <div class="form-container">
                    <form class="app-form needs-validation" id="formCambiarPasswordObligatorio" novalidate>
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($tokenCsrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-4 text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <i class="fa-solid fa-shield-halved text-primary f-s-32 me-2"></i>
                                        <h3 class="text-blue f-w-700 mb-0">Casa<span class="text-primary">PRO</span></h3>
                                    </div>
                                    <h5 class="f-w-600 text-dark mb-1">Actualización Obligatoria de Contraseña</h5>
                                    <p class="f-s-13 text-secondary mb-0">
                                        Estimado(a) <strong><?= htmlspecialchars((string) ($usuario['nombre_completo'] ?? $usuario['nombre_usuario'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?></strong>, por políticas de seguridad institucional debe renovar su contraseña antes de acceder a la plataforma.
                                    </p>
                                </div>
                            </div>

                            <div class="col-12" id="alertaContenedor"></div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="password_actual" name="password_actual" placeholder="Contraseña Actual"
                                           type="password" required autocomplete="current-password">
                                    <label for="password_actual"><i class="fa-solid fa-key me-2 text-muted"></i>Contraseña Actual o Temporal</label>
                                    <div class="invalid-feedback">Ingrese la contraseña actual o temporal proporcionada.</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-2">
                                    <input class="form-control" id="nuevo_password" name="nuevo_password" placeholder="Nueva Contraseña"
                                           type="password" required minlength="8" maxlength="128" autocomplete="new-password">
                                    <label for="nuevo_password"><i class="fa-solid fa-lock me-2 text-muted"></i>Nueva Contraseña</label>
                                    <div class="invalid-feedback">La nueva contraseña debe cumplir con los requisitos mínimos de seguridad.</div>
                                </div>

                                <!-- Indicadores dinámicos de política de seguridad -->
                                <div class="p-3 mb-3 border rounded bg-light-subtle f-s-12" id="requisitosPassword">
                                    <div class="f-w-600 mb-1 text-dark">Requisitos de la nueva contraseña:</div>
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
                                        <li id="req-longitud" class="text-muted"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Mínimo 8 caracteres</li>
                                        <li id="req-mayus" class="text-muted"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Al menos una letra mayúscula (A-Z)</li>
                                        <li id="req-minus" class="text-muted"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Al menos una letra minúscula (a-z)</li>
                                        <li id="req-numero" class="text-muted"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Al menos un número (0-9)</li>
                                        <li id="req-simbolo" class="text-muted"><i class="fa-solid fa-circle-xmark text-danger me-1"></i> Al menos un símbolo (!@#$%^&*...)</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="confirmar_password" name="confirmar_password" placeholder="Confirmar Nueva Contraseña"
                                           type="password" required autocomplete="new-password">
                                    <label for="confirmar_password"><i class="fa-solid fa-lock-open me-2 text-muted"></i>Confirmar Nueva Contraseña</label>
                                    <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                                </div>
                            </div>

                            <div class="col-12 mt-2">
                                <button class="btn bg-gradient-primary btn-lg b-r-16 w-100" id="btnActualizarPassword" type="submit">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spinnerCarga" role="status" aria-hidden="true"></span>
                                    <i class="fa-solid fa-check-double me-2" id="iconoActualizar"></i>
                                    <span>Actualizar Contraseña y Continuar</span>
                                </button>
                            </div>

                            <div class="col-12 mt-3 text-center">
                                <form method="POST" action="/logout" id="formCerrarSesion" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenCsrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-link text-danger f-s-13 text-decoration-none">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Cerrar Sesión
                                    </button>
                                </form>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 js -->
<script src="/assets/vendor/sweetalert/sweetalert.js"></script>
<!-- JS Lógica Vanilla -->
<script src="/assets/js/modulos/usuarios/cambiar-password-obligatorio.js"></script>
</body>
</html>
