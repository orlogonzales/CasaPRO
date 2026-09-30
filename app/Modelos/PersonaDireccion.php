<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Entidad de dominio para Direcciones físicas, fiscales y postales vinculadas a Persona.
 */
class PersonaDireccion
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaId;
    private int $tipoDireccionId;
    private ?int $distritoId;
    private string $direccion;
    private ?string $referencia;
    private ?string $codigoPostal;
    private bool $esPrincipal;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        int $tipoDireccionId,
        string $direccion,
        ?int $distritoId = null,
        ?string $referencia = null,
        ?string $codigoPostal = null,
        bool $esPrincipal = false,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona debe ser mayor a 0.");
        }
        if ($tipoDireccionId <= 0) {
            throw new InvalidArgumentException("El tipo de dirección debe ser mayor a 0.");
        }
        $dirTrim = trim($direccion);
        if ($dirTrim === '') {
            throw new InvalidArgumentException("La dirección no puede estar vacía.");
        }

        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de dirección inválido: {$estado}.");
        }

        $this->personaId = $personaId;
        $this->tipoDireccionId = $tipoDireccionId;
        $this->direccion = $dirTrim;
        $this->distritoId = $distritoId;
        $this->referencia = $referencia !== null ? trim($referencia) : null;
        $this->codigoPostal = $codigoPostal !== null ? trim($codigoPostal) : null;
        $this->esPrincipal = $esPrincipal;
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

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerTipoDireccionId(): int
    {
        return $this->tipoDireccionId;
    }

    public function establecerTipoDireccionId(int $tipoDireccionId): void
    {
        if ($tipoDireccionId <= 0) {
            throw new InvalidArgumentException("Tipo de dirección inválido.");
        }
        $this->tipoDireccionId = $tipoDireccionId;
    }

    public function obtenerDistritoId(): ?int
    {
        return $this->distritoId;
    }

    public function establecerDistritoId(?int $distritoId): void
    {
        $this->distritoId = $distritoId;
    }

    public function obtenerDireccion(): string
    {
        return $this->direccion;
    }

    public function establecerDireccion(string $direccion): void
    {
        $trim = trim($direccion);
        if ($trim === '') {
            throw new InvalidArgumentException("La dirección no puede estar vacía.");
        }
        $this->direccion = $trim;
    }

    public function obtenerReferencia(): ?string
    {
        return $this->referencia;
    }

    public function establecerReferencia(?string $referencia): void
    {
        $this->referencia = $referencia !== null ? trim($referencia) : null;
    }

    public function obtenerCodigoPostal(): ?string
    {
        return $this->codigoPostal;
    }

    public function establecerCodigoPostal(?string $codigoPostal): void
    {
        $this->codigoPostal = $codigoPostal !== null ? trim($codigoPostal) : null;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function marcarComoPrincipal(bool $esPrincipal = true): void
    {
        $this->esPrincipal = $esPrincipal;
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
            personaId: (int) ($datos['persona_id'] ?? 0),
            tipoDireccionId: (int) ($datos['tipo_direccion_id'] ?? 0),
            direccion: (string) ($datos['direccion'] ?? ''),
            distritoId: isset($datos['distrito_id']) ? (int) $datos['distrito_id'] : null,
            referencia: isset($datos['referencia']) ? (string) $datos['referencia'] : null,
            codigoPostal: isset($datos['codigo_postal']) ? (string) $datos['codigo_postal'] : null,
            esPrincipal: (bool) ($datos['es_principal'] ?? false),
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
            'persona_id' => $this->personaId,
            'tipo_direccion_id' => $this->tipoDireccionId,
            'distrito_id' => $this->distritoId,
            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'codigo_postal' => $this->codigoPostal,
            'es_principal' => $this->esPrincipal,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
