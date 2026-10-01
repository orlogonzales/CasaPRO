<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * ActualizarPredioMatrizDTO — Valida la actualización de un Predio Matriz existente.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class ActualizarPredioMatrizDTO
{
    private const CAMPOS_PERMITIDOS = [
        'denominacion',
        'partida_registral',
        'tomo_ficha',
        'area_registral_m2',
        'area_topografica_m2',
        'distrito_id',
        'antecedente_dominial',
        'poligono_geojson',
        'procedencia_topografica',
        'csrf_token',
        '_csrf_token'
    ];

    public string $denominacion;
    public ?string $partidaRegistral = null;
    public ?string $tomoFicha = null;
    public float $areaRegistralM2;
    public ?float $areaTopograficaM2 = null;
    public int $distritoId;
    public ?string $antecedenteDominial = null;
    public ?array $poligonoGeojson = null;
    public ?array $procedenciaTopografica = null;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->denominacion = $instancia->denominacion;
            $this->partidaRegistral = $instancia->partidaRegistral;
            $this->tomoFicha = $instancia->tomoFicha;
            $this->areaRegistralM2 = $instancia->areaRegistralM2;
            $this->areaTopograficaM2 = $instancia->areaTopograficaM2;
            $this->distritoId = $instancia->distritoId;
            $this->antecedenteDominial = $instancia->antecedenteDominial;
            $this->poligonoGeojson = $instancia->poligonoGeojson;
            $this->procedenciaTopografica = $instancia->procedenciaTopografica;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la actualización del predio matriz: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados detectados en la petición.']],
                422
            );
        }

        $errores = [];

        // 1. Denominación
        $denominacion = trim((string) ($datos['denominacion'] ?? ''));
        if ($denominacion === '') {
            $errores['denominacion'][] = 'La denominación del predio matriz es obligatoria.';
        } elseif (mb_strlen($denominacion) < 3 || mb_strlen($denominacion) > 150) {
            $errores['denominacion'][] = 'La denominación debe tener entre 3 y 150 caracteres.';
        }

        // 2. Partida Registral
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

        // 3. Área Registral
        if (!isset($datos['area_registral_m2']) || !is_numeric($datos['area_registral_m2'])) {
            $errores['area_registral_m2'][] = 'El área registral en m² es obligatoria y debe ser numérica.';
        } else {
            $areaRegistral = (float) $datos['area_registral_m2'];
            if ($areaRegistral <= 0) {
                $errores['area_registral_m2'][] = 'El área registral debe ser estrictamente mayor a cero.';
            }
        }

        // 4. Área Topográfica
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

        // 5. Distrito
        $distritoId = (int) ($datos['distrito_id'] ?? 0);
        if ($distritoId <= 0) {
            $errores['distrito_id'][] = 'Debe indicar un distrito válido del catálogo UBIGEO.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de predio matriz inválidos.', $errores, 422);
        }

        $dto = new self();
        $dto->denominacion = $denominacion;
        $dto->partidaRegistral = $partidaRegistral;
        $dto->tomoFicha = isset($datos['tomo_ficha']) && trim((string) $datos['tomo_ficha']) !== '' ? trim((string) $datos['tomo_ficha']) : null;
        $dto->areaRegistralM2 = (float) $datos['area_registral_m2'];
        $dto->areaTopograficaM2 = $areaTopografica;
        $dto->distritoId = $distritoId;
        $dto->antecedenteDominial = isset($datos['antecedente_dominial']) && trim((string) $datos['antecedente_dominial']) !== '' ? trim((string) $datos['antecedente_dominial']) : null;
        $dto->poligonoGeojson = isset($datos['poligono_geojson']) && is_array($datos['poligono_geojson']) ? $datos['poligono_geojson'] : null;
        $dto->procedenciaTopografica = isset($datos['procedencia_topografica']) && is_array($datos['procedencia_topografica']) ? $datos['procedencia_topografica'] : null;

        return $dto;
    }
}
