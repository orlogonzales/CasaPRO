<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Entidad de dominio para el Historial de Representación Legal de Persona Jurídica por Persona Natural.
 */
class PersonaRepresentante
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaJuridicaId;
    private int $personaNaturalId;
    private string $cargo;
    private ?string $partidaRegistral;
    private string $fechaInicio;
    private ?string $fechaFin;
    private bool $esRepresentanteActual;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaJuridicaId,
        int $personaNaturalId,
        string $cargo,
        string $fechaInicio,
        ?string $fechaFin = null,
        ?string $partidaRegistral = null,
        bool $esRepresentanteActual = true,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaJuridicaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona jurídica debe ser mayor a 0.");
        }
        if ($personaNaturalId <= 0) {
            throw new InvalidArgumentException("El identificador de persona natural debe ser mayor a 0.");
        }
        $cargoTrim = trim($cargo);
        if ($cargoTrim === '') {
            throw new InvalidArgumentException("El cargo del representante no puede estar vacío.");
        }
        $fechaInicioTrim = trim($fechaInicio);
        if ($fechaInicioTrim === '') {
            throw new InvalidArgumentException("La fecha de inicio de representación no puede estar vacía.");
        }

        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de representante inválido: {$estado}.");
        }

        $this->personaJuridicaId = $personaJuridicaId;
        $this->personaNaturalId = $personaNaturalId;
        $this->cargo = $cargoTrim;
        $this->fechaInicio = $fechaInicioTrim;
        $this->fechaFin = $fechaFin;
        $this->partidaRegistral = $partidaRegistral !== null ? trim($partidaRegistral) : null;
        $this->esRepresentanteActual = $esRepresentanteActual;
        $this->estado = $estadoUpper;
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

    public function obtenerPersonaJuridicaId(): int
    {
        return $this->personaJuridicaId;
    }

    public function obtenerPersonaNaturalId(): int
    {
        return $this->personaNaturalId;
    }

    public function obtenerCargo(): string
    {
        return $this->cargo;
    }

    public function establecerCargo(string $cargo): void
    {
        $trim = trim($cargo);
        if ($trim === '') {
            throw new InvalidArgumentException("El cargo no puede estar vacío.");
        }
        $this->cargo = $trim;
    }

    public function obtenerPartidaRegistral(): ?string
    {
        return $this->partidaRegistral;
    }

    public function establecerPartidaRegistral(?string $partidaRegistral): void
    {
        $this->partidaRegistral = $partidaRegistral !== null ? trim($partidaRegistral) : null;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function establecerFechaInicio(string $fechaInicio): void
    {
        $this->fechaInicio = $fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }

    public function establecerFechaFin(?string $fechaFin): void
    {
        $this->fechaFin = $fechaFin;
    }

    public function esRepresentanteActual(): bool
    {
        return $this->esRepresentanteActual;
    }

    public function marcarComoRepresentanteActual(bool $actual = true): void
    {
        $this->esRepresentanteActual = $actual;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function establecerEstado(string $estado): void
    {
        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado inválido.");
        }
        $this->estado = $estadoUpper;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            personaJuridicaId: (int) ($datos['persona_juridica_id'] ?? 0),
            personaNaturalId: (int) ($datos['persona_natural_id'] ?? 0),
            cargo: (string) ($datos['cargo'] ?? ''),
            fechaInicio: (string) ($datos['fecha_inicio'] ?? ''),
            fechaFin: isset($datos['fecha_fin']) ? (string) $datos['fecha_fin'] : null,
            partidaRegistral: isset($datos['partida_registral']) ? (string) $datos['partida_registral'] : null,
            esRepresentanteActual: (bool) ($datos['es_representante_actual'] ?? true),
            estado: (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            id: isset($datos['id']) ? (int) $datos['id'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'persona_juridica_id' => $this->personaJuridicaId,
            'persona_natural_id' => $this->personaNaturalId,
            'cargo' => $this->cargo,
            'partida_registral' => $this->partidaRegistral,
            'fecha_inicio' => $this->fechaInicio,
            'fecha_fin' => $this->fechaFin,
            'es_representante_actual' => $this->esRepresentanteActual,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
