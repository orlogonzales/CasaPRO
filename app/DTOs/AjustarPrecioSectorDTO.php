<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * AjustarPrecioSectorDTO — Valida los datos para fijar un nuevo precio por m2 en un Sector.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class AjustarPrecioSectorDTO
{
    private const CAMPOS_PERMITIDOS = [
        'precio_m2_base',
        'fecha_inicio',
        'motivo',
        'csrf_token',
        '_csrf_token'
    ];

    public float $precioM2Base;
    public string $fechaInicio;
    public string $motivo;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->precioM2Base = $instancia->precioM2Base;
            $this->fechaInicio = $instancia->fechaInicio;
            $this->motivo = $instancia->motivo;
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

        // 2. precio_m2_base
        if (!isset($datos['precio_m2_base']) || !is_numeric($datos['precio_m2_base']) || (float) $datos['precio_m2_base'] <= 0) {
            $errores['precio_m2_base'] = 'El precio base por m² es obligatorio y debe ser mayor a cero.';
        } else {
            $dto->precioM2Base = round((float) $datos['precio_m2_base'], 4);
        }

        // 3. fecha_inicio
        $fechaInicio = trim((string) ($datos['fecha_inicio'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $errores['fecha_inicio'] = 'La fecha de inicio debe tener el formato YYYY-MM-DD.';
        } else {
            $dto->fechaInicio = $fechaInicio;
        }

        // 4. motivo
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $errores['motivo'] = 'Debe indicar el motivo o justificación del nuevo precio.';
        } elseif (strlen($motivo) < 3 || strlen($motivo) > 255) {
            $errores['motivo'] = 'El motivo debe tener entre 3 y 255 caracteres.';
        } else {
            $dto->motivo = $motivo;
        }

        if (!empty($errores)) {
            $primerError = is_array(reset($errores)) ? reset($errores)[0] : reset($errores);
            throw new ValidacionExcepcion((string) $primerError, $errores, 422);
        }

        return $dto;
    }
}
