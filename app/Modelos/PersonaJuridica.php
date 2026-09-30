<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Extensión de atributos específicos para Persona Jurídica.
 *
 * El RUC no se almacena en esta entidad; reside en persona_documentos.
 */
class PersonaJuridica
{
    private int $personaId;
    private string $razonSocial;
    private ?string $nombreComercial;
    private ?string $fechaConstitucion;
    private ?string $objetoSocial;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        string $razonSocial,
        ?string $nombreComercial = null,
        ?string $fechaConstitucion = null,
        ?string $objetoSocial = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona debe ser mayor a 0.");
        }
        $razonTrim = trim($razonSocial);
        if ($razonTrim === '') {
            throw new InvalidArgumentException("La razón social no puede estar vacía.");
        }

        $this->personaId = $personaId;
        $this->razonSocial = $razonTrim;
        $this->nombreComercial = $nombreComercial !== null ? trim($nombreComercial) : null;
        $this->fechaConstitucion = $fechaConstitucion;
        $this->objetoSocial = $objetoSocial !== null ? trim($objetoSocial) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerRazonSocial(): string
    {
        return $this->razonSocial;
    }

    public function establecerRazonSocial(string $razonSocial): void
    {
        $trim = trim($razonSocial);
        if ($trim === '') {
            throw new InvalidArgumentException("La razón social no puede estar vacía.");
        }
        $this->razonSocial = $trim;
    }

    public function obtenerNombreComercial(): ?string
    {
        return $this->nombreComercial;
    }

    public function establecerNombreComercial(?string $nombreComercial): void
    {
        $this->nombreComercial = $nombreComercial !== null ? trim($nombreComercial) : null;
    }

    public function obtenerDenominacion(): string
    {
        if ($this->nombreComercial !== null && $this->nombreComercial !== '') {
            return "{$this->razonSocial} ({$this->nombreComercial})";
        }
        return $this->razonSocial;
    }

    public function obtenerFechaConstitucion(): ?string
    {
        return $this->fechaConstitucion;
    }

    public function establecerFechaConstitucion(?string $fechaConstitucion): void
    {
        $this->fechaConstitucion = $fechaConstitucion;
    }

    public function obtenerObjetoSocial(): ?string
    {
        return $this->objetoSocial;
    }

    public function establecerObjetoSocial(?string $objetoSocial): void
    {
        $this->objetoSocial = $objetoSocial !== null ? trim($objetoSocial) : null;
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
            personaId: (int) ($datos['persona_id'] ?? 0),
            razonSocial: (string) ($datos['razon_social'] ?? ''),
            nombreComercial: isset($datos['nombre_comercial']) ? (string) $datos['nombre_comercial'] : null,
            fechaConstitucion: isset($datos['fecha_constitucion']) ? (string) $datos['fecha_constitucion'] : null,
            objetoSocial: isset($datos['objeto_social']) ? (string) $datos['objeto_social'] : null,
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
            'persona_id' => $this->personaId,
            'razon_social' => $this->razonSocial,
            'nombre_comercial' => $this->nombreComercial,
            'denominacion' => $this->obtenerDenominacion(),
            'fecha_constitucion' => $this->fechaConstitucion,
            'objeto_social' => $this->objetoSocial,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
