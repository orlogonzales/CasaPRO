<?php

declare(strict_types=1);

namespace App\Controladores;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Core\Vista;

/**
 * Controlador centralizado para la gestión y emisión de errores HTTP en CasaPRO.
 * Mapea códigos de error hacia las vistas oficiales de Alina (400, 403, 404, 500, 503).
 */
class ErrorControlador extends BaseControlador
{
    /**
     * Mapeo de títulos oficiales para páginas de error.
     */
    private const TITULOS = [
        400 => 'Solicitud Incorrecta (400) | CasaPRO',
        403 => 'Acceso Denegado (403) | CasaPRO',
        404 => 'Página No Encontrada (404) | CasaPRO',
        500 => 'Error Interno del Servidor (500) | CasaPRO',
        503 => 'Servicio No Disponible (503) | CasaPRO',
    ];

    /**
     * Mapeo de descripciones por defecto para cada código de error.
     */
    private const MENSAJES = [
        400 => 'El servidor no pudo interpretar o procesar la solicitud debido a un formato o parámetro incorrecto.',
        403 => 'No dispone de los privilegios o permisos necesarios para acceder al recurso solicitado.',
        404 => 'El recurso o página que busca no existe, ha sido movido o no se encuentra disponible.',
        500 => 'El servidor encontró un error inesperado al procesar la solicitud. Nuestro equipo técnico ha sido notificado.',
        503 => 'El servicio no se encuentra disponible temporalmente debido a labores de mantenimiento o alta demanda.',
    ];

    /**
     * Emite una respuesta de error estandarizada (HTML o JSON según tipo de petición).
     *
     * @param int $codigo Código de estado HTTP (400, 403, 404, 500, 503)
     * @param Peticion $peticion Instancia de la petición HTTP actual
     * @param Respuesta $respuesta Instancia de la respuesta HTTP a emitir
     * @param string|null $mensaje Mensaje contextual seguro (sin revelar datos sensibles)
     * @param string|null $idCorrelacion Identificador de correlación para auditoría técnica
     */
    public static function responder(
        int $codigo,
        Peticion $peticion,
        Respuesta $respuesta,
        ?string $mensaje = null,
        ?string $idCorrelacion = null
    ): void {
        $respuesta->establecerCodigoEstado($codigo);

        $mensajeEfectivo = $mensaje ?? (self::MENSAJES[$codigo] ?? 'Ha ocurrido un error en la solicitud.');
        $titulo = self::TITULOS[$codigo] ?? "Error ({$codigo}) | CasaPRO";

        if ($peticion->esAjax()) {
            $cuerpoJson = [
                'estado' => 'error',
                'codigo' => $codigo,
                'mensaje' => $mensajeEfectivo,
            ];
            if ($idCorrelacion !== null) {
                $cuerpoJson['correlacion'] = $idCorrelacion;
            }
            $respuesta->json($cuerpoJson, $codigo);
            return;
        }

        $vista = in_array($codigo, [400, 403, 404, 500, 503], true)
            ? "modulos/errores/{$codigo}"
            : "modulos/errores/500";

        $html = Vista::renderizar($vista, [
            'tituloPagina' => $titulo,
            'codigoEstado' => $codigo,
            'mensaje' => $mensajeEfectivo,
            'idCorrelacion' => $idCorrelacion,
        ], 'error');

        $respuesta->establecerCuerpo($html);
        $respuesta->enviar();
    }
}
