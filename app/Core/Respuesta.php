<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsula la respuesta HTTP emitida por el sistema CasaPRO.
 */
class Respuesta
{
    private int $codigoEstado = 200;
    private array $cabeceras = [];
    private string $cuerpo = '';

    public function establecerCodigoEstado(int $codigo): self
    {
        $this->codigoEstado = $codigo;
        return $this;
    }

    public function agregarCabecera(string $nombre, string $valor): self
    {
        $this->cabeceras[$nombre] = $valor;
        return $this;
    }

    public function establecerCuerpo(string $cuerpo): self
    {
        $this->cuerpo = $cuerpo;
        return $this;
    }

    /**
     * Emite una respuesta JSON estructurada y finaliza.
     */
    public function json(array $datos, int $codigo = 200): void
    {
        $this->establecerCodigoEstado($codigo);
        $this->agregarCabecera('Content-Type', 'application/json; charset=utf-8');
        $this->enviarCabeceras();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        if (!defined('CASAPRO_TESTING')) {
            exit;
        }
    }

    /**
     * Redirige hacia otra URL del sistema.
     */
    public function redirigir(string $url, int $codigo = 302): void
    {
        http_response_code($codigo);
        header('Location: ' . $url);
        if (!defined('CASAPRO_TESTING')) {
            exit;
        }
    }

    /**
     * Envía la respuesta HTML u otro contenido al cliente.
     */
    public function enviar(): void
    {
        $this->enviarCabeceras();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $this->cuerpo;
        }
    }

    private function enviarCabeceras(): void
    {
        if (!headers_sent()) {
            http_response_code($this->codigoEstado);
            foreach ($this->cabeceras as $nombre => $valor) {
                header("{$nombre}: {$valor}");
            }
        }
    }
}
