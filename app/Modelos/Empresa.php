<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Empresa — Modelo de dominio que representa una entidad corporativa administrada en CasaPRO.
 * 
 * Actúa como extensión operativa de una Persona Jurídica en el sistema (relación 1 : 0..1).
 */
class Empresa
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaId;
    private string $codigo;
    private string $nombreCorto;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        string $codigo,
        string $nombreCorto,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException('El ID de persona debe ser un entero positivo.');
        }

        $this->personaId = $personaId;
        $this->setCodigo($codigo);
        $this->setNombreCorto($nombreCorto);
        $this->setEstado($estado);
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): self
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('No se puede reasignar el ID de una empresa ya persistida.');
        }
        $this->id = $id;
        return $this;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombreCorto(): string
    {
        return $this->nombreCorto;
    }

    public function establecerNombreCorto(string $nombreCorto): self
    {
        $this->setNombreCorto($nombreCorto);
        return $this;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function establecerEstado(string $estado): self
    {
        $this->setEstado($estado);
        return $this;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    private function setCodigo(string $codigo): void
    {
        $codigoLimpio = trim($codigo);
        if ($codigoLimpio === '' || strlen($codigoLimpio) < 3 || strlen($codigoLimpio) > 32) {
            throw new InvalidArgumentException('El código de empresa debe tener entre 3 y 32 caracteres.');
        }

        if (!preg_match('/^[A-Z0-9_]+$/', $codigoLimpio)) {
            throw new InvalidArgumentException('El código de empresa solo puede contener letras mayúsculas, números y guiones bajos.');
        }

        $this->codigo = $codigoLimpio;
    }

    private function setNombreCorto(string $nombreCorto): void
    {
        $nombreLimpio = trim($nombreCorto);
        if ($nombreLimpio === '' || strlen($nombreLimpio) < 2 || strlen($nombreLimpio) > 64) {
            throw new InvalidArgumentException('El nombre corto debe tener entre 2 y 64 caracteres.');
        }
        $this->nombreCorto = $nombreLimpio;
    }

    private function setEstado(string $estado): void
    {
        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de empresa inválido: {$estado}. Permitidos: ACTIVO, INACTIVO.");
        }
        $this->estado = $estadoUpper;
    }

    public function aArray(): array
    {
        return [
            'id'             => $this->id,
            'persona_id'     => $this->personaId,
            'codigo'         => $this->codigo,
            'nombre_corto'   => $this->nombreCorto,
            'estado'         => $this->estado,
            'creado_en'      => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre_corto'] ?? ''),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }
}
