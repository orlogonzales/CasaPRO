<?php

declare(strict_types=1);

namespace App\Modelos;

/**
 * Modelo de Dominio: MenuOpcion
 * Representa una opción o agrupador en el árbol de navegación dinámica.
 */
class MenuOpcion
{
    public const TIPO_AGRUPADOR = 'AGRUPADOR';
    public const TIPO_ENLACE = 'ENLACE';

    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    public function __construct(
        private ?int $id,
        private ?int $padreId,
        private string $tipo,
        private string $codigo,
        private string $etiqueta,
        private ?string $ruta,
        private ?string $icono,
        private int $orden,
        private ?int $privilegioId,
        private string $estado,
        private int $visible,
        private ?string $creadoEn = null,
        private ?string $actualizadoEn = null
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            isset($datos['id']) ? (int) $datos['id'] : null,
            isset($datos['padre_id']) && $datos['padre_id'] !== null && $datos['padre_id'] !== '' ? (int) $datos['padre_id'] : null,
            (string) ($datos['tipo'] ?? self::TIPO_ENLACE),
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['etiqueta'] ?? ''),
            isset($datos['ruta']) && $datos['ruta'] !== null && $datos['ruta'] !== '' ? (string) $datos['ruta'] : null,
            isset($datos['icono']) && $datos['icono'] !== null && $datos['icono'] !== '' ? (string) $datos['icono'] : null,
            isset($datos['orden']) ? (int) $datos['orden'] : 1,
            isset($datos['privilegio_id']) && $datos['privilegio_id'] !== null && $datos['privilegio_id'] !== '' ? (int) $datos['privilegio_id'] : null,
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['visible']) ? (int) $datos['visible'] : 1,
            isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    public function aArray(): array
    {
        return [
            'id'             => $this->id,
            'padre_id'       => $this->padreId,
            'tipo'           => $this->tipo,
            'codigo'         => $this->codigo,
            'etiqueta'       => $this->etiqueta,
            'titulo'         => $this->etiqueta,
            'ruta'           => $this->ruta,
            'icono'          => $this->icono,
            'orden'          => $this->orden,
            'privilegio_id'  => $this->privilegioId,
            'estado'         => $this->estado,
            'visible'        => $this->visible,
            'creado_en'      => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function obtenerPadreId(): ?int
    {
        return $this->padreId;
    }

    public function obtenerTipo(): string
    {
        return $this->tipo;
    }

    public function esAgrupador(): bool
    {
        return $this->tipo === self::TIPO_AGRUPADOR;
    }

    public function esEnlace(): bool
    {
        return $this->tipo === self::TIPO_ENLACE;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerEtiqueta(): string
    {
        return $this->etiqueta;
    }

    public function obtenerRuta(): ?string
    {
        return $this->ruta;
    }

    public function obtenerIcono(): ?string
    {
        return $this->icono;
    }

    public function obtenerOrden(): int
    {
        return $this->orden;
    }

    public function obtenerPrivilegioId(): ?int
    {
        return $this->privilegioId;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerVisible(): int
    {
        return $this->visible;
    }

    public function esVisible(): bool
    {
        return $this->visible === 1;
    }
}
