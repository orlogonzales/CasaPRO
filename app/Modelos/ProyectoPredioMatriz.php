<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * ProyectoPredioMatriz — Modelo de dominio que representa un predio o partida registral de origen.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class ProyectoPredioMatriz
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $proyectoId;
    private string $denominacion;
    private ?string $partidaRegistral;
    private ?string $tomoFicha;
    private float $areaRegistralM2;
    private ?float $areaTopograficaM2;
    private int $distritoId;
    private ?string $antecedenteDominial;
    private ?array $poligonoGeojson;
    private ?array $procedenciaTopografica;
    private string $estado;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $proyectoId,
        string $denominacion,
        float $areaRegistralM2,
        int $distritoId,
        ?string $partidaRegistral = null,
        ?string $tomoFicha = null,
        ?float $areaTopograficaM2 = null,
        ?string $antecedenteDominial = null,
        ?array $poligonoGeojson = null,
        ?array $procedenciaTopografica = null,
        string $estado = self::ESTADO_ACTIVO,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($proyectoId <= 0) {
            throw new InvalidArgumentException('El ID de proyecto debe ser un entero positivo.');
        }
        if ($distritoId <= 0) {
            throw new InvalidArgumentException('El ID de distrito debe ser un entero positivo.');
        }
        if ($areaRegistralM2 <= 0) {
            throw new InvalidArgumentException('El área registral debe ser estrictamente mayor a cero.');
        }
        if ($areaTopograficaM2 !== null && $areaTopograficaM2 <= 0) {
            throw new InvalidArgumentException('El área topográfica, de existir, debe ser mayor a cero.');
        }

        $this->proyectoId = $proyectoId;
        $this->setDenominacion($denominacion);
        $this->setPartidaRegistral($partidaRegistral);
        $this->tomoFicha = $tomoFicha !== null ? trim($tomoFicha) : null;
        $this->areaRegistralM2 = $areaRegistralM2;
        $this->areaTopograficaM2 = $areaTopograficaM2;
        $this->distritoId = $distritoId;
        $this->antecedenteDominial = $antecedenteDominial !== null ? trim($antecedenteDominial) : null;
        $this->poligonoGeojson = $poligonoGeojson;
        $this->procedenciaTopografica = $procedenciaTopografica;
        $this->setEstado($estado);
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
            throw new InvalidArgumentException('No se puede reasignar el ID de un predio ya persistido.');
        }
        $this->id = $id;
        return $this;
    }

    public function obtenerProyectoId(): int
    {
        return $this->proyectoId;
    }

    public function obtenerDenominacion(): string
    {
        return $this->denominacion;
    }

    public function establecerDenominacion(string $denominacion): self
    {
        $this->setDenominacion($denominacion);
        return $this;
    }

    public function obtenerPartidaRegistral(): ?string
    {
        return $this->partidaRegistral;
    }

    public function establecerPartidaRegistral(?string $partida): self
    {
        $this->setPartidaRegistral($partida);
        return $this;
    }

    public function tienePartidaRegistral(): bool
    {
        return $this->partidaRegistral !== null && $this->partidaRegistral !== '';
    }

    public function obtenerTomoFicha(): ?string
    {
        return $this->tomoFicha;
    }

    public function establecerTomoFicha(?string $tomoFicha): self
    {
        $this->tomoFicha = $tomoFicha !== null ? trim($tomoFicha) : null;
        return $this;
    }

    public function obtenerAreaRegistralM2(): float
    {
        return $this->areaRegistralM2;
    }

    public function establecerAreaRegistralM2(float $area): self
    {
        if ($area <= 0) {
            throw new InvalidArgumentException('El área registral debe ser estrictamente mayor a cero.');
        }
        $this->areaRegistralM2 = $area;
        return $this;
    }

    public function obtenerAreaTopograficaM2(): ?float
    {
        return $this->areaTopograficaM2;
    }

    public function establecerAreaTopograficaM2(?float $area): self
    {
        if ($area !== null && $area <= 0) {
            throw new InvalidArgumentException('El área topográfica debe ser estrictamente mayor a cero.');
        }
        $this->areaTopograficaM2 = $area;
        return $this;
    }

    public function tieneLevantamientoTopografico(): bool
    {
        return $this->areaTopograficaM2 !== null && $this->areaTopograficaM2 > 0;
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

    public function obtenerAntecedenteDominial(): ?string
    {
        return $this->antecedenteDominial;
    }

    public function establecerAntecedenteDominial(?string $antecedente): self
    {
        $this->antecedenteDominial = $antecedente !== null ? trim($antecedente) : null;
        return $this;
    }

    public function obtenerPoligonoGeojson(): ?array
    {
        return $this->poligonoGeojson;
    }

    public function establecerPoligonoGeojson(?array $geojson): self
    {
        $this->poligonoGeojson = $geojson;
        return $this;
    }

    public function obtenerProcedenciaTopografica(): ?array
    {
        return $this->procedenciaTopografica;
    }

    public function establecerProcedenciaTopografica(?array $procedencia): self
    {
        $this->procedenciaTopografica = $procedencia;
        return $this;
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
        return $this->estado === self::ESTADO_ACTIVO;
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
            'id'                     => $this->id,
            'proyecto_id'            => $this->proyectoId,
            'denominacion'           => $this->denominacion,
            'partida_registral'      => $this->partidaRegistral,
            'tomo_ficha'             => $this->tomoFicha,
            'area_registral_m2'      => $this->areaRegistralM2,
            'area_topografica_m2'    => $this->areaTopograficaM2,
            'distrito_id'            => $this->distritoId,
            'antecedente_dominial'   => $this->antecedenteDominial,
            'poligono_geojson'       => $this->poligonoGeojson,
            'procedencia_topografica'=> $this->procedenciaTopografica,
            'estado'                 => $this->estado,
            'creado_en'              => $this->creadoEn,
            'actualizado_en'         => $this->actualizadoEn
        ];
    }

    public static function desdeArray(array $datos): self
    {
        $geojson = null;
        if (isset($datos['poligono_geojson'])) {
            $geojson = is_string($datos['poligono_geojson'])
                ? json_decode($datos['poligono_geojson'], true)
                : (is_array($datos['poligono_geojson']) ? $datos['poligono_geojson'] : null);
        }

        $procedencia = null;
        if (isset($datos['procedencia_topografica'])) {
            $procedencia = is_string($datos['procedencia_topografica'])
                ? json_decode($datos['procedencia_topografica'], true)
                : (is_array($datos['procedencia_topografica']) ? $datos['procedencia_topografica'] : null);
        }

        return new self(
            (int) ($datos['proyecto_id'] ?? 0),
            (string) ($datos['denominacion'] ?? ''),
            (float) ($datos['area_registral_m2'] ?? 0),
            (int) ($datos['distrito_id'] ?? 0),
            isset($datos['partida_registral']) && trim((string) $datos['partida_registral']) !== ''
                ? trim((string) $datos['partida_registral'])
                : null,
            isset($datos['tomo_ficha']) && trim((string) $datos['tomo_ficha']) !== ''
                ? trim((string) $datos['tomo_ficha'])
                : null,
            isset($datos['area_topografica_m2']) && $datos['area_topografica_m2'] !== null
                ? (float) $datos['area_topografica_m2']
                : null,
            isset($datos['antecedente_dominial']) ? (string) $datos['antecedente_dominial'] : null,
            $geojson,
            $procedencia,
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    private function setDenominacion(string $denominacion): void
    {
        $limpio = trim($denominacion);
        if ($limpio === '' || mb_strlen($limpio) < 3 || mb_strlen($limpio) > 150) {
            throw new InvalidArgumentException('La denominación del predio matriz debe tener entre 3 y 150 caracteres.');
        }
        $this->denominacion = $limpio;
    }

    private function setPartidaRegistral(?string $partida): void
    {
        if ($partida === null) {
            $this->partidaRegistral = null;
            return;
        }
        $limpio = trim($partida);
        if ($limpio === '') {
            $this->partidaRegistral = null;
            return;
        }
        // Rechazo de cadenas ficticias prohibidas
        $prohibidas = ['SIN PARTIDA', 'PENDIENTE', 'NO TIENE', 'S/P', 'NULL', 'NINGUNA'];
        if (in_array(strtoupper($limpio), $prohibidas, true)) {
            throw new InvalidArgumentException('Prohibido utilizar cadenas ficticias en partida registral. Si el predio está en saneamiento, debe ser NULL.');
        }
        $this->partidaRegistral = $limpio;
    }

    private function setEstado(string $estado): void
    {
        $permitidos = [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException('Estado de predio matriz inválido: ' . $estado);
        }
        $this->estado = $estado;
    }
}
