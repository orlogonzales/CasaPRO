<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * DTO para la consulta asistida de documentos de identidad (DNI / RUC).
 * Aplica allowlist estricta: rechaza cualquier campo no autorizado con HTTP 422.
 */
class ConsultaDocumentoDTO
{
    private const CAMPOS_PERMITIDOS = [
        'tipo_documento_id',
        'numero_documento'
    ];

    public int $tipoDocumentoId;
    public string $numeroDocumento;

    public static function desdeArray(array $datos): self
    {
        CrearPersonaDTO::validarCamposPermitidos($datos, self::CAMPOS_PERMITIDOS, 'ConsultaDocumento');

        $errores = [];

        // Validar tipo_documento_id
        if (!isset($datos['tipo_documento_id']) || !is_numeric($datos['tipo_documento_id'])) {
            $errores['tipo_documento_id'][] = 'El tipo de documento es obligatorio y debe ser un identificador numérico.';
        } else {
            $tipoId = (int) $datos['tipo_documento_id'];
            if ($tipoId <= 0) {
                $errores['tipo_documento_id'][] = 'El tipo de documento debe ser mayor a 0.';
            }
        }

        // Validar numero_documento
        if (empty($datos['numero_documento']) || !is_string($datos['numero_documento'])) {
            $errores['numero_documento'][] = 'El número de documento es obligatorio.';
        } else {
            $numDoc = trim($datos['numero_documento']);
            if (strlen($numDoc) < 4 || strlen($numDoc) > 30) {
                $errores['numero_documento'][] = 'El número de documento debe tener entre 4 y 30 caracteres.';
            }
            if (!preg_match('/^[a-zA-Z0-9]+$/', $numDoc)) {
                $errores['numero_documento'][] = 'El número de documento solo puede contener caracteres alfanuméricos.';
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación en los parámetros de consulta documental.', $errores, 422);
        }

        $dto = new self();
        $dto->tipoDocumentoId = (int) $datos['tipo_documento_id'];
        $dto->numeroDocumento = strtoupper(trim((string) $datos['numero_documento']));

        return $dto;
    }
}
