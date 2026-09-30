<?php

declare(strict_types=1);

use App\Core\Vista;

/**
 * Vista de Gestión de Menú Dinámico y Navegación — Microfase 1G-3.
 * Basada en blank.html, draggable.html y modals.html de Alina.
 */

// Extraer padres válidos (solo AGRUPADORES de nivel 0 y 1) para el selector de padre
$obtenerPadresValidos = function (array $nodos) use (&$obtenerPadresValidos): array {
    $padres = [];
    foreach ($nodos as $nodo) {
        if (($nodo['tipo'] ?? '') === 'AGRUPADOR') {
            $padres[] = [
                'id'     => (int) $nodo['id'],
                'titulo' => (string) $nodo['titulo'],
                'codigo' => (string) $nodo['codigo'],
                'nivel'  => 0
            ];
            foreach ($nodo['hijos'] ?? [] as $hijo) {
                if (($hijo['tipo'] ?? '') === 'AGRUPADOR') {
                    $padres[] = [
                        'id'     => (int) $hijo['id'],
                        'titulo' => '— ' . (string) $hijo['titulo'],
                        'codigo' => (string) $hijo['codigo'],
                        'nivel'  => 1
                    ];
                }
            }
        }
    }
    return $padres;
};

$padresValidos = $obtenerPadresValidos($arbol);

// Función recursiva para renderizar el árbol de opciones
$renderizarOpciones = function (array $nodos, int $nivel = 0) use (&$renderizarOpciones): void {
    if (empty($nodos)) {
        return;
    }
    foreach ($nodos as $opcion):
        $id = (int) $opcion['id'];
        $padreId = $opcion['padre_id'] !== null ? (int) $opcion['padre_id'] : null;
        $esAgrupador = ($opcion['tipo'] === 'AGRUPADOR');
        $tieneHijos = !empty($opcion['hijos']);
        $esActivo = ($opcion['estado'] === 'ACTIVO');
        $claseBorde = match ($nivel) {
            0 => 'border-top border-3 border-primary shadow-sm mb-3',
            1 => 'border-start border-3 border-info mb-2',
            default => 'border-start border-2 border-secondary mb-1'
        };
        $bgClase = match ($nivel) {
            0 => 'bg-white',
            1 => 'bg-light-subtle',
            default => 'bg-white'
        };
?>
        <div class="list-group-item <?= $claseBorde ?> <?= $bgClase ?> p-3 rounded item-menu"
             data-id="<?= $id ?>"
             data-padre-id="<?= $padreId ?? '' ?>"
             data-nivel="<?= $nivel ?>"
             data-tipo="<?= Vista::e($opcion['tipo']) ?>"
             data-codigo="<?= Vista::e($opcion['codigo']) ?>">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center flex-grow-1 flex-wrap gap-2">
                    <span class="drag-handle cursor-grab px-1 text-secondary" title="Arrastrar para reordenar" style="cursor: grab;">
                        <i class="fa-solid fa-grip-vertical fs-5"></i>
                    </span>

                    <span class="badge bg-light text-dark border p-2">
                        <i class="<?= Vista::e(!empty($opcion['icono']) ? $opcion['icono'] : ($esAgrupador ? 'fa-solid fa-folder' : 'fa-solid fa-link')) ?> text-primary fs-6"></i>
                    </span>

                    <span class="fw-bold text-dark fs-6">
                        <?= Vista::e($opcion['titulo']) ?>
                    </span>

                    <span class="badge bg-light text-secondary border">
                        <code><?= Vista::e($opcion['codigo']) ?></code>
                    </span>

                    <?php if ($esAgrupador): ?>
                        <span class="badge bg-light-primary text-primary border border-primary-subtle">
                            <i class="fa-solid fa-folder me-1"></i>Agrupador (Nivel <?= $nivel ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light-success text-success border border-success-subtle">
                            <i class="fa-solid fa-link me-1"></i>/<?= Vista::e($opcion['ruta'] ?? '') ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($opcion['privilegio_codigo'])): ?>
                        <span class="badge bg-light-warning text-dark border border-warning-subtle" title="Privilegio RBAC requerido">
                            <i class="fa-solid fa-shield-halved text-warning me-1"></i><?= Vista::e($opcion['privilegio_codigo']) ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-light-secondary text-muted border" title="Visible para todos los usuarios autenticados">
                            <i class="fa-solid fa-lock-open me-1"></i>Público / Autenticado
                        </span>
                    <?php endif; ?>

                    <?php if ($esActivo): ?>
                        <span class="badge bg-success">Activo</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactivo</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-1">
                    <?php if ($esAgrupador && $nivel < 2): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm btn-agregar-hijo"
                                data-padre-id="<?= $id ?>"
                                data-padre-titulo="<?= Vista::e($opcion['titulo']) ?>"
                                data-padre-nivel="<?= $nivel ?>"
                                title="Agregar sub-opción">
                            <i class="fa-solid fa-plus me-1"></i>Sub-opción
                        </button>
                    <?php endif; ?>

                    <button type="button" class="btn btn-outline-secondary btn-sm btn-editar"
                            data-id="<?= $id ?>"
                            title="Editar opción">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>

                    <button type="button" class="btn btn-sm <?= $esActivo ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-cambiar-estado"
                            data-id="<?= $id ?>"
                            data-estado="<?= $opcion['estado'] ?>"
                            title="<?= $esActivo ? 'Desactivar opción' : 'Activar opción' ?>">
                        <i class="fa-solid <?= $esActivo ? 'fa-ban' : 'fa-check' ?>"></i>
                    </button>

                    <button type="button" class="btn btn-outline-danger btn-sm btn-eliminar"
                            data-id="<?= $id ?>"
                            data-titulo="<?= Vista::e($opcion['titulo']) ?>"
                            data-hijos="<?= count($opcion['hijos'] ?? []) ?>"
                            title="Eliminar opción">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Contenedor anidado de sub-opciones -->
            <?php if ($esAgrupador && $nivel < 2): ?>
                <div class="list-group nested-sortable mt-2 ms-md-4 ms-2 ps-2 border-start border-2 border-primary-subtle"
                     data-padre-id="<?= $id ?>"
                     data-nivel="<?= $nivel + 1 ?>"
                     style="min-height: 25px;">
                    <?php if ($tieneHijos): ?>
                        <?php $renderizarOpciones($opcion['hijos'], $nivel + 1); ?>
                    <?php else: ?>
                        <div class="text-muted f-s-12 py-2 px-3 bg-light rounded placeholder-vacio">
                            <i class="fa-solid fa-arrow-turn-up fa-rotate-90 me-1"></i>
                            <em>Sin sub-opciones. Arrastre elementos aquí o presione "+ Sub-opción".</em>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
<?php
    endforeach;
};
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-0 text-dark f-w-600">
                        <i class="fa-solid fa-bars-staggered text-primary me-2"></i>Gestión de Menú y Navegación
                    </h5>
                    <p class="text-secondary f-s-13 mb-0">Configuración jerárquica de la barra de navegación, reordenamiento interactivo y control RBAC.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnGuardarOrden" disabled>
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Reordenamiento
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecargarArbol">
                        <i class="fa-solid fa-rotate me-1"></i> Refrescar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaOpcion">
                        <i class="fa-solid fa-circle-plus me-1"></i> Nueva Opción
                    </button>
                </div>
            </div>

            <!-- Alerta Informativa de Arquitectura y Seguridad -->
            <div class="card-body border-bottom bg-light-subtle py-2">
                <div class="alert alert-light-primary border-primary d-flex align-items-start mb-0 py-2" role="alert">
                    <i class="fa-solid fa-shield-halved text-primary fs-5 me-2 mt-1"></i>
                    <div class="f-s-13">
                        <strong>Principio de Seguridad Soberana: Menú ≠ Autorización.</strong><br>
                        Ocultar una opción del menú orienta al operador, pero no sustituye la seguridad del sistema. Todos los accesos directos por URL están blindados con HTTP 403 en el middleware backend.
                        Se permite un máximo de <strong>3 niveles</strong> jerárquicos (Raíz 0, Agrupador 1 y Enlace 2).
                    </div>
                </div>
            </div>

            <!-- Contenedor del Árbol Jerárquico -->
            <div class="card-body p-3">
                <div id="contenedorArbolMenu"
                     class="list-group nested-sortable"
                     data-padre-id=""
                     data-nivel="0"
                     data-api-menu-url="<?= Vista::url('api/menu') ?>"
                     data-csrf-token="<?= $tokenCsrf ?>">
                    <?php if (!empty($arbol)): ?>
                        <?php $renderizarOpciones($arbol, 0); ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-folder-open fs-1 mb-3 text-secondary"></i>
                            <p class="fs-6">No existen opciones de menú registradas en el sistema.</p>
                            <button type="button" class="btn btn-primary btn-sm" id="btnNuevaOpcionVacio">
                                <i class="fa-solid fa-circle-plus me-1"></i> Crear Primera Opción
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Formulario: Crear / Editar Opción de Menú -->
<div class="modal fade" id="modalMenuOpcion" tabindex="-1" aria-labelledby="tituloModalMenuOpcion" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title f-w-600 text-white" id="tituloModalMenuOpcion">
                    <i class="fa-solid fa-bars-staggered me-2"></i>Nueva Opción de Menú
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formMenuOpcion" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="menuOpcionId" value="">
                    <input type="hidden" name="token_csrf" value="<?= $tokenCsrf ?>">

                    <div class="row g-3">
                        <!-- Padre y Tipo -->
                        <div class="col-md-6">
                            <label for="padreIdSelect" class="form-label f-s-13 fw-semibold">Ubicación Jerárquica (Padre)</label>
                            <select class="form-select" id="padreIdSelect" name="padre_id">
                                <option value="">(Raíz — Nivel 0)</option>
                                <?php foreach ($padresValidos as $pv): ?>
                                    <option value="<?= $pv['id'] ?>" data-nivel="<?= $pv['nivel'] ?>">
                                        <?= Vista::e($pv['titulo']) ?> [<?= Vista::e($pv['codigo']) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text f-s-12">Seleccione el agrupador padre. Deje en blanco para nivel raíz.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="tipoSelect" class="form-label f-s-13 fw-semibold">Tipo de Opción <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipoSelect" name="tipo" required>
                                <option value="AGRUPADOR">AGRUPADOR (Contenedor con sub-opciones)</option>
                                <option value="ENLACE">ENLACE (Ruta navegable)</option>
                            </select>
                            <div class="form-text f-s-12">Los agrupadores contienen submenús; los enlaces abren pantallas.</div>
                        </div>

                        <!-- Código y Título -->
                        <div class="col-md-6">
                            <label for="codigoInput" class="form-label f-s-13 fw-semibold">Código Técnico <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="codigoInput" name="codigo" required maxlength="50" placeholder="Ej: MOD_INICIO">
                            <div class="invalid-feedback">3 a 50 letras mayúsculas, números o guiones bajos.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="tituloInput" class="form-label f-s-13 fw-semibold">Título Visible <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tituloInput" name="titulo" required maxlength="100" placeholder="Ej: Resumen General">
                            <div class="invalid-feedback">El título es obligatorio (máximo 100 caracteres).</div>
                        </div>

                        <!-- Icono y Ruta -->
                        <div class="col-md-6">
                            <label for="iconoInput" class="form-label f-s-13 fw-semibold">Icono Font Awesome</label>
                            <div class="input-group">
                                <span class="input-group-text" id="previewIcono"><i class="fa-solid fa-folder"></i></span>
                                <input type="text" class="form-control" id="iconoInput" name="icono" maxlength="50" placeholder="fa-solid fa-house">
                            </div>
                            <div class="form-text f-s-12">Ejemplos: <code>fa-solid fa-house</code>, <code>fa-solid fa-users</code>, <code>fa-solid fa-shield-halved</code></div>
                        </div>

                        <div class="col-md-6" id="contenedorRuta">
                            <label for="rutaInput" class="form-label f-s-13 fw-semibold">Ruta Interna <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input type="text" class="form-control" id="rutaInput" name="ruta" maxlength="255" placeholder="personas">
                            </div>
                            <div class="invalid-feedback">Ruta relativa obligatoria para enlaces (solo letras, números, guiones y barras).</div>
                        </div>

                        <!-- Privilegio RBAC y Estado -->
                        <div class="col-md-6">
                            <label for="privilegioIdSelect" class="form-label f-s-13 fw-semibold">Privilegio RBAC Requerido</label>
                            <select class="form-select" id="privilegioIdSelect" name="privilegio_id">
                                <option value="">(Sin restricción — Visible a cualquier autenticado)</option>
                                <?php foreach ($privilegios as $priv): ?>
                                    <option value="<?= (int) $priv['id'] ?>">
                                        <?= Vista::e($priv['codigo']) ?> — <?= Vista::e($priv['nombre']) ?> (<?= Vista::e($priv['modulo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text f-s-12">Si se asigna, solo los usuarios con este privilegio verán la opción.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="estadoSelect" class="form-label f-s-13 fw-semibold">Estado</label>
                            <select class="form-select" id="estadoSelect" name="estado">
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="ordenInput" class="form-label f-s-13 fw-semibold">Orden</label>
                            <input type="number" class="form-control" id="ordenInput" name="orden" min="1" max="999" value="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarModal">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Opción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
