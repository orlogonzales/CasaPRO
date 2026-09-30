<?php

declare(strict_types=1);

namespace App\Core;

/**
 * ContextoPeticion — Encapsula metadatos transversales de trazabilidad y seguridad para la petición.
 */
class ContextoPeticion
{
    private string $idCorrelacion;
    private string $ipCliente;
    private string $userAgent;
    private string $origen;
    private int $actorId;

    public function __construct(
        ?string $idCorrelacion = null,
        ?string $ipCliente = null,
        ?string $userAgent = null,
        ?string $origen = null,
        int $actorId = 1
    ) {
        $this->idCorrelacion = $this->resolverIdCorrelacion($idCorrelacion);
        $this->ipCliente = $this->resolverIp($ipCliente);
        $this->userAgent = $this->resolverUserAgent($userAgent);
        $this->origen = $this->resolverOrigen($origen);
        $this->actorId = $actorId;
    }

    /**
     * Construye un contexto a partir del entorno HTTP global o CLI.
     */
    public static function crearDesdeEntorno(?Peticion $peticion = null): self
    {
        $idEntrante = null;
        if ($peticion !== null) {
            $idEntrante = $peticion->obtenerCabecera('X-Correlation-ID');
        } elseif (!empty($_SERVER['HTTP_X_CORRELATION_ID'])) {
            $idEntrante = (string) $_SERVER['HTTP_X_CORRELATION_ID'];
        }

        $ip = $peticion !== null ? $peticion->obtenerIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $peticion !== null ? $peticion->obtenerUserAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Desconocido');
        $origen = (PHP_SAPI === 'cli') ? 'CLI' : 'WEB';

        return new self($idEntrante, $ip, $ua, $origen, 1);
    }

    private function resolverIdCorrelacion(?string $candidato): string
    {
        if ($candidato !== null) {
            $limpio = preg_replace('/[^A-Za-z0-9_\-]/', '', $candidato);
            if (!empty($limpio) && strlen($limpio) <= 64) {
                return $limpio;
            }
        }

        // Generar identificador de correlación criptográficamente seguro
        return 'REQ-' . strtoupper(bin2hex(random_bytes(12)));
    }

    private function resolverIp(?string $candidato): string
    {
        if ($candidato !== null && filter_var($candidato, FILTER_VALIDATE_IP)) {
            return $candidato;
        }

        $ipRemota = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ipRemota, FILTER_VALIDATE_IP) ? $ipRemota : '127.0.0.1';
    }

    private function resolverUserAgent(?string $candidato): string
    {
        $ua = trim($candidato ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'));
        if (strlen($ua) > 500) {
            $ua = substr($ua, 0, 497) . '...';
        }
        return !empty($ua) ? $ua : 'Desconocido';
    }

    private function resolverOrigen(?string $candidato): string
    {
        if ($candidato !== null && in_array(strtoupper($candidato), ['WEB', 'CLI', 'API'], true)) {
            return strtoupper($candidato);
        }

        return (PHP_SAPI === 'cli') ? 'CLI' : 'WEB';
    }

    public function obtenerIdCorrelacion(): string
    {
        return $this->idCorrelacion;
    }

    public function obtenerIp(): string
    {
        return $this->ipCliente;
    }

    public function obtenerUserAgent(): string
    {
        return $this->userAgent;
    }

    public function obtenerOrigen(): string
    {
        return $this->origen;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function establecerActorId(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new \InvalidArgumentException('El ID del actor debe ser un entero positivo.');
        }
        $this->actorId = $actorId;
    }
}
