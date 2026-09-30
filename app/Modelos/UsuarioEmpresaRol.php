<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * UsuarioEmpresaRol — Modelo de dominio que representa la asignación territorial
 * de un rol funcional a un usuario dentro del ámbito de una empresa específica.
 */
class UsuarioEmpresaRol
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $usuarioId;
    private int $empresaId;
    private int $rolId;
    private string $estado;
    private int $asignadoPor;
    private ?string $asignadoEn;
    private ?int $revocadoPor;
    private ?string $revocadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $usuarioId,
        int $empresaId,
        int $rolId,
        int $asignadoPor,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $asignadoEn = null,
        ?int $revocadoPor = null,
        ?string $revocadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('El ID de usuario debe ser un entero positivo.');
        }
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('El ID de empresa debe ser un entero positivo.');
        }
        if ($rolId <= 0) {
            throw new InvalidArgumentException('El ID de rol debe ser un entero positivo.');
        }
        if ($asignadoPor <= 0) {
            throw new InvalidArgumentException('El ID del actor asignador debe ser un entero positivo.');
        }

        $this->usuarioId = $usuarioId;
        $this->empresaId = $empresaId;
        $this->rolId = $rolId;
        $this->asignadoPor = $asignadoPor;
        $this->setEstado($estado);
        $this->id = $id;
        $this->asignadoEn = $asignadoEn;
        $this->revocadoPor = ($revocadoPor !== null && $revocadoPor > 0) ? $revocadoPor : null;
        $this->revocadoEn = $revocadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['usuario_id'] ?? 0),
            (int) ($datos['empresa_id'] ?? 0),
            (int) ($datos['rol_id'] ?? 0),
            (int) ($datos['asignado_por'] ?? 0),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['asignado_en']) ? (string) $datos['asignado_en'] : null,
            isset($datos['revocado_por']) ? (int) $datos['revocado_por'] : null,
            isset($datos['revocado_en']) ? (string) $datos['revocado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): self
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('No se puede reasignar el ID de una asignación ya persistida.');
        }
        if ($id <= 0) {
            throw new InvalidArgumentException('El ID asignado debe ser un entero positivo.');
        }
        $this->id = $id;
        return $this;
    }

    public function obtenerUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function obtenerEmpresaId(): int
    {
        return $this->empresaId;
    }

    public function obtenerRolId(): int
    {
        return $this->rolId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $estadoLimpio = strtoupper(trim($estado));
        if (!in_array($estadoLimpio, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de asignación no válido: '{$estado}'. Debe ser ACTIVO o INACTIVO.");
        }
        $this->estado = $estadoLimpio;
        return $this;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function revocar(int $actorId): self
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('El ID del actor que revoca debe ser un entero positivo.');
        }
        $this->estado = self::ESTADO_INACTIVO;
        $this->revocadoPor = $actorId;
        $this->revocadoEn = date('Y-m-d H:i:s');
        return $this;
    }

    public function reactivar(int $actorId): self
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('El ID del actor que reactiva debe ser un entero positivo.');
        }
        $this->estado = self::ESTADO_ACTIVO;
        $this->asignadoPor = $actorId;
        $this->asignadoEn = date('Y-m-d H:i:s');
        $this->revocadoPor = null;
        $this->revocadoEn = null;
        return $this;
    }

    public function obtenerAsignadoPor(): int
    {
        return $this->asignadoPor;
    }

    public function obtenerAsignadoEn(): ?string
    {
        return $this->asignadoEn;
    }

    public function obtenerRevocadoPor(): ?int
    {
        return $this->revocadoPor;
    }

    public function obtenerRevocadoEn(): ?string
    {
        return $this->revocadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function aArray(): array
    {
        return [
            'id'             => $this->id,
            'usuario_id'     => $this->usuarioId,
            'empresa_id'     => $this->empresaId,
            'rol_id'         => $this->rolId,
            'estado'         => $this->estado,
            'asignado_por'   => $this->asignadoPor,
            'asignado_en'    => $this->asignadoEn,
            'revocado_por'   => $this->revocadoPor,
            'revocado_en'    => $this->revocadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
