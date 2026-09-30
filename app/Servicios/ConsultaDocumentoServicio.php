<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\DTOs\ConsultaDocumentoDTO;
use App\Excepciones\ValidacionExcepcion;
use App\Repositorios\PersonaRepositorio;

/**
 * ConsultaDocumentoServicio — Orquesta la consulta documental desacoplada (DNI / RUC).
 *
 * Aplica los principios inviolables de CasaPRO:
 * 1. Validación previa según reglas del catálogo `tipos_documento`.
 * 2. Regla de Oro Anti-Duplicidad: verifica primero la existencia en `persona_documentos`.
 *    Si la persona ya existe, retorna datos mínimos de advertencia y NO consume el servicio externo.
 * 3. Consulta externa asistida mediante proveedor desacoplado e inyectable.
 * 4. Fallback manual transparente ante cualquier contingencia del proveedor.
 */
class ConsultaDocumentoServicio
{
    private ProveedorConexion $proveedorConexion;
    private PersonaRepositorio $personaRepositorio;
    private ProveedorDocumentoInterface $proveedorDocumento;

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?PersonaRepositorio $personaRepositorio = null,
        ?ProveedorDocumentoInterface $proveedorDocumento = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->personaRepositorio = $personaRepositorio ?? new PersonaRepositorio($this->proveedorConexion);
        $this->proveedorDocumento = $proveedorDocumento ?? new ApisPeruAdaptador();
    }

    /**
     * Procesa la consulta documental completa.
     *
     * @param ConsultaDocumentoDTO $dto
     * @return array{
     *     estado_consulta: string,
     *     mensaje: string,
     *     persona_existente?: array{id: int, tipo_persona: string, estado: string, nombre_completo: string},
     *     datos?: array<string, mixed>,
     *     reglas_documento?: array<string, mixed>
     * }
     */
    public function consultar(ConsultaDocumentoDTO $dto): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();

        // 1. Obtener reglas oficiales del tipo de documento desde la base de datos
        $tipoDoc = $this->personaRepositorio->obtenerTipoDocumentoPorId($dto->tipoDocumentoId, $conexion);
        if (!$tipoDoc) {
            throw new ValidacionExcepcion(
                'El tipo de documento indicado no existe o se encuentra inactivo.',
                ['tipo_documento_id' => ['Tipo de documento inválido.']],
                422
            );
        }

        // 2. Validación de formato y longitud según el catálogo real
        $numero = $dto->numeroDocumento;
        $erroresFormato = [];

        if (!empty($tipoDoc['longitud_exacta']) && strlen($numero) !== (int) $tipoDoc['longitud_exacta']) {
            $erroresFormato['numero_documento'][] = "El documento {$tipoDoc['codigo']} debe tener exactamente {$tipoDoc['longitud_exacta']} caracteres.";
        }

        if (strlen($numero) < (int) $tipoDoc['longitud_minima']) {
            $erroresFormato['numero_documento'][] = "El documento {$tipoDoc['codigo']} debe tener al menos {$tipoDoc['longitud_minima']} caracteres.";
        }

        if (strlen($numero) > (int) $tipoDoc['longitud_maxima']) {
            $erroresFormato['numero_documento'][] = "El documento {$tipoDoc['codigo']} no puede superar {$tipoDoc['longitud_maxima']} caracteres.";
        }

        if (!empty($tipoDoc['patron_regex']) && !preg_match('/' . str_replace('/', '\/', $tipoDoc['patron_regex']) . '/', $numero)) {
            $erroresFormato['numero_documento'][] = "El formato del documento no coincide con el patrón requerido para {$tipoDoc['codigo']}.";
        }

        if (!empty($erroresFormato)) {
            throw new ValidacionExcepcion('Error de formato en el número de documento.', $erroresFormato, 422);
        }

        // 3. REGLA DE ORO ANTI-DUPLICIDAD: Verificar existencia previa local en CasaPRO
        $personaLocal = $this->personaRepositorio->buscarPersonaPorDocumento($dto->tipoDocumentoId, $numero, $conexion);
        if ($personaLocal !== null) {
            return [
                'estado_consulta' => 'DUPLICADO_LOCAL',
                'mensaje' => "El documento {$tipoDoc['codigo']} {$numero} ya pertenece a una persona registrada en CasaPRO.",
                'persona_existente' => [
                    'id' => $personaLocal['id'],
                    'tipo_persona' => $personaLocal['tipo_persona'],
                    'estado' => $personaLocal['estado'],
                    'nombre_completo' => $personaLocal['nombre_completo']
                ]
            ];
        }

        // 4. CONSULTA EXTERNA ASISTIDA: Consultar proveedor únicamente si el tipo de documento es soportado
        $codigoDoc = strtoupper(trim((string) $tipoDoc['codigo']));

        if ($codigoDoc === 'DNI') {
            $resultado = $this->proveedorDocumento->consultarDni($numero);
        } elseif ($codigoDoc === 'RUC') {
            $resultado = $this->proveedorDocumento->consultarRuc($numero);

            // Si se recuperó UBIGEO tributario, intentar correlacionar con catálogo local
            if (!empty($resultado['exito']) && !empty($resultado['datos']['codigo_ubigeo'])) {
                $jerarquia = $this->personaRepositorio->buscarJerarquiaUbigeo($resultado['datos']['codigo_ubigeo'], $conexion);
                if ($jerarquia !== null) {
                    $resultado['datos']['ubigeo_local'] = $jerarquia;
                }
            }
        } else {
            return [
                'estado_consulta' => 'NO_SOPORTADO_PROVEEDOR',
                'mensaje' => "El tipo de documento {$tipoDoc['codigo']} no cuenta con consulta automática. Ingrese los datos manualmente.",
                'reglas_documento' => [
                    'codigo' => $tipoDoc['codigo'],
                    'nombre' => $tipoDoc['nombre'],
                    'tipo_persona' => $tipoDoc['tipo_persona']
                ]
            ];
        }

        if (!empty($resultado['exito'])) {
            return [
                'estado_consulta' => 'ENCONTRADO',
                'mensaje' => $resultado['mensaje'],
                'datos' => $resultado['datos']
            ];
        }

        // Si el proveedor no lo encontró o falló la comunicación
        $codigoFallo = $resultado['codigo'] ?? 'NO_ENCONTRADO';
        $estadoRetorno = ($codigoFallo === 'NO_ENCONTRADO') ? 'NO_ENCONTRADO' : 'NO_DISPONIBLE';

        return [
            'estado_consulta' => $estadoRetorno,
            'mensaje' => $resultado['mensaje'] ?? 'No se pudo autocompletar la información. Puede continuar de forma manual.'
        ];
    }
}
