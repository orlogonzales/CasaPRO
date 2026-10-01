<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * SectorPrecioHistorico — Modelo de dominio que representa un registro temporal de precio base por m2.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class SectorPrecioHistorico
{
    public const MONEDA_PEN = 'PEN';
    public const MONEDA_USD = 'USD';

    public const MONEDAS_PERMITIDAS = [
        self::MONEDA_PEN,
        self::MONEDA_USD,
    ];

    private ?int $id;
    private int $sectorId;
    private float $precioM2Base;
    private string $moneda;
    private string $fechaInicio;
    private ?string $fechaFin;
    private string $motivo;
    private int $creadoPor;
    private ?string $creadoEn;

    public function __construct(
        int $sectorId,
        float $precioM2Base,
        string $moneda,
        string $fechaInicio,
        string $motivo,
        int $creadoPor,
        ?string $fechaFin = null,
        ?int $id = null,
        ?string $creadoEn = null
    ) {
        if ($sectorId <= 0) {
            throw new InvalidArgumentException('El ID del sector debe ser un entero positivo.');
        }
        if ($precioM2Base <= 0) {
            throw new InvalidArgumentException('El precio por m2 base debe ser estrictamente mayor a cero.');
        }

        $monedaLimpia = strtoupper(trim($moneda));
        if (!in_array($monedaLimpia, self::MONEDAS_PERMITIDAS, true)) {
            throw new InvalidArgumentException("Moneda no válida: {$moneda}");
        }

        $fechaInicioLimpia = trim($fechaInicio);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicioLimpia)) {
            throw new InvalidArgumentException('La fecha de inicio debe tener el formato YYYY-MM-DD.');
        }

        $fechaFinLimpia = $fechaFin !== null ? trim($fechaFin) : null;
        if ($fechaFinLimpia !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinLimpia)) {
                throw new InvalidArgumentException('La fecha de fin debe tener el formato YYYY-MM-DD.');
            }
            if ($fechaFinLimpia < $fechaInicioLimpia) {
                throw new InvalidArgumentException('La fecha de fin no puede ser anterior a la fecha de inicio.');
            }
        }

        $motivoLimpio = trim($motivo);
        if ($motivoLimpio === '' || strlen($motivoLimpio) > 255) {
            throw new InvalidArgumentException('El motivo del precio debe tener entre 1 y 255 caracteres.');
        }

        if ($creadoPor <= 0) {
            throw new InvalidArgumentException('El ID de actor creador debe ser un entero positivo.');
        }

        $this->id = $id;
        $this->sectorId = $sectorId;
        $this->precioM2Base = $precioM2Base;
        $this->moneda = $monedaLimpia;
        $this->fechaInicio = $fechaInicioLimpia;
        $this->fechaFin = $fechaFinLimpia;
        $this->motivo = $motivoLimpio;
        $this->creadoPor = $creadoPor;
        $this->creadoEn = $creadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerSectorId(): int
    {
        return $this->sectorId;
    }

    public function obtenerPrecioM2Base(): float
    {
        return $this->precioM2Base;
    }

    public function obtenerMoneda(): string
    {
        return $this->moneda;
    }

    public function obtenerFechaInicio(): string
    {
        return $this->fechaInicio;
    }

    public function obtenerFechaFin(): ?string
    {
        return $this->fechaFin;
    }

    public function obtenerMotivo(): string
    {
        return $this->motivo;
    }

    public function obtenerCreadoPor(): int
    {
        return $this->creadoPor;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function esVigente(): bool
    {
        return $this->fechaFin === null;
    }

    public function aArray(): array
    {
        return [
            'id'             => $this->id,
            'sector_id'      => $this->sectorId,
            'precio_m2_base' => $this->precioM2Base,
            'moneda'         => $this->moneda,
            'fecha_inicio'   => $this->fechaInicio,
            'fecha_fin'      => $this->fechaFin,
            'es_vigente'     => $this->esVigente(),
            'motivo'         => $this->motivo,
            'creado_por'     => $this->creadoPor,
            'creado_en'      => $this->creadoEn,
        ];
    }
}
