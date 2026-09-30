<?php

declare(strict_types=1);

namespace App\Modelos;

/**
 * AuditoriaRegistro — Modelo de dominio que representa un registro forense inmutable de auditoría.
 */
class AuditoriaRegistro
{
    private ?int $id;
    private string $idCorrelacion;
    private int $actorId;
    private string $modulo;
    private string $entidad;
    private ?int $registroId;
    private string $accion;
    private string $resultado;
    private ?array $datosAnteriores;
    private ?array $datosNuevos;
    private ?array $metadatos;
    private string $origen;
    private ?string $ip;
    private ?string $userAgent;
    private ?string $creadoEn;

    public function __construct(
        string $idCorrelacion,
        int $actorId,
        string $modulo,
        string $entidad,
        string $accion,
        ?int $registroId = null,
        string $resultado = 'EXITO',
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
        ?array $metadatos = null,
        string $origen = 'WEB',
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $id = null,
        ?string $creadoEn = null
    ) {
        $this->idCorrelacion = $idCorrelacion;
        $this->actorId = $actorId;
        $this->modulo = $modulo;
        $this->entidad = $entidad;
        $this->accion = strtoupper($accion);
        $this->registroId = $registroId;
        $this->resultado = strtoupper($resultado);
        $this->datosAnteriores = $datosAnteriores;
        $this->datosNuevos = $datosNuevos;
        $this->metadatos = $metadatos;
        $this->origen = strtoupper($origen);
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        $decodificarJson = function (mixed $valor): ?array {
            if (is_array($valor)) {
                return $valor;
            }
            if (is_string($valor) && trim($valor) !== '') {
                $dec = json_decode($valor, true);
                return is_array($dec) ? $dec : null;
            }
            return null;
        };

        return new self(
            (string) ($datos['id_correlacion'] ?? ''),
            (int) ($datos['actor_id'] ?? 1),
            (string) ($datos['modulo'] ?? ''),
            (string) ($datos['entidad'] ?? ''),
            (string) ($datos['accion'] ?? 'CREAR'),
            isset($datos['registro_id']) && $datos['registro_id'] !== null ? (int) $datos['registro_id'] : null,
            (string) ($datos['resultado'] ?? 'EXITO'),
            $decodificarJson($datos['datos_anteriores'] ?? null),
            $decodificarJson($datos['datos_nuevos'] ?? null),
            $decodificarJson($datos['metadatos'] ?? null),
            (string) ($datos['origen'] ?? 'WEB'),
            $datos['ip'] ?? null,
            $datos['user_agent'] ?? null,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'id_correlacion' => $this->idCorrelacion,
            'actor_id' => $this->actorId,
            'modulo' => $this->modulo,
            'entidad' => $this->entidad,
            'registro_id' => $this->registroId,
            'accion' => $this->accion,
            'resultado' => $this->resultado,
            'datos_anteriores' => $this->datosAnteriores,
            'datos_nuevos' => $this->datosNuevos,
            'metadatos' => $this->metadatos,
            'origen' => $this->origen,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function obtenerId(): ?int { return $this->id; }
    public function obtenerIdCorrelacion(): string { return $this->idCorrelacion; }
    public function obtenerActorId(): int { return $this->actorId; }
    public function obtenerModulo(): string { return $this->modulo; }
    public function obtenerEntidad(): string { return $this->entidad; }
    public function obtenerRegistroId(): ?int { return $this->registroId; }
    public function obtenerAccion(): string { return $this->accion; }
    public function obtenerResultado(): string { return $this->resultado; }
    public function obtenerDatosAnteriores(): ?array { return $this->datosAnteriores; }
    public function obtenerDatosNuevos(): ?array { return $this->datosNuevos; }
    public function obtenerMetadatos(): ?array { return $this->metadatos; }
    public function obtenerOrigen(): string { return $this->origen; }
    public function obtenerIp(): ?string { return $this->ip; }
    public function obtenerUserAgent(): ?string { return $this->userAgent; }
    public function obtenerCreadoEn(): ?string { return $this->creadoEn; }
}
