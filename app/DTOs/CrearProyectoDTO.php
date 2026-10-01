<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Proyecto;

/**
 * CrearProyectoDTO — Encapsula y valida los datos para el alta de un Proyecto inmobiliario.
 *
 * Microfase 3A — CasaPRO Inmobiliario.
 */
class CrearProyectoDTO
{
    private const CAMPOS_PERMITIDOS = [
        'empresa_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo_proyecto',
        'moneda',
        'distrito_id',
        'direccion_referencia',
        'tipo_tolerancia',
        'valor_tolerancia',
        'latitud',
        'longitud',
        'zoom_mapa',
        'estado',
        'csrf_token',
        '_csrf_token'
    ];

    public ?int $empresaId = null;
    public string $codigo;
    public string $nombre;
    public ?string $descripcion = null;
    public string $tipoProyecto = Proyecto::TIPO_PROPIO;
    public string $moneda = Proyecto::MONEDA_PEN;
    public int $distritoId;
    public ?string $direccionReferencia = null;
    public string $tipoTolerancia = Proyecto::TOLERANCIA_ABSOLUTA_M2;
    public float $valorTolerancia = 0.5000;
    public ?float $latitud = null;
    public ?float $longitud = null;
    public int $zoomMapa = 16;
    public string $estado = Proyecto::ESTADO_PLANIFICACION;

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->empresaId = $instancia->empresaId;
            $this->codigo = $instancia->codigo;
            $this->nombre = $instancia->nombre;
            $this->descripcion = $instancia->descripcion;
            $this->tipoProyecto = $instancia->tipoProyecto;
            $this->moneda = $instancia->moneda;
            $this->distritoId = $instancia->distritoId;
            $this->direccionReferencia = $instancia->direccionReferencia;
            $this->tipoTolerancia = $instancia->tipoTolerancia;
            $this->valorTolerancia = $instancia->valorTolerancia;
            $this->latitud = $instancia->latitud;
            $this->longitud = $instancia->longitud;
            $this->zoomMapa = $instancia->zoomMapa;
            $this->estado = $instancia->estado;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $desconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($desconocidos)) {
            throw new ValidacionExcepcion(
                'Campos no permitidos en la creación del proyecto: ' . implode(', ', $desconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $errores = [];

        // 1. Código
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            $errores['codigo'][] = 'El código del proyecto es obligatorio.';
        } elseif (strlen($codigo) < 3 || strlen($codigo) > 32) {
            $errores['codigo'][] = 'El código debe tener entre 3 y 32 caracteres.';
        } elseif (!preg_match('/^[A-Z0-9_]+$/', $codigo)) {
            $errores['codigo'][] = 'El código solo puede contener letras mayúsculas, números y guiones bajos (ej: PRJ_VALLE_SUR).';
        }

        // 2. Nombre
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'][] = 'El nombre comercial del proyecto es obligatorio.';
        } elseif (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) {
            $errores['nombre'][] = 'El nombre debe tener entre 3 y 150 caracteres.';
        }

        // 3. Tipo Proyecto
        $tipoProyecto = (string) ($datos['tipo_proyecto'] ?? Proyecto::TIPO_PROPIO);
        if (!in_array($tipoProyecto, [Proyecto::TIPO_PROPIO, Proyecto::TIPO_CONVENIO_APV, Proyecto::TIPO_ASOCIATIVO], true)) {
            $errores['tipo_proyecto'][] = 'El tipo de proyecto debe ser PROPIO, CONVENIO_APV o ASOCIATIVO.';
        }

        // 4. Moneda
        $moneda = strtoupper(trim((string) ($datos['moneda'] ?? Proyecto::MONEDA_PEN)));
        if (!in_array($moneda, [Proyecto::MONEDA_PEN, Proyecto::MONEDA_USD], true)) {
            $errores['moneda'][] = 'La moneda debe ser PEN (Soles) o USD (Dólares).';
        }

        // 5. Distrito (UBIGEO)
        $distritoId = (int) ($datos['distrito_id'] ?? 0);
        if ($distritoId <= 0) {
            $errores['distrito_id'][] = 'Debe seleccionar un distrito válido del catálogo UBIGEO.';
        }

        // 6. Política de Tolerancia (GATE 3A-04)
        $tipoTolerancia = (string) ($datos['tipo_tolerancia'] ?? Proyecto::TOLERANCIA_ABSOLUTA_M2);
        if (!in_array($tipoTolerancia, [Proyecto::TOLERANCIA_ABSOLUTA_M2, Proyecto::TOLERANCIA_PORCENTUAL], true)) {
            $errores['tipo_tolerancia'][] = 'La política de tolerancia debe ser ABSOLUTA_M2 o PORCENTUAL.';
        }

        $valorTolerancia = isset($datos['valor_tolerancia']) ? (float) $datos['valor_tolerancia'] : 0.5000;
        if ($valorTolerancia < 0) {
            $errores['valor_tolerancia'][] = 'El valor de tolerancia no puede ser negativo.';
        } elseif ($tipoTolerancia === Proyecto::TOLERANCIA_PORCENTUAL && $valorTolerancia > 100.0) {
            $errores['valor_tolerancia'][] = 'El valor de tolerancia porcentual no puede exceder el 100%.';
        }

        // 7. Estado
        $estado = (string) ($datos['estado'] ?? Proyecto::ESTADO_PLANIFICACION);
        $estadosPermitidos = [
            Proyecto::ESTADO_PLANIFICACION,
            Proyecto::ESTADO_EN_VENTA,
            Proyecto::ESTADO_CONSOLIDADO,
            Proyecto::ESTADO_CERRADO,
            Proyecto::ESTADO_INACTIVO
        ];
        if (!in_array($estado, $estadosPermitidos, true)) {
            $errores['estado'][] = 'Estado inicial del proyecto no válido.';
        }

        // 8. Coordenadas opcionales
        $latitud = isset($datos['latitud']) && $datos['latitud'] !== '' ? (float) $datos['latitud'] : null;
        $longitud = isset($datos['longitud']) && $datos['longitud'] !== '' ? (float) $datos['longitud'] : null;
        if ($latitud !== null && ($latitud < -90.0 || $latitud > 90.0)) {
            $errores['latitud'][] = 'La latitud debe estar comprendida entre -90 y 90 grados.';
        }
        if ($longitud !== null && ($longitud < -180.0 || $longitud > 180.0)) {
            $errores['longitud'][] = 'La longitud debe estar comprendida entre -180 y 180 grados.';
        }

        $zoomMapa = isset($datos['zoom_mapa']) ? (int) $datos['zoom_mapa'] : 16;
        if ($zoomMapa < 1 || $zoomMapa > 22) {
            $errores['zoom_mapa'][] = 'El zoom del mapa debe estar entre 1 y 22.';
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Datos de proyecto inválidos o incompletos.', $errores, 422);
        }

        $dto = new self();
        $dto->empresaId = isset($datos['empresa_id']) ? (int) $datos['empresa_id'] : null;
        $dto->codigo = $codigo;
        $dto->nombre = $nombre;
        $dto->descripcion = isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== '' ? trim((string) $datos['descripcion']) : null;
        $dto->tipoProyecto = $tipoProyecto;
        $dto->moneda = $moneda;
        $dto->distritoId = $distritoId;
        $dto->direccionReferencia = isset($datos['direccion_referencia']) && trim((string) $datos['direccion_referencia']) !== '' ? trim((string) $datos['direccion_referencia']) : null;
        $dto->tipoTolerancia = $tipoTolerancia;
        $dto->valorTolerancia = $valorTolerancia;
        $dto->latitud = $latitud;
        $dto->longitud = $longitud;
        $dto->zoomMapa = $zoomMapa;
        $dto->estado = $estado;

        return $dto;
    }
}
