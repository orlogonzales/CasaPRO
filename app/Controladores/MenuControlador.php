<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\ContextoPeticion;
use App\Core\GestorSesion;
use App\Core\CsrfServicio;
use App\Core\Vista;
use App\Servicios\MenuServicio;
use App\DTOs\CrearMenuOpcionDTO;
use App\DTOs\ActualizarMenuOpcionDTO;
use App\DTOs\ReordenarMenuDTO;
use App\Excepciones\ValidacionExcepcion;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use Throwable;

class MenuControlador extends BaseControlador
{
    private MenuServicio $menuServicio;

    public function __construct(?MenuServicio $menuServicio = null)
    {
        $this->menuServicio = $menuServicio ?? new MenuServicio();
    }

    public function index(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): string
    {
        $arbol = $this->menuServicio->obtenerArbolCompleto();
        $tokenCsrf = CsrfServicio::obtenerToken();

        // Obtener catálogo de privilegios para el dropdown modal
        $conn = (new \App\Core\ProveedorConexion())->obtenerConexion();
        $privilegios = $conn->query("SELECT id, codigo, nombre, modulo FROM `privilegios` ORDER BY modulo ASC, codigo ASC")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->renderizar('modulos/menu/index', [
            'arbol'          => $arbol,
            'privilegios'    => $privilegios,
            'tokenCsrf'      => $tokenCsrf,
            'tituloPagina'   => 'Gestión de Menú y Navegación — CasaPRO',
            'migaPan'        => [
                ['texto' => 'Inicio', 'url' => Vista::url()],
                ['texto' => 'Identidad y Seguridad', 'url' => null],
                ['texto' => 'Menú y Navegación', 'url' => null]
            ],
            'jsAdicionales'  => [
                'vendor/sortable/Sortable.min.js',
                'js/modulos/menu/gestion-menu.js'
            ]
        ]);
    }

    public function obtenerArbol(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        $arbol = $this->menuServicio->obtenerArbolCompleto();

        $respuesta->establecerCodigoEstado(200);
        $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
        $respuesta->establecerCuerpo(json_encode([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => $arbol
        ]));
        $respuesta->enviar();
    }

    public function obtenerPorId(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        $id = (int) ($args['id'] ?? 0);
        $opcion = $this->menuServicio->obtenerPorId($id);

        if ($opcion === null) {
            $respuesta->establecerCodigoEstado(404);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => "La opción de menú solicitada no existe."
            ]));
            $respuesta->enviar();
            return;
        }

        $respuesta->establecerCodigoEstado(200);
        $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
        $respuesta->establecerCuerpo(json_encode([
            'estado' => 'exito',
            'codigo' => 200,
            'datos'  => $opcion
        ]));
        $respuesta->enviar();
    }

    public function crear(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = CrearMenuOpcionDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->menuServicio->crearOpcion($dto, $operadorId, $contexto);

            $respuesta->establecerCodigoEstado(201);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'exito',
                'codigo'  => 201,
                'mensaje' => 'Opción de menú creada exitosamente.',
                'datos'   => $resultado
            ]));
        } catch (ValidacionExcepcion $ve) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => 'Errores de validación en el formulario.',
                'errores' => $ve->obtenerErrores()
            ]));
        } catch (ReglaNegocioExcepcion $re) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $re->getMessage()
            ]));
        } catch (Throwable $t) {
            $respuesta->establecerCodigoEstado(500);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al crear la opción de menú: ' . $t->getMessage()
            ]));
        }
        $respuesta->enviar();
    }

    public function actualizar(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        $id = (int) ($args['id'] ?? 0);
        try {
            $existente = $this->menuServicio->obtenerPorId($id);
            if ($existente === null) {
                throw new RecursoNoEncontradoExcepcion("La opción de menú solicitada no existe.");
            }

            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = ActualizarMenuOpcionDTO::desdeArray($datos, $existente['tipo']);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->menuServicio->actualizarOpcion($id, $dto, $operadorId, $contexto);

            $respuesta->establecerCodigoEstado(200);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Opción de menú actualizada exitosamente.',
                'datos'   => $resultado
            ]));
        } catch (RecursoNoEncontradoExcepcion $rne) {
            $respuesta->establecerCodigoEstado(404);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $rne->getMessage()
            ]));
        } catch (ValidacionExcepcion $ve) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => 'Errores de validación en la actualización.',
                'errores' => $ve->obtenerErrores()
            ]));
        } catch (ReglaNegocioExcepcion $re) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $re->getMessage()
            ]));
        } catch (Throwable $t) {
            $respuesta->establecerCodigoEstado(500);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al actualizar la opción: ' . $t->getMessage()
            ]));
        }
        $respuesta->enviar();
    }

    public function cambiarEstado(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        $id = (int) ($args['id'] ?? 0);
        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $estado = (string) ($datos['estado'] ?? '');

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $resultado = $this->menuServicio->cambiarEstado($id, $estado, $operadorId, $contexto);

            $respuesta->establecerCodigoEstado(200);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => "Estado de la opción actualizado a {$resultado['estado']}.",
                'datos'   => $resultado
            ]));
        } catch (RecursoNoEncontradoExcepcion $rne) {
            $respuesta->establecerCodigoEstado(404);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $rne->getMessage()
            ]));
        } catch (ReglaNegocioExcepcion $re) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $re->getMessage()
            ]));
        } catch (Throwable $t) {
            $respuesta->establecerCodigoEstado(500);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al cambiar el estado: ' . $t->getMessage()
            ]));
        }
        $respuesta->enviar();
    }

    public function reordenar(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        try {
            $datos = $this->obtenerDatosEntrada($peticion);
            $dto = ReordenarMenuDTO::desdeArray($datos);

            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $this->menuServicio->reordenar($dto, $operadorId, $contexto);

            $respuesta->establecerCodigoEstado(200);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Estructura jerárquica de menú reordenada exitosamente.'
            ]));
        } catch (RecursoNoEncontradoExcepcion $rne) {
            $respuesta->establecerCodigoEstado(404);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $rne->getMessage()
            ]));
        } catch (ValidacionExcepcion $ve) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => 'Errores de validación en la estructura.',
                'errores' => $ve->obtenerErrores()
            ]));
        } catch (ReglaNegocioExcepcion $re) {
            $respuesta->establecerCodigoEstado(422);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 422,
                'mensaje' => $re->getMessage()
            ]));
        } catch (Throwable $t) {
            $respuesta->establecerCodigoEstado(500);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al reordenar el menú: ' . $t->getMessage()
            ]));
        }
        $respuesta->enviar();
    }

    public function eliminar(Peticion $peticion, Respuesta $respuesta, mixed $arg3 = null, mixed $arg4 = null): void
    {
        [$args, $contexto] = $this->resolverParametrosYContexto($arg3, $arg4);
        $id = (int) ($args['id'] ?? 0);
        try {
            $auth = GestorSesion::obtener('auth');
            $operadorId = (int) ($auth['usuario_id'] ?? 0);

            $this->menuServicio->eliminarOpcion($id, $operadorId, $contexto);

            $respuesta->establecerCodigoEstado(200);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'exito',
                'codigo'  => 200,
                'mensaje' => 'Opción de menú eliminada exitosamente.'
            ]));
        } catch (RecursoNoEncontradoExcepcion $rne) {
            $respuesta->establecerCodigoEstado(404);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 404,
                'mensaje' => $rne->getMessage()
            ]));
        } catch (ReglaNegocioExcepcion $re) {
            // Error de dominio si tiene hijos: HTTP 409 Conflict
            $codigoHttp = str_contains($re->getMessage(), 'sub-elementos') ? 409 : 422;
            $respuesta->establecerCodigoEstado($codigoHttp);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => $codigoHttp,
                'mensaje' => $re->getMessage()
            ]));
        } catch (Throwable $t) {
            $respuesta->establecerCodigoEstado(500);
            $respuesta->establecerCabecera('Content-Type', 'application/json; charset=utf-8');
            $respuesta->establecerCuerpo(json_encode([
                'estado'  => 'error',
                'codigo'  => 500,
                'mensaje' => 'Error interno al eliminar la opción: ' . $t->getMessage()
            ]));
        }
        $respuesta->enviar();
    }

    private function resolverParametrosYContexto(mixed $arg3, mixed $arg4): array
    {
        $parametros = is_array($arg3) ? $arg3 : (is_array($arg4) ? $arg4 : []);
        $contexto = ($arg3 instanceof ContextoPeticion) ? $arg3 : (($arg4 instanceof ContextoPeticion) ? $arg4 : null);
        return [$parametros, $contexto];
    }

    private function obtenerDatosEntrada(Peticion $peticion): array
    {
        $json = $peticion->obtenerJson();
        if (!empty($json)) {
            return $json;
        }

        $cuerpo = $peticion->obtenerCuerpo();
        if (!empty($cuerpo)) {
            return $cuerpo;
        }

        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST;
    }
}
