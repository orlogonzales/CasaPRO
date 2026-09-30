<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Entidad de dominio para Documento de Identidad oficial vinculado a Persona.
 */
class PersonaDocumento
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaId;
    private int $tipoDocumentoId;
    private string $numeroDocumento;
    private bool $esPrincipal;
    private ?int $paisEmisionId;
    private ?string $fechaEmision;
    private ?string $fechaVencimiento;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        int $tipoDocumentoId,
        string $numeroDocumento,
        bool $esPrincipal = false,
        ?int $paisEmisionId = null,
        ?string $fechaEmision = null,
        ?string $fechaVencimiento = null,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona debe ser mayor a 0.");
        }
        if ($tipoDocumentoId <= 0) {
            throw new InvalidArgumentException("El tipo de documento debe ser mayor a 0.");
        }

        // Normalización canónica del número de documento (sin espacios exteriores)
        $numeroLimpio = trim($numeroDocumento);
        if ($numeroLimpio === '') {
            throw new InvalidArgumentException("El número de documento no puede estar vacío.");
        }

        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de documento inválido: {$estado}.");
        }

        $this->personaId = $personaId;
        $this->tipoDocumentoId = $tipoDocumentoId;
        $this->numeroDocumento = $numeroLimpio;
        $this->esPrincipal = $esPrincipal;
        $this->paisEmisionId = $paisEmisionId;
        $this->fechaEmision = $fechaEmision;
        $this->fechaVencimiento = $fechaVencimiento;
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

    public function obtenerTipoDocumentoId(): int
    {
        return $this->tipoDocumentoId;
    }

    public function establecerTipoDocumentoId(int $tipoDocumentoId): void
    {
        if ($tipoDocumentoId <= 0) {
            throw new InvalidArgumentException("Tipo de documento inválido.");
        }
        $this->tipoDocumentoId = $tipoDocumentoId;
    }

    public function obtenerNumeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function establecerNumeroDocumento(string $numeroDocumento): void
    {
        $trim = trim($numeroDocumento);
        if ($trim === '') {
            throw new InvalidArgumentException("El número de documento no puede estar vacío.");
        }
        $this->numeroDocumento = $trim;
    }

    public function esPrincipal(): bool
    {
        return $this->esPrincipal;
    }

    public function marcarComoPrincipal(bool $esPrincipal = true): void
    {
        $this->esPrincipal = $esPrincipal;
    }

    public function obtenerPaisEmisionId(): ?int
    {
        return $this->paisEmisionId;
    }

    public function establecerPaisEmisionId(?int $paisEmisionId): void
    {
        $this->paisEmisionId = $paisEmisionId;
    }

    public function obtenerFechaEmision(): ?string
    {
        return $this->fechaEmision;
    }

    public function establecerFechaEmision(?string $fechaEmision): void
    {
        $this->fechaEmision = $fechaEmision;
    }

    public function obtenerFechaVencimiento(): ?string
    {
        return $this->fechaVencimiento;
    }

    public function establecerFechaVencimiento(?string $fechaVencimiento): void
    {
        $this->fechaVencimiento = $fechaVencimiento;
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
            tipoDocumentoId: (int) ($datos['tipo_documento_id'] ?? 0),
            numeroDocumento: (string) ($datos['numero_documento'] ?? ''),
            esPrincipal: (bool) ($datos['es_principal'] ?? false),
            paisEmisionId: isset($datos['pais_emision_id']) ? (int) $datos['pais_emision_id'] : null,
            fechaEmision: isset($datos['fecha_emision']) ? (string) $datos['fecha_emision'] : null,
            fechaVencimiento: isset($datos['fecha_vencimiento']) ? (string) $datos['fecha_vencimiento'] : null,
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
            'tipo_documento_id' => $this->tipoDocumentoId,
            'numero_documento' => $this->numeroDocumento,
            'es_principal' => $this->esPrincipal,
            'pais_emision_id' => $this->paisEmisionId,
            'fecha_emision' => $this->fechaEmision,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
