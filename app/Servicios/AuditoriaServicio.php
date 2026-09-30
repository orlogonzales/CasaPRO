<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Modelos\AuditoriaRegistro;
use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * AuditoriaServicio — Servicio transversal de bitácora forense e inmutable de mutaciones en CasaPRO.
 *
 * Reglas de diseño:
 * 1. Opera de forma obligatoria dentro de la misma transacción PDO del servicio de dominio.
 * 2. Si el registro de auditoría falla, lanza excepción forzando el rollback de la mutación.
 * 3. Aplica minimización de snapshots en actualizaciones y sanitización recursiva de datos sensibles.
 * 4. Garantiza inmutabilidad a nivel de aplicación (estricto append-only: sin métodos de actualización o eliminación).
 */
class AuditoriaServicio
{
    private ProveedorConexion $proveedorConexion;
    private ?ContextoPeticion $contextoPeticion;

    private const CLAVES_SENSIBLES = [
        'password',
        'contrasena',
        'contraseña',
        'clave',
        'token',
        'secret',
        'secreto',
        'cvv',
        'pin',
        'api_key',
        'apikey',
        'authorization',
        'credencial',
        'tarjeta',
        'hash'
    ];

    public function __construct(
        ?ProveedorConexion $proveedorConexion = null,
        ?ContextoPeticion $contextoPeticion = null
    ) {
        $this->proveedorConexion = $proveedorConexion ?? new ProveedorConexion();
        $this->contextoPeticion = $contextoPeticion;
    }

    /**
     * Registra una operación de mutación de manera atómica dentro de la transacción PDO activa.
     *
     * @param array<string, mixed> $datos Parámetros del evento de auditoría
     * @param PDO|null $conexionExterna Conexión PDO que contiene la transacción activa del dominio
     * @return int ID de la fila de auditoría insertada
     * @throws RuntimeException Si ocurre un error de persistencia
     */
    public function registrar(array $datos, ?PDO $conexionExterna = null): int
    {
        $conexion = $conexionExterna ?? ($datos['conexion'] ?? $this->proveedorConexion->obtenerConexion());
        if (!($conexion instanceof PDO)) {
            throw new InvalidArgumentException('Se requiere una instancia válida de PDO para registrar auditoría.');
        }

        // Resolver contexto de la petición si existe
        $contexto = $datos['contexto'] ?? $this->contextoPeticion;

        $modulo = trim((string) ($datos['modulo'] ?? ''));
        $entidad = trim((string) ($datos['entidad'] ?? ''));
        $accion = strtoupper(trim((string) ($datos['accion'] ?? '')));

        if ($modulo === '' || $entidad === '' || $accion === '') {
            throw new InvalidArgumentException('Los campos modulo, entidad y accion son obligatorios en auditoría.');
        }

        $registroId = isset($datos['registro_id']) ? (int) $datos['registro_id'] : (isset($datos['entidad_id']) ? (int) $datos['entidad_id'] : null);
        $actorId = isset($datos['actor_id']) ? (int) $datos['actor_id'] : ($contexto ? $contexto->obtenerActorId() : 1);
        $idCorrelacion = (string) ($datos['id_correlacion'] ?? ($contexto ? $contexto->obtenerIdCorrelacion() : 'REQ-' . strtoupper(bin2hex(random_bytes(8)))));
        $resultado = strtoupper((string) ($datos['resultado'] ?? 'EXITO'));
        $origen = strtoupper((string) ($datos['origen'] ?? ($contexto ? $contexto->obtenerOrigen() : 'WEB')));
        $ip = $datos['ip'] ?? ($contexto ? $contexto->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $userAgent = $datos['user_agent'] ?? ($contexto ? $contexto->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'));

        // Extraer snapshots y metadatos
        $datosAnteriores = $datos['datos_anteriores'] ?? ($datos['datos_previos'] ?? null);
        $datosNuevos = $datos['datos_nuevos'] ?? null;
        $metadatos = $datos['metadatos'] ?? [];

        if (isset($datos['descripcion'])) {
            $metadatos['descripcion'] = $datos['descripcion'];
        }
        if (isset($datos['empresa_id'])) {
            $metadatos['empresa_id'] = $datos['empresa_id'];
        }

        // Aplicar minimización de snapshots en actualizaciones
        if ($accion === 'ACTUALIZAR' && is_array($datosAnteriores) && is_array($datosNuevos)) {
            [$datosAnteriores, $datosNuevos] = $this->calcularDiferencialSnapshots($datosAnteriores, $datosNuevos);
        }

        // Sanitización recursiva de datos sensibles
        $datosAnterioresSanitizados = is_array($datosAnteriores) ? $this->sanitizarRecursivo($datosAnteriores) : null;
        $datosNuevosSanitizados = is_array($datosNuevos) ? $this->sanitizarRecursivo($datosNuevos) : null;
        $metadatosSanitizados = !empty($metadatos) ? $this->sanitizarRecursivo($metadatos) : null;

        $jsonAnteriores = $datosAnterioresSanitizados !== null ? json_encode($datosAnterioresSanitizados, JSON_UNESCAPED_UNICODE) : null;
        $jsonNuevos = $datosNuevosSanitizados !== null ? json_encode($datosNuevosSanitizados, JSON_UNESCAPED_UNICODE) : null;
        $jsonMetadatos = $metadatosSanitizados !== null ? json_encode($metadatosSanitizados, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO `auditorias` (
                    `id_correlacion`, `actor_id`, `modulo`, `entidad`, `registro_id`,
                    `accion`, `resultado`, `datos_anteriores`, `datos_nuevos`, `metadatos`,
                    `origen`, `ip`, `user_agent`, `creado_en`
                ) VALUES (
                    :id_correlacion, :actor_id, :modulo, :entidad, :registro_id,
                    :accion, :resultado, :datos_anteriores, :datos_nuevos, :metadatos,
                    :origen, :ip, :user_agent, NOW()
                )";

        try {
            $stmt = $conexion->prepare($sql);
            $stmt->bindValue(':id_correlacion', $idCorrelacion, PDO::PARAM_STR);
            $stmt->bindValue(':actor_id', $actorId, PDO::PARAM_INT);
            $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
            $stmt->bindValue(':entidad', $entidad, PDO::PARAM_STR);
            if ($registroId !== null) {
                $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':registro_id', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
            $stmt->bindValue(':resultado', $resultado, PDO::PARAM_STR);
            $stmt->bindValue(':datos_anteriores', $jsonAnteriores, $jsonAnteriores !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':datos_nuevos', $jsonNuevos, $jsonNuevos !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':metadatos', $jsonMetadatos, $jsonMetadatos !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':origen', $origen, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, $ip !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':user_agent', $userAgent, $userAgent !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $stmt->execute();
            return (int) $conexion->lastInsertId();
        } catch (\Throwable $e) {
            throw new RuntimeException("Fallo crítico en AuditoriaServicio al registrar evento: {$e->getMessage()}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Calcula la diferencia estricta entre snapshots para almacenar únicamente los atributos mutados.
     */
    private function calcularDiferencialSnapshots(array $anteriores, array $nuevos): array
    {
        $diferenciaAnteriores = [];
        $diferenciaNuevos = [];

        $todasLasClaves = array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)));

        foreach ($todasLasClaves as $clave) {
            $valAnt = $anteriores[$clave] ?? null;
            $valNue = $nuevos[$clave] ?? null;

            // Comparar representación
            if ($valAnt !== $valNue) {
                $diferenciaAnteriores[$clave] = $valAnt;
                $diferenciaNuevos[$clave] = $valNue;
            }
        }

        return [$diferenciaAnteriores, $diferenciaNuevos];
    }

    /**
     * Sanitiza de manera recursiva claves sensibles en matrices asociativas o listas.
     */
    public function sanitizarRecursivo(array $datos, bool $heredarSensible = false): array
    {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            $claveTexto = strtolower((string) $clave);

            $esSensible = $heredarSensible;
            if (!$esSensible) {
                foreach (self::CLAVES_SENSIBLES as $sensible) {
                    if (str_contains($claveTexto, $sensible)) {
                        $esSensible = true;
                        break;
                    }
                }
            }

            if (is_array($valor)) {
                $resultado[$clave] = $this->sanitizarRecursivo($valor, $esSensible);
            } elseif ($esSensible) {
                $resultado[$clave] = '[PROTEGIDO]';
            } else {
                $resultado[$clave] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * Consulta el historial forense de auditoría para una entidad y registro específico.
     *
     * @return array<int, AuditoriaRegistro>
     */
    public function consultarPorRegistro(string $modulo, string $entidad, int $registroId): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT *
            FROM `auditorias`
            WHERE `modulo` = :modulo
              AND `entidad` = :entidad
              AND `registro_id` = :registro_id
            ORDER BY `id` ASC
        ");
        $stmt->bindValue(':modulo', $modulo, PDO::PARAM_STR);
        $stmt->bindValue(':entidad', $entidad, PDO::PARAM_STR);
        $stmt->bindValue(':registro_id', $registroId, PDO::PARAM_INT);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $f) => AuditoriaRegistro::desdeArray($f), $filas);
    }

    /**
     * Consulta los registros de auditoría asociados a un identificador de correlación.
     *
     * @return array<int, AuditoriaRegistro>
     */
    public function consultarPorCorrelacion(string $idCorrelacion): array
    {
        $conexion = $this->proveedorConexion->obtenerConexion();
        $stmt = $conexion->prepare("
            SELECT *
            FROM `auditorias`
            WHERE `id_correlacion` = :id_correlacion
            ORDER BY `id` ASC
        ");
        $stmt->bindValue(':id_correlacion', $idCorrelacion, PDO::PARAM_STR);
        $stmt->execute();

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $f) => AuditoriaRegistro::desdeArray($f), $filas);
    }
}
