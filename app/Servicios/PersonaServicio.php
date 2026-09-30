<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Repositorios\PersonaRepositorio;
use App\DTOs\CrearPersonaDTO;
use App\DTOs\ActualizarPersonaDTO;
use App\DTOs\CambiarEstadoPersonaDTO;
use App\DTOs\ConsultaDataTablesDTO;
use App\Modelos\Persona;
use App\Modelos\PersonaNatural;
use App\Modelos\PersonaJuridica;
use App\Modelos\PersonaDocumento;
use App\Modelos\PersonaContacto;
use App\Modelos\PersonaDireccion;
use App\Modelos\PersonaRepresentante;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use App\Excepciones\ValidacionExcepcion;
use PDO;
use PDOException;
use Throwable;

/**
 * PersonaServicio — Soberano de la lógica de negocio, validaciones semánticas, transacciones y auditoría.
 */
class PersonaServicio
{
    public function __construct(
        private ProveedorConexion $proveedorConexion,
        private PersonaRepositorio $personaRepositorio,
        private AuditoriaServicio $auditoriaServicio
    ) {}

    /**
     * Registra integralmente una nueva Persona (Natural o Jurídica) de manera atómica con auditoría.
     * Soporta transacciones externas propagadas: Quien abre la transacción es quien controla su commit/rollback.
     */
    public function crear(CrearPersonaDTO $dto, ContextoPeticion $contexto, ?PDO $conexionExterna = null): array
    {
        $conexion = $conexionExterna ?? $this->proveedorConexion->obtenerConexion();
        $this->validarReglasCreacion($dto, $conexion);

        $transaccionPropia = !$conexion->inTransaction();
        if ($transaccionPropia) {
            $conexion->beginTransaction();
        }

        try {
            // 1. Crear entidad raíz Persona
            $persona = new Persona(
                $dto->tipoPersona,
                Persona::ESTADO_ACTIVO,
                $dto->notas
            );
            $personaId = $this->personaRepositorio->insertarPersona($persona, $conexion);
            $persona->asignarId($personaId);

            // 2. Extensión Natural o Jurídica
            if ($dto->tipoPersona === Persona::TIPO_NATURAL) {
                $natural = new PersonaNatural(
                    $personaId,
                    (string) $dto->nombres,
                    (string) $dto->apellidoPaterno,
                    $dto->apellidoMaterno,
                    $dto->fechaNacimiento,
                    $dto->sexoId,
                    $dto->estadoCivilId,
                    $dto->paisNacimientoId,
                    $dto->profesionOcupacion
                );
                $this->personaRepositorio->insertarNatural($natural, $conexion);
            } else {
                $juridica = new PersonaJuridica(
                    $personaId,
                    (string) $dto->razonSocial,
                    $dto->nombreComercial,
                    $dto->fechaConstitucion,
                    $dto->objetoSocial
                );
                $this->personaRepositorio->insertarJuridica($juridica, $conexion);
            }

            // 3. Documentos (0..N permitidos; si existen, máximo 1 principal activo)
            $this->procesarDocumentos($personaId, $dto->documentos, $conexion);

            // 4. Contactos (0..N permitidos; si existen, máximo 1 principal activo)
            $this->procesarContactos($personaId, $dto->contactos, $conexion);

            // 5. Direcciones (0..N permitidas; si existen, máximo 1 principal activo)
            $this->procesarDirecciones($personaId, $dto->direcciones, $conexion);

            // 6. Representantes (solo si Jurídica)
            if ($dto->tipoPersona === Persona::TIPO_JURIDICA && !empty($dto->representantes)) {
                $this->procesarRepresentantes($personaId, $dto->representantes, $conexion);
            }

            // 7. Registro de Auditoría Transversal en la misma conexión
            $snapshot = $this->construirSnapshot($personaId, $conexion);
            $this->auditoriaServicio->registrar([
                'modulo'           => 'identidad',
                'entidad'          => 'personas',
                'registro_id'      => $personaId,
                'accion'           => 'CREAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => null,
                'datos_nuevos'     => $snapshot,
                'metadatos'        => [
                    'tipo_persona' => $dto->tipoPersona,
                    'descripcion'  => 'Creación de persona ' . ($dto->tipoPersona === Persona::TIPO_NATURAL ? 'natural' : 'jurídica')
                ],
                'contexto'         => $contexto
            ], $conexion);

            if ($transaccionPropia) {
                $conexion->commit();
            }

            return [
                'id'                  => $personaId,
                'tipo_persona'        => $dto->tipoPersona,
                'nombre_completo'     => $dto->tipoPersona === Persona::TIPO_NATURAL
                    ? trim($dto->apellidoPaterno . ' ' . ($dto->apellidoMaterno ?? '') . ', ' . $dto->nombres)
                    : (string) $dto->razonSocial,
                'documento_principal' => $this->obtenerTextoDocumentoPrincipal($personaId, $conexion),
                'estado'              => Persona::ESTADO_ACTIVO
            ];

        } catch (PDOException $e) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->traducirExcepcionPDO($e);
        } catch (Throwable $t) {
            if ($transaccionPropia && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Actualiza integralmente los datos editables de la ficha de Persona en una única transacción.
     */
    public function actualizar(int $id, ActualizarPersonaDTO $dto, ContextoPeticion $contexto): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();
        $personaActual = $this->personaRepositorio->buscarPorId($id, $conexion);

        if (!$personaActual) {
            throw new RecursoNoEncontradoExcepcion("No se encontró la persona con ID {$id}.");
        }

        $tipoPersona = $personaActual['tipo_persona'];
        $this->validarReglasActualizacion($dto, $tipoPersona, $conexion);

        $conexion->beginTransaction();

        try {
            // Snapshot previo para auditoría
            $snapshotPrevio = $this->construirSnapshot($id, $conexion);

            // 1. Actualizar notas de Persona raíz
            $persona = new Persona($tipoPersona, $personaActual['estado'], $dto->notas, $id);
            $this->personaRepositorio->actualizarPersona($persona, $conexion);

            // 2. Actualizar extensión
            if ($tipoPersona === Persona::TIPO_NATURAL) {
                if ($dto->nombres !== null && $dto->apellidoPaterno !== null) {
                    $natural = new PersonaNatural(
                        $id,
                        $dto->nombres,
                        $dto->apellidoPaterno,
                        $dto->apellidoMaterno,
                        $dto->fechaNacimiento,
                        $dto->sexoId,
                        $dto->estadoCivilId,
                        $dto->paisNacimientoId,
                        $dto->profesionOcupacion
                    );
                    $this->personaRepositorio->actualizarNatural($natural, $conexion);
                }
            } else {
                if ($dto->razonSocial !== null) {
                    $juridica = new PersonaJuridica(
                        $id,
                        $dto->razonSocial,
                        $dto->nombreComercial,
                        $dto->fechaConstitucion,
                        $dto->objetoSocial
                    );
                    $this->personaRepositorio->actualizarJuridica($juridica, $conexion);
                }
            }

            // 3. Sincronizar colecciones (solo si la clave fue enviada en el DTO)
            if ($dto->documentos !== null) {
                $this->personaRepositorio->desactivarDocumentos($id, $conexion);
                $this->procesarDocumentos($id, $dto->documentos, $conexion);
            }

            if ($dto->contactos !== null) {
                $this->personaRepositorio->desactivarContactos($id, $conexion);
                $this->procesarContactos($id, $dto->contactos, $conexion);
            }

            if ($dto->direcciones !== null) {
                $this->personaRepositorio->desactivarDirecciones($id, $conexion);
                $this->procesarDirecciones($id, $dto->direcciones, $conexion);
            }

            if ($tipoPersona === Persona::TIPO_JURIDICA && $dto->representantes !== null) {
                $this->personaRepositorio->desactivarRepresentantes($id, $conexion);
                $this->procesarRepresentantes($id, $dto->representantes, $conexion);
            }

            // 4. Registro de Auditoría con snapshot diferencial
            $snapshotNuevo = $this->construirSnapshot($id, $conexion);
            $this->auditoriaServicio->registrar([
                'modulo'           => 'identidad',
                'entidad'          => 'personas',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => $snapshotPrevio,
                'datos_nuevos'     => $snapshotNuevo,
                'metadatos'        => ['tipo_persona' => $tipoPersona, 'descripcion' => 'Actualización de ficha persona'],
                'contexto'         => $contexto
            ], $conexion);

            $conexion->commit();

            return [
                'id'              => $id,
                'tipo_persona'    => $tipoPersona,
                'mensaje'         => 'Persona actualizada exitosamente.'
            ];

        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->traducirExcepcionPDO($e);
        } catch (Throwable $t) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Transición especializada de estado (ACTIVO <-> INACTIVO) con auditoría obligatoria.
     */
    public function cambiarEstado(int $id, CambiarEstadoPersonaDTO $dto, ContextoPeticion $contexto): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();
        $personaActual = $this->personaRepositorio->buscarPorId($id, $conexion);

        if (!$personaActual) {
            throw new RecursoNoEncontradoExcepcion("No se encontró la persona con ID {$id}.");
        }

        if ($personaActual['estado'] === $dto->estado) {
            return [
                'id'     => $id,
                'estado' => $dto->estado,
                'mensaje'=> "La persona ya se encontraba en estado {$dto->estado}."
            ];
        }

        $conexion->beginTransaction();

        try {
            $this->personaRepositorio->cambiarEstadoPersona($id, $dto->estado, $conexion);

            $this->auditoriaServicio->registrar([
                'modulo'           => 'identidad',
                'entidad'          => 'personas',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'resultado'        => 'EXITO',
                'datos_anteriores' => ['estado' => $personaActual['estado']],
                'datos_nuevos'     => ['estado' => $dto->estado],
                'metadatos'        => [
                    'motivo'      => $dto->motivo,
                    'descripcion' => "Cambio de estado de {$personaActual['estado']} a {$dto->estado}"
                ],
                'contexto'         => $contexto
            ], $conexion);

            $conexion->commit();

            return [
                'id'      => $id,
                'estado'  => $dto->estado,
                'mensaje' => "Estado actualizado exitosamente a {$dto->estado}."
            ];

        } catch (Throwable $t) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Obtiene el detalle compuesto 360 de identidad de una Persona sin exponer datos innecesarios.
     */
    public function obtenerPorId(int $id): array
    {
        $detalle = $this->personaRepositorio->obtenerDetalleCompleto360($id);
        if (!$detalle) {
            throw new RecursoNoEncontradoExcepcion("No se encontró ninguna persona registrada con el identificador proporcionado: {$id}.");
        }

        return $this->serializarDetalle360($detalle);
    }

    /**
     * Ejecuta consulta server-side para DataTables.
     */
    public function listarDataTables(ConsultaDataTablesDTO $dto): array
    {
        $resultado = $this->personaRepositorio->consultarDataTables($dto);

        return [
            'draw'            => $dto->draw,
            'recordsTotal'    => $resultado['total_general'],
            'recordsFiltered' => $resultado['total_filtrado'],
            'data'            => $resultado['datos']
        ];
    }

    // -------------------------------------------------------------------------
    // VALIDACIONES Y PROCESAMIENTO INTERNO
    // -------------------------------------------------------------------------

    private function validarReglasCreacion(CrearPersonaDTO $dto, PDO $conexion): void
    {
        $errores = [];

        if ($dto->tipoPersona === Persona::TIPO_NATURAL) {
            if (empty($dto->nombres)) {
                $errores['nombres'][] = 'Los nombres son obligatorios para Persona Natural.';
            }
            if (empty($dto->apellidoPaterno)) {
                $errores['apellido_paterno'][] = 'El apellido paterno es obligatorio para Persona Natural.';
            }
            if (!empty($dto->razonSocial) || !empty($dto->nombreComercial) || !empty($dto->representantes)) {
                $errores['tipo_persona'][] = 'Una Persona Natural no puede contener razón social, nombre comercial ni representantes.';
            }
            // Validar existencia de catálogos si vienen informados
            if ($dto->sexoId !== null && !$this->existeEnCatalogo('sexos', $dto->sexoId, $conexion)) {
                $errores['sexo_id'][] = 'El sexo seleccionado no existe en el catálogo oficial.';
            }
            if ($dto->estadoCivilId !== null && !$this->existeEnCatalogo('estados_civiles', $dto->estadoCivilId, $conexion)) {
                $errores['estado_civil_id'][] = 'El estado civil seleccionado no existe en el catálogo oficial.';
            }
            if ($dto->paisNacimientoId !== null && !$this->existeEnCatalogo('paises', $dto->paisNacimientoId, $conexion)) {
                $errores['pais_nacimiento_id'][] = 'El país de nacimiento seleccionado no existe en el catálogo oficial.';
            }
        } else {
            if (empty($dto->razonSocial)) {
                $errores['razon_social'][] = 'La razón social es obligatoria para Persona Jurídica.';
            }
            if (!empty($dto->nombres) || !empty($dto->apellidoPaterno) || !empty($dto->apellidoMaterno)) {
                $errores['tipo_persona'][] = 'Una Persona Jurídica no puede contener nombres ni apellidos.';
            }
            // Validar representantes
            foreach ($dto->representantes as $idx => $rep) {
                if (!$this->esPersonaNaturalReal($rep['persona_natural_id'], $conexion)) {
                    $errores["representantes[{$idx}].persona_natural_id"][] = 'El representante legal debe ser una Persona Natural válida existente.';
                }
            }
        }

        // Validar unicidad previa de documentos
        foreach ($dto->documentos as $idx => $doc) {
            if (!$this->existeEnCatalogo('tipos_documento', $doc['tipo_documento_id'], $conexion)) {
                $errores["documentos[{$idx}].tipo_documento_id"][] = 'El tipo de documento no existe en el catálogo oficial.';
            }
            $this->validarFormatoDocumento($doc['tipo_documento_id'], $doc['numero_documento'], "documentos[{$idx}]", $errores, $conexion);

            if ($this->personaRepositorio->existeDocumento($doc['tipo_documento_id'], $doc['numero_documento'], null, $conexion)) {
                $errores["documentos[{$idx}].numero_documento"][] = "El número de documento '{$doc['numero_documento']}' ya se encuentra registrado para ese tipo.";
            }
        }

        // Validar direcciones y UBIGEO
        foreach ($dto->direcciones as $idx => $dir) {
            if (!$this->existeEnCatalogo('tipos_direccion', $dir['tipo_direccion_id'], $conexion)) {
                $errores["direcciones[{$idx}].tipo_direccion_id"][] = 'El tipo de dirección no existe en el catálogo oficial.';
            }
            if ($dir['distrito_id'] !== null && !$this->existeEnCatalogo('distritos', $dir['distrito_id'], $conexion)) {
                $errores["direcciones[{$idx}].distrito_id"][] = 'El distrito no existe en el catálogo UBIGEO vigente de CasaPRO.';
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación semántica en creación de persona.', $errores, 422);
        }
    }

    private function validarReglasActualizacion(ActualizarPersonaDTO $dto, string $tipoPersona, PDO $conexion): void
    {
        $errores = [];

        if ($tipoPersona === Persona::TIPO_NATURAL) {
            if (!empty($dto->razonSocial) || !empty($dto->nombreComercial) || !empty($dto->representantes)) {
                $errores['tipo_persona'][] = 'No se pueden enviar datos de Persona Jurídica en una Persona Natural.';
            }
            if ($dto->sexoId !== null && !$this->existeEnCatalogo('sexos', $dto->sexoId, $conexion)) {
                $errores['sexo_id'][] = 'El sexo seleccionado no existe en el catálogo oficial.';
            }
            if ($dto->estadoCivilId !== null && !$this->existeEnCatalogo('estados_civiles', $dto->estadoCivilId, $conexion)) {
                $errores['estado_civil_id'][] = 'El estado civil seleccionado no existe en el catálogo oficial.';
            }
            if ($dto->paisNacimientoId !== null && !$this->existeEnCatalogo('paises', $dto->paisNacimientoId, $conexion)) {
                $errores['pais_nacimiento_id'][] = 'El país de nacimiento seleccionado no existe en el catálogo oficial.';
            }
        } else {
            if (!empty($dto->nombres) || !empty($dto->apellidoPaterno) || !empty($dto->apellidoMaterno)) {
                $errores['tipo_persona'][] = 'No se pueden enviar datos de Persona Natural en una Persona Jurídica.';
            }
            if ($dto->representantes !== null) {
                foreach ($dto->representantes as $idx => $rep) {
                    if (!$this->esPersonaNaturalReal($rep['persona_natural_id'], $conexion)) {
                        $errores["representantes[{$idx}].persona_natural_id"][] = 'El representante legal debe ser una Persona Natural válida existente.';
                    }
                }
            }
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Error de validación semántica en actualización de persona.', $errores, 422);
        }
    }

    private function procesarDocumentos(int $personaId, array $documentos, PDO $conexion): void
    {
        // En identidad central: 0..N documentos permitidos. Si existen, como máximo 1 principal activo.
        $unPrincipalAsignado = false;

        // Desactivar principales previos de esta persona en la misma transacción
        $this->personaRepositorio->desactivarDocumentosPrincipales($personaId, $conexion);

        foreach ($documentos as $doc) {
            $esPrincipal = $doc['es_principal'] && !$unPrincipalAsignado;
            if ($esPrincipal) {
                $unPrincipalAsignado = true;
            }

            $modelo = new PersonaDocumento(
                $personaId,
                $doc['tipo_documento_id'],
                $doc['numero_documento'],
                $esPrincipal,
                $doc['pais_emision_id'],
                $doc['fecha_emision'],
                $doc['fecha_vencimiento'],
                $doc['estado'] ?? PersonaDocumento::ESTADO_ACTIVO
            );
            $this->personaRepositorio->insertarDocumento($modelo, $conexion);
        }
    }

    private function procesarContactos(int $personaId, array $contactos, PDO $conexion): void
    {
        $unPrincipalAsignado = false;
        $this->personaRepositorio->desactivarContactosPrincipales($personaId, $conexion);

        foreach ($contactos as $con) {
            $esPrincipal = $con['es_principal'] && !$unPrincipalAsignado;
            if ($esPrincipal) {
                $unPrincipalAsignado = true;
            }

            $modelo = new PersonaContacto(
                $personaId,
                $con['tipo_contacto_id'],
                $con['valor'],
                $con['etiqueta'] ?? null,
                $esPrincipal,
                $con['estado'] ?? PersonaContacto::ESTADO_ACTIVO
            );
            $this->personaRepositorio->insertarContacto($modelo, $conexion);
        }
    }

    private function procesarDirecciones(int $personaId, array $direcciones, PDO $conexion): void
    {
        $unPrincipalAsignado = false;
        $this->personaRepositorio->desactivarDireccionesPrincipales($personaId, $conexion);

        foreach ($direcciones as $dir) {
            $esPrincipal = $dir['es_principal'] && !$unPrincipalAsignado;
            if ($esPrincipal) {
                $unPrincipalAsignado = true;
            }

            $modelo = new PersonaDireccion(
                $personaId,
                $dir['tipo_direccion_id'],
                $dir['direccion'],
                $dir['distrito_id'],
                $dir['referencia'] ?? null,
                $dir['codigo_postal'] ?? null,
                $esPrincipal,
                $dir['estado'] ?? PersonaDireccion::ESTADO_ACTIVO
            );
            $this->personaRepositorio->insertarDireccion($modelo, $conexion);
        }
    }

    private function procesarRepresentantes(int $personaJuridicaId, array $representantes, PDO $conexion): void
    {
        $unActualAsignado = false;
        $this->personaRepositorio->desactivarRepresentantesActuales($personaJuridicaId, $conexion);

        foreach ($representantes as $rep) {
            $esActual = $rep['es_representante_actual'] && !$unActualAsignado;
            if ($esActual) {
                $unActualAsignado = true;
            }

            $modelo = new PersonaRepresentante(
                $personaJuridicaId,
                $rep['persona_natural_id'],
                $rep['cargo'],
                $rep['fecha_inicio'],
                $rep['fecha_fin'] ?? null,
                $rep['partida_registral'] ?? null,
                $esActual,
                $rep['estado'] ?? PersonaRepresentante::ESTADO_ACTIVO
            );
            $this->personaRepositorio->insertarRepresentante($modelo, $conexion);
        }
    }

    private function validarFormatoDocumento(int $tipoId, string $numero, string $campoContexto, array &$errores, PDO $conexion): void
    {
        $stmt = $conexion->prepare("SELECT `codigo` FROM `tipos_documento` WHERE `id` = :id");
        $stmt->bindValue(':id', $tipoId, PDO::PARAM_INT);
        $stmt->execute();
        $codigo = $stmt->fetchColumn();

        if ($codigo === 'DNI') {
            if (!preg_match('/^[0-9]{8}$/', $numero)) {
                $errores["{$campoContexto}.numero_documento"][] = 'El DNI debe contener exactamente 8 dígitos numéricos.';
            }
        } elseif ($codigo === 'RUC') {
            if (!preg_match('/^[0-9]{11}$/', $numero)) {
                $errores["{$campoContexto}.numero_documento"][] = 'El RUC debe contener exactamente 11 dígitos numéricos.';
            }
        }
    }

    private function existeEnCatalogo(string $tabla, int $id, PDO $conexion): bool
    {
        $stmt = $conexion->prepare("SELECT 1 FROM `{$tabla}` WHERE `id` = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function esPersonaNaturalReal(int $personaId, PDO $conexion): bool
    {
        $stmt = $conexion->prepare("SELECT 1 FROM `persona_natural` WHERE `persona_id` = :id LIMIT 1");
        $stmt->bindValue(':id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function traducirExcepcionPDO(PDOException $e): void
    {
        $mensajeSql = $e->getMessage();
        $codigoError = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

        // Conflicto de unicidad específico para documentos
        if ($e->getCode() === '23000' && $codigoError === 1062 && str_contains($mensajeSql, 'uq_persona_documento_tipo_numero')) {
            throw new ReglaNegocioExcepcion(
                'Conflicto de unicidad: El documento de identidad especificado ya se encuentra registrado en el sistema.',
                409,
                ['numero_documento' => ['Documento duplicado']]
            );
        }

        // Conflicto de unicidad en otra restricción UNIQUE
        if ($e->getCode() === '23000' && $codigoError === 1062) {
            throw new ReglaNegocioExcepcion(
                'Conflicto de unicidad: Uno de los valores proporcionados ya se encuentra registrado.',
                409,
                ['conflicto' => ['Valor duplicado en registro']]
            );
        }

        // Violación de clave foránea
        if ($e->getCode() === '23000' && in_array($codigoError, [1451, 1452], true)) {
            throw new ReglaNegocioExcepcion(
                'Conflicto de integridad referencial: El registro no puede procesarse debido a dependencias activas o inexistentes.',
                422,
                ['integridad' => ['Violación de clave foránea']]
            );
        }

        throw $e;
    }

    private function construirSnapshot(int $personaId, PDO $conexion): array
    {
        $detalle = $this->personaRepositorio->obtenerDetalleCompleto360($personaId, $conexion);
        return $detalle ?: ['id' => $personaId];
    }

    private function obtenerTextoDocumentoPrincipal(int $personaId, PDO $conexion): ?string
    {
        $stmt = $conexion->prepare("
            SELECT CONCAT(td.codigo, ' ', pd.numero_documento)
            FROM `persona_documentos` pd
            JOIN `tipos_documento` td ON pd.tipo_documento_id = td.id
            WHERE pd.persona_id = :id AND pd.es_principal = 1 AND pd.estado = 'ACTIVO'
            LIMIT 1
        ");
        $stmt->bindValue(':id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
        $texto = $stmt->fetchColumn();
        return $texto ? (string) $texto : null;
    }

    private function serializarDetalle360(array $detalle): array
    {
        $p = $detalle['persona'];
        $n = $detalle['natural'];
        $j = $detalle['juridica'];

        return [
            'persona' => [
                'id'             => (int) $p['id'],
                'tipo_persona'   => $p['tipo_persona'],
                'estado'         => $p['estado'],
                'notas'          => $p['notas'],
                'creado_en'      => $p['creado_en'],
                'actualizado_en' => $p['actualizado_en']
            ],
            'natural' => $n ? [
                'nombres'             => $n['nombres'],
                'apellido_paterno'    => $n['apellido_paterno'],
                'apellido_materno'    => $n['apellido_materno'],
                'nombre_completo'     => trim($n['apellido_paterno'] . ' ' . ($n['apellido_materno'] ?? '') . ', ' . $n['nombres']),
                'fecha_nacimiento'    => $n['fecha_nacimiento'],
                'sexo'                => $n['sexo_id'] ? ['id' => (int) $n['sexo_id'], 'codigo' => $n['sexo_codigo'], 'nombre' => $n['sexo_nombre']] : null,
                'estado_civil'        => $n['estado_civil_id'] ? ['id' => (int) $n['estado_civil_id'], 'codigo' => $n['estado_civil_codigo'], 'nombre' => $n['estado_civil_nombre']] : null,
                'pais_nacimiento'     => $n['pais_nacimiento_id'] ? ['id' => (int) $n['pais_nacimiento_id'], 'nombre' => $n['pais_nacimiento_nombre']] : null,
                'profesion_ocupacion' => $n['profesion_ocupacion']
            ] : null,
            'juridica' => $j ? [
                'razon_social'       => $j['razon_social'],
                'nombre_comercial'   => $j['nombre_comercial'],
                'fecha_constitucion' => $j['fecha_constitucion'],
                'objeto_social'      => $j['objeto_social']
            ] : null,
            'documentos' => array_map(fn($d) => [
                'id'               => (int) $d['id'],
                'tipo_documento'   => ['id' => (int) $d['tipo_documento_id'], 'codigo' => $d['tipo_codigo'], 'nombre' => $d['tipo_nombre'], 'abreviatura' => $d['tipo_abreviatura']],
                'numero_documento' => $d['numero_documento'],
                'es_principal'     => (bool) $d['es_principal'],
                'pais_emision'     => $d['pais_emision_id'] ? ['id' => (int) $d['pais_emision_id'], 'nombre' => $d['pais_emision_nombre']] : null,
                'fecha_emision'    => $d['fecha_emision'],
                'fecha_vencimiento'=> $d['fecha_vencimiento'],
                'estado'           => $d['estado']
            ], $detalle['documentos']),
            'contactos' => array_map(fn($c) => [
                'id'            => (int) $c['id'],
                'tipo_contacto' => ['id' => (int) $c['tipo_contacto_id'], 'codigo' => $c['tipo_codigo'], 'nombre' => $c['tipo_nombre']],
                'valor'         => $c['valor'],
                'etiqueta'      => $c['etiqueta'],
                'es_principal'  => (bool) $c['es_principal'],
                'estado'        => $c['estado']
            ], $detalle['contactos']),
            'direcciones' => array_map(fn($dir) => [
                'id'             => (int) $dir['id'],
                'tipo_direccion' => ['id' => (int) $dir['tipo_direccion_id'], 'codigo' => $dir['tipo_codigo'], 'nombre' => $dir['tipo_nombre']],
                'ubigeo'         => $dir['distrito_id'] ? [
                    'distrito_id'         => (int) $dir['distrito_id'],
                    'distrito_codigo'     => $dir['distrito_codigo'],
                    'distrito'            => $dir['distrito_nombre'],
                    'provincia'           => $dir['provincia_nombre'],
                    'departamento'        => $dir['departamento_nombre']
                ] : null,
                'direccion'      => $dir['direccion'],
                'referencia'     => $dir['referencia'],
                'codigo_postal'  => $dir['codigo_postal'],
                'es_principal'   => (bool) $dir['es_principal'],
                'estado'         => $dir['estado']
            ], $detalle['direcciones']),
            'representantes' => array_map(fn($r) => [
                'id'                      => (int) $r['id'],
                'persona_natural_id'      => (int) $r['persona_natural_id'],
                'nombre_representante'    => $r['nombre_completo_representante'],
                'cargo'                   => $r['cargo'],
                'partida_registral'       => $r['partida_registral'],
                'fecha_inicio'            => $r['fecha_inicio'],
                'fecha_fin'               => $r['fecha_fin'],
                'es_representante_actual' => (bool) $r['es_representante_actual'],
                'estado'                  => $r['estado']
            ], $detalle['representantes'])
        ];
    }
}
