<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Rol — Modelo de dominio que representa un rol funcional en CasaPRO.
 */
class Rol
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public const ROL_SUPERADMIN = 'SUPERADMIN';

    private ?int $id;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private bool $esSistema;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        string $codigo,
        string $nombre,
        ?string $descripcion = null,
        bool $esSistema = false,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->setCodigo($codigo);
        $this->setNombre($nombre);
        $this->descripcion = $descripcion;
        $this->esSistema = $esSistema;
        $this->setEstado($estado);
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            (bool) ($datos['es_sistema'] ?? false),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'es_sistema' => $this->esSistema ? 1 : 0,
            'estado' => $this->estado,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
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
        $limpio = strtoupper(trim($codigo));
        if ($limpio === '') {
            throw new InvalidArgumentException('El código del rol no puede estar vacío.');
        }
        $this->codigo = $limpio;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): void
    {
        $limpio = trim($nombre);
        if ($limpio === '') {
            throw new InvalidArgumentException('El nombre del rol no puede estar vacío.');
        }
        $this->nombre = $limpio;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function esSistema(): bool
    {
        return $this->esSistema;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): void
    {
        $permitidos = [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO];
        $normalizado = strtoupper(trim($estado));
        if (!in_array($normalizado, $permitidos, true)) {
            throw new InvalidArgumentException("Estado de rol inválido: {$estado}");
        }
        $this->estado = $normalizado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }
}
