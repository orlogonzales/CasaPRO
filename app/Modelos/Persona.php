<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Entidad de dominio raíz para Identidad: Persona.
 *
 * Representa la identidad civil o jurídica base del sistema CasaPRO.
 * No acoplada a roles satélites (Cliente, Personal, Usuario, Socio APV).
 */
class Persona
{
    public const TIPO_NATURAL = 'NATURAL';
    public const TIPO_JURIDICA = 'JURIDICA';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private string $tipoPersona;
    private string $estado;
    private ?string $notas;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    /**
     * @param string $tipoPersona 'NATURAL' o 'JURIDICA'
     * @param string $estado 'ACTIVO' o 'INACTIVO' (por defecto 'ACTIVO')
     * @param string|null $notas
     * @param int|null $id
     * @param string|null $creadoEn
     * @param string|null $actualizadoEn
     */
    public function __construct(
        string $tipoPersona,
        string $estado = self::ESTADO_ACTIVO,
        ?string $notas = null,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->establecerTipoPersona($tipoPersona);
        $this->establecerEstado($estado);
        $this->notas = $notas;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerTipoPersona(): string
    {
        return $this->tipoPersona;
    }

    public function establecerTipoPersona(string $tipoPersona): void
    {
        $tipoUpper = strtoupper(trim($tipoPersona));
        if (!in_array($tipoUpper, [self::TIPO_NATURAL, self::TIPO_JURIDICA], true)) {
            throw new InvalidArgumentException("Tipo de persona inválido: {$tipoPersona}. Debe ser NATURAL o JURIDICA.");
        }
        $this->tipoPersona = $tipoUpper;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function establecerEstado(string $estado): void
    {
        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de persona inválido: {$estado}. Solo se admite ACTIVO o INACTIVO.");
        }
        $this->estado = $estadoUpper;
    }

    public function obtenerNotas(): ?string
    {
        return $this->notas;
    }

    public function establecerNotas(?string $notas): void
    {
        $this->notas = $notas;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function esNatural(): bool
    {
        return $this->tipoPersona === self::TIPO_NATURAL;
    }

    public function esJuridica(): bool
    {
        return $this->tipoPersona === self::TIPO_JURIDICA;
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    /**
     * Mapea un array asociativo de base de datos a una instancia de Persona.
     *
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            tipoPersona: (string) ($datos['tipo_persona'] ?? self::TIPO_NATURAL),
            estado: (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            notas: isset($datos['notas']) ? (string) $datos['notas'] : null,
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * Convierte la entidad a un arreglo representativo.
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'tipo_persona' => $this->tipoPersona,
            'estado' => $this->estado,
            'notas' => $this->notas,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
