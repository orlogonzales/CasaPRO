<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\DTOs\ConsultaDataTablesDTO;
use App\Modelos\Persona;
use App\Modelos\PersonaNatural;
use App\Modelos\PersonaJuridica;
use App\Modelos\PersonaDocumento;
use App\Modelos\PersonaContacto;
use App\Modelos\PersonaDireccion;
use App\Modelos\PersonaRepresentante;
use PDO;

/**
 * PersonaRepositorio — Persistencia y consultas exclusivas con sentencias preparadas nativas PDO.
 * Cero concatenación de parámetros de usuario y cumplimiento estricto de whitelist.
 */
class PersonaRepositorio
{
    private ProveedorConexion $proveedorConexion;

    public function __construct(?ProveedorConexion $proveedorConexion = null)
    {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
    }

    private function obtenerConexion(?PDO $conexion = null): PDO
    {
        return $conexion ?? $this->proveedorConexion->obtenerConexion();
    }

    public function insertarPersona(Persona $persona, PDO $conexion): int
    {
        $sql = "INSERT INTO `personas` (`tipo_persona`, `estado`, `notas`, `creado_en`)
                VALUES (:tipo_persona, :estado, :notas, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':tipo_persona', $persona->obtenerTipoPersona(), PDO::PARAM_STR);
        $stmt->bindValue(':estado', $persona->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':notas', $persona->obtenerNotas(), $persona->obtenerNotas() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return (int) $conexion->lastInsertId();
    }

    public function insertarNatural(PersonaNatural $natural, PDO $conexion): void
    {
        $sql = "INSERT INTO `persona_natural` (
                    `persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`,
                    `fecha_nacimiento`, `sexo_id`, `estado_civil_id`, `pais_nacimiento_id`,
                    `profesion_ocupacion`, `creado_en`
                ) VALUES (
                    :persona_id, :nombres, :apellido_paterno, :apellido_materno,
                    :fecha_nacimiento, :sexo_id, :estado_civil_id, :pais_nacimiento_id,
                    :profesion_ocupacion, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $natural->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':nombres', $natural->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $natural->obtenerApellidoPaterno(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_materno', $natural->obtenerApellidoMaterno(), $natural->obtenerApellidoMaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $natural->obtenerFechaNacimiento(), $natural->obtenerFechaNacimiento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':sexo_id', $natural->obtenerSexoId(), $natural->obtenerSexoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':estado_civil_id', $natural->obtenerEstadoCivilId(), $natural->obtenerEstadoCivilId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacimiento_id', $natural->obtenerPaisNacimientoId(), $natural->obtenerPaisNacimientoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':profesion_ocupacion', $natural->obtenerProfesionOcupacion(), $natural->obtenerProfesionOcupacion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function insertarJuridica(PersonaJuridica $juridica, PDO $conexion): void
    {
        $sql = "INSERT INTO `persona_juridica` (
                    `persona_id`, `razon_social`, `nombre_comercial`, `fecha_constitucion`,
                    `objeto_social`, `creado_en`
                ) VALUES (
                    :persona_id, :razon_social, :nombre_comercial, :fecha_constitucion,
                    :objeto_social, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $juridica->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':razon_social', $juridica->obtenerRazonSocial(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_comercial', $juridica->obtenerNombreComercial(), $juridica->obtenerNombreComercial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_constitucion', $juridica->obtenerFechaConstitucion(), $juridica->obtenerFechaConstitucion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':objeto_social', $juridica->obtenerObjetoSocial(), $juridica->obtenerObjetoSocial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function insertarDocumento(PersonaDocumento $doc, PDO $conexion): int
    {
        $sql = "INSERT INTO `persona_documentos` (
                    `persona_id`, `tipo_documento_id`, `numero_documento`, `es_principal`,
                    `pais_emision_id`, `fecha_emision`, `fecha_vencimiento`, `estado`, `creado_en`
                ) VALUES (
                    :persona_id, :tipo_documento_id, :numero_documento, :es_principal,
                    :pais_emision_id, :fecha_emision, :fecha_vencimiento, :estado, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $doc->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_documento_id', $doc->obtenerTipoDocumentoId(), PDO::PARAM_INT);
        $stmt->bindValue(':numero_documento', $doc->obtenerNumeroDocumento(), PDO::PARAM_STR);
        $stmt->bindValue(':es_principal', $doc->esPrincipal() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':pais_emision_id', $doc->obtenerPaisEmisionId(), $doc->obtenerPaisEmisionId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_emision', $doc->obtenerFechaEmision(), $doc->obtenerFechaEmision() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_vencimiento', $doc->obtenerFechaVencimiento(), $doc->obtenerFechaVencimiento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $doc->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $conexion->lastInsertId();
    }

    public function insertarContacto(PersonaContacto $contacto, PDO $conexion): int
    {
        $sql = "INSERT INTO `persona_contactos` (
                    `persona_id`, `tipo_contacto_id`, `valor`, `etiqueta`, `es_principal`, `estado`, `creado_en`
                ) VALUES (
                    :persona_id, :tipo_contacto_id, :valor, :etiqueta, :es_principal, :estado, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $contacto->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_contacto_id', $contacto->obtenerTipoContactoId(), PDO::PARAM_INT);
        $stmt->bindValue(':valor', $contacto->obtenerValor(), PDO::PARAM_STR);
        $stmt->bindValue(':etiqueta', $contacto->obtenerEtiqueta(), $contacto->obtenerEtiqueta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':es_principal', $contacto->esPrincipal() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $contacto->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $conexion->lastInsertId();
    }

    public function insertarDireccion(PersonaDireccion $direccion, PDO $conexion): int
    {
        $sql = "INSERT INTO `persona_direcciones` (
                    `persona_id`, `tipo_direccion_id`, `distrito_id`, `direccion`,
                    `referencia`, `codigo_postal`, `es_principal`, `estado`, `creado_en`
                ) VALUES (
                    :persona_id, :tipo_direccion_id, :distrito_id, :direccion,
                    :referencia, :codigo_postal, :es_principal, :estado, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $direccion->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->bindValue(':tipo_direccion_id', $direccion->obtenerTipoDireccionId(), PDO::PARAM_INT);
        $stmt->bindValue(':distrito_id', $direccion->obtenerDistritoId(), $direccion->obtenerDistritoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':direccion', $direccion->obtenerDireccion(), PDO::PARAM_STR);
        $stmt->bindValue(':referencia', $direccion->obtenerReferencia(), $direccion->obtenerReferencia() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':codigo_postal', $direccion->obtenerCodigoPostal(), $direccion->obtenerCodigoPostal() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':es_principal', $direccion->esPrincipal() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $direccion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $conexion->lastInsertId();
    }

    public function insertarRepresentante(PersonaRepresentante $rep, PDO $conexion): int
    {
        $sql = "INSERT INTO `persona_representantes` (
                    `persona_juridica_id`, `persona_natural_id`, `cargo`, `partida_registral`,
                    `fecha_inicio`, `fecha_fin`, `es_representante_actual`, `estado`, `creado_en`
                ) VALUES (
                    :persona_juridica_id, :persona_natural_id, :cargo, :partida_registral,
                    :fecha_inicio, :fecha_fin, :es_representante_actual, :estado, NOW()
                )";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_juridica_id', $rep->obtenerPersonaJuridicaId(), PDO::PARAM_INT);
        $stmt->bindValue(':persona_natural_id', $rep->obtenerPersonaNaturalId(), PDO::PARAM_INT);
        $stmt->bindValue(':cargo', $rep->obtenerCargo(), PDO::PARAM_STR);
        $stmt->bindValue(':partida_registral', $rep->obtenerPartidaRegistral(), $rep->obtenerPartidaRegistral() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_inicio', $rep->obtenerFechaInicio(), PDO::PARAM_STR);
        $stmt->bindValue(':fecha_fin', $rep->obtenerFechaFin(), $rep->obtenerFechaFin() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':es_representante_actual', $rep->esRepresentanteActual() ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $rep->obtenerEstado(), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $conexion->lastInsertId();
    }

    public function actualizarPersona(Persona $persona, PDO $conexion): void
    {
        $sql = "UPDATE `personas`
                SET `notas` = :notas, `actualizado_en` = NOW()
                WHERE `id` = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':notas', $persona->obtenerNotas(), $persona->obtenerNotas() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':id', $persona->obtenerId(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizarNatural(PersonaNatural $natural, PDO $conexion): void
    {
        $sql = "UPDATE `persona_natural` SET
                    `nombres` = :nombres,
                    `apellido_paterno` = :apellido_paterno,
                    `apellido_materno` = :apellido_materno,
                    `fecha_nacimiento` = :fecha_nacimiento,
                    `sexo_id` = :sexo_id,
                    `estado_civil_id` = :estado_civil_id,
                    `pais_nacimiento_id` = :pais_nacimiento_id,
                    `profesion_ocupacion` = :profesion_ocupacion,
                    `actualizado_en` = NOW()
                WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':nombres', $natural->obtenerNombres(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_paterno', $natural->obtenerApellidoPaterno(), PDO::PARAM_STR);
        $stmt->bindValue(':apellido_materno', $natural->obtenerApellidoMaterno(), $natural->obtenerApellidoMaterno() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_nacimiento', $natural->obtenerFechaNacimiento(), $natural->obtenerFechaNacimiento() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':sexo_id', $natural->obtenerSexoId(), $natural->obtenerSexoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':estado_civil_id', $natural->obtenerEstadoCivilId(), $natural->obtenerEstadoCivilId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':pais_nacimiento_id', $natural->obtenerPaisNacimientoId(), $natural->obtenerPaisNacimientoId() !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':profesion_ocupacion', $natural->obtenerProfesionOcupacion(), $natural->obtenerProfesionOcupacion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':persona_id', $natural->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizarJuridica(PersonaJuridica $juridica, PDO $conexion): void
    {
        $sql = "UPDATE `persona_juridica` SET
                    `razon_social` = :razon_social,
                    `nombre_comercial` = :nombre_comercial,
                    `fecha_constitucion` = :fecha_constitucion,
                    `objeto_social` = :objeto_social,
                    `actualizado_en` = NOW()
                WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':razon_social', $juridica->obtenerRazonSocial(), PDO::PARAM_STR);
        $stmt->bindValue(':nombre_comercial', $juridica->obtenerNombreComercial(), $juridica->obtenerNombreComercial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_constitucion', $juridica->obtenerFechaConstitucion(), $juridica->obtenerFechaConstitucion() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':objeto_social', $juridica->obtenerObjetoSocial(), $juridica->obtenerObjetoSocial() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':persona_id', $juridica->obtenerPersonaId(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function cambiarEstadoPersona(int $personaId, string $nuevoEstado, PDO $conexion): void
    {
        $sql = "UPDATE `personas` SET `estado` = :estado, `actualizado_en` = NOW() WHERE `id` = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarDocumentosPrincipales(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_documentos` SET `es_principal` = 0 WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarContactosPrincipales(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_contactos` SET `es_principal` = 0 WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarDireccionesPrincipales(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_direcciones` SET `es_principal` = 0 WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarRepresentantesActuales(int $personaJuridicaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_representantes` SET `es_representante_actual` = 0 WHERE `persona_juridica_id` = :juridica_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':juridica_id', $personaJuridicaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarDocumentos(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_documentos` SET `estado` = 'INACTIVO' WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarContactos(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_contactos` SET `estado` = 'INACTIVO' WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarDirecciones(int $personaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_direcciones` SET `estado` = 'INACTIVO' WHERE `persona_id` = :persona_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function desactivarRepresentantes(int $personaJuridicaId, PDO $conexion): void
    {
        $sql = "UPDATE `persona_representantes` SET `estado` = 'INACTIVO', `es_representante_actual` = 0 WHERE `persona_juridica_id` = :juridica_id";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':juridica_id', $personaJuridicaId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?array
    {
        $pdo = $this->obtenerConexion($conexion);
        $sql = "SELECT `id`, `tipo_persona`, `estado`, `notas`, `creado_en`, `actualizado_en`
                FROM `personas` WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function existePersona(int $id, ?PDO $conexion = null): bool
    {
        $pdo = $this->obtenerConexion($conexion);
        $stmt = $pdo->prepare("SELECT 1 FROM `personas` WHERE `id` = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function existeDocumento(int $tipoDocumentoId, string $numeroDocumento, ?int $excluirPersonaId = null, ?PDO $conexion = null): bool
    {
        $pdo = $this->obtenerConexion($conexion);
        $sql = "SELECT 1 FROM `persona_documentos`
                WHERE `tipo_documento_id` = :tipo_id
                  AND `numero_documento` = :numero";
        if ($excluirPersonaId !== null) {
            $sql .= " AND `persona_id` != :excluir_id";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_id', $tipoDocumentoId, PDO::PARAM_INT);
        $stmt->bindValue(':numero', trim($numeroDocumento), PDO::PARAM_STR);
        if ($excluirPersonaId !== null) {
            $stmt->bindValue(':excluir_id', $excluirPersonaId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function obtenerDetalleCompleto360(int $id, ?PDO $conexion = null): ?array
    {
        $pdo = $this->obtenerConexion($conexion);

        // 1. Cabecera Persona
        $stmtPersona = $pdo->prepare("SELECT * FROM `personas` WHERE `id` = :id");
        $stmtPersona->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtPersona->execute();
        $persona = $stmtPersona->fetch(PDO::FETCH_ASSOC);
        if (!$persona) {
            return null;
        }

        // 2. Extensión Natural o Jurídica
        $natural = null;
        $juridica = null;

        if ($persona['tipo_persona'] === 'NATURAL') {
            $stmtNat = $pdo->prepare("
                SELECT pn.*,
                       s.nombre AS sexo_nombre, s.codigo AS sexo_codigo,
                       ec.nombre AS estado_civil_nombre, ec.codigo AS estado_civil_codigo,
                       p.nombre AS pais_nacimiento_nombre
                FROM `persona_natural` pn
                LEFT JOIN `sexos` s ON pn.sexo_id = s.id
                LEFT JOIN `estados_civiles` ec ON pn.estado_civil_id = ec.id
                LEFT JOIN `paises` p ON pn.pais_nacimiento_id = p.id
                WHERE pn.persona_id = :id
            ");
            $stmtNat->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtNat->execute();
            $natural = $stmtNat->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $stmtJur = $pdo->prepare("SELECT * FROM `persona_juridica` WHERE `persona_id` = :id");
            $stmtJur->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtJur->execute();
            $juridica = $stmtJur->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 3. Documentos
        $stmtDocs = $pdo->prepare("
            SELECT pd.*, td.codigo AS tipo_codigo, td.nombre AS tipo_nombre, td.codigo AS tipo_abreviatura,
                   p.nombre AS pais_emision_nombre
            FROM `persona_documentos` pd
            JOIN `tipos_documento` td ON pd.tipo_documento_id = td.id
            LEFT JOIN `paises` p ON pd.pais_emision_id = p.id
            WHERE pd.persona_id = :id AND pd.estado = 'ACTIVO'
            ORDER BY pd.es_principal DESC, pd.id ASC
        ");
        $stmtDocs->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtDocs->execute();
        $documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        // 4. Contactos
        $stmtCon = $pdo->prepare("
            SELECT pc.*, tc.codigo AS tipo_codigo, tc.nombre AS tipo_nombre
            FROM `persona_contactos` pc
            JOIN `tipos_contacto` tc ON pc.tipo_contacto_id = tc.id
            WHERE pc.persona_id = :id AND pc.estado = 'ACTIVO'
            ORDER BY pc.es_principal DESC, pc.id ASC
        ");
        $stmtCon->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCon->execute();
        $contactos = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

        // 5. Direcciones con UBIGEO
        $stmtDir = $pdo->prepare("
            SELECT pdir.*, tdir.codigo AS tipo_codigo, tdir.nombre AS tipo_nombre,
                   d.nombre AS distrito_nombre, d.codigo_ubigeo AS distrito_codigo,
                   pr.nombre AS provincia_nombre,
                   dep.nombre AS departamento_nombre
            FROM `persona_direcciones` pdir
            JOIN `tipos_direccion` tdir ON pdir.tipo_direccion_id = tdir.id
            LEFT JOIN `distritos` d ON pdir.distrito_id = d.id
            LEFT JOIN `provincias` pr ON d.provincia_id = pr.id
            LEFT JOIN `departamentos` dep ON pr.departamento_id = dep.id
            WHERE pdir.persona_id = :id AND pdir.estado = 'ACTIVO'
            ORDER BY pdir.es_principal DESC, pdir.id ASC
        ");
        $stmtDir->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtDir->execute();
        $direcciones = $stmtDir->fetchAll(PDO::FETCH_ASSOC);

        // 6. Representantes (si es jurídica)
        $representantes = [];
        if ($persona['tipo_persona'] === 'JURIDICA') {
            $stmtRep = $pdo->prepare("
                SELECT pr.*,
                       pn.nombres, pn.apellido_paterno, pn.apellido_materno,
                       CONCAT(pn.apellido_paterno, ' ', COALESCE(pn.apellido_materno, ''), ', ', pn.nombres) AS nombre_completo_representante
                FROM `persona_representantes` pr
                JOIN `persona_natural` pn ON pr.persona_natural_id = pn.persona_id
                WHERE pr.persona_juridica_id = :id AND pr.estado = 'ACTIVO'
                ORDER BY pr.es_representante_actual DESC, pr.id ASC
            ");
            $stmtRep->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtRep->execute();
            $representantes = $stmtRep->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'persona'        => $persona,
            'natural'        => $natural,
            'juridica'       => $juridica,
            'documentos'     => $documentos,
            'contactos'      => $contactos,
            'direcciones'    => $direcciones,
            'representantes' => $representantes
        ];
    }

    public function consultarDataTables(ConsultaDataTablesDTO $dto, ?PDO $conexion = null): array
    {
        $pdo = $this->obtenerConexion($conexion);

        // 1. Total general sin filtros de búsqueda
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM `personas`");
        $totalGeneral = (int) $stmtTotal->fetchColumn();

        // 2. Construir cláusula WHERE
        $condiciones = [];
        $parametros = [];

        if ($dto->filtroTipoPersona !== null) {
            $condiciones[] = "p.tipo_persona = :filtro_tipo";
            $parametros[':filtro_tipo'] = $dto->filtroTipoPersona;
        }

        if ($dto->filtroEstado !== null) {
            $condiciones[] = "p.estado = :filtro_estado";
            $parametros[':filtro_estado'] = $dto->filtroEstado;
        }

        if ($dto->searchValue !== null) {
            $condiciones[] = "(
                pn.nombres LIKE :busqueda1 OR
                pn.apellido_paterno LIKE :busqueda2 OR
                pn.apellido_materno LIKE :busqueda3 OR
                pj.razon_social LIKE :busqueda4 OR
                pj.nombre_comercial LIKE :busqueda5 OR
                doc.numero_documento LIKE :busqueda6 OR
                con.valor LIKE :busqueda7
            )";
            $patronLike = '%' . $dto->searchValue . '%';
            $parametros[':busqueda1'] = $patronLike;
            $parametros[':busqueda2'] = $patronLike;
            $parametros[':busqueda3'] = $patronLike;
            $parametros[':busqueda4'] = $patronLike;
            $parametros[':busqueda5'] = $patronLike;
            $parametros[':busqueda6'] = $patronLike;
            $parametros[':busqueda7'] = $patronLike;
        }

        $whereSql = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        // 3. Conteo filtrado
        $sqlConteo = "
            SELECT COUNT(DISTINCT p.id)
            FROM `personas` p
            LEFT JOIN `persona_natural` pn ON p.id = pn.persona_id
            LEFT JOIN `persona_juridica` pj ON p.id = pj.persona_id
            LEFT JOIN `persona_documentos` doc ON p.id = doc.persona_id AND doc.es_principal = 1 AND doc.estado = 'ACTIVO'
            LEFT JOIN `persona_contactos` con ON p.id = con.persona_id AND con.es_principal = 1 AND con.estado = 'ACTIVO'
            {$whereSql}
        ";
        $stmtConteo = $pdo->prepare($sqlConteo);
        foreach ($parametros as $clave => $val) {
            $stmtConteo->bindValue($clave, $val, PDO::PARAM_STR);
        }
        $stmtConteo->execute();
        $totalFiltrado = (int) $stmtConteo->fetchColumn();

        // 4. Consulta de datos paginada con whitelist estricta
        $sqlDatos = "
            SELECT
                p.id,
                p.tipo_persona,
                p.estado,
                p.creado_en,
                CASE
                    WHEN p.tipo_persona = 'NATURAL' THEN CONCAT(pn.apellido_paterno, ' ', COALESCE(pn.apellido_materno, ''), ', ', pn.nombres)
                    ELSE pj.razon_social
                END AS nombre_completo,
                doc.numero_documento AS documento_principal,
                td.codigo AS tipo_documento_abreviatura,
                con.valor AS contacto_principal
            FROM `personas` p
            LEFT JOIN `persona_natural` pn ON p.id = pn.persona_id
            LEFT JOIN `persona_juridica` pj ON p.id = pj.persona_id
            LEFT JOIN `persona_documentos` doc ON p.id = doc.persona_id AND doc.es_principal = 1 AND doc.estado = 'ACTIVO'
            LEFT JOIN `tipos_documento` td ON doc.tipo_documento_id = td.id
            LEFT JOIN `persona_contactos` con ON p.id = con.persona_id AND con.es_principal = 1 AND con.estado = 'ACTIVO'
            {$whereSql}
            ORDER BY {$dto->orderColumn} {$dto->orderDir}
            LIMIT :limit OFFSET :offset
        ";

        $stmtDatos = $pdo->prepare($sqlDatos);
        foreach ($parametros as $clave => $val) {
            $stmtDatos->bindValue($clave, $val, PDO::PARAM_STR);
        }
        $stmtDatos->bindValue(':limit', $dto->length, PDO::PARAM_INT);
        $stmtDatos->bindValue(':offset', $dto->start, PDO::PARAM_INT);
        $stmtDatos->execute();
        $filas = $stmtDatos->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_general'  => $totalGeneral,
            'total_filtrado' => $totalFiltrado,
            'datos'          => $filas
        ];
    }

    /**
     * Busca los datos mínimos de identidad de una persona a partir de un documento específico.
     * Utilizado para verificación anti-duplicidad previa en consultas de identidad.
     *
     * @return array{id: int, tipo_persona: string, estado: string, nombre_completo: string}|null
     */
    public function buscarPersonaPorDocumento(int $tipoDocumentoId, string $numeroDocumento, ?PDO $conexion = null): ?array
    {
        $pdo = $this->obtenerConexion($conexion);
        $sql = "
            SELECT
                p.id,
                p.tipo_persona,
                p.estado,
                CASE
                    WHEN p.tipo_persona = 'NATURAL' THEN CONCAT(pn.apellido_paterno, ' ', COALESCE(pn.apellido_materno, ''), ', ', pn.nombres)
                    ELSE pj.razon_social
                END AS nombre_completo
            FROM `persona_documentos` doc
            JOIN `personas` p ON doc.persona_id = p.id
            LEFT JOIN `persona_natural` pn ON p.id = pn.persona_id
            LEFT JOIN `persona_juridica` pj ON p.id = pj.persona_id
            WHERE doc.tipo_documento_id = :tipo_id
              AND doc.numero_documento = :numero
              AND doc.estado = 'ACTIVO'
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tipo_id', $tipoDocumentoId, PDO::PARAM_INT);
        $stmt->bindValue(':numero', trim($numeroDocumento), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        return [
            'id' => (int) $fila['id'],
            'tipo_persona' => (string) $fila['tipo_persona'],
            'estado' => (string) $fila['estado'],
            'nombre_completo' => trim((string) ($fila['nombre_completo'] ?? ''))
        ];
    }

    /**
     * Obtiene los metadatos y reglas de validación de un tipo de documento por su ID.
     */
    public function obtenerTipoDocumentoPorId(int $tipoDocumentoId, ?PDO $conexion = null): ?array
    {
        $pdo = $this->obtenerConexion($conexion);
        $stmt = $pdo->prepare("SELECT * FROM `tipos_documento` WHERE `id` = :id AND `estado` = 'ACTIVO' LIMIT 1");
        $stmt->bindValue(':id', $tipoDocumentoId, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    /**
     * Obtiene todos los catálogos necesarios para alimentar el modal de formulario de Persona.
     *
     * @return array<string, mixed>
     */
    public function obtenerCatalogosFormulario(?PDO $conexion = null): array
    {
        $pdo = $this->obtenerConexion($conexion);

        $tiposDocumento = $pdo->query("SELECT * FROM `tipos_documento` WHERE `estado` = 'ACTIVO' ORDER BY `orden` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $sexos = $pdo->query("SELECT * FROM `sexos` WHERE `activo` = 1 ORDER BY `orden` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $estadosCiviles = $pdo->query("SELECT * FROM `estados_civiles` WHERE `activo` = 1 ORDER BY `orden` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $tiposContacto = $pdo->query("SELECT * FROM `tipos_contacto` WHERE `activo` = 1 ORDER BY `orden` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $tiposDireccion = $pdo->query("SELECT * FROM `tipos_direccion` WHERE `activo` = 1 ORDER BY `orden` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $departamentos = $pdo->query("SELECT * FROM `departamentos` WHERE `activo` = 1 ORDER BY `nombre` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $paises = $pdo->query("SELECT id, codigo_iso2, nombre, nacionalidad FROM `paises` WHERE `activo` = 1 ORDER BY `nombre` ASC")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tipos_documento'  => $tiposDocumento,
            'sexos'            => $sexos,
            'estados_civiles'  => $estadosCiviles,
            'tipos_contacto'   => $tiposContacto,
            'tipos_direccion'  => $tiposDireccion,
            'departamentos'    => $departamentos,
            'paises'           => $paises
        ];
    }

    /**
     * Obtiene provincias filtradas por ID de departamento.
     */
    public function obtenerProvinciasPorDepartamento(int $departamentoId, ?PDO $conexion = null): array
    {
        $pdo = $this->obtenerConexion($conexion);
        $stmt = $pdo->prepare("SELECT id, departamento_id, codigo_ubigeo, nombre FROM `provincias` WHERE `departamento_id` = :dep_id AND `activo` = 1 ORDER BY `nombre` ASC");
        $stmt->bindValue(':dep_id', $departamentoId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene distritos filtrados por ID de provincia.
     */
    public function obtenerDistritosPorProvincia(int $provinciaId, ?PDO $conexion = null): array
    {
        $pdo = $this->obtenerConexion($conexion);
        $stmt = $pdo->prepare("SELECT id, provincia_id, codigo_ubigeo, nombre FROM `distritos` WHERE `provincia_id` = :prov_id AND `activo` = 1 ORDER BY `nombre` ASC");
        $stmt->bindValue(':prov_id', $provinciaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca la jerarquía UBIGEO (distrito, provincia, departamento) a partir del código de ubigeo (6 dígitos).
     * Utilizado para autocompletar la cascada geográfica devuelta por consultas tributarias (RUC).
     */
    public function buscarJerarquiaUbigeo(string $codigoUbigeo, ?PDO $conexion = null): ?array
    {
        $pdo = $this->obtenerConexion($conexion);
        $sql = "
            SELECT
                dis.id AS distrito_id,
                dis.nombre AS distrito_nombre,
                pro.id AS provincia_id,
                pro.nombre AS provincia_nombre,
                dep.id AS departamento_id,
                dep.nombre AS departamento_nombre
            FROM `distritos` dis
            JOIN `provincias` pro ON dis.provincia_id = pro.id
            JOIN `departamentos` dep ON pro.departamento_id = dep.id
            WHERE dis.codigo_ubigeo = :ubigeo AND dis.activo = 1
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':ubigeo', trim($codigoUbigeo), PDO::PARAM_STR);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }
}

