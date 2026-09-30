<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

class CrearMenuOpcionDTO
{
    private const CAMPOS_PERMITIDOS = [
        'padre_id',
        'tipo',
        'codigo',
        'etiqueta',
        'titulo',
        'ruta',
        'icono',
        'orden',
        'privilegio_id',
        'estado',
        'visible'
    ];

    public function __construct(
        public readonly ?int $padreId,
        public readonly string $tipo,
        public readonly string $codigo,
        public readonly string $etiqueta,
        public readonly ?string $ruta,
        public readonly ?string $icono,
        public readonly int $orden,
        public readonly ?int $privilegioId,
        public readonly string $estado,
        public readonly int $visible
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        $errores = [];

        // 1. Allowlist estricto
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            $errores['campos_desconocidos'] = 'Campos no permitidos en el payload: ' . implode(', ', $desconocidos);
        }

        // 2. Tipo
        $tipo = strtoupper(trim((string) ($datos['tipo'] ?? '')));
        if (!in_array($tipo, ['AGRUPADOR', 'ENLACE'], true)) {
            $errores['tipo'] = 'El tipo de opción debe ser AGRUPADOR o ENLACE.';
        }

        // 3. Código canónico
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        if ($codigo === '' || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $codigo)) {
            $errores['codigo'] = 'El código debe ser alfanumérico con guiones bajos (3-50 caracteres).';
        }

        // 4. Etiqueta / Título
        $etiqueta = trim((string) ($datos['etiqueta'] ?? $datos['titulo'] ?? ''));
        if ($etiqueta === '' || mb_strlen($etiqueta) > 100) {
            $errores['etiqueta'] = 'La etiqueta o título es obligatorio (máximo 100 caracteres).';
        }
        if (str_contains($etiqueta, '<') || str_contains($etiqueta, '>')) {
            $errores['etiqueta'] = 'La etiqueta no debe contener caracteres HTML.';
        }

        // 5. Padre ID
        $padreId = isset($datos['padre_id']) && $datos['padre_id'] !== '' && $datos['padre_id'] !== null
            ? (int) $datos['padre_id']
            : null;

        // 6. Icono Font Awesome
        $icono = isset($datos['icono']) && trim((string) $datos['icono']) !== ''
            ? trim((string) $datos['icono'])
            : null;

        if ($padreId === null && ($icono === null || $icono === '')) {
            $errores['icono'] = 'Las opciones de Nivel 0 (Raíz) requieren obligatoriamente un icono Font Awesome.';
        }

        if ($icono !== null) {
            if (!preg_match('/^fa-(solid|regular|brands)\s+fa-[a-z0-9\-]+$/', $icono) || str_contains($icono, '<') || str_contains($icono, '"') || str_contains($icono, '\'')) {
                $errores['icono'] = 'El icono debe ser una clase Font Awesome válida (ej: fa-solid fa-users) sin HTML ni atributos.';
            }
            if (str_contains(strtolower($icono), 'ti-') || str_contains(strtolower($icono), 'ti ')) {
                $errores['icono'] = 'Iconos Tabler están estrictamente prohibidos.';
            }
        }

        // 7. Ruta
        $ruta = isset($datos['ruta']) && trim((string) $datos['ruta']) !== ''
            ? trim((string) $datos['ruta'])
            : null;

        if ($tipo === 'AGRUPADOR') {
            if ($ruta !== null && $ruta !== '') {
                $errores['ruta'] = 'Un nodo de tipo AGRUPADOR no debe tener ruta de navegación asignada.';
            }
            $ruta = null;
        } else {
            // ENLACE requiere ruta
            if ($ruta === null || $ruta === '') {
                $errores['ruta'] = 'Una opción de tipo ENLACE requiere una ruta interna de navegación.';
            } else {
                $rutaLimpia = ltrim($ruta, '/');
                $rutaCheck = $rutaLimpia === '' ? 'inicio' : $rutaLimpia;
                if (!preg_match('/^[a-zA-Z0-9_\-]+(\/[a-zA-Z0-9_\-]+)*$/', $rutaCheck)) {
                    $errores['ruta'] = 'La ruta contiene caracteres o formato no válido.';
                }
                if (
                    str_starts_with(strtolower($ruta), 'javascript:') ||
                    str_starts_with(strtolower($ruta), 'data:') ||
                    str_starts_with(strtolower($ruta), 'vbscript:') ||
                    str_starts_with(strtolower($ruta), 'http://') ||
                    str_starts_with(strtolower($ruta), 'https://') ||
                    str_starts_with(strtolower($ruta), '//') ||
                    str_contains($ruta, '<') ||
                    str_contains($ruta, ' ')
                ) {
                    $errores['ruta'] = 'Ruta insegura o protocolo externo no permitido.';
                }
            }
        }

        // 8. Privilegio ID
        $privilegioId = isset($datos['privilegio_id']) && $datos['privilegio_id'] !== '' && $datos['privilegio_id'] !== null
            ? (int) $datos['privilegio_id']
            : null;

        // 9. Estado y Visible
        $estado = strtoupper(trim((string) ($datos['estado'] ?? 'ACTIVO')));
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            $errores['estado'] = 'Estado debe ser ACTIVO o INACTIVO.';
        }

        $visible = isset($datos['visible']) ? (int) $datos['visible'] : 1;
        if (!in_array($visible, [0, 1], true)) {
            $errores['visible'] = 'El campo visible debe ser 0 o 1.';
        }

        $orden = isset($datos['orden']) ? max(1, (int) $datos['orden']) : 1;

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Errores de validación en los datos de la opción de menú.', $errores);
        }

        return new self(
            $padreId,
            $tipo,
            $codigo,
            $etiqueta,
            $ruta,
            $icono,
            $orden,
            $privilegioId,
            $estado,
            $visible
        );
    }
}
