<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador central para despachar peticiones HTTP hacia controladores y acciones en CasaPRO.
 */
class Enrutador
{
    private array $rutas = [];
    private array $middlewaresGlobales = [];

    public function agregarMiddlewareGlobal(object|string $middleware): self
    {
        $this->middlewaresGlobales[] = $middleware;
        return $this;
    }

    public function agregar(string $metodo, string $ruta, array|callable $manejador, array $middlewares = []): void
    {
        $metodo = strtoupper($metodo);
        $rutaNormalizada = '/' . trim($ruta, '/');
        if ($rutaNormalizada === '//') {
            $rutaNormalizada = '/';
        }

        $this->rutas[] = [
            'metodo' => $metodo,
            'ruta' => $rutaNormalizada,
            'manejador' => $manejador,
            'middlewares' => $middlewares
        ];
    }

    public function get(string $ruta, array|callable $manejador, array $middlewares = []): void
    {
        $this->agregar('GET', $ruta, $manejador, $middlewares);
    }

    public function post(string $ruta, array|callable $manejador, array $middlewares = []): void
    {
        $this->agregar('POST', $ruta, $manejador, $middlewares);
    }

    public function put(string $ruta, array|callable $manejador, array $middlewares = []): void
    {
        $this->agregar('PUT', $ruta, $manejador, $middlewares);
    }

    public function delete(string $ruta, array|callable $manejador, array $middlewares = []): void
    {
        $this->agregar('DELETE', $ruta, $manejador, $middlewares);
    }

    /**
     * Despacha la petición actual buscando coincidencia en la tabla de rutas.
     */
    public function despachar(Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto = null): void
    {
        $metodoPeticion = $peticion->obtenerMetodo();
        $metodoEfectivo = ($metodoPeticion === 'HEAD') ? 'GET' : $metodoPeticion;
        $rutaPeticion = $peticion->obtenerRuta();

        foreach ($this->rutas as $entrada) {
            if ($entrada['metodo'] !== $metodoEfectivo) {
                continue;
            }

            // Comparación simple o parametrizada
            $patronRegex = $this->convertirRutaEnRegex($entrada['ruta']);
            if (preg_match($patronRegex, $rutaPeticion, $coincidencias)) {
                // Extraer parámetros nombrados
                $parametros = array_filter($coincidencias, '\is_string', ARRAY_FILTER_USE_KEY);

                // Ejecutar middlewares globales
                if (!$this->ejecutarMiddlewares($this->middlewaresGlobales, $peticion, $respuesta, $contexto)) {
                    return;
                }

                // Ejecutar middlewares específicos de la ruta
                if (!empty($entrada['middlewares'])) {
                    if (!$this->ejecutarMiddlewares($entrada['middlewares'], $peticion, $respuesta, $contexto)) {
                        return;
                    }
                }

                // Ejecutar manejador
                $manejador = $entrada['manejador'];
                if (is_callable($manejador)) {
                    $salida = call_user_func_array($manejador, [$peticion, $respuesta, $parametros]);
                    if (is_string($salida)) {
                        $respuesta->establecerCuerpo($salida);
                        $respuesta->enviar();
                    }
                    return;
                }

                if (is_array($manejador) && count($manejador) === 2) {
                    [$claseControlador, $metodoAccion] = $manejador;
                    if (!class_exists($claseControlador)) {
                        throw new \RuntimeException("El controlador no existe: {$claseControlador}");
                    }

                    $instanciaControlador = new $claseControlador();
                    if (!method_exists($instanciaControlador, $metodoAccion)) {
                        throw new \RuntimeException("La acción no existe: {$claseControlador}::{$metodoAccion}");
                    }

                    $salida = $instanciaControlador->$metodoAccion($peticion, $respuesta, $parametros);
                    if (is_string($salida)) {
                        $respuesta->establecerCuerpo($salida);
                        $respuesta->enviar();
                    }
                    return;
                }
            }
        }

        // Manejo centralizado 404 No Encontrado
        \App\Controladores\ErrorControlador::responder(
            404,
            $peticion,
            $respuesta,
            "El recurso o página solicitado no existe: {$rutaPeticion}"
        );
    }

    private function ejecutarMiddlewares(array $middlewares, Peticion $peticion, Respuesta $respuesta, ?ContextoPeticion $contexto): bool
    {
        foreach ($middlewares as $mw) {
            $instancia = is_string($mw) ? new $mw() : $mw;
            if (is_object($instancia) && method_exists($instancia, 'procesar')) {
                $resultado = $instancia->procesar($peticion, $respuesta, $contexto);
                if ($resultado === false) {
                    return false;
                }
            } elseif (is_callable($instancia)) {
                $resultado = $instancia($peticion, $respuesta, $contexto);
                if ($resultado === false) {
                    return false;
                }
            }
        }
        return true;
    }

    private function convertirRutaEnRegex(string $ruta): string
    {
        // Reemplazar {parametro} por (?P<parametro>[^/]+)
        $patron = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $ruta);
        return '#^' . $patron . '$#';
    }
}
