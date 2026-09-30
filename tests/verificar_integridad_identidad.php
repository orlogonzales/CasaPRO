<?php

declare(strict_types=1);

/**
 * Suite de Verificación de Integridad Referencial y Dominio de Identidad (Microfase 1B).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\CargadorEntorno;
use App\Core\ProveedorConexion;
use App\Modelos\Persona;
use App\Modelos\PersonaNatural;
use App\Modelos\PersonaJuridica;
use App\Modelos\PersonaDocumento;
use App\Modelos\PersonaContacto;
use App\Modelos\PersonaDireccion;
use App\Modelos\PersonaRepresentante;

CargadorEntorno::cargar(dirname(__DIR__));

echo "===================================================================\n";
echo " PRUEBAS DE INTEGRIDAD REFERENCIAL Y MODELO IDENTIDAD (FASE 1B)\n";
echo "===================================================================\n";

$proveedor = new ProveedorConexion();
$pdo = $proveedor->obtenerConexion();

$errores = 0;
$pruebas = 0;

function afirmar(bool $condicion, string $descripcion, string &$detalles = ''): void
{
    global $errores, $pruebas;
    $pruebas++;
    if ($condicion) {
        echo "  [PASS] {$descripcion}\n";
    } else {
        $errores++;
        echo "  [FAIL] {$descripcion}" . ($detalles !== '' ? " -> {$detalles}" : '') . "\n";
    }
}

// Iniciar transacción de prueba para garantizar cero basura residual
$pdo->beginTransaction();

try {
    // -------------------------------------------------------------------------
    // 1. Integridad de Catálogos y UBIGEO
    // -------------------------------------------------------------------------
    echo "\n1. Verificando Catálogos Estructurales y UBIGEO Oficial INEI...\n";
    $stmtDeptos = $pdo->query("SELECT COUNT(*) FROM `departamentos`");
    $totalDeptos = (int) $stmtDeptos->fetchColumn();
    afirmar($totalDeptos === 25, "Departamentos oficiales INEI: 25 (obtenido: {$totalDeptos})");

    $stmtProvs = $pdo->query("SELECT COUNT(*) FROM `provincias`");
    $totalProvs = (int) $stmtProvs->fetchColumn();
    afirmar($totalProvs === 196, "Provincias oficiales INEI: 196 (obtenido: {$totalProvs})");

    $stmtDists = $pdo->query("SELECT COUNT(*) FROM `distritos`");
    $totalDists = (int) $stmtDists->fetchColumn();
    afirmar($totalDists === 1874, "Distritos oficiales INEI: 1,874 (obtenido: {$totalDists})");

    // Verificar jerarquía relacional UBIGEO (Distrito Cusco 080101 o similar)
    $stmtCusco = $pdo->query("
        SELECT d.nombre AS distrito, p.nombre AS provincia, dep.nombre AS depto
        FROM distritos d
        JOIN provincias p ON d.provincia_id = p.id
        JOIN departamentos dep ON p.departamento_id = dep.id
        WHERE d.codigo_ubigeo = '080101'
    ");
    $cusco = $stmtCusco->fetch();
    afirmar(
        $cusco && $cusco['distrito'] === 'Cusco' && $cusco['provincia'] === 'Cusco' && $cusco['depto'] === 'Cusco',
        "Jerarquía relacional UBIGEO válida (080101: Cusco -> Cusco -> Cusco)"
    );

    // -------------------------------------------------------------------------
    // 2. Persona Natural Válida y Entidades PHP
    // -------------------------------------------------------------------------
    echo "\n2. Creando Persona Natural con entidad PHP...\n";
    $personaN = new Persona(tipoPersona: Persona::TIPO_NATURAL, estado: Persona::ESTADO_ACTIVO, notas: 'Prospecto residencial');
    afirmar($personaN->esNatural() && $personaN->estaActiva(), "Entidad Persona (Natural, ACTIVO) válida");

    $stmtInsPer = $pdo->prepare("INSERT INTO `personas` (`tipo_persona`, `estado`, `notas`) VALUES (:tipo, :estado, :notas)");
    $stmtInsPer->execute([
        ':tipo' => $personaN->obtenerTipoPersona(),
        ':estado' => $personaN->obtenerEstado(),
        ':notas' => $personaN->obtenerNotas(),
    ]);
    $idPersonaNatural = (int) $pdo->lastInsertId();
    $personaN->asignarId($idPersonaNatural);
    afirmar($idPersonaNatural > 0, "Inserción raíz de Persona Natural exitosa (ID: {$idPersonaNatural})");

    $natModel = new PersonaNatural(
        personaId: $idPersonaNatural,
        nombres: 'Carlos Alberto',
        apellidoPaterno: 'Quispe',
        apellidoMaterno: 'Mamani',
        fechaNacimiento: '1992-06-15',
        sexoId: 1, // MASCULINO
        estadoCivilId: 1, // SOLTERO
        paisNacimientoId: 1, // Perú
        profesionOcupacion: 'Ingeniero Civil'
    );
    afirmar($natModel->obtenerNombreCompleto() === 'Carlos Alberto Quispe Mamani', "Nombre completo formateado correctamente");

    $stmtInsNat = $pdo->prepare("
        INSERT INTO `persona_natural` (
            `persona_id`, `nombres`, `apellido_paterno`, `apellido_materno`,
            `fecha_nacimiento`, `sexo_id`, `estado_civil_id`, `pais_nacimiento_id`, `profesion_ocupacion`
        ) VALUES (
            :pid, :nom, :pat, :mat, :fnac, :sexo, :ecivil, :pais, :prof
        )
    ");
    $stmtInsNat->execute([
        ':pid' => $natModel->obtenerPersonaId(),
        ':nom' => $natModel->obtenerNombres(),
        ':pat' => $natModel->obtenerApellidoPaterno(),
        ':mat' => $natModel->obtenerApellidoMaterno(),
        ':fnac' => $natModel->obtenerFechaNacimiento(),
        ':sexo' => $natModel->obtenerSexoId(),
        ':ecivil' => $natModel->obtenerEstadoCivilId(),
        ':pais' => $natModel->obtenerPaisNacimientoId(),
        ':prof' => $natModel->obtenerProfesionOcupacion(),
    ]);
    afirmar(true, "Inserción satélite persona_natural exitosa");

    // -------------------------------------------------------------------------
    // 3. Persona Jurídica Válida
    // -------------------------------------------------------------------------
    echo "\n3. Creando Persona Jurídica con entidad PHP...\n";
    $personaJ = new Persona(tipoPersona: Persona::TIPO_JURIDICA, estado: Persona::ESTADO_ACTIVO, notas: 'Empresa contratista');
    $stmtInsPer->execute([
        ':tipo' => $personaJ->obtenerTipoPersona(),
        ':estado' => $personaJ->obtenerEstado(),
        ':notas' => $personaJ->obtenerNotas(),
    ]);
    $idPersonaJuridica = (int) $pdo->lastInsertId();
    $personaJ->asignarId($idPersonaJuridica);

    $jurModel = new PersonaJuridica(
        personaId: $idPersonaJuridica,
        razonSocial: 'INMOBILIARIA BONIFACIO S.A.C.',
        nombreComercial: 'GRUPO BONIFACIO',
        fechaConstitucion: '2016-04-10',
        objetoSocial: 'Desarrollo y comercialización de proyectos inmobiliarios'
    );
    afirmar($jurModel->obtenerDenominacion() === 'INMOBILIARIA BONIFACIO S.A.C. (GRUPO BONIFACIO)', "Denominación comercial formateada");

    $stmtInsJur = $pdo->prepare("
        INSERT INTO `persona_juridica` (`persona_id`, `razon_social`, `nombre_comercial`, `fecha_constitucion`, `objeto_social`)
        VALUES (:pid, :raz, :com, :fconst, :obj)
    ");
    $stmtInsJur->execute([
        ':pid' => $jurModel->obtenerPersonaId(),
        ':raz' => $jurModel->obtenerRazonSocial(),
        ':com' => $jurModel->obtenerNombreComercial(),
        ':fconst' => $jurModel->obtenerFechaConstitucion(),
        ':obj' => $jurModel->obtenerObjetoSocial(),
    ]);
    afirmar(true, "Inserción satélite persona_juridica exitosa (sin RUC directo)");

    // -------------------------------------------------------------------------
    // 4. Múltiples Documentos y Unicidad (tipo_documento_id + numero_documento)
    // -------------------------------------------------------------------------
    echo "\n4. Verificando documentos y unicidad estricta...\n";
    $docDni = new PersonaDocumento(
        personaId: $idPersonaNatural,
        tipoDocumentoId: 1, // DNI
        numeroDocumento: '45892147',
        esPrincipal: true
    );
    $stmtInsDoc = $pdo->prepare("
        INSERT INTO `persona_documentos` (`persona_id`, `tipo_documento_id`, `numero_documento`, `es_principal`, `estado`)
        VALUES (:pid, :tdoc, :num, :princi, :estado)
    ");
    $stmtInsDoc->execute([
        ':pid' => $docDni->obtenerPersonaId(),
        ':tdoc' => $docDni->obtenerTipoDocumentoId(),
        ':num' => $docDni->obtenerNumeroDocumento(),
        ':princi' => $docDni->esPrincipal() ? 1 : 0,
        ':estado' => $docDni->obtenerEstado(),
    ]);
    afirmar(true, "Documento DNI principal registrado para Persona Natural");

    // Segundo documento para la misma persona (Pasaporte)
    $docPas = new PersonaDocumento(
        personaId: $idPersonaNatural,
        tipoDocumentoId: 4, // PASAPORTE
        numeroDocumento: 'PE884125',
        esPrincipal: false
    );
    $stmtInsDoc->execute([
        ':pid' => $docPas->obtenerPersonaId(),
        ':tdoc' => $docPas->obtenerTipoDocumentoId(),
        ':num' => $docPas->obtenerNumeroDocumento(),
        ':princi' => $docPas->esPrincipal() ? 1 : 0,
        ':estado' => $docPas->obtenerEstado(),
    ]);
    afirmar(true, "Segundo documento (Pasaporte) registrado exitosamente (1:N)");

    // RUC para Persona Jurídica
    $docRuc = new PersonaDocumento(
        personaId: $idPersonaJuridica,
        tipoDocumentoId: 2, // RUC
        numeroDocumento: '20601234567',
        esPrincipal: true
    );
    $stmtInsDoc->execute([
        ':pid' => $docRuc->obtenerPersonaId(),
        ':tdoc' => $docRuc->obtenerTipoDocumentoId(),
        ':num' => $docRuc->obtenerNumeroDocumento(),
        ':princi' => $docRuc->esPrincipal() ? 1 : 0,
        ':estado' => $docRuc->obtenerEstado(),
    ]);
    afirmar(true, "RUC asignado a Persona Jurídica mediante persona_documentos");

    // Intento de duplicar DNI en otra persona -> Debe fallar con SQLSTATE 23000
    $duplicadoCapturado = false;
    try {
        $stmtInsDoc->execute([
            ':pid' => $idPersonaJuridica, // otra persona
            ':tdoc' => 1, // mismo DNI
            ':num' => '45892147', // mismo número
            ':princi' => 0,
            ':estado' => 'ACTIVO',
        ]);
    } catch (\PDOException $e) {
        $duplicadoCapturado = true;
    }
    afirmar($duplicadoCapturado, "Rechazo de documento duplicado por clave UNIQUE (tipo_documento_id + numero_documento)");

    // -------------------------------------------------------------------------
    // 5. Múltiples Contactos
    // -------------------------------------------------------------------------
    echo "\n5. Verificando múltiples contactos (1:N)...\n";
    $stmtInsCont = $pdo->prepare("
        INSERT INTO `persona_contactos` (`persona_id`, `tipo_contacto_id`, `valor`, `etiqueta`, `es_principal`, `estado`)
        VALUES (:pid, :tcont, :val, :etiq, :princi, :estado)
    ");
    $stmtInsCont->execute([':pid' => $idPersonaNatural, ':tcont' => 1, ':val' => 'carlos.quispe@ejemplo.pe', ':etiq' => 'Personal', ':princi' => 1, ':estado' => 'ACTIVO']);
    $stmtInsCont->execute([':pid' => $idPersonaNatural, ':tcont' => 2, ':val' => '+51 984 123 456', ':etiq' => 'Móvil Principal', ':princi' => 1, ':estado' => 'ACTIVO']);
    $stmtInsCont->execute([':pid' => $idPersonaNatural, ':tcont' => 4, ':val' => '+51 984 123 456', ':etiq' => 'WhatsApp', ':princi' => 0, ':estado' => 'ACTIVO']);

    $stmtCountCont = $pdo->prepare("SELECT COUNT(*) FROM `persona_contactos` WHERE `persona_id` = ?");
    $stmtCountCont->execute([$idPersonaNatural]);
    $totalContactos = (int) $stmtCountCont->fetchColumn();
    afirmar($totalContactos === 3, "Múltiples contactos asociados a la persona (total: {$totalContactos})");

    // -------------------------------------------------------------------------
    // 6. Múltiples Direcciones con UBIGEO
    // -------------------------------------------------------------------------
    echo "\n6. Verificando múltiples direcciones con UBIGEO (1:N)...\n";
    $distritoCuscoId = (int) $pdo->query("SELECT id FROM distritos WHERE codigo_ubigeo = '080101'")->fetchColumn();
    $distritoWanchaqId = (int) $pdo->query("SELECT id FROM distritos WHERE codigo_ubigeo = '080108'")->fetchColumn();

    $stmtInsDir = $pdo->prepare("
        INSERT INTO `persona_direcciones` (`persona_id`, `tipo_direccion_id`, `distrito_id`, `direccion`, `referencia`, `es_principal`, `estado`)
        VALUES (:pid, :tdir, :dist, :dir, :ref, :princi, :estado)
    ");
    $stmtInsDir->execute([':pid' => $idPersonaNatural, ':tdir' => 1, ':dist' => $distritoCuscoId, ':dir' => 'Av. El Sol 450', ':ref' => 'Frente al Qorikancha', ':princi' => 1, ':estado' => 'ACTIVO']);
    $stmtInsDir->execute([':pid' => $idPersonaNatural, ':tdir' => 3, ':dist' => $distritoWanchaqId, ':dir' => 'Av. Huayruropata 120 Oficina 301', ':ref' => 'A media cuadra del terminal', ':princi' => 0, ':estado' => 'ACTIVO']);

    $stmtCountDir = $pdo->prepare("SELECT COUNT(*) FROM `persona_direcciones` WHERE `persona_id` = ?");
    $stmtCountDir->execute([$idPersonaNatural]);
    $totalDirecciones = (int) $stmtCountDir->fetchColumn();
    afirmar($totalDirecciones === 2, "Múltiples direcciones asociadas con UBIGEO válido (total: {$totalDirecciones})");

    // -------------------------------------------------------------------------
    // 7. Representación Legal Histórica (Natural -> Jurídica)
    // -------------------------------------------------------------------------
    echo "\n7. Verificando historial de representación legal...\n";
    $repModel = new PersonaRepresentante(
        personaJuridicaId: $idPersonaJuridica,
        personaNaturalId: $idPersonaNatural,
        cargo: 'GERENTE GENERAL',
        fechaInicio: '2020-01-01',
        partidaRegistral: '11029482',
        esRepresentanteActual: true
    );
    $stmtInsRep = $pdo->prepare("
        INSERT INTO `persona_representantes` (
            `persona_juridica_id`, `persona_natural_id`, `cargo`, `partida_registral`,
            `fecha_inicio`, `es_representante_actual`, `estado`
        ) VALUES (
            :pjid, :pnid, :cargo, :part, :finicio, :actual, :estado
        )
    ");
    $stmtInsRep->execute([
        ':pjid' => $repModel->obtenerPersonaJuridicaId(),
        ':pnid' => $repModel->obtenerPersonaNaturalId(),
        ':cargo' => $repModel->obtenerCargo(),
        ':part' => $repModel->obtenerPartidaRegistral(),
        ':finicio' => $repModel->obtenerFechaInicio(),
        ':actual' => $repModel->esRepresentanteActual() ? 1 : 0,
        ':estado' => $repModel->obtenerEstado(),
    ]);
    afirmar(true, "Representante legal Natural -> Jurídica registrado con éxito");

    // -------------------------------------------------------------------------
    // 8. Integridad Referencial: Rechazo de FK inválida
    // -------------------------------------------------------------------------
    echo "\n8. Verificando rechazo de Foreign Keys inválidas...\n";
    $fkInvalidaCapturada = false;
    try {
        $stmtInsNat->execute([
            ':pid' => 999999999, // persona inexistente
            ':nom' => 'Fantasma',
            ':pat' => 'Inexistente',
            ':mat' => null,
            ':fnac' => null,
            ':sexo' => null,
            ':ecivil' => null,
            ':pais' => null,
            ':prof' => null,
        ]);
    } catch (\PDOException $e) {
        $fkInvalidaCapturada = true;
    }
    afirmar($fkInvalidaCapturada, "Rechazo de FK inexistente en persona_id");

    $fkDistritoInvalida = false;
    try {
        $stmtInsDir->execute([
            ':pid' => $idPersonaNatural,
            ':tdir' => 1,
            ':dist' => 9999999, // distrito inexistente
            ':dir' => 'Calle Falsa 123',
            ':ref' => null,
            ':princi' => 0,
            ':estado' => 'ACTIVO',
        ]);
    } catch (\PDOException $e) {
        $fkDistritoInvalida = true;
    }
    afirmar($fkDistritoInvalida, "Rechazo de FK inexistente en distrito_id");

    // -------------------------------------------------------------------------
    // 9. Integridad Referencial: Imposibilidad de destrucción ordinaria (RESTRICT)
    // -------------------------------------------------------------------------
    echo "\n9. Verificando política ON DELETE RESTRICT (protección contra borrado accidental)...\n";
    $borradoBloqueado = false;
    try {
        $pdo->exec("DELETE FROM `personas` WHERE `id` = {$idPersonaNatural}");
    } catch (\PDOException $e) {
        $borradoBloqueado = true;
    }
    afirmar($borradoBloqueado, "ON DELETE RESTRICT impide borrar identidad con registros dependientes (documentos, contactos, direcciones)");

    // -------------------------------------------------------------------------
    // 10. Validación de Estados de Persona (ACTIVO / INACTIVO)
    // -------------------------------------------------------------------------
    echo "\n10. Verificando integridad de estados de Persona...\n";
    $personaN->establecerEstado(Persona::ESTADO_INACTIVO);
    afirmar(!$personaN->estaActiva() && $personaN->obtenerEstado() === 'INACTIVO', "Transición a INACTIVO en entidad Persona válida");

    $estadoBloqueadoInvalido = false;
    try {
        new Persona(tipoPersona: 'NATURAL', estado: 'BLOQUEADO');
    } catch (\InvalidArgumentException $e) {
        $estadoBloqueadoInvalido = true;
    }
    afirmar($estadoBloqueadoInvalido, "Rechazo del estado BLOQUEADO en Persona (reservado para cuentas de usuario)");

} catch (\Throwable $e) {
    afirmar(false, "Excepción no esperada: " . $e->getMessage());
} finally {
    // Revertir toda la transacción para dejar la base de datos de desarrollo 100% limpia
    $pdo->rollBack();
    echo "\nTransacción revertida: Cero basura residual en base de datos.\n";
}

echo "===================================================================\n";
echo " RESULTADO DE PRUEBAS DE INTEGRIDAD: " . ($pruebas - $errores) . "/{$pruebas} PASS, {$errores} FAIL\n";
echo "===================================================================\n";

exit($errores === 0 ? 0 : 1);
