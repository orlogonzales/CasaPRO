<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

/**
 * DTO para la actualización integral de datos editables de Persona.
 * Representa la edición completa del formulario de ficha.
 * Aplica allowlist estricta y preserva colecciones omitidas (null) frente a colecciones enviadas vacías ([]).
 */
class ActualizarPersonaDTO
{
    private const CAMPOS_PERMITIDOS_RAIZ = [
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
        'id',
        'tipo_documento_id',
        'numero_documento',
        'es_principal',
        'pais_emision_id',
        'fecha_emision',
        'fecha_vencimiento',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_CONTACTO = [
        'id',
        'tipo_contacto_id',
        'valor',
        'etiqueta',
        'es_principal',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_DIRECCION = [
        'id',
        'tipo_direccion_id',
        'distrito_id',
        'direccion',
        'referencia',
        'codigo_postal',
        'es_principal',
        'estado'
    ];

    private const CAMPOS_PERMITIDOS_REPRESENTANTE = [
        'id',
        'persona_natural_id',
        'cargo',
        'partida_registral',
        'fecha_inicio',
        'fecha_fin',
        'es_representante_actual',
        'estado'
    ];

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

    // Colecciones: null = no enviada (preservar existentes); array = enviada (sincronizar)
    public ?array $documentos = null;
    public ?array $contactos = null;
    public ?array $direcciones = null;
    public ?array $representantes = null;

    public static function desdeArray(array $datos): self
    {
        // Si el cliente intenta enviar tipo_persona en un PUT, rechazar con 422
        if (array_key_exists('tipo_persona', $datos)) {
            throw new ValidacionExcepcion(
                'El tipo de persona (NATURAL o JURIDICA) no puede modificarse mediante edición ordinaria.',
                ['tipo_persona' => ['El campo tipo_persona no es editable.']],
                422
            );
        }

        CrearPersonaDTO::validarCamposPermitidos($datos, self::CAMPOS_PERMITIDOS_RAIZ, 'ActualizarPersona');

        $dto = new self();
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

        // Colección Documentos
        if (array_key_exists('documentos', $datos)) {
            $dto->documentos = [];
            if (is_array($datos['documentos'])) {
                foreach ($datos['documentos'] as $idx => $doc) {
                    if (!is_array($doc)) {
                        throw new ValidacionExcepcion("documentos[{$idx}] debe ser un objeto estructurado.", [], 422);
                    }
                    CrearPersonaDTO::validarCamposPermitidos($doc, self::CAMPOS_PERMITIDOS_DOCUMENTO, "documentos[{$idx}]");
                    $dto->documentos[] = [
                        'id'                => isset($doc['id']) ? (int) $doc['id'] : null,
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

        // Colección Contactos
        if (array_key_exists('contactos', $datos)) {
            $dto->contactos = [];
            if (is_array($datos['contactos'])) {
                foreach ($datos['contactos'] as $idx => $con) {
                    if (!is_array($con)) {
                        throw new ValidacionExcepcion("contactos[{$idx}] debe ser un objeto estructurado.", [], 422);
                    }
                    CrearPersonaDTO::validarCamposPermitidos($con, self::CAMPOS_PERMITIDOS_CONTACTO, "contactos[{$idx}]");
                    $dto->contactos[] = [
                        'id'               => isset($con['id']) ? (int) $con['id'] : null,
                        'tipo_contacto_id' => (int) ($con['tipo_contacto_id'] ?? 0),
                        'valor'            => trim((string) ($con['valor'] ?? '')),
                        'etiqueta'         => isset($con['etiqueta']) ? trim((string) $con['etiqueta']) : null,
                        'es_principal'     => filter_var($con['es_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'estado'           => strtoupper(trim((string) ($con['estado'] ?? 'ACTIVO')))
                    ];
                }
            }
        }

        // Colección Direcciones
        if (array_key_exists('direcciones', $datos)) {
            $dto->direcciones = [];
            if (is_array($datos['direcciones'])) {
                foreach ($datos['direcciones'] as $idx => $dir) {
                    if (!is_array($dir)) {
                        throw new ValidacionExcepcion("direcciones[{$idx}] debe ser un objeto estructurado.", [], 422);
                    }
                    CrearPersonaDTO::validarCamposPermitidos($dir, self::CAMPOS_PERMITIDOS_DIRECCION, "direcciones[{$idx}]");
                    $dto->direcciones[] = [
                        'id'                => isset($dir['id']) ? (int) $dir['id'] : null,
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

        // Colección Representantes
        if (array_key_exists('representantes', $datos)) {
            $dto->representantes = [];
            if (is_array($datos['representantes'])) {
                foreach ($datos['representantes'] as $idx => $rep) {
                    if (!is_array($rep)) {
                        throw new ValidacionExcepcion("representantes[{$idx}] debe ser un objeto estructurado.", [], 422);
                    }
                    CrearPersonaDTO::validarCamposPermitidos($rep, self::CAMPOS_PERMITIDOS_REPRESENTANTE, "representantes[{$idx}]");
                    $dto->representantes[] = [
                        'id'                      => isset($rep['id']) ? (int) $rep['id'] : null,
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

        return $dto;
    }
}
