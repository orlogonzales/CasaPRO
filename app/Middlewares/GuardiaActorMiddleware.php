<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Controladores\ErrorControlador;

/**
 * GuardiaActorMiddleware — Aplica la directiva de Deny by Default para endpoints de Persona.
 *
 * Regla: Toda petición (lectura o mutación de datos de identidad) requiere un actor autenticado en sesión.
 * Ninguna petición anónima web adquiere privilegios por fallback hacia SISTEMA_CASAPRO.
 */
class GuardiaActorMiddleware
{
    public function procesar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): bool
    {
        $actorId = GestorSesion::obtener('actor_id');

        if ($actorId === null || (int) $actorId <= 0) {
            return $this->rechazarAcceso($peticion, $respuesta, $contexto);
        }

        if ($contexto !== null) {
            $contexto->establecerActorId((int) $actorId);
        }

        return true;
    }

    private function rechazarAcceso(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto): bool
    {
        $idCorrelacion = $contexto ? $contexto->obtenerIdCorrelacion() : ('REQ-' . strtoupper(bin2hex(random_bytes(8))));
        $mensaje = 'Acceso no autorizado: Se requiere una sesión de actor autenticado para consultar o modificar datos de identidad. El sistema opera bajo política Deny by Default.';

        if ($peticion->esAjax()) {
            $respuesta->json([
                'estado' => 'error',
                'codigo' => 401,
                'mensaje' => $mensaje,
                'id_correlacion' => $idCorrelacion
            ], 401);
            return false;
        }

        ErrorControlador::responder(
            401,
            $peticion,
            $respuesta,
            $mensaje,
            $idCorrelacion
        );

        return false;
    }
}
