<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsula la solicitud HTTP entrante al sistema CasaPRO.
 */
class Peticion
{
    private string $metodo;
    private string $ruta;
    private array $parametrosConsulta;
    private array $parametrosCuerpo;
    private ?array $datosJson = null;
    private array $cabeceras;
    private array $archivos;
    private string $ipCliente;

    public function __construct(
        ?string $metodo = null,
        ?string $ruta = null,
        ?array $cuerpo = null,
        ?array $consulta = null,
        ?array $archivos = null,
        ?array $servidor = null
    ) {
        $servidorEntorno = $servidor ?? $_SERVER;
        $this->metodo = strtoupper($metodo ?? ($servidorEntorno['REQUEST_METHOD'] ?? 'GET'));
        $this->parametrosConsulta = $consulta ?? $_GET;
        $this->parametrosCuerpo = $cuerpo ?? $_POST;
        $this->archivos = $archivos ?? $_FILES;
        $this->ipCliente = $servidorEntorno['REMOTE_ADDR'] ?? '127.0.0.1';

        // Extraer cabeceras HTTP combinando getallheaders() con $servidorEntorno para máxima compatibilidad
        $cabeceras = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        foreach ($servidorEntorno as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $nombre = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($clave, 5)))));
                if (!isset($cabeceras[$nombre])) {
                    $cabeceras[$nombre] = (string) $valor;
                }
            } elseif (in_array($clave, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $nombre = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $clave))));
                if (!isset($cabeceras[$nombre])) {
                    $cabeceras[$nombre] = (string) $valor;
                }
            }
        }
        $this->cabeceras = $cabeceras;

        if ($ruta !== null) {
            $this->ruta = '/' . trim($ruta, '/');
            if ($this->ruta === '//') {
                $this->ruta = '/';
            }
        } else {
            $this->ruta = $this->extraerRutaNormalizada();
        }
    }

    /**
     * Extrae y normaliza la ruta solicitada, soportando tanto VirtualHosts como subcarpetas.
     */
    private function extraerRutaNormalizada(): string
    {
        $uriCompleta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Detectar si se está ejecutando en subcarpeta (e.g. /app.casa-pro/ o /app.casa-pro/public/)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $baseDir = str_replace('\\', '/', $baseDir);

        // Si Apache reescribe internamente hacia /public pero la URL del cliente no incluye /public
        if (str_ends_with($baseDir, '/public') && !str_starts_with($uriCompleta, $baseDir)) {
            $baseDir = substr($baseDir, 0, -7);
        }

        if ($baseDir !== '/' && $baseDir !== '' && str_starts_with($uriCompleta, $baseDir)) {
            $rutaRelativa = substr($uriCompleta, strlen($baseDir));
        } else {
            $rutaRelativa = $uriCompleta;
        }

        $rutaNormalizada = '/' . trim($rutaRelativa, '/');
        return $rutaNormalizada === '//' ? '/' : $rutaNormalizada;
    }

    public function obtenerMetodo(): string
    {
        return $this->metodo;
    }

    public function obtenerRuta(): string
    {
        return $this->ruta;
    }

    public function obtenerConsulta(string $clave, mixed $defecto = null): mixed
    {
        return $this->parametrosConsulta[$clave] ?? $defecto;
    }

    public function obtenerCuerpo(?string $clave = null, mixed $defecto = null): mixed
    {
        if ($clave === null) {
            return $this->parametrosCuerpo;
        }
        return $this->parametrosCuerpo[$clave] ?? $defecto;
    }

    public function obtenerTodosLosParametros(): array
    {
        return array_merge($this->parametrosConsulta, $this->parametrosCuerpo, $this->obtenerJson());
    }

    public function obtenerJson(): array
    {
        if ($this->datosJson === null) {
            $cuerpoCrudo = file_get_contents('php://input');
            if (!empty($cuerpoCrudo)) {
                $decodificado = json_decode($cuerpoCrudo, true);
                $this->datosJson = is_array($decodificado) ? $decodificado : [];
            } else {
                $this->datosJson = [];
            }
        }
        return $this->datosJson;
    }

    public function obtenerCabecera(string $nombre, ?string $defecto = null): ?string
    {
        $nombreNormalizado = strtolower($nombre);
        foreach ($this->cabeceras as $clave => $valor) {
            if (strtolower($clave) === $nombreNormalizado) {
                return (string) $valor;
            }
        }
        return $defecto;
    }

    public function obtenerIp(): string
    {
        return $this->ipCliente;
    }

    public function obtenerUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    }

    public function esAjax(): bool
    {
        return strtolower($this->obtenerCabecera('X-Requested-With', '')) === 'xmlhttprequest'
            || str_contains($this->obtenerCabecera('Accept', ''), 'application/json');
    }

    public function establecerMetodo(string $metodo): self
    {
        $this->metodo = strtoupper($metodo);
        return $this;
    }

    public function establecerRuta(string $ruta): self
    {
        $this->ruta = '/' . trim($ruta, '/');
        if ($this->ruta === '//') {
            $this->ruta = '/';
        }
        return $this;
    }

    public function establecerJson(array $datos): self
    {
        $this->datosJson = $datos;
        return $this;
    }

    public function establecerCuerpo(array $parametros): self
    {
        $this->parametrosCuerpo = $parametros;
        return $this;
    }

    public function obtener(string $clave, mixed $defecto = null): mixed
    {
        $parametros = $this->obtenerTodosLosParametros();
        return $parametros[$clave] ?? $defecto;
    }

    public function obtenerQuery(string $clave, mixed $defecto = null): mixed
    {
        return $this->obtenerConsulta($clave, $defecto);
    }

    public function establecerConsulta(array $parametros): self
    {
        $this->parametrosConsulta = $parametros;
        return $this;
    }

    public function establecerCabecera(string $nombre, string $valor): self
    {
        $this->cabeceras[strtolower($nombre)] = $valor;
        return $this;
    }
}
