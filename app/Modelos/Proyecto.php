<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Proyecto — Modelo de dominio que representa un desarrollo inmobiliario bajo una Empresa.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class Proyecto
{
    public const TIPO_PROPIO = 'PROPIO';
    public const TIPO_CONVENIO_APV = 'CONVENIO_APV';
    public const TIPO_ASOCIATIVO = 'ASOCIATIVO';

    public const MONEDA_PEN = 'PEN';
    public const MONEDA_USD = 'USD';

    public const TOLERANCIA_ABSOLUTA_M2 = 'ABSOLUTA_M2';
    public const TOLERANCIA_PORCENTUAL = 'PORCENTUAL';

    public const ESTADO_PLANIFICACION = 'PLANIFICACION';
    public const ESTADO_EN_VENTA = 'EN_VENTA';
    public const ESTADO_CONSOLIDADO = 'CONSOLIDADO';
    public const ESTADO_CERRADO = 'CERRADO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $empresaId;
    private string $codigo;
    private string $nombre;
    private ?string $descripcion;
    private string $tipoProyecto;
    private string $moneda;
    private int $distritoId;
    private ?string $direccionReferencia;
    private string $tipoTolerancia;
    private float $valorTolerancia;
    private ?float $latitud;
    private ?float $longitud;
    private int $zoomMapa;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $empresaId,
        string $codigo,
        string $nombre,
        int $distritoId,
        string $tipoProyecto = self::TIPO_PROPIO,
        string $moneda = self::MONEDA_PEN,
        string $tipoTolerancia = self::TOLERANCIA_ABSOLUTA_M2,
        float $valorTolerancia = 0.5000,
        string $estado = self::ESTADO_PLANIFICACION,
        ?string $descripcion = null,
        ?string $direccionReferencia = null,
        ?float $latitud = null,
        ?float $longitud = null,
        int $zoomMapa = 16,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('El ID de empresa debe ser un entero positivo.');
        }
        if ($distritoId <= 0) {
            throw new InvalidArgumentException('El ID de distrito (UBIGEO) debe ser un entero positivo.');
        }

        $this->empresaId = $empresaId;
        $this->setCodigo($codigo);
        $this->setNombre($nombre);
        $this->distritoId = $distritoId;
        $this->setTipoProyecto($tipoProyecto);
        $this->setMoneda($moneda);
        $this->setPoliticaTolerancia($tipoTolerancia, $valorTolerancia);
        $this->setEstado($estado);
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        $this->direccionReferencia = $direccionReferencia !== null ? trim($direccionReferencia) : null;
        $this->latitud = $latitud;
        $this->longitud = $longitud;
        $this->zoomMapa = max(1, min(22, $zoomMapa));
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): self
    {
        if ($this->id !== null) {
            throw new InvalidArgumentException('No se puede reasignar el ID de un proyecto ya persistido.');
        }
        $this->id = $id;
        return $this;
    }

    public function obtenerEmpresaId(): int
    {
        return $this->empresaId;
    }

    public function obtenerCodigo(): string
    {
        return $this->codigo;
    }

    public function obtenerNombre(): string
    {
        return $this->nombre;
    }

    public function establecerNombre(string $nombre): self
    {
        $this->setNombre($nombre);
        return $this;
    }

    public function obtenerDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function establecerDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion !== null ? trim($descripcion) : null;
        return $this;
    }

    public function obtenerTipoProyecto(): string
    {
        return $this->tipoProyecto;
    }

    public function establecerTipoProyecto(string $tipo): self
    {
        $this->setTipoProyecto($tipo);
        return $this;
    }

    public function obtenerMoneda(): string
    {
        return $this->moneda;
    }

    public function establecerMoneda(string $moneda): self
    {
        $this->setMoneda($moneda);
        return $this;
    }

    public function obtenerDistritoId(): int
    {
        return $this->distritoId;
    }

    public function establecerDistritoId(int $distritoId): self
    {
        if ($distritoId <= 0) {
            throw new InvalidArgumentException('El ID de distrito debe ser positivo.');
        }
        $this->distritoId = $distritoId;
        return $this;
    }

    public function obtenerDireccionReferencia(): ?string
    {
        return $this->direccionReferencia;
    }

    public function establecerDireccionReferencia(?string $direccion): self
    {
        $this->direccionReferencia = $direccion !== null ? trim($direccion) : null;
        return $this;
    }

    public function obtenerTipoTolerancia(): string
    {
        return $this->tipoTolerancia;
    }

    public function obtenerValorTolerancia(): float
    {
        return $this->valorTolerancia;
    }

    public function establecerPoliticaTolerancia(string $tipo, float $valor): self
    {
        $this->setPoliticaTolerancia($tipo, $valor);
        return $this;
    }

    public function obtenerLatitud(): ?float
    {
        return $this->latitud;
    }

    public function obtenerLongitud(): ?float
    {
        return $this->longitud;
    }

    public function establecerCoordenadas(?float $latitud, ?float $longitud, ?int $zoom = null): self
    {
        $this->latitud = $latitud;
        $this->longitud = $longitud;
        if ($zoom !== null) {
            $this->zoomMapa = max(1, min(22, $zoom));
        }
        return $this;
    }

    public function obtenerZoomMapa(): int
    {
        return $this->zoomMapa;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function establecerEstado(string $estado): self
    {
        $this->setEstado($estado);
        return $this;
    }

    public function estaActivo(): bool
    {
        return $this->estado !== self::ESTADO_INACTIVO && $this->estado !== self::ESTADO_CERRADO;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    public function toArray(): array
    {
        return [
            'id'                   => $this->id,
            'empresa_id'           => $this->empresaId,
            'codigo'               => $this->codigo,
            'nombre'               => $this->nombre,
            'descripcion'          => $this->descripcion,
            'tipo_proyecto'        => $this->tipoProyecto,
            'moneda'               => $this->moneda,
            'distrito_id'          => $this->distritoId,
            'direccion_referencia' => $this->direccionReferencia,
            'tipo_tolerancia'      => $this->tipoTolerancia,
            'valor_tolerancia'     => $this->valorTolerancia,
            'latitud'              => $this->latitud,
            'longitud'             => $this->longitud,
            'zoom_mapa'            => $this->zoomMapa,
            'estado'               => $this->estado,
            'creado_en'            => $this->creadoEn,
            'actualizado_en'       => $this->actualizadoEn
        ];
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['empresa_id'] ?? 0),
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
            (int) ($datos['distrito_id'] ?? 0),
            (string) ($datos['tipo_proyecto'] ?? self::TIPO_PROPIO),
            (string) ($datos['moneda'] ?? self::MONEDA_PEN),
            (string) ($datos['tipo_tolerancia'] ?? self::TOLERANCIA_ABSOLUTA_M2),
            (float) ($datos['valor_tolerancia'] ?? 0.5000),
            (string) ($datos['estado'] ?? self::ESTADO_PLANIFICACION),
            isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
            isset($datos['direccion_referencia']) ? (string) $datos['direccion_referencia'] : null,
            isset($datos['latitud']) && $datos['latitud'] !== null ? (float) $datos['latitud'] : null,
            isset($datos['longitud']) && $datos['longitud'] !== null ? (float) $datos['longitud'] : null,
            isset($datos['zoom_mapa']) ? (int) $datos['zoom_mapa'] : 16,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    private function setCodigo(string $codigo): void
    {
        $limpio = strtoupper(trim($codigo));
        if ($limpio === '' || strlen($limpio) < 3 || strlen($limpio) > 32) {
            throw new InvalidArgumentException('El código del proyecto debe tener entre 3 y 32 caracteres.');
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $limpio)) {
            throw new InvalidArgumentException('El código del proyecto solo admite letras mayúsculas, números y guiones bajos.');
        }
        $this->codigo = $limpio;
    }

    private function setNombre(string $nombre): void
    {
        $limpio = trim($nombre);
        if ($limpio === '' || mb_strlen($limpio) < 3 || mb_strlen($limpio) > 150) {
            throw new InvalidArgumentException('El nombre del proyecto debe tener entre 3 y 150 caracteres.');
        }
        $this->nombre = $limpio;
    }

    private function setTipoProyecto(string $tipo): void
    {
        $permitidos = [self::TIPO_PROPIO, self::TIPO_CONVENIO_APV, self::TIPO_ASOCIATIVO];
        if (!in_array($tipo, $permitidos, true)) {
            throw new InvalidArgumentException('El tipo de proyecto debe ser PROPIO, CONVENIO_APV o ASOCIATIVO.');
        }
        $this->tipoProyecto = $tipo;
    }

    private function setMoneda(string $moneda): void
    {
        $permitidas = [self::MONEDA_PEN, self::MONEDA_USD];
        if (!in_array($moneda, $permitidas, true)) {
            throw new InvalidArgumentException('La moneda debe ser PEN o USD.');
        }
        $this->moneda = $moneda;
    }

    private function setPoliticaTolerancia(string $tipo, float $valor): void
    {
        $permitidos = [self::TOLERANCIA_ABSOLUTA_M2, self::TOLERANCIA_PORCENTUAL];
        if (!in_array($tipo, $permitidos, true)) {
            throw new InvalidArgumentException('El tipo de tolerancia debe ser ABSOLUTA_M2 o PORCENTUAL.');
        }
        if ($valor < 0) {
            throw new InvalidArgumentException('El valor de tolerancia no puede ser negativo.');
        }
        $this->tipoTolerancia = $tipo;
        $this->valorTolerancia = $valor;
    }

    private function setEstado(string $estado): void
    {
        $permitidos = [
            self::ESTADO_PLANIFICACION,
            self::ESTADO_EN_VENTA,
            self::ESTADO_CONSOLIDADO,
            self::ESTADO_CERRADO,
            self::ESTADO_INACTIVO
        ];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('Estado de proyecto inválido: ' . $estado);
        }
        $this->estado = $estado;
    }
}
