<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * RevocarRolEmpresaDTO — DTO para registrar la baja lógica de un rol territorial
 * con justificación obligatoria de auditoría.
 */
class RevocarRolEmpresaDTO
{
    public int $usuarioId;
    public int $empresaId;
    public int $rolId;
    public string $motivo;

    public function __construct(
        int $usuarioId,
        int $empresaId,
        int $rolId,
        string $motivo
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

        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '' || strlen($motivoLimpio) < 5) {
            $errores['motivo'] = ['El motivo de revocación es obligatorio (mínimo 5 caracteres).'];
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de revocación territorial inválidos.', $errores);
        }

        $this->usuarioId = $usuarioId;
        $this->empresaId = $empresaId;
        $this->rolId = $rolId;
        $this->motivo = $motivoLimpio;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['usuario_id'] ?? 0),
            (int) ($datos['empresa_id'] ?? 0),
            (int) ($datos['rol_id'] ?? 0),
            (string) ($datos['motivo'] ?? '')
        );
    }
}
