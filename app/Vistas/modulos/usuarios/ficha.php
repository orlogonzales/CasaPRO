<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de Ficha de Seguridad y Acceso — Microfase 1G-2.
 * Basada en profile.html y blank.html de Alina.
 */
?>

<div class="row">
    <!-- Columna Izquierda: Perfil y Credenciales -->
    <div class="col-lg-4 col-md-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="position-relative d-inline-block mb-3">
                    <div class="h-80 w-80 b-r-50 bg-light-primary text-primary d-flex align-items-center justify-content-center mx-auto f-s-28 f-w-700">
                        <?= strtoupper(substr((string) ($usuario['nombre_usuario'] ?? 'U'), 0, 2)) ?>
                    </div>
                    <?php if (($usuario['estado'] ?? '') === 'ACTIVO'): ?>
                        <span class="position-absolute bottom-0 end-0 p-2 bg-success border border-light rounded-circle" title="Estado: ACTIVO"></span>
                    <?php else: ?>
                        <span class="position-absolute bottom-0 end-0 p-2 bg-danger border border-light rounded-circle" title="Estado: INACTIVO"></span>
                    <?php endif; ?>
                </div>

                <h5 class="f-w-700 text-dark mb-1"><?= htmlspecialchars((string) ($usuario['nombre_usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="text-secondary f-s-13 mb-3"><?= htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <?php if (($usuario['estado'] ?? '') === 'ACTIVO'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">ACTIVO</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">INACTIVO</span>
                    <?php endif; ?>

                    <?php if (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                            <i class="fa-solid fa-lock me-1"></i>BLOQUEADO
                        </span>
                    <?php else: ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                            <i class="fa-solid fa-shield-halved me-1"></i>NORMAL
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($usuario['debe_cambiar_password'])): ?>
                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" title="Debe cambiar contraseña al iniciar sesión">
                            CAMBIO OBLIGATORIO
                        </span>
                    <?php endif; ?>
                </div>

                <div class="border-top pt-3 text-start">
                    <div class="mb-2">
                        <span class="text-secondary f-s-12 d-block">Persona Natural Vinculada:</span>
                        <strong class="text-dark f-s-14">
                            <?= htmlspecialchars((string) ($usuario['persona_nombre'] ?? 'Sin vincular'), ENT_QUOTES, 'UTF-8') ?>
                        </strong>
                        <?php if (!empty($usuario['numero_documento'])): ?>
                            <div class="text-muted f-s-12">
                                <?= htmlspecialchars((string) ($usuario['documento_tipo'] ?? 'DOC'), ENT_QUOTES, 'UTF-8') ?>:
                                <?= htmlspecialchars((string) ($usuario['numero_documento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-2">
                        <span class="text-secondary f-s-12 d-block">Identificador de Actor Inmutable:</span>
                        <code class="text-dark f-s-12">ACT_ID #<?= (int) ($usuario['actor_id'] ?? 0) ?></code>
                    </div>

                    <div class="mb-2">
                        <span class="text-secondary f-s-12 d-block">Versión de Autorización:</span>
                        <span class="badge bg-primary text-white">v<?= (int) ($usuario['version_autorizacion'] ?? 1) ?></span>
                        <span class="text-muted f-s-11 ms-1" data-bs-toggle="tooltip" title="Cualquier cambio de rol o contraseña incrementa esta versión revocando sesiones remotas"><i class="fa-solid fa-circle-question"></i></span>
                    </div>

                    <div>
                        <span class="text-secondary f-s-12 d-block">Fecha de Creación:</span>
                        <span class="text-dark f-s-13"><?= htmlspecialchars((string) ($usuario['creado_en'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Acciones Administrativas Rápidas -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h6 class="card-title mb-0 f-w-600 text-dark">
                    <i class="fa-solid fa-gears text-primary me-2"></i>Acciones de Seguridad
                </h6>
            </div>
            <div class="card-body p-3 d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm text-start" id="btnFichaEditar" data-id="<?= (int) $usuario['id'] ?>" data-usuario="<?= htmlspecialchars(json_encode($usuario), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Editar Datos Generales
                </button>

                <button type="button" class="btn btn-outline-secondary btn-sm text-start" id="btnFichaAsignarRoles" data-id="<?= (int) $usuario['id'] ?>">
                    <i class="fa-solid fa-users-gear me-2"></i>Modificar Roles Asignados
                </button>

                <button type="button" class="btn btn-outline-warning btn-sm text-start" id="btnFichaCambiarEstado" data-id="<?= (int) $usuario['id'] ?>" data-estado="<?= htmlspecialchars($usuario['estado'], ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-solid fa-power-off me-2"></i>Cambiar Estado Administrativo
                </button>

                <?php if (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()): ?>
                    <button type="button" class="btn btn-outline-success btn-sm text-start" id="btnFichaDesbloquear" data-id="<?= (int) $usuario['id'] ?>">
                        <i class="fa-solid fa-unlock me-2"></i>Desbloquear Cuenta
                    </button>
                <?php endif; ?>

                <button type="button" class="btn btn-outline-danger btn-sm text-start" id="btnFichaResetearPassword" data-id="<?= (int) $usuario['id'] ?>">
                    <i class="fa-solid fa-key me-2"></i>Resetear Contraseña (Admin)
                </button>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Métricas, Roles y Timeline de Seguridad -->
    <div class="col-lg-8 col-md-12">
        <!-- Métricas Resumen -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <div class="card border-0 shadow-sm bg-light-primary">
                    <div class="card-body p-3">
                        <span class="text-secondary f-s-12 d-block mb-1">Último Inicio de Sesión</span>
                        <h6 class="f-w-700 text-dark mb-0 f-s-13">
                            <?= !empty($usuario['ultimo_login_en']) ? htmlspecialchars((string) $usuario['ultimo_login_en'], ENT_QUOTES, 'UTF-8') : 'Sin registros' ?>
                        </h6>
                        <span class="text-muted f-s-11">IP: <?= htmlspecialchars((string) ($usuario['ultimo_login_ip'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="card border-0 shadow-sm <?= ((int) ($usuario['intentos_fallidos'] ?? 0) > 0) ? 'bg-light-warning' : 'bg-light-success' ?>">
                    <div class="card-body p-3">
                        <span class="text-secondary f-s-12 d-block mb-1">Intentos Fallidos Actuales</span>
                        <h4 class="f-w-700 mb-0"><?= (int) ($usuario['intentos_fallidos'] ?? 0) ?></h4>
                        <span class="text-muted f-s-11">
                            <?= !empty($usuario['bloqueado_hasta']) ? ('Bloqueado hasta ' . substr((string) $usuario['bloqueado_hasta'], 11, 5)) : 'Sin restricciones' ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="card border-0 shadow-sm bg-light-secondary">
                    <div class="card-body p-3">
                        <span class="text-secondary f-s-12 d-block mb-1">Roles Asignados</span>
                        <h4 class="f-w-700 text-dark mb-0"><?= count($usuario['roles'] ?? []) ?></h4>
                        <span class="text-muted f-s-11">Catálogo RBAC Activo</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pestañas de Gestión de Accesos y Seguridad (Alina nav-bottom-line) -->
        <ul class="nav nav-tabs nav-bottom-line mb-4" id="fichaUsuarioTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active f-w-600" id="tab-territorial-btn" data-bs-toggle="tab" data-bs-target="#tab-territorial" type="button" role="tab" aria-controls="tab-territorial" aria-selected="true">
                    <i class="fa-solid fa-building-user text-primary me-2"></i>Ámbitos Territoriales
                    <span class="badge bg-primary text-white ms-1" id="badgeTotalAsignaciones"><?= count($asignacionesTerritoriales ?? []) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tab-roles-globales-btn" data-bs-toggle="tab" data-bs-target="#tab-roles-globales" type="button" role="tab" aria-controls="tab-roles-globales" aria-selected="false">
                    <i class="fa-solid fa-shield-halved text-secondary me-2"></i>Roles Globales
                    <span class="badge bg-secondary text-white ms-1"><?= count($usuario['roles'] ?? []) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link f-w-600" id="tab-eventos-btn" data-bs-toggle="tab" data-bs-target="#tab-eventos" type="button" role="tab" aria-controls="tab-eventos" aria-selected="false">
                    <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Eventos de Seguridad
                </button>
            </li>
        </ul>

        <div class="tab-content" id="fichaUsuarioTabsContent">
            <!-- Pestaña 1: Ámbitos Territoriales (Empresa ↔ Rol) -->
            <div class="tab-pane fade show active" id="tab-territorial" role="tabpanel" aria-labelledby="tab-territorial-btn">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0 f-w-600 text-dark">
                                <i class="fa-solid fa-building-user text-primary me-2"></i>Asignaciones Territoriales de Rol
                            </h6>
                            <span class="text-secondary f-s-12">Empresas en las que el usuario opera formalmente con roles territoriales</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="btnAbrirModalAsignarRol">
                            <i class="fa-solid fa-plus me-1"></i>Asignar Empresa y Rol
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tablaAsignacionesTerritoriales">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th scope="col" class="f-s-13">Empresa</th>
                                        <th scope="col" class="f-s-13">Rol Territorial</th>
                                        <th scope="col" class="f-s-13">Estado</th>
                                        <th scope="col" class="f-s-13">Fecha Asignación</th>
                                        <th scope="col" class="f-s-13">Asignado Por</th>
                                        <th scope="col" class="f-s-13 text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyAsignacionesTerritoriales">
                                    <?php if (empty($asignacionesTerritoriales)): ?>
                                        <tr id="filaSinAsignaciones">
                                            <td colspan="6" class="text-center py-4 text-muted f-s-13">
                                                <i class="fa-solid fa-building-circle-exclamation d-block f-s-24 mb-2 text-secondary opacity-50"></i>
                                                El usuario no cuenta con roles asignados en empresas actualmente.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($asignacionesTerritoriales as $asig): ?>
                                            <tr id="fila-asig-<?= (int) $asig['id'] ?>">
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                                        <?= htmlspecialchars((string) ($asig['empresa_codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <strong class="text-dark f-s-13"><?= htmlspecialchars((string) ($asig['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                                        <?= htmlspecialchars((string) ($asig['rol_codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <span class="text-muted f-s-12 ms-1"><?= htmlspecialchars((string) ($asig['rol_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td>
                                                    <?php if (($asig['estado'] ?? '') === 'ACTIVO'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">ACTIVO</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">INACTIVO</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-dark f-s-12">
                                                    <?= htmlspecialchars((string) ($asig['asignado_en'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-secondary f-s-12">
                                                    <?= htmlspecialchars((string) ($asig['asignado_por_nombre'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (($asig['estado'] ?? '') === 'ACTIVO'): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm btn-conmutar-asignacion"
                                                                data-id="<?= (int) $asig['id'] ?>"
                                                                data-estado-actual="ACTIVO"
                                                                data-empresa="<?= htmlspecialchars((string) ($asig['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-rol="<?= htmlspecialchars((string) ($asig['rol_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-bs-toggle="tooltip" data-bs-title="Revocar asignación de rol">
                                                            <i class="fa-solid fa-user-xmark me-1"></i>Revocar
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-success btn-sm btn-conmutar-asignacion"
                                                                data-id="<?= (int) $asig['id'] ?>"
                                                                data-estado-actual="INACTIVO"
                                                                data-empresa="<?= htmlspecialchars((string) ($asig['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-rol="<?= htmlspecialchars((string) ($asig['rol_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-bs-toggle="tooltip" data-bs-title="Reactivar asignación histórica">
                                                            <i class="fa-solid fa-user-check me-1"></i>Reactivar
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Roles Globales Asignados a la Cuenta -->
            <div class="tab-pane fade" id="tab-roles-globales" role="tabpanel" aria-labelledby="tab-roles-globales-btn">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 f-w-600 text-dark">
                            <i class="fa-solid fa-shield-halved text-primary me-2"></i>Roles Globales del Sistema (usuario_roles)
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th scope="col" class="f-s-13">Código</th>
                                        <th scope="col" class="f-s-13">Nombre del Rol</th>
                                        <th scope="col" class="f-s-13">Descripción</th>
                                        <th scope="col" class="f-s-13">Fecha Asignación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuario['roles'])): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-muted f-s-13">El usuario no posee roles globales asignados actualmente.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usuario['roles'] as $r): ?>
                                            <tr>
                                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars((string) $r['codigo'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td class="f-w-600 text-dark"><?= htmlspecialchars((string) $r['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-secondary f-s-13"><?= htmlspecialchars((string) ($r['descripcion'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-muted f-s-12"><?= htmlspecialchars((string) ($r['asignado_en'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 3: Timeline / Eventos Recientes de Seguridad -->
            <div class="tab-pane fade" id="tab-eventos" role="tabpanel" aria-labelledby="tab-eventos-btn">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header">
                        <h6 class="card-title mb-0 f-w-600 text-dark">
                            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Últimos Eventos de Seguridad Registrados
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th scope="col" class="f-s-13">Tipo de Evento</th>
                                        <th scope="col" class="f-s-13">Fecha y Hora</th>
                                        <th scope="col" class="f-s-13">IP / Origen</th>
                                        <th scope="col" class="f-s-13">Detalle / Metadatos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuario['eventos_recientes'])): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted f-s-13">No existen eventos de seguridad registrados para este usuario.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($usuario['eventos_recientes'] as $ev): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                        $tipo = (string) ($ev['tipo_evento'] ?? '');
                                                        $badgeClass = 'bg-secondary-subtle text-secondary';
                                                        if (str_contains($tipo, 'EXITO') || str_contains($tipo, 'DESBLOQUEO')) {
                                                            $badgeClass = 'bg-success-subtle text-success';
                                                        } elseif (str_contains($tipo, 'FALLO') || str_contains($tipo, 'BLOQUEO') || str_contains($tipo, 'RESET')) {
                                                            $badgeClass = 'bg-danger-subtle text-danger';
                                                        }
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?> border px-2 py-1"><?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></span>
                                                </td>
                                                <td class="text-dark f-s-12"><?= htmlspecialchars((string) ($ev['creado_en'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-muted f-s-12"><?= htmlspecialchars((string) ($ev['ip'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="f-s-12 text-secondary">
                                                    <?php
                                                        $meta = $ev['metadatos'] ?? [];
                                                        if (is_array($meta) && !empty($meta)) {
                                                            echo htmlspecialchars(json_encode($meta, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alina Bootstrap 5: Asignar Rol Territorial en Empresa -->
<div class="modal fade" id="modalAsignarRolEmpresa" tabindex="-1" aria-labelledby="modalAsignarRolEmpresaTitulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title f-w-600 text-dark" id="modalAsignarRolEmpresaTitulo">
                    <i class="fa-solid fa-building-user text-primary me-2"></i>Asignar Rol Territorial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarRolEmpresa" class="needs-validation" novalidate data-usuario-id="<?= (int) $usuario['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="selectEmpresaAsignar" class="form-label f-w-600 f-s-13">Empresa Destino <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="selectEmpresaAsignar" name="empresa_id" required style="width: 100%;">
                            <option value="">Seleccione una empresa activa...</option>
                        </select>
                        <div class="invalid-feedback">Por favor seleccione una empresa activa.</div>
                    </div>

                    <div class="mb-3">
                        <label for="selectRolAsignar" class="form-label f-w-600 f-s-13">Rol Territorial <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="selectRolAsignar" name="rol_id" required style="width: 100%;">
                            <option value="">Seleccione un rol asignable...</option>
                        </select>
                        <div class="invalid-feedback">Por favor seleccione un rol territorial.</div>
                        <div class="form-text f-s-11 text-muted">
                            <i class="fa-solid fa-circle-info me-1"></i>El rol SUPERADMIN no está disponible para asignación por empresa (exclusivo de ámbito global).
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="inputMotivoAsignacion" class="form-label f-w-600 f-s-13">Motivo o Justificación</label>
                        <textarea class="form-control f-s-13" id="inputMotivoAsignacion" name="motivo" rows="2" placeholder="Motivo administrativo de la asignación territorial..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarAsignacionRol">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Asignar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
