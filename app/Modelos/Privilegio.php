<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Privilegio — Modelo de dominio que representa un privilegio funcional granular (modulo.accion).
 */
class Privilegio
{
    private ?int $id;
    private string $codigo;
    private string $modulo;
    private string $accion;
    private string $nombre;
    private ?string $descripcion;
    private ?string $creadoEn;

    public function __construct(
        string $codigo,
        string $modulo,
        string $accion,
        string $nombre,
        ?string $descripcion = null,
        ?int $id = null,
        ?string $creadoEn = null
    ) {
        $this->setCodigo($codigo);
        $this->setModulo($modulo);
        $this->setAccion($accion);
        $this->setNombre($nombre);
        $this->descripcion = $descripcion;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['modulo'] ?? ''),
            (string) ($datos['accion'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'modulo' => $this->modulo,
            'accion' => $this->accion,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): void
    {
        $limpio = strtolower(trim($codigo));
        if ($limpio === '' || !str_contains($limpio, '.')) {
            throw new InvalidArgumentException("El código del privilegio debe tener el formato canónico 'modulo.accion': {$codigo}");
        }
        $this->codigo = $limpio;
    }

    public function obtenerModulo(): string
    {
        return $this->modulo;
    }

    public function setModulo(string $modulo): void
    {
        $limpio = strtolower(trim($modulo));
        if ($limpio === '') {
            throw new InvalidArgumentException('El módulo del privilegio no puede estar vacío.');
        }
        $this->modulo = $limpio;
    }

    public function obtenerAccion(): string
    {
        return $this->accion;
    }

    public function setAccion(string $accion): void
    {
        $limpio = strtolower(trim($accion));
        if ($limpio === '') {
            throw new InvalidArgumentException('La acción del privilegio no puede estar vacía.');
        }
        $this->accion = $limpio;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): void
    {
        $limpio = trim($nombre);
        if ($limpio === '') {
            throw new InvalidArgumentException('El nombre del privilegio no puede estar vacío.');
        }
        $this->nombre = $limpio;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }
}
