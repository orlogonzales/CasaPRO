<?php

declare(strict_types=1);

namespace App\Modelos;

/**
 * Actor — Modelo de dominio que representa a los actores que interactúan con CasaPRO.
 */
class Actor
{
    private ?int $id;
    private string $tipoActor;
    private string $codigo;
    private string $nombre;
    private string $estado;
    private ?array $metadatos;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        string $tipoActor,
        string $codigo,
        string $nombre,
        string $estado = 'ACTIVO',
        ?array $metadatos = null,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->establecerTipoActor($tipoActor);
        $this->establecerCodigo($codigo);
        $this->establecerNombre($nombre);
        $this->establecerEstado($estado);
        $this->metadatos = $metadatos;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        $metadatos = null;
        if (isset($datos['metadatos'])) {
            if (is_array($datos['metadatos'])) {
                $metadatos = $datos['metadatos'];
            } elseif (is_string($datos['metadatos'])) {
                $decodificado = json_decode($datos['metadatos'], true);
                $metadatos = is_array($decodificado) ? $decodificado : null;
            }
        }

        return new self(
            (string) ($datos['tipo_actor'] ?? ''),
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            (string) ($datos['estado'] ?? 'ACTIVO'),
            $metadatos,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'tipo_actor' => $this->tipoActor,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'metadatos' => $this->metadatos,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function establecerId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerTipoActor(): string
    {
        return $this->tipoActor;
    }

    public function establecerTipoActor(string $tipoActor): void
    {
        $permitidos = ['SISTEMA', 'USUARIO', 'SERVICIO_EXTERNO'];
        $tipoNormalizado = strtoupper(trim($tipoActor));
        if (!in_array($tipoNormalizado, $permitidos, true)) {
            throw new \InvalidArgumentException("Tipo de actor inválido: {$tipoActor}");
        }
        $this->tipoActor = $tipoNormalizado;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function establecerCodigo(string $codigo): void
    {
        $limpio = trim($codigo);
        if ($limpio === '') {
            throw new \InvalidArgumentException('El código del actor no puede estar vacío.');
        }
        $this->codigo = $limpio;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function establecerNombre(string $nombre): void
    {
        $limpio = trim($nombre);
        if ($limpio === '') {
            throw new \InvalidArgumentException('El nombre del actor no puede estar vacío.');
        }
        $this->nombre = $limpio;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function establecerEstado(string $estado): void
    {
        $permitidos = ['ACTIVO', 'INACTIVO'];
        $estadoNormalizado = strtoupper(trim($estado));
        if (!in_array($estadoNormalizado, $permitidos, true)) {
            throw new \InvalidArgumentException("Estado de actor inválido: {$estado}");
        }
        $this->estado = $estadoNormalizado;
    }

    public function obtenerMetadatos(): ?array
    {
        return $this->metadatos;
    }

    public function establecerMetadatos(?array $metadatos): void
    {
        $this->metadatos = $metadatos;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }
}
