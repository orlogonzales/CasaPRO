<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * AsignarRolEmpresaDTO — DTO para formalizar la asignación territorial de un rol
 * a un usuario en el ámbito de una empresa activa.
 */
class AsignarRolEmpresaDTO
{
    public int $usuarioId;
    public int $empresaId;
    public int $rolId;
    public ?string $motivo;

    public function __construct(
        int $usuarioId,
        int $empresaId,
        int $rolId,
        ?string $motivo = null
    ) {
        $errores = [];

        if ($usuarioId <= 0) {
            $errores['usuario_id'] = ['Debe especificar un usuario válido.'];
        }

        if ($empresaId <= 0) {
            $errores['empresa_id'] = ['Debe especificar una empresa válida.'];
        }

        if ($rolId <= 0) {
            $errores['rol_id'] = ['Debe especificar un rol válido.'];
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de asignación territorial inválidos.', $errores);
        }

        $this->usuarioId = $usuarioId;
        $this->empresaId = $empresaId;
        $this->rolId = $rolId;
        $this->motivo = ($motivo !== null && trim($motivo) !== '') ? trim($motivo) : null;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['usuario_id'] ?? 0),
            (int) ($datos['empresa_id'] ?? 0),
            (int) ($datos['rol_id'] ?? 0),
            isset($datos['motivo']) ? (string) $datos['motivo'] : null
        );
    }
}
