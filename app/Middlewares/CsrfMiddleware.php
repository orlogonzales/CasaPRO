<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\CsrfServicio;
use App\Core\ContextoPeticion;
use App\Controladores\ErrorControlador;

/**
 * CsrfMiddleware — Intercepta solicitudes de mutación HTTP para garantizar protección anti-CSRF.
 * Aplica regla estricta: NO existe bypass genérico por cabecera Bearer.
 */
class CsrfMiddleware
{
    private const METODOS_PROTEGIDOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Procesa la petición y valida el token CSRF si el método HTTP es de mutación.
     * Retorna true si es válido o no aplica; false si la petición fue rechazada con 403.
     */
    public function procesar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): bool
    {
        $metodo = strtoupper($peticion->obtenerMetodo());

        // Métodos de solo lectura son inocuos y no requieren validación CSRF
        if (!in_array($metodo, self::METODOS_PROTEGIDOS, true)) {
            return true;
        }

        $tokenCandidato = $this->extraerToken($peticion);

        if (CsrfServicio::validarToken($tokenCandidato)) {
            return true;
        }

        // Rechazo: Token ausente, corrupto o manipulado
        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(8))));

        if ($peticion->esAjax()) {
            $respuesta->json([
                'estado' => 'error',
                'codigo' => 403,
                'mensaje' => 'Token de seguridad CSRF ausente o inválido.',
                'id_correlacion' => $idCorrelacion
            ], 403);
            return false;
        }

        ErrorControlador::responder(
            403,
            $peticion,
            $respuesta,
            'Token de seguridad CSRF ausente o inválido. Por favor recargue la página e intente nuevamente.',
            $idCorrelacion
        );

        return false;
    }

    /**
     * Extrae el token CSRF inspeccionando cabeceras HTTP, cuerpo POST o payload JSON.
     */
    private function extraerToken(Peticion $peticion): ?string
    {
        // 1. Cabecera HTTP X-CSRF-Token
        $cabecera = $peticion->obtenerCabecera('X-CSRF-Token');
        if ($cabecera !== null && trim($cabecera) !== '') {
            return trim($cabecera);
        }

        // 2. Parámetro de formulario POST _csrf_token
        $campoPost = $peticion->obtenerCuerpo(CsrfServicio::obtenerNombreCampo());
        if (is_string($campoPost) && trim($campoPost) !== '') {
            return trim($campoPost);
        }

        // 3. Parámetro en payload JSON _csrf_token
        $datosJson = $peticion->obtenerJson();
        if (isset($datosJson[CsrfServicio::obtenerNombreCampo()]) && is_string($datosJson[CsrfServicio::obtenerNombreCampo()])) {
            return trim($datosJson[CsrfServicio::obtenerNombreCampo()]);
        }

        return null;
    }
}
