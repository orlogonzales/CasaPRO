<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * CrearEmpresaDTO — Encapsula y valida los datos para el alta de una entidad corporativa.
 * 
 * Admite dos modalidades de alta:
 *   1. Vinculación: Se suministra `persona_id` de una Persona Jurídica existente en el padrón.
 *   2. Orquestación: Se suministra `datos_persona` para dar de alta la Persona Jurídica de forma transaccional.
 */
class CrearEmpresaDTO
{
    private const CAMPOS_PERMITIDOS = [
        'persona_id',
        'datos_persona',
        'persona',
        'codigo',
        'nombre_corto',
        'csrf_token',
        '_csrf_token'
    ];

    public ?int $personaId = null;
    public ?array $datosPersona = null;
    public string $codigo;
    public string $nombreCorto;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->personaId = $instancia->personaId;
            $this->datosPersona = $instancia->datosPersona;
            $this->codigo = $instancia->codigo;
            $this->nombreCorto = $instancia->nombreCorto;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en el alta de empresa: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        // 1. Identificación de modalidad
        $personaId = isset($datos['persona_id']) && $datos['persona_id'] !== '' ? (int) $datos['persona_id'] : null;
        $datosPersona = null;
        if (isset($datos['datos_persona']) && is_array($datos['datos_persona'])) {
            $datosPersona = $datos['datos_persona'];
        } elseif (isset($datos['persona']) && is_array($datos['persona'])) {
            $datosPersona = $datos['persona'];
        }

        if ($personaId === null && $datosPersona === null) {
            $errores['persona_id'][] = 'Debe indicar un persona_id existente o suministrar datos_persona para su creación.';
        } elseif ($personaId !== null && $datosPersona !== null) {
            $errores['persona_id'][] = 'No puede enviar persona_id y datos_persona simultáneamente. Elija vinculación u orquestación.';
        } elseif ($personaId !== null && $personaId <= 0) {
            $errores['persona_id'][] = 'El persona_id debe ser un número entero positivo.';
        }

        // 2. Validación de código canónico corporativo
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        if ($codigo === '') {
            $errores['codigo'][] = 'El código de empresa es obligatorio.';
        } elseif (strlen($codigo) < 3 || strlen($codigo) > 32) {
            $errores['codigo'][] = 'El código de empresa debe tener entre 3 y 32 caracteres.';
        } elseif (!preg_match('/^[A-Z0-9_]+$/', $codigo)) {
            $errores['codigo'][] = 'El código de empresa solo puede contener letras mayúsculas, números y guiones bajos (ej: CASAPRO_SAC).';
        }

        // 3. Validación de nombre corto
        $nombreCorto = trim((string) ($datos['nombre_corto'] ?? ''));
        if ($nombreCorto === '') {
            $errores['nombre_corto'][] = 'El nombre corto de empresa es obligatorio.';
        } elseif (strlen($nombreCorto) < 2 || strlen($nombreCorto) > 64) {
            $errores['nombre_corto'][] = 'El nombre corto debe tener entre 2 y 64 caracteres.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de empresa inválidos o incompletos.', $errores, 422);
        }

        $dto = new self();
        $dto->personaId = $personaId;
        $dto->datosPersona = $datosPersona;
        $dto->codigo = $codigo;
        $dto->nombreCorto = $nombreCorto;

        return $dto;
    }

    public function esModalidadOrquestada(): bool
    {
        return $this->personaId === null && is_array($this->datosPersona);
    }
}
