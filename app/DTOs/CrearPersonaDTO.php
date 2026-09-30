<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * DTO para la creación de Personas (Natural o Jurídica).
 * Aplica allowlist estricta: cualquier clave no permitida en raíz o colecciones detona HTTP 422.
 */
class CrearPersonaDTO
{
    private const CAMPOS_PERMITIDOS_RAIZ = [
        'tipo_persona',
        'notas',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'sexo_id',
        'estado_civil_id',
        'pais_nacimiento_id',
        'profesion_ocupacion',
        'razon_social',
        'nombre_comercial',
        'fecha_constitucion',
        'objeto_social',
        'documentos',
        'contactos',
        'direcciones',
        'representantes'
    ];

    private const CAMPOS_PERMITIDOS_DOCUMENTO = [
        'tipo_documento_id',
        'numero_documento',
        'es_principal',
        'pais_emision_id',
        'fecha_emision',
        'fecha_vencimiento',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_CONTACTO = [
        'tipo_contacto_id',
        'valor',
        'etiqueta',
        'es_principal',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_DIRECCION = [
        'tipo_direccion_id',
        'distrito_id',
        'direccion',
        'referencia',
        'codigo_postal',
        'es_principal',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_REPRESENTANTE = [
        'persona_natural_id',
        'cargo',
        'partida_registral',
        'fecha_inicio',
        'fecha_fin',
        'es_representante_actual',
        'estado'
    ];

    public string $tipoPersona;
    public ?string $notas = null;

    // Campos Natural
    public ?string $nombres = null;
    public ?string $apellidoPaterno = null;
    public ?string $apellidoMaterno = null;
    public ?string $fechaNacimiento = null;
    public ?int $sexoId = null;
    public ?int $estadoCivilId = null;
    public ?int $paisNacimientoId = null;
    public ?string $profesionOcupacion = null;

    // Campos Jurídica
    public ?string $razonSocial = null;
    public ?string $nombreComercial = null;
    public ?string $fechaConstitucion = null;
    public ?string $objetoSocial = null;

    // Colecciones estructuradas
    public array $documentos = [];
    public array $contactos = [];
    public array $direcciones = [];
    public array $representantes = [];

    public static function desdeArray(array $datos): self
    {
        self::validarCamposPermitidos($datos, self::CAMPOS_PERMITIDOS_RAIZ, 'CrearPersona');

        $errores = [];

        if (empty($datos['tipo_persona']) || !is_string($datos['tipo_persona'])) {
            $errores['tipo_persona'][] = 'El campo tipo_persona es obligatorio y debe ser texto (NATURAL o JURIDICA).';
        }

        $tipo = strtoupper(trim((string) ($datos['tipo_persona'] ?? '')));
        if (!in_array($tipo, ['NATURAL', 'JURIDICA'], true)) {
            $errores['tipo_persona'][] = 'El tipo de persona debe ser exactamente NATURAL o JURIDICA.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación estructural en payload.', $errores, 422);
        }

        $dto = new self();
        $dto->tipoPersona = $tipo;
        $dto->notas = isset($datos['notas']) ? (is_string($datos['notas']) ? trim($datos['notas']) : null) : null;

        // Datos Persona Natural
        $dto->nombres = isset($datos['nombres']) ? trim((string) $datos['nombres']) : null;
        $dto->apellidoPaterno = isset($datos['apellido_paterno']) ? trim((string) $datos['apellido_paterno']) : null;
        $dto->apellidoMaterno = isset($datos['apellido_materno']) ? trim((string) $datos['apellido_materno']) : null;
        $dto->fechaNacimiento = isset($datos['fecha_nacimiento']) ? trim((string) $datos['fecha_nacimiento']) : null;
        $dto->sexoId = isset($datos['sexo_id']) && $datos['sexo_id'] !== '' ? (int) $datos['sexo_id'] : null;
        $dto->estadoCivilId = isset($datos['estado_civil_id']) && $datos['estado_civil_id'] !== '' ? (int) $datos['estado_civil_id'] : null;
        $dto->paisNacimientoId = isset($datos['pais_nacimiento_id']) && $datos['pais_nacimiento_id'] !== '' ? (int) $datos['pais_nacimiento_id'] : null;
        $dto->profesionOcupacion = isset($datos['profesion_ocupacion']) ? trim((string) $datos['profesion_ocupacion']) : null;

        // Datos Persona Jurídica
        $dto->razonSocial = isset($datos['razon_social']) ? trim((string) $datos['razon_social']) : null;
        $dto->nombreComercial = isset($datos['nombre_comercial']) ? trim((string) $datos['nombre_comercial']) : null;
        $dto->fechaConstitucion = isset($datos['fecha_constitucion']) ? trim((string) $datos['fecha_constitucion']) : null;
        $dto->objetoSocial = isset($datos['objeto_social']) ? trim((string) $datos['objeto_social']) : null;

        // Validar y parsear documentos
        if (isset($datos['documentos'])) {
            if (!is_array($datos['documentos'])) {
                $errores['documentos'][] = 'El campo documentos debe ser un arreglo.';
            } else {
                foreach ($datos['documentos'] as $idx => $doc) {
                    if (!is_array($doc)) {
                        $errores["documentos[{$idx}]"][] = 'Cada documento debe ser un objeto estructurado.';
                        continue;
                    }
                    self::validarCamposPermitidos($doc, self::CAMPOS_PERMITIDOS_DOCUMENTO, "documentos[{$idx}]");
                    $dto->documentos[] = [
                        'tipo_documento_id' => (int) ($doc['tipo_documento_id'] ?? 0),
                        'numero_documento'  => trim((string) ($doc['numero_documento'] ?? '')),
                        'es_principal'      => filter_var($doc['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'pais_emision_id'   => isset($doc['pais_emision_id']) && $doc['pais_emision_id'] !== '' ? (int) $doc['pais_emision_id'] : null,
                        'fecha_emision'     => isset($doc['fecha_emision']) ? trim((string) $doc['fecha_emision']) : null,
                        'fecha_vencimiento' => isset($doc['fecha_vencimiento']) ? trim((string) $doc['fecha_vencimiento']) : null,
                        'estado'            => strtoupper(trim((string) ($doc['estado'] ?? 'ACTIVO')))
                    ];
                }
            }
        }

        // Validar y parsear contactos
        if (isset($datos['contactos'])) {
            if (!is_array($datos['contactos'])) {
                $errores['contactos'][] = 'El campo contactos debe ser un arreglo.';
            } else {
                foreach ($datos['contactos'] as $idx => $con) {
                    if (!is_array($con)) {
                        $errores["contactos[{$idx}]"][] = 'Cada contacto debe ser un objeto estructurado.';
                        continue;
                    }
                    self::validarCamposPermitidos($con, self::CAMPOS_PERMITIDOS_CONTACTO, "contactos[{$idx}]");
                    $dto->contactos[] = [
                        'tipo_contacto_id' => (int) ($con['tipo_contacto_id'] ?? 0),
                        'valor'            => trim((string) ($con['valor'] ?? '')),
                        'etiqueta'         => isset($con['etiqueta']) ? trim((string) $con['etiqueta']) : null,
                        'es_principal'     => filter_var($con['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'estado'           => strtoupper(trim((string) ($con['estado'] ?? 'ACTIVO')))
                    ];
                }
            }
        }

        // Validar y parsear direcciones
        if (isset($datos['direcciones'])) {
            if (!is_array($datos['direcciones'])) {
                $errores['direcciones'][] = 'El campo direcciones debe ser un arreglo.';
            } else {
                foreach ($datos['direcciones'] as $idx => $dir) {
                    if (!is_array($dir)) {
                        $errores["direcciones[{$idx}]"][] = 'Cada dirección debe ser un objeto estructurado.';
                        continue;
                    }
                    self::validarCamposPermitidos($dir, self::CAMPOS_PERMITIDOS_DIRECCION, "direcciones[{$idx}]");
                    $dto->direcciones[] = [
                        'tipo_direccion_id' => (int) ($dir['tipo_direccion_id'] ?? 0),
                        'distrito_id'       => isset($dir['distrito_id']) && $dir['distrito_id'] !== '' ? (int) $dir['distrito_id'] : null,
                        'direccion'         => trim((string) ($dir['direccion'] ?? '')),
                        'referencia'        => isset($dir['referencia']) ? trim((string) $dir['referencia']) : null,
                        'codigo_postal'     => isset($dir['codigo_postal']) ? trim((string) $dir['codigo_postal']) : null,
                        'es_principal'      => filter_var($dir['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'estado'            => strtoupper(trim((string) ($dir['estado'] ?? 'ACTIVO')))
                    ];
                }
            }
        }

        // Validar y parsear representantes
        if (isset($datos['representantes'])) {
            if (!is_array($datos['representantes'])) {
                $errores['representantes'][] = 'El campo representantes debe ser un arreglo.';
            } else {
                foreach ($datos['representantes'] as $idx => $rep) {
                    if (!is_array($rep)) {
                        $errores["representantes[{$idx}]"][] = 'Cada representante debe ser un objeto estructurado.';
                        continue;
                    }
                    self::validarCamposPermitidos($rep, self::CAMPOS_PERMITIDOS_REPRESENTANTE, "representantes[{$idx}]");
                    $dto->representantes[] = [
                        'persona_natural_id'      => (int) ($rep['persona_natural_id'] ?? 0),
                        'cargo'                   => trim((string) ($rep['cargo'] ?? '')),
                        'partida_registral'       => isset($rep['partida_registral']) ? trim((string) $rep['partida_registral']) : null,
                        'fecha_inicio'            => trim((string) ($rep['fecha_inicio'] ?? '')),
                        'fecha_fin'               => isset($rep['fecha_fin']) ? trim((string) $rep['fecha_fin']) : null,
                        'es_representante_actual' => filter_var($rep['es_representante_actual'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'estado'                  => strtoupper(trim((string) ($rep['estado'] ?? 'ACTIVO')))
                    ];
                }
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación estructural en payload.', $errores, 422);
        }

        return $dto;
    }

    public static function validarCamposPermitidos(array $datos, array $permitidos, string $contexto): void
    {
        $recibidos = array_keys($datos);
        $noPermitidos = array_diff($recibidos, $permitidos);

        if (!empty($noPermitidos)) {
            $errores = [];
            foreach ($noPermitidos as $campo) {
                $errores[$campo] = ["El campo '{$campo}' no está permitido en {$contexto}."];
            }
            throw new ValidacionExcepcion(
                "Se detectaron campos no admitidos en {$contexto}: " . implode(', ', $noPermitidos),
                $errores,
                422
            );
        }
    }
}
