<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Entidad de dominio para Medios de Contacto de Persona (email, móvil, fijo, whatsapp).
 */
class PersonaContacto
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaId;
    private int $tipoContactoId;
    private string $valor;
    private ?string $etiqueta;
    private bool $esPrincipal;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        int $tipoContactoId,
        string $valor,
        ?string $etiqueta = null,
        bool $esPrincipal = false,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona debe ser mayor a 0.");
        }
        if ($tipoContactoId <= 0) {
            throw new InvalidArgumentException("El tipo de contacto debe ser mayor a 0.");
        }
        $valorTrim = trim($valor);
        if ($valorTrim === '') {
            throw new InvalidArgumentException("El valor de contacto no puede estar vacío.");
        }

        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            throw new InvalidArgumentException("Estado de contacto inválido: {$estado}.");
        }

        $this->personaId = $personaId;
        $this->tipoContactoId = $tipoContactoId;
        $this->valor = $valorTrim;
        $this->etiqueta = $etiqueta !== null ? trim($etiqueta) : null;
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

    public function obtenerTipoContactoId(): int
    {
        return $this->tipoContactoId;
    }

    public function establecerTipoContactoId(int $tipoContactoId): void
    {
        if ($tipoContactoId <= 0) {
            throw new InvalidArgumentException("Tipo de contacto inválido.");
        }
        $this->tipoContactoId = $tipoContactoId;
    }

    public function obtenerValor(): string
    {
        return $this->valor;
    }

    public function establecerValor(string $valor): void
    {
        $trim = trim($valor);
        if ($trim === '') {
            throw new InvalidArgumentException("El valor de contacto no puede estar vacío.");
        }
        $this->valor = $trim;
    }

    public function obtenerEtiqueta(): ?string
    {
        return $this->etiqueta;
    }

    public function establecerEtiqueta(?string $etiqueta): void
    {
        $this->etiqueta = $etiqueta !== null ? trim($etiqueta) : null;
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
            throw new InvalidArgumentException("Estado de contacto inválido.");
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
            tipoContactoId: (int) ($datos['tipo_contacto_id'] ?? 0),
            valor: (string) ($datos['valor'] ?? ''),
            etiqueta: isset($datos['etiqueta']) ? (string) $datos['etiqueta'] : null,
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
            'tipo_contacto_id' => $this->tipoContactoId,
            'valor' => $this->valor,
            'etiqueta' => $this->etiqueta,
            'es_principal' => $this->esPrincipal,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
