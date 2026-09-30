<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CsrfServicio — Generación, almacenamiento y validación criptográfica de tokens CSRF.
 */
class CsrfServicio
{
    private const CLAVE_SESION = '_token_csrf';
    private const NOMBRE_CAMPO = '_csrf_token';
    private const NOMBRE_CABECERA = 'X-CSRF-Token';

    /**
     * Obtiene el token CSRF actual de la sesión o genera uno nuevo si no existe.
     */
    public static function obtenerToken(): string
    {
        $token = GestorSesion::obtener(self::CLAVE_SESION);
        if (!is_string($token) || empty($token)) {
            $token = self::regenerarToken();
        }
        return $token;
    }

    /**
     * Alias de compatibilidad para obtenerToken.
     */
    public static function generarToken(): string
    {
        return self::obtenerToken();
    }

    /**
     * Genera un nuevo token criptográficamente seguro de 32 bytes (64 caracteres hex).
     */
    public static function regenerarToken(): string
    {
        $nuevoToken = bin2hex(random_bytes(32));
        GestorSesion::establecer(self::CLAVE_SESION, $nuevoToken);
        return $nuevoToken;
    }

    /**
     * Valida de manera segura en tiempo constante un token provisto contra el token en sesión.
     */
    public static function validarToken(?string $tokenCandidato): bool
    {
        if ($tokenCandidato === null || trim($tokenCandidato) === '') {
            return false;
        }

        $tokenSesion = GestorSesion::obtener(self::CLAVE_SESION);
        if (!is_string($tokenSesion) || empty($tokenSesion)) {
            return false;
        }

        return hash_equals($tokenSesion, trim($tokenCandidato));
    }

    public static function obtenerNombreCampo(): string
    {
        return self::NOMBRE_CAMPO;
    }

    public static function obtenerNombreCabecera(): string
    {
        return self::NOMBRE_CABECERA;
    }
}
