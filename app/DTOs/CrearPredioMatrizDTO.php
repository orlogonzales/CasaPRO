<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\ProyectoPredioMatriz;

/**
 * CrearPredioMatrizDTO — Valida los datos para dar de alta un Predio Matriz en un Proyecto.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class CrearPredioMatrizDTO
{
    private const CAMPOS_PERMITIDOS = [
        'proyecto_id',
        'denominacion',
        'partida_registral',
        'tomo_ficha',
        'area_registral_m2',
        'area_topografica_m2',
        'distrito_id',
        'antecedente_dominial',
        'poligono_geojson',
        'procedencia_topografica',
        'estado',
        'csrf_token',
        '_csrf_token'
    ];

    public int $proyectoId;
    public string $denominacion;
    public ?string $partidaRegistral = null;
    public ?string $tomoFicha = null;
    public float $areaRegistralM2;
    public ?float $areaTopograficaM2 = null;
    public int $distritoId;
    public ?string $antecedenteDominial = null;
    public ?array $poligonoGeojson = null;
    public ?array $procedenciaTopografica = null;
    public string $estado = ProyectoPredioMatriz::ESTADO_ACTIVO;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->proyectoId = $instancia->proyectoId;
            $this->denominacion = $instancia->denominacion;
            $this->partidaRegistral = $instancia->partidaRegistral;
            $this->tomoFicha = $instancia->tomoFicha;
            $this->areaRegistralM2 = $instancia->areaRegistralM2;
            $this->areaTopograficaM2 = $instancia->areaTopograficaM2;
            $this->distritoId = $instancia->distritoId;
            $this->antecedenteDominial = $instancia->antecedenteDominial;
            $this->poligonoGeojson = $instancia->poligonoGeojson;
            $this->procedenciaTopografica = $instancia->procedenciaTopografica;
            $this->estado = $instancia->estado;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en alta de predio matriz: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados detectados en la petición.']],
                422
            );
        }

        $errores = [];

        // 1. Proyecto ID
        $proyectoId = (int) ($datos['proyecto_id'] ?? 0);
        if ($proyectoId <= 0) {
            $errores['proyecto_id'][] = 'El proyecto_id debe ser un entero positivo.';
        }

        // 2. Denominación
        $denominacion = trim((string) ($datos['denominacion'] ?? ''));
        if ($denominacion === '') {
            $errores['denominacion'][] = 'La denominación del predio matriz es obligatoria.';
        } elseif (mb_strlen($denominacion) < 3 || mb_strlen($denominacion) > 150) {
            $errores['denominacion'][] = 'La denominación debe tener entre 3 y 150 caracteres.';
        }

        // 3. Partida Registral (GATE 3A-02: admite NULL, prohíbe ficticios)
        $partidaRegistral = null;
        if (isset($datos['partida_registral']) && trim((string) $datos['partida_registral']) !== '') {
            $limpio = trim((string) $datos['partida_registral']);
            $prohibidos = ['SIN PARTIDA', 'PENDIENTE', 'NO TIENE', 'S/P', 'NULL', 'NINGUNA'];
            if (in_array(strtoupper($limpio), $prohibidos, true)) {
                $errores['partida_registral'][] = 'Prohibido utilizar cadenas ficticias en partida registral. Si está en trámite, déjelo en blanco.';
            } else {
                $partidaRegistral = $limpio;
            }
        }

        // 4. Área Registral
        if (!isset($datos['area_registral_m2']) || !is_numeric($datos['area_registral_m2'])) {
            $errores['area_registral_m2'][] = 'El área registral en m² es obligatoria y debe ser numérica.';
        } else {
            $areaRegistral = (float) $datos['area_registral_m2'];
            if ($areaRegistral <= 0) {
                $errores['area_registral_m2'][] = 'El área registral debe ser estrictamente mayor a cero.';
            }
        }

        // 5. Área Topográfica (GATE 3A-03: admite NULL)
        $areaTopografica = null;
        if (isset($datos['area_topografica_m2']) && $datos['area_topografica_m2'] !== '' && $datos['area_topografica_m2'] !== null) {
            if (!is_numeric($datos['area_topografica_m2'])) {
                $errores['area_topografica_m2'][] = 'El área topográfica debe ser numérica.';
            } else {
                $areaTopografica = (float) $datos['area_topografica_m2'];
                if ($areaTopografica <= 0) {
                    $errores['area_topografica_m2'][] = 'El área topográfica, si se suministra, debe ser estrictamente mayor a cero.';
                }
            }
        }

        // 6. Distrito (UBIGEO)
        $distritoId = (int) ($datos['distrito_id'] ?? 0);
        if ($distritoId <= 0) {
            $errores['distrito_id'][] = 'Debe indicar un distrito válido del catálogo UBIGEO.';
        }

        // 7. Estado
        $estado = (string) ($datos['estado'] ?? ProyectoPredioMatriz::ESTADO_ACTIVO);
        if (!in_array($estado, [ProyectoPredioMatriz::ESTADO_ACTIVO, ProyectoPredioMatriz::ESTADO_INACTIVO], true)) {
            $errores['estado'][] = 'El estado debe ser ACTIVO o INACTIVO.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de predio matriz inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->proyectoId = $proyectoId;
        $dto->denominacion = $denominacion;
        $dto->partidaRegistral = $partidaRegistral;
        $dto->tomoFicha = isset($datos['tomo_ficha']) && trim((string) $datos['tomo_ficha']) !== '' ? trim((string) $datos['tomo_ficha']) : null;
        $dto->areaRegistralM2 = (float) $datos['area_registral_m2'];
        $dto->areaTopograficaM2 = $areaTopografica;
        $dto->distritoId = $distritoId;
        $dto->antecedenteDominial = isset($datos['antecedente_dominial']) && trim((string) $datos['antecedente_dominial']) !== '' ? trim((string) $datos['antecedente_dominial']) : null;
        $dto->poligonoGeojson = isset($datos['poligono_geojson']) && is_array($datos['poligono_geojson']) ? $datos['poligono_geojson'] : null;
        $dto->procedenciaTopografica = isset($datos['procedencia_topografica']) && is_array($datos['procedencia_topografica']) ? $datos['procedencia_topografica'] : null;
        $dto->estado = $estado;

        return $dto;
    }
}
