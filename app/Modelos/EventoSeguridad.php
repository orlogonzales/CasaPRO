<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * EventoSeguridad — Modelo de telemetría y auditoría técnica de seguridad.
 */
class EventoSeguridad
{
    public const TIPO_LOGIN_EXITO = 'LOGIN_EXITO';
    public const TIPO_LOGIN_FALLO = 'LOGIN_FALLO';
    public const TIPO_LOGOUT = 'LOGOUT';
    public const TIPO_BLOQUEO_TEMPORAL = 'BLOQUEO_TEMPORAL';
    public const TIPO_SESION_INVALIDADA = 'SESION_INVALIDADA';
    public const TIPO_CSRF_FALLIDO = 'CSRF_FALLIDO';
    public const TIPO_ACCESO_DENEGADO = 'ACCESO_DENEGADO';
    public const TIPO_BOOTSTRAP_ADMIN = 'BOOTSTRAP_ADMIN';

    private ?int $id;
    private string $tipoEvento;
    private ?int $usuarioId;
    private ?string $identificadorIntento;
    private ?string $ip;
    private ?string $userAgent;
    private ?array $metadatos;
    private ?string $creadoEn;

    public function __construct(
        string $tipoEvento,
        ?int $usuarioId = null,
        ?string $identificadorIntento = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $metadatos = null,
        ?int $id = null,
        ?string $creadoEn = null
    ) {
        $this->setTipoEvento($tipoEvento);
        $this->usuarioId = $usuarioId;
        $this->identificadorIntento = $identificadorIntento;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->metadatos = $metadatos;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        $metadatos = null;
        if (isset($datos['metadatos'])) {
            if (is_array($datos['metadatos'])) {
                $metadatos = $datos['metadatos'];
            } elseif (is_string($datos['metadatos'])) {
                $decodificado = json_decode($datos['metadatos'], true);
                $metadatos = is_array($decodificado) ? $decodificado : null;
            }
        }

        return new self(
            (string) ($datos['tipo_evento'] ?? ''),
            isset($datos['usuario_id']) ? (int) $datos['usuario_id'] : null,
            $datos['identificador_intento'] ?? null,
            $datos['ip'] ?? null,
            $datos['user_agent'] ?? null,
            $metadatos,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'tipo_evento' => $this->tipoEvento,
            'usuario_id' => $this->usuarioId,
            'identificador_intento' => $this->identificadorIntento,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'metadatos' => $this->metadatos,
            'creado_en' => $this->creadoEn,
        ];
    }

    public function obtenerId(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): void
    {
        $this->id = $id;
    }

    public function obtenerTipoEvento(): string
    {
        return $this->tipoEvento;
    }

    public function setTipoEvento(string $tipoEvento): void
    {
        $permitidos = [
            self::TIPO_LOGIN_EXITO,
            self::TIPO_LOGIN_FALLO,
            self::TIPO_LOGOUT,
            self::TIPO_BLOQUEO_TEMPORAL,
            self::TIPO_SESION_INVALIDADA,
            self::TIPO_CSRF_FALLIDO,
            self::TIPO_ACCESO_DENEGADO,
            self::TIPO_BOOTSTRAP_ADMIN,
        ];

        $normalizado = strtoupper(trim($tipoEvento));
        if (!in_array($normalizado, $permitidos, true)) {
            throw new InvalidArgumentException("Tipo de evento de seguridad inválido: {$tipoEvento}");
        }

        $this->tipoEvento = $normalizado;
    }

    public function obtenerUsuarioId(): ?int
    {
        return $this->usuarioId;
    }

    public function obtenerIdentificadorIntento(): ?string
    {
        return $this->identificadorIntento;
    }

    public function obtenerIp(): ?string
    {
        return $this->ip;
    }

    public function obtenerUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function obtenerMetadatos(): ?array
    {
        return $this->metadatos;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }
}
