<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * ProyectoParticipante — Modelo que representa una contraparte o participante asociado al proyecto.
 *
 * Microfase 3A (GATE 3A-01) — CasaPRO Inmobiliario.
 */
class ProyectoParticipante
{
    public const VINCULO_PROPIETARIO = 'PROPIETARIO_TERRENO';
    public const VINCULO_APV = 'APV_CONVENIO';
    public const VINCULO_COMUNIDAD = 'COMUNIDAD_CAMPESINA';
    public const VINCULO_EMPRESA = 'EMPRESA_ASOCIADA';
    public const VINCULO_INVERSIONISTA = 'INVERSIONISTA';
    public const VINCULO_OTRO = 'OTRO';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $proyectoId;
    private int $personaId;
    private string $tipoVinculo;
    private ?float $porcentajeParticipacion;
    private ?int $representanteLegalId;
    private ?string $partidaRegistralPoder;
    private string $fechaInicio;
    private ?string $fechaFin;
    private ?string $observaciones;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $proyectoId,
        int $personaId,
        string $tipoVinculo,
        string $fechaInicio,
        ?float $porcentajeParticipacion = null,
        ?int $representanteLegalId = null,
        ?string $partidaRegistralPoder = null,
        ?string $fechaFin = null,
        ?string $observaciones = null,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($proyectoId <= 0) {
            throw new InvalidArgumentException('El ID de proyecto debe ser un entero positivo.');
        }
        if ($personaId <= 0) {
            throw new InvalidArgumentException('El ID de persona debe ser un entero positivo.');
        }
        if ($porcentajeParticipacion !== null && ($porcentajeParticipacion < 0 || $porcentajeParticipacion > 100)) {
            throw new InvalidArgumentException('El porcentaje de participación debe estar entre 0.00 y 100.00.');
        }

        $this->proyectoId = $proyectoId;
        $this->personaId = $personaId;
        $this->setTipoVinculo($tipoVinculo);
        $this->fechaInicio = $fechaInicio;
        $this->porcentajeParticipacion = $porcentajeParticipacion;
        $this->representanteLegalId = $representanteLegalId;
        $this->partidaRegistralPoder = $partidaRegistralPoder !== null ? trim($partidaRegistralPoder) : null;
        $this->fechaFin = $fechaFin;
        $this->observaciones = $observaciones !== null ? trim($observaciones) : null;
        $this->setEstado($estado);
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerProyectoId(): int
    {
        return $this->proyectoId;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerTipoVinculo(): string
    {
        return $this->tipoVinculo;
    }

    public function obtenerPorcentajeParticipacion(): ?float
    {
        return $this->porcentajeParticipacion;
    }

    public function obtenerRepresentanteLegalId(): ?int
    {
        return $this->representanteLegalId;
    }

    public function obtenerPartidaRegistralPoder(): ?string
    {
        return $this->partidaRegistralPoder;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }

    public function obtenerObservaciones(): ?string
    {
        return $this->observaciones;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function toArray(): array
    {
        return [
            'id'                       => $this->id,
            'proyecto_id'              => $this->proyectoId,
            'persona_id'               => $this->personaId,
            'tipo_vinculo'             => $this->tipoVinculo,
            'porcentaje_participacion' => $this->porcentajeParticipacion,
            'representante_legal_id'   => $this->representanteLegalId,
            'partida_registral_poder'  => $this->partidaRegistralPoder,
            'fecha_inicio'             => $this->fechaInicio,
            'fecha_fin'                => $this->fechaFin,
            'observaciones'            => $this->observaciones,
            'estado'                   => $this->estado,
            'creado_en'                => $this->creadoEn,
            'actualizado_en'           => $this->actualizadoEn
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['proyecto_id'] ?? 0),
            (int) ($datos['persona_id'] ?? 0),
            (string) ($datos['tipo_vinculo'] ?? self::VINCULO_PROPIETARIO),
            (string) ($datos['fecha_inicio'] ?? date('Y-m-d')),
            isset($datos['porcentaje_participacion']) && $datos['porcentaje_participacion'] !== null
                ? (float) $datos['porcentaje_participacion']
                : null,
            isset($datos['representante_legal_id']) && $datos['representante_legal_id'] !== null
                ? (int) $datos['representante_legal_id']
                : null,
            isset($datos['partida_registral_poder']) ? (string) $datos['partida_registral_poder'] : null,
            isset($datos['fecha_fin']) ? (string) $datos['fecha_fin'] : null,
            isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    private function setTipoVinculo(string $vinculo): void
    {
        $permitidos = [
            self::VINCULO_PROPIETARIO,
            self::VINCULO_APV,
            self::VINCULO_COMUNIDAD,
            self::VINCULO_EMPRESA,
            self::VINCULO_INVERSIONISTA,
            self::VINCULO_OTRO
        ];
        if (!in_array($vinculo, $permitidos, true)) {
            throw new InvalidArgumentException('Tipo de vínculo no válido: ' . $vinculo);
        }
        $this->tipoVinculo = $vinculo;
    }

    private function setEstado(string $estado): void
    {
        $permitidos = [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('Estado inválido: ' . $estado);
        }
        $this->estado = $estado;
    }
}
