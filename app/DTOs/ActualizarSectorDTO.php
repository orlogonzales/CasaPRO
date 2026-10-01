<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * ActualizarSectorDTO — Valida los datos para actualizar la configuración de un Sector.
 * El código del sector es inmutable y no forma parte de este DTO.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class ActualizarSectorDTO
{
    private const CAMPOS_PERMITIDOS = [
        'nombre',
        'descripcion',
        'area_bruta_m2',
        'area_util_m2',
        'area_cesion_m2',
        'area_comun_m2',
        'orden',
        'csrf_token',
        '_csrf_token'
    ];

    public string $nombre;
    public ?string $descripcion = null;
    public float $areaBrutaM2;
    public float $areaUtilM2 = 0.0000;
    public float $areaCesionM2 = 0.0000;
    public float $areaComunM2 = 0.0000;
    public int $orden = 1;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->nombre = $instancia->nombre;
            $this->descripcion = $instancia->descripcion;
            $this->areaBrutaM2 = $instancia->areaBrutaM2;
            $this->areaUtilM2 = $instancia->areaUtilM2;
            $this->areaCesionM2 = $instancia->areaCesionM2;
            $this->areaComunM2 = $instancia->areaComunM2;
            $this->orden = $instancia->orden;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $errores = [];

        // 1. Blindaje Anti-Polución
        $camposDesconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($camposDesconocidos)) {
            throw new ValidacionExcepcion(
                'Se detectaron campos no permitidos en el formulario: ' . implode(', ', $camposDesconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $dto = new self();

        // 2. nombre
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'] = 'El nombre del sector es obligatorio.';
        } elseif (strlen($nombre) < 3 || strlen($nombre) > 150) {
            $errores['nombre'] = 'El nombre del sector debe tener entre 3 y 150 caracteres.';
        } else {
            $dto->nombre = $nombre;
        }

        // 3. descripcion
        if (isset($datos['descripcion'])) {
            $dto->descripcion = trim((string) $datos['descripcion']) ?: null;
        }

        // 4. area_bruta_m2
        if (!isset($datos['area_bruta_m2']) || !is_numeric($datos['area_bruta_m2']) || (float) $datos['area_bruta_m2'] <= 0) {
            $errores['area_bruta_m2'] = 'El área bruta del sector es obligatoria y debe ser mayor a 0 m².';
        } else {
            $dto->areaBrutaM2 = round((float) $datos['area_bruta_m2'], 4);
        }

        // 5. area_util_m2
        if (isset($datos['area_util_m2']) && $datos['area_util_m2'] !== '') {
            if (!is_numeric($datos['area_util_m2']) || (float) $datos['area_util_m2'] < 0) {
                $errores['area_util_m2'] = 'El área útil debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaUtilM2 = round((float) $datos['area_util_m2'], 4);
            }
        }

        // 6. area_cesion_m2
        if (isset($datos['area_cesion_m2']) && $datos['area_cesion_m2'] !== '') {
            if (!is_numeric($datos['area_cesion_m2']) || (float) $datos['area_cesion_m2'] < 0) {
                $errores['area_cesion_m2'] = 'El área de cesión debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaCesionM2 = round((float) $datos['area_cesion_m2'], 4);
            }
        }

        // 7. area_comun_m2
        if (isset($datos['area_comun_m2']) && $datos['area_comun_m2'] !== '') {
            if (!is_numeric($datos['area_comun_m2']) || (float) $datos['area_comun_m2'] < 0) {
                $errores['area_comun_m2'] = 'El área común debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaComunM2 = round((float) $datos['area_comun_m2'], 4);
            }
        }

        // Invariante local de consistencia interna
        if (empty($errores)) {
            $sumaInterna = round($dto->areaUtilM2 + $dto->areaCesionM2 + $dto->areaComunM2, 4);
            if ($sumaInterna > ($dto->areaBrutaM2 + 0.0001)) {
                $errores['area_bruta_m2'] = "La suma de área útil ({$dto->areaUtilM2} m²), cesión ({$dto->areaCesionM2} m²) y común ({$dto->areaComunM2} m²) totaliza {$sumaInterna} m², lo cual excede el área bruta asignada ({$dto->areaBrutaM2} m²).";
            }
        }

        // 8. orden
        if (isset($datos['orden']) && $datos['orden'] !== '') {
            if (!is_numeric($datos['orden']) || (int) $datos['orden'] < 1) {
                $errores['orden'] = 'El orden de visualización debe ser un número entero mayor o igual a 1.';
            } else {
                $dto->orden = (int) $datos['orden'];
            }
        }

        if (!empty($errores)) {
            $primerError = is_array(reset($errores)) ? reset($errores)[0] : reset($errores);
            throw new ValidacionExcepcion((string) $primerError, $errores, 422);
        }

        return $dto;
    }
}
