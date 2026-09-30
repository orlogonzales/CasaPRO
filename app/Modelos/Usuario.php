<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Usuario — Modelo de dominio que representa una cuenta de acceso de usuario en CasaPRO.
 */
class Usuario
{
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';

    private ?int $id;
    private int $personaId;
    private int $actorId;
    private string $email;
    private string $nombreUsuario;
    private string $passwordHash;
    private string $estado;
    private int $intentosFallidos;
    private ?string $ultimoIntentoFallido;
    private ?string $bloqueadoHasta;
    private int $versionAutorizacion;
    private bool $debeCambiarPassword;
    private ?string $ultimoLoginEn;
    private ?string $ultimoLoginIp;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        int $actorId,
        string $email,
        string $nombreUsuario,
        string $passwordHash,
        string $estado = self::ESTADO_ACTIVO,
        int $intentosFallidos = 0,
        ?string $ultimoIntentoFallido = null,
        ?string $bloqueadoHasta = null,
        int $versionAutorizacion = 1,
        bool $debeCambiarPassword = false,
        ?string $ultimoLoginEn = null,
        ?string $ultimoLoginIp = null,
        ?int $id = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        $this->personaId = $personaId;
        $this->actorId = $actorId;
        $this->setEmail($email);
        $this->setNombreUsuario($nombreUsuario);
        $this->passwordHash = $passwordHash;
        $this->setEstado($estado);
        $this->intentosFallidos = max(0, $intentosFallidos);
        $this->ultimoIntentoFallido = $ultimoIntentoFallido;
        $this->bloqueadoHasta = $bloqueadoHasta;
        $this->versionAutorizacion = max(1, $versionAutorizacion);
        $this->debeCambiarPassword = $debeCambiarPassword;
        $this->ultimoLoginEn = $ultimoLoginEn;
        $this->ultimoLoginIp = $ultimoLoginIp;
        $this->id = $id;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public static function desdeArray(array $datos): self
    {
        return new self(
            (int) ($datos['persona_id'] ?? 0),
            (int) ($datos['actor_id'] ?? 0),
            (string) ($datos['email'] ?? ''),
            (string) ($datos['nombre_usuario'] ?? ''),
            (string) ($datos['password_hash'] ?? ''),
            (string) ($datos['estado'] ?? self::ESTADO_ACTIVO),
            (int) ($datos['intentos_fallidos'] ?? 0),
            $datos['ultimo_intento_fallido'] ?? null,
            $datos['bloqueado_hasta'] ?? null,
            (int) ($datos['version_autorizacion'] ?? 1),
            filter_var($datos['debe_cambiar_password'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $datos['ultimo_login_en'] ?? null,
            $datos['ultimo_login_ip'] ?? null,
            isset($datos['id']) ? (int) $datos['id'] : null,
            $datos['creado_en'] ?? null,
            $datos['actualizado_en'] ?? null
        );
    }

    public function aArray(): array
    {
        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'actor_id' => $this->actorId,
            'email' => $this->email,
            'nombre_usuario' => $this->nombreUsuario,
            'password_hash' => $this->passwordHash,
            'estado' => $this->estado,
            'intentos_fallidos' => $this->intentosFallidos,
            'ultimo_intento_fallido' => $this->ultimoIntentoFallido,
            'bloqueado_hasta' => $this->bloqueadoHasta,
            'version_autorizacion' => $this->versionAutorizacion,
            'debe_cambiar_password' => $this->debeCambiarPassword,
            'ultimo_login_en' => $this->ultimoLoginEn,
            'ultimo_login_ip' => $this->ultimoLoginIp,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
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

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerActorId(): int
    {
        return $this->actorId;
    }

    public function obtenerEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $limpio = strtolower(trim($email));
        if (!filter_var($limpio, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("El formato del correo electrónico es inválido: {$email}");
        }
        $this->email = $limpio;
    }

    public function obtenerNombreUsuario(): string
    {
        return $this->nombreUsuario;
    }

    public function setNombreUsuario(string $nombreUsuario): void
    {
        $limpio = trim($nombreUsuario);
        if ($limpio === '') {
            throw new InvalidArgumentException('El nombre de usuario no puede estar vacío.');
        }
        $this->nombreUsuario = $limpio;
    }

    public function obtenerPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    public function obtenerEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): void
    {
        $permitidos = [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO];
        $normalizado = strtoupper(trim($estado));
        if (!in_array($normalizado, $permitidos, true)) {
            throw new InvalidArgumentException("Estado de usuario inválido: {$estado}");
        }
        $this->estado = $normalizado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    public function obtenerIntentosFallidos(): int
    {
        return $this->intentosFallidos;
    }

    public function incrementarIntentosFallidos(): void
    {
        $this->intentosFallidos++;
        $this->ultimoIntentoFallido = date('Y-m-d H:i:s');
    }

    public function reiniciarIntentosFallidos(): void
    {
        $this->intentosFallidos = 0;
        $this->ultimoIntentoFallido = null;
        $this->bloqueadoHasta = null;
    }

    public function obtenerUltimoIntentoFallido(): ?string
    {
        return $this->ultimoIntentoFallido;
    }

    public function obtenerBloqueadoHasta(): ?string
    {
        return $this->bloqueadoHasta;
    }

    public function establecerBloqueoTemporal(int $minutosBloqueo): void
    {
        $this->bloqueadoHasta = date('Y-m-d H:i:s', time() + ($minutosBloqueo * 60));
    }

    public function estaBloqueado(): bool
    {
        if ($this->bloqueadoHasta === null) {
            return false;
        }
        return strtotime($this->bloqueadoHasta) > time();
    }

    public function obtenerVersionAutorizacion(): int
    {
        return $this->versionAutorizacion;
    }

    public function incrementarVersionAutorizacion(): void
    {
        $this->versionAutorizacion++;
    }

    public function obtenerUltimoLoginEn(): ?string
    {
        return $this->ultimoLoginEn;
    }

    public function obtenerUltimoLoginIp(): ?string
    {
        return $this->ultimoLoginIp;
    }

    public function registrarLoginExitoso(string $ip): void
    {
        $this->reiniciarIntentosFallidos();
        $this->ultimoLoginEn = date('Y-m-d H:i:s');
        $this->ultimoLoginIp = $ip;
    }

    public function debeCambiarPassword(): bool
    {
        return $this->debeCambiarPassword;
    }

    public function establecerDebeCambiarPassword(bool $debe): void
    {
        $this->debeCambiarPassword = $debe;
    }
}
