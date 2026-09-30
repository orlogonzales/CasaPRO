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

    public function obtenerCodigoEstado(): int
    {
        return $this->codigoEstado;
    }

    public function obtenerCabeceras(): array
    {
        return $this->cabeceras;
    }

    public function obtenerCuerpo(): string
    {
        return $this->cuerpo;
    }

    /**
     * Emite una respuesta JSON estructurada y finaliza.
     */
    public function json(array $datos, int $codigo = 200): void
    {
        $this->establecerCodigoEstado($codigo);
        $this->agregarCabecera('Content-Type', 'application/json; charset=utf-8');
        $this->enviarCabeceras();
        $this->cuerpo = (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $this->cuerpo;
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
        $this->establecerCodigoEstado($codigo);
        $this->agregarCabecera('Location', $url);
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Location: ' . $url);
        }
        if (!defined('CASAPRO_TESTING')) {
            exit;
        }
    }

    public function redireccionar(string $url, int $codigo = 302): void
    {
        $this->redirigir($url, $codigo);
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
