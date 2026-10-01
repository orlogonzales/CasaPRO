<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Sector — Modelo de dominio que representa una etapa o sector urbanístico de un proyecto.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class Sector
{
    public const ESTADO_EN_DESARROLLO = 'EN_DESARROLLO';
    public const ESTADO_EN_VENTA = 'EN_VENTA';
    public const ESTADO_CONSOLIDADO = 'CONSOLIDADO';
    public const ESTADO_CERRADO = 'CERRADO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public const ESTADOS_PERMITIDOS = [
        self::ESTADO_EN_DESARROLLO,
        self::ESTADO_EN_VENTA,
        self::ESTADO_CONSOLIDADO,
        self::ESTADO_CERRADO,
        self::ESTADO_INACTIVO,
    ];

    private ?int $id;
    private int $proyectoId;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private float $areaBrutaM2;
    private float $areaUtilM2;
    private float $areaCesionM2;
    private float $areaComunM2;
    private int $orden;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $proyectoId,
        string $codigo,
        string $nombre,
        float $areaBrutaM2,
        float $areaUtilM2 = 0.0000,
        float $areaCesionM2 = 0.0000,
        float $areaComunM2 = 0.0000,
        int $orden = 1,
        string $estado = self::ESTADO_EN_DESARROLLO,
        ?string $descripcion = null,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($proyectoId <= 0) {
            throw new InvalidArgumentException('El ID del proyecto debe ser un entero positivo.');
        }

        $codigoLimpio = strtoupper(trim($codigo));
        if ($codigoLimpio === '' || strlen($codigoLimpio) > 32) {
            throw new InvalidArgumentException('El código del sector debe tener entre 1 y 32 caracteres.');
        }

        $nombreLimpio = trim($nombre);
        if ($nombreLimpio === '' || strlen($nombreLimpio) > 150) {
            throw new InvalidArgumentException('El nombre del sector debe tener entre 1 y 150 caracteres.');
        }

        if ($areaBrutaM2 <= 0) {
            throw new InvalidArgumentException('El área bruta del sector debe ser un valor positivo mayor a cero.');
        }
        if ($areaUtilM2 < 0 || $areaCesionM2 < 0 || $areaComunM2 < 0) {
            throw new InvalidArgumentException('Las áreas del sector no pueden ser negativas.');
        }
        if ($orden < 1) {
            throw new InvalidArgumentException('El orden visual del sector debe ser un entero mayor o igual a 1.');
        }

        if (!in_array($estado, self::ESTADOS_PERMITIDOS, true)) {
            throw new InvalidArgumentException("Estado de sector no reconocido: {$estado}");
        }

        $this->id = $id;
        $this->proyectoId = $proyectoId;
        $this->codigo = $codigoLimpio;
        $this->nombre = $nombreLimpio;
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->areaBrutaM2 = $areaBrutaM2;
        $this->areaUtilM2 = $areaUtilM2;
        $this->areaCesionM2 = $areaCesionM2;
        $this->areaComunM2 = $areaComunM2;
        $this->orden = $orden;
        $this->estado = $estado;
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

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function obtenerAreaBrutaM2(): float
    {
        return $this->areaBrutaM2;
    }

    public function obtenerAreaUtilM2(): float
    {
        return $this->areaUtilM2;
    }

    public function obtenerAreaCesionM2(): float
    {
        return $this->areaCesionM2;
    }

    public function obtenerAreaComunM2(): float
    {
        return $this->areaComunM2;
    }

    public function obtenerOrden(): int
    {
        return $this->orden;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
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
     * Calcula la suma de áreas desglosadas (útil + cesión + común).
     */
    public function obtenerSumaAreasInternasM2(): float
    {
        return round($this->areaUtilM2 + $this->areaCesionM2 + $this->areaComunM2, 4);
    }

    /**
     * Calcula el remanente local no asignado del sector.
     */
    public function obtenerAreaRemanenteLocalM2(): float
    {
        return round($this->areaBrutaM2 - $this->obtenerSumaAreasInternasM2(), 4);
    }

    /**
     * Valida la invariante local de consistencia interna.
     */
    public function esConsistenteInternamente(): bool
    {
        // Tolerancia de precisión de punto flotante de 0.0001
        return $this->obtenerAreaRemanenteLocalM2() >= -0.0001;
    }

    public function aArray(): array
    {
        return [
            'id'             => $this->id,
            'proyecto_id'    => $this->proyectoId,
            'codigo'         => $this->codigo,
            'nombre'         => $this->nombre,
            'descripcion'    => $this->descripcion,
            'area_bruta_m2'  => $this->areaBrutaM2,
            'area_util_m2'   => $this->areaUtilM2,
            'area_cesion_m2' => $this->areaCesionM2,
            'area_comun_m2'  => $this->areaComunM2,
            'orden'          => $this->orden,
            'estado'         => $this->estado,
            'creado_en'      => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
