<?php

declare(strict_types=1);

namespace App\Servicios;

/**
 * Adaptador de integración con APIsPERU (https://apiperu.dev).
 * Implementa ProveedorDocumentoInterface para resolver consultas de DNI y RUC
 * mediante peticiones HTTP seguras desde el backend sin exponer credenciales al cliente.
 */
class ApisPeruAdaptador implements ProveedorDocumentoInterface
{
    private const URL_BASE = 'https://api.apiperu.dev';
    private const TIEMPO_ESPERA_SEGUNDOS = 4;

    private ?string $token;
    private string $urlBase;

    public function __construct(?string $token = null, ?string $urlBase = null)
    {
        $this->token = $token ?? (
            $_ENV['APISPERU_DNIRUC_TOKEN'] ??
            $_SERVER['APISPERU_DNIRUC_TOKEN'] ??
            $_ENV['APISPERU_TOKEN'] ??
            $_SERVER['APISPERU_TOKEN'] ??
            null
        );

        if ($this->token !== null) {
            $this->token = trim($this->token);
            if ($this->token === '') {
                $this->token = null;
            }
        }

        $this->urlBase = rtrim($urlBase ?? self::URL_BASE, '/');
    }

    /**
     * Consulta información de DNI ante APIsPERU.
     * Endpoint oficial: POST https://api.apiperu.dev/dni
     * Payload: {"dni": "12345678"}
     */
    public function consultarDni(string $dni): array
    {
        if ($this->token === null) {
            return [
                'exito' => false,
                'codigo' => 'NO_CONFIGURADO',
                'mensaje' => 'El token del servicio de consulta documental no se encuentra configurado en el servidor.'
            ];
        }

        $respuestaHttp = $this->ejecutarPeticionPost('/dni', ['dni' => $dni]);

        if (!$respuestaHttp['exito']) {
            return [
                'exito' => false,
                'codigo' => $respuestaHttp['codigo'],
                'mensaje' => $respuestaHttp['mensaje']
            ];
        }

        $cuerpo = $respuestaHttp['cuerpo'];

        if (!empty($cuerpo['success']) && !empty($cuerpo['data'])) {
            $data = $cuerpo['data'];
            return [
                'exito' => true,
                'codigo' => 'ENCONTRADO',
                'mensaje' => 'Datos obtenidos satisfactoriamente del padrón oficial.',
                'datos' => [
                    'numero_documento' => (string) ($data['numero'] ?? $dni),
                    'nombres' => trim((string) ($data['nombres'] ?? '')),
                    'apellido_paterno' => trim((string) ($data['apellido_paterno'] ?? '')),
                    'apellido_materno' => trim((string) ($data['apellido_materno'] ?? '')),
                    'nombre_completo' => trim((string) ($data['nombre_completo'] ?? ''))
                ]
            ];
        }

        $mensajeError = $cuerpo['message'] ?? 'El número de DNI consultado no fue encontrado en los registros oficiales.';
        return [
            'exito' => false,
            'codigo' => 'NO_ENCONTRADO',
            'mensaje' => $mensajeError
        ];
    }

    /**
     * Consulta información de RUC ante APIsPERU.
     * Endpoint oficial: POST https://api.apiperu.dev/ruc
     * Payload: {"ruc": "20123456789"}
     */
    public function consultarRuc(string $ruc): array
    {
        if ($this->token === null) {
            return [
                'exito' => false,
                'codigo' => 'NO_CONFIGURADO',
                'mensaje' => 'El token del servicio de consulta documental no se encuentra configurado en el servidor.'
            ];
        }

        $respuestaHttp = $this->ejecutarPeticionPost('/ruc', ['ruc' => $ruc]);

        if (!$respuestaHttp['exito']) {
            return [
                'exito' => false,
                'codigo' => $respuestaHttp['codigo'],
                'mensaje' => $respuestaHttp['mensaje']
            ];
        }

        $cuerpo = $respuestaHttp['cuerpo'];

        if (!empty($cuerpo['success']) && !empty($cuerpo['data'])) {
            $data = $cuerpo['data'];
            return [
                'exito' => true,
                'codigo' => 'ENCONTRADO',
                'mensaje' => 'Datos obtenidos satisfactoriamente del registro tributario.',
                'datos' => [
                    'numero_documento' => (string) ($data['ruc'] ?? $ruc),
                    'razon_social' => trim((string) ($data['razon_social'] ?? '')),
                    'estado_contribuyente' => trim((string) ($data['estado'] ?? '')),
                    'condicion_contribuyente' => trim((string) ($data['condicion'] ?? '')),
                    'direccion' => trim((string) ($data['direccion'] ?? '')),
                    'departamento' => trim((string) ($data['departamento'] ?? '')),
                    'provincia' => trim((string) ($data['provincia'] ?? '')),
                    'distrito' => trim((string) ($data['distrito'] ?? '')),
                    'codigo_ubigeo' => trim((string) ($data['ubigeo'] ?? ''))
                ]
            ];
        }

        $mensajeError = $cuerpo['message'] ?? 'El número de RUC consultado no fue encontrado en los registros tributarios.';
        return [
            'exito' => false,
            'codigo' => 'NO_ENCONTRADO',
            'mensaje' => $mensajeError
        ];
    }

    /**
     * Ejecuta una petición HTTP POST mediante cURL con protección de credenciales y timeout.
     *
     * @param string $ruta Ruta relativa del endpoint
     * @param array<string, mixed> $payload Datos a enviar en formato JSON
     * @return array{exito: bool, codigo: string, mensaje: string, cuerpo?: array<string, mixed>}
     */
    private function ejecutarPeticionPost(string $ruta, array $payload): array
    {
        $url = $this->urlBase . $ruta;
        $jsonPayload = json_encode($payload);

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'exito' => false,
                'codigo' => 'ERROR_INICIALIZACION',
                'mensaje' => 'No se pudo inicializar la conexión con el servicio externo.'
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIEMPO_ESPERA_SEGUNDOS,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === CURLE_COULDNT_CONNECT) {
                return [
                    'exito' => false,
                    'codigo' => 'TIMEOUT',
                    'mensaje' => 'El servicio externo no respondió a tiempo. Puede ingresar los datos manualmente.'
                ];
            }
            return [
                'exito' => false,
                'codigo' => 'ERROR_RED',
                'mensaje' => 'Error de conexión con el proveedor externo. Puede continuar de forma manual.'
            ];
        }

        $cuerpoDecodificado = json_decode((string) $rawResponse, true);
        if (!is_array($cuerpoDecodificado)) {
            return [
                'exito' => false,
                'codigo' => 'RESPUESTA_INVALIDA',
                'mensaje' => 'El proveedor externo devolvió un formato no reconocible.'
            ];
        }

        if ($httpCode >= 500) {
            return [
                'exito' => false,
                'codigo' => 'ERROR_PROVEEDOR',
                'mensaje' => 'El servicio de consulta del proveedor no está disponible temporalmente.'
            ];
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return [
                'exito' => false,
                'codigo' => 'AUTORIZACION_PROVEEDOR',
                'mensaje' => 'Credenciales del servicio de consulta inválidas o expiradas.'
            ];
        }

        return [
            'exito' => true,
            'codigo' => 'OK',
            'mensaje' => 'Petición procesada correctamente.',
            'cuerpo' => $cuerpoDecodificado
        ];
    }
}
