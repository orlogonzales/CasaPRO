<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de Administración de Usuarios y Accesos — Microfase 1G-2.
 * Basada en blank.html, data_table.html y modals.html de Alina.
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 text-dark f-w-600">
                        <i class="fa-solid fa-user-shield text-primary me-2"></i>Administración de Usuarios y Accesos
                    </h5>
                    <p class="text-secondary f-s-13 mb-0">Gestión de credenciales, roles, ciclo de vida administrativo y desbloqueo de seguridad.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevoUsuario">
                        <i class="fa-solid fa-user-plus me-1"></i> Nuevo Usuario
                    </button>
                </div>
            </div>

            <!-- Barra de Filtros Rápidos -->
            <div class="card-body border-bottom bg-light-subtle py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label for="filtroEstado" class="form-label f-s-13 text-secondary mb-1">Estado Administrativo</label>
                        <select id="filtroEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <option value="ACTIVO">Activo</option>
                            <option value="INACTIVO">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label for="filtroBloqueo" class="form-label f-s-13 text-secondary mb-1">Seguridad / Bloqueo</label>
                        <select id="filtroBloqueo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="BLOQUEADO">Bloqueados temporalmente</option>
                            <option value="NORMAL">Sin bloqueo activo</option>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <label for="filtroRol" class="form-label f-s-13 text-secondary mb-1">Rol Asignado</label>
                        <select id="filtroRol" class="form-select form-select-sm">
                            <option value="">Todos los roles</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-sm-6 d-flex justify-content-md-end">
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm">
                            <i class="fa-solid fa-filter-circle-xmark me-1"></i> Limpiar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contenedor DataTables Server-Side -->
            <div class="card-body p-0">
                <div class="app-datatable-default overflow-auto app-scroll p-3">
                    <table id="tablaUsuarios" class="display app-data-table default-data-table table table-hover align-middle w-100"
                        data-api-usuarios-url="<?= Vista::url('api/usuarios') ?>"
                        data-api-personas-disponibles-url="<?= Vista::url('api/usuarios/personas-disponibles') ?>"
                        data-api-roles-url="<?= Vista::url('api/usuarios/roles') ?>"
                        data-csrf-token="<?= $tokenCsrf ?>">
                        <thead>
                            <tr>
                                <th scope="col">Usuario</th>
                                <th scope="col">Persona Vinculada</th>
                                <th scope="col">Roles Asignados</th>
                                <th scope="col" class="text-center">Estado</th>
                                <th scope="col" class="text-center">Seguridad</th>
                                <th scope="col">Último Acceso</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Poblado dinámicamente vía fetch por DataTables Server-Side -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Alta de Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="tituloModalCrearUsuario" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalCrearUsuario">
                    <i class="fa-solid fa-user-plus text-primary me-2"></i>Crear Cuenta de Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCrearUsuario" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <div class="alert alert-info d-flex align-items-center mb-3 f-s-13" role="alert">
                        <i class="fa-solid fa-circle-info f-s-16 me-2"></i>
                        <div>El alta generará un Actor USER inmutable. La contraseña temporal requerirá cambio obligatorio al primer inicio de sesión.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="crearPersonaId" class="form-label f-s-13 f-w-600">Persona Natural Titular <span class="text-danger">*</span></label>
                            <select id="crearPersonaId" name="persona_id" class="form-select form-select-sm" required>
                                <option value="">Seleccione una Persona Natural disponible...</option>
                            </select>
                            <div class="invalid-feedback">Debe seleccionar una persona natural activa.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="crearNombreUsuario" class="form-label f-s-13 f-w-600">Nombre de Usuario <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" class="form-control form-control-sm" id="crearNombreUsuario" name="nombre_usuario" required minlength="3" maxlength="50" pattern="^[a-zA-Z0-9_\.\-]+$">
                            </div>
                            <div class="invalid-feedback">3 a 50 caracteres (letras, números, puntos, guiones).</div>
                        </div>

                        <div class="col-md-6">
                            <label for="crearEmail" class="form-label f-s-13 f-w-600">Correo Electrónico <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                <input type="email" class="form-control form-control-sm" id="crearEmail" name="email" required maxlength="100">
                            </div>
                            <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label f-s-13 f-w-600">Roles a Asignar <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-3 p-2 border rounded bg-light-subtle" id="contenedorRolesCrear">
                                <?php foreach ($roles as $r): ?>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input check-rol-crear" type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" id="rol_crear_<?= (int) $r['id'] ?>">
                                        <label class="form-check-label f-s-13" for="rol_crear_<?= (int) $r['id'] ?>">
                                            <span class="badge bg-secondary-subtle text-secondary me-1"><?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-danger f-s-12 d-none mt-1" id="errorRolesCrear">Debe seleccionar al menos un rol.</div>
                        </div>

                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="crearPassword" class="form-label f-s-13 f-w-600 mb-0">Contraseña Temporal <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 f-s-12 text-decoration-none" id="btnGenerarPasswordTemporal">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generar segura
                                </button>
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                <input type="text" class="form-control form-control-sm" id="crearPassword" name="password" required minlength="8" maxlength="128">
                                <button class="btn btn-outline-secondary" type="button" id="btnCopiarPassword" title="Copiar al portapapeles">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
                            <div class="form-text f-s-12 text-muted">Mínimo 8 caracteres, al menos una mayúscula, una minúscula, un número y un símbolo.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarCrearUsuario">
                        <i class="fa-solid fa-check me-1"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="tituloModalEditarUsuario" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalEditarUsuario">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Editar Datos de Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarUsuario" class="needs-validation" novalidate>
                <input type="hidden" id="editarUsuarioId" name="usuario_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editarNombreUsuario" class="form-label f-s-13 f-w-600">Nombre de Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="editarNombreUsuario" name="nombre_usuario" required minlength="3" maxlength="50">
                        <div class="invalid-feedback">Ingrese un nombre de usuario válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="editarEmail" class="form-label f-s-13 f-w-600">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" id="editarEmail" name="email" required maxlength="100">
                        <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarEditarUsuario">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Estado Administrativo -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="tituloModalCambiarEstado" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalCambiarEstado">
                    <i class="fa-solid fa-power-off text-warning me-2"></i>Cambiar Estado de Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formCambiarEstado" class="needs-validation" novalidate>
                <input type="hidden" id="estadoUsuarioId" name="usuario_id">
                <div class="modal-body p-4">
                    <p class="f-s-14 text-secondary mb-3">
                        Usuario seleccionado: <strong id="estadoNombreUsuario" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <label for="nuevoEstadoUsuario" class="form-label f-s-13 f-w-600">Nuevo Estado <span class="text-danger">*</span></label>
                        <select id="nuevoEstadoUsuario" name="nuevo_estado" class="form-select form-select-sm" required>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="motivoCambioEstado" class="form-label f-s-13 f-w-600">Motivo de la Modificación <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="motivoCambioEstado" name="motivo" rows="3" required minlength="5" placeholder="Especifique la justificación para auditoría (mínimo 5 caracteres)..."></textarea>
                        <div class="invalid-feedback">El motivo es obligatorio y debe tener al menos 5 caracteres.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnGuardarCambioEstado">
                        <i class="fa-solid fa-check me-1"></i> Confirmar Cambio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Desbloqueo Administrativo -->
<div class="modal fade" id="modalDesbloquear" tabindex="-1" aria-labelledby="tituloModalDesbloquear" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalDesbloquear">
                    <i class="fa-solid fa-lock-open text-success me-2"></i>Desbloquear Cuenta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formDesbloquear" class="needs-validation" novalidate>
                <input type="hidden" id="desbloquearUsuarioId" name="usuario_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning f-s-13 mb-3">
                        Esta acción restablecerá el contador de intentos fallidos a 0 y cancelará la ventana de bloqueo de seguridad. El estado administrativo de la cuenta permanecerá inalterado.
                    </div>
                    <p class="f-s-14 text-secondary mb-3">
                        Usuario a desbloquear: <strong id="desbloquearNombreUsuario" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <label for="motivoDesbloqueo" class="form-label f-s-13 f-w-600">Motivo del Desbloqueo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="motivoDesbloqueo" name="motivo" rows="3" required minlength="5" placeholder="Especifique el motivo para registro de seguridad (mínimo 5 caracteres)..."></textarea>
                        <div class="invalid-feedback">El motivo es obligatorio (mínimo 5 caracteres).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnGuardarDesbloqueo">
                        <i class="fa-solid fa-unlock me-1"></i> Desbloquear Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Asignar Roles -->
<div class="modal fade" id="modalAsignarRoles" tabindex="-1" aria-labelledby="tituloModalAsignarRoles" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalAsignarRoles">
                    <i class="fa-solid fa-users-gear text-primary me-2"></i>Asignación de Roles
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarRoles" class="needs-validation" novalidate>
                <input type="hidden" id="rolesUsuarioId" name="usuario_id">
                <div class="modal-body p-4">
                    <p class="f-s-14 text-secondary mb-3">
                        Usuario: <strong id="rolesNombreUsuario" class="text-dark"></strong>
                    </p>
                    <label class="form-label f-s-13 f-w-600">Roles Disponibles <span class="text-danger">*</span></label>
                    <div class="p-3 border rounded bg-light-subtle d-flex flex-column gap-2" id="contenedorRolesAsignar">
                        <?php foreach ($roles as $r): ?>
                            <div class="form-check">
                                <input class="form-check-input check-rol-asignar" type="checkbox" name="roles[]" value="<?= (int) $r['id'] ?>" id="rol_asig_<?= (int) $r['id'] ?>">
                                <label class="form-check-label f-s-13" for="rol_asig_<?= (int) $r['id'] ?>">
                                    <strong class="text-dark"><?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="badge bg-secondary-subtle text-secondary ms-1"><?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="f-s-12 text-muted"><?= htmlspecialchars($r['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-danger f-s-12 d-none mt-1" id="errorRolesAsignar">Debe seleccionar al menos un rol.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarAsignarRoles">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Roles
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Resetear Contraseña -->
<div class="modal fade" id="modalResetearPassword" tabindex="-1" aria-labelledby="tituloModalResetearPassword" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="tituloModalResetearPassword">
                    <i class="fa-solid fa-key text-danger me-2"></i>Resetear Contraseña
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formResetearPassword" class="needs-validation" novalidate>
                <input type="hidden" id="resetUsuarioId" name="usuario_id">
                <div class="modal-body p-4">
                    <div class="alert alert-warning f-s-13 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Se generará una contraseña temporal y se exigirá cambio obligatorio en el siguiente inicio de sesión. Todas las sesiones activas concurrentes del usuario quedarán revocadas.
                    </div>
                    <p class="f-s-14 text-secondary mb-3">
                        Usuario: <strong id="resetNombreUsuario" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="resetPasswordTemporal" class="form-label f-s-13 f-w-600 mb-0">Contraseña Temporal <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-link btn-sm text-primary p-0 f-s-12 text-decoration-none" id="btnGenerarResetTemporal">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generar segura
                            </button>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                            <input type="text" class="form-control form-control-sm" id="resetPasswordTemporal" name="password_temporal" required minlength="8" maxlength="128">
                            <button class="btn btn-outline-secondary" type="button" id="btnCopiarResetPassword" title="Copiar al portapapeles">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Debe cumplir con los requisitos mínimos de contraseña.</div>
                    </div>
                    <div class="mb-3">
                        <label for="motivoResetPassword" class="form-label f-s-13 f-w-600">Motivo del Reseteo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="motivoResetPassword" name="motivo" rows="3" required minlength="5" placeholder="Especifique el motivo del reseteo para auditoría (mínimo 5 caracteres)..."></textarea>
                        <div class="invalid-feedback">El motivo es obligatorio (mínimo 5 caracteres).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm" id="btnGuardarResetPassword">
                        <i class="fa-solid fa-rotate-left me-1"></i> Resetear Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
