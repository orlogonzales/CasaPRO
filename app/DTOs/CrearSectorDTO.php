<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;
use App\Modelos\Sector;

/**
 * CrearSectorDTO — Valida los datos para dar de alta un Sector Urbanístico en un Proyecto.
 *
 * Microfase 3B — CasaPRO Inmobiliario.
 */
class CrearSectorDTO
{
    private const CAMPOS_PERMITIDOS = [
        'proyecto_id',
        'codigo',
        'nombre',
        'descripcion',
        'area_bruta_m2',
        'area_util_m2',
        'area_cesion_m2',
        'area_comun_m2',
        'orden',
        'estado',
        'precio_m2_inicial',
        'motivo_precio_inicial',
        'csrf_token',
        '_csrf_token'
    ];

    public int $proyectoId;
    public string $codigo;
    public string $nombre;
    public ?string $descripcion = null;
    public float $areaBrutaM2;
    public float $areaUtilM2 = 0.0000;
    public float $areaCesionM2 = 0.0000;
    public float $areaComunM2 = 0.0000;
    public int $orden = 1;
    public string $estado = Sector::ESTADO_EN_DESARROLLO;
    public float $precioM2Inicial;
    public string $motivoPrecioInicial = 'Precio base inicial de lanzamiento de sector';

    public function __construct(array $datos = [])
    {
        if (!empty($datos)) {
            $instancia = self::desdeArray($datos);
            $this->proyectoId = $instancia->proyectoId;
            $this->codigo = $instancia->codigo;
            $this->nombre = $instancia->nombre;
            $this->descripcion = $instancia->descripcion;
            $this->areaBrutaM2 = $instancia->areaBrutaM2;
            $this->areaUtilM2 = $instancia->areaUtilM2;
            $this->areaCesionM2 = $instancia->areaCesionM2;
            $this->areaComunM2 = $instancia->areaComunM2;
            $this->orden = $instancia->orden;
            $this->estado = $instancia->estado;
            $this->precioM2Inicial = $instancia->precioM2Inicial;
            $this->motivoPrecioInicial = $instancia->motivoPrecioInicial;
        }
    }

    public static function desdeArray(array $datos): self
    {
        $errores = [];

        // 1. Blindaje Anti-Polución
        $camposDesconocidos = array_diff(array_keys($datos), self::CAMPOS_PERMITIDOS);
        if (!empty($camposDesconocidos)) {
            throw new ValidacionExcepcion(
                'Se detectaron campos no permitidos en el formulario: ' . implode(', ', $camposDesconocidos),
                ['payload' => ['Campos no autorizados presentes en el envío.']],
                422
            );
        }

        $dto = new self();

        // 2. proyecto_id
        if (!isset($datos['proyecto_id']) || !is_numeric($datos['proyecto_id']) || (int) $datos['proyecto_id'] <= 0) {
            $errores['proyecto_id'] = 'El proyecto asociado es obligatorio y debe ser válido.';
        } else {
            $dto->proyectoId = (int) $datos['proyecto_id'];
        }

        // 3. codigo
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        if ($codigo === '') {
            $errores['codigo'] = 'El código del sector es obligatorio.';
        } elseif (strlen($codigo) < 2 || strlen($codigo) > 32) {
            $errores['codigo'] = 'El código debe tener entre 2 y 32 caracteres.';
        } elseif (!preg_match('/^[A-Z0-9_\-]+$/i', $codigo)) {
            $errores['codigo'] = 'El código solo puede contener letras, números, guiones y guiones bajos.';
        } else {
            $dto->codigo = strtoupper($codigo);
        }

        // 4. nombre
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'] = 'El nombre del sector es obligatorio.';
        } elseif (strlen($nombre) < 3 || strlen($nombre) > 150) {
            $errores['nombre'] = 'El nombre del sector debe tener entre 3 y 150 caracteres.';
        } else {
            $dto->nombre = $nombre;
        }

        // 5. descripcion
        if (isset($datos['descripcion']) && trim((string) $datos['descripcion']) !== '') {
            $dto->descripcion = trim((string) $datos['descripcion']);
        }

        // 6. area_bruta_m2
        if (!isset($datos['area_bruta_m2']) || !is_numeric($datos['area_bruta_m2']) || (float) $datos['area_bruta_m2'] <= 0) {
            $errores['area_bruta_m2'] = 'El área bruta del sector es obligatoria y debe ser mayor a 0 m².';
        } else {
            $dto->areaBrutaM2 = round((float) $datos['area_bruta_m2'], 4);
        }

        // 7. area_util_m2
        if (isset($datos['area_util_m2']) && $datos['area_util_m2'] !== '') {
            if (!is_numeric($datos['area_util_m2']) || (float) $datos['area_util_m2'] < 0) {
                $errores['area_util_m2'] = 'El área útil debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaUtilM2 = round((float) $datos['area_util_m2'], 4);
            }
        }

        // 8. area_cesion_m2
        if (isset($datos['area_cesion_m2']) && $datos['area_cesion_m2'] !== '') {
            if (!is_numeric($datos['area_cesion_m2']) || (float) $datos['area_cesion_m2'] < 0) {
                $errores['area_cesion_m2'] = 'El área de cesión debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaCesionM2 = round((float) $datos['area_cesion_m2'], 4);
            }
        }

        // 9. area_comun_m2
        if (isset($datos['area_comun_m2']) && $datos['area_comun_m2'] !== '') {
            if (!is_numeric($datos['area_comun_m2']) || (float) $datos['area_comun_m2'] < 0) {
                $errores['area_comun_m2'] = 'El área común debe ser un valor numérico mayor o igual a cero.';
            } else {
                $dto->areaComunM2 = round((float) $datos['area_comun_m2'], 4);
            }
        }

        // Invariante local de consistencia interna
        if (empty($errores)) {
            $sumaInterna = round($dto->areaUtilM2 + $dto->areaCesionM2 + $dto->areaComunM2, 4);
            if ($sumaInterna > ($dto->areaBrutaM2 + 0.0001)) {
                $errores['area_bruta_m2'] = "La suma de área útil ({$dto->areaUtilM2} m²), cesión ({$dto->areaCesionM2} m²) y común ({$dto->areaComunM2} m²) totaliza {$sumaInterna} m², lo cual excede el área bruta asignada ({$dto->areaBrutaM2} m²).";
            }
        }

        // 10. orden
        if (isset($datos['orden']) && $datos['orden'] !== '') {
            if (!is_numeric($datos['orden']) || (int) $datos['orden'] < 1) {
                $errores['orden'] = 'El orden de visualización debe ser un número entero mayor o igual a 1.';
            } else {
                $dto->orden = (int) $datos['orden'];
            }
        }

        // 11. estado
        if (isset($datos['estado']) && trim((string) $datos['estado']) !== '') {
            $estado = strtoupper(trim((string) $datos['estado']));
            if (!in_array($estado, Sector::ESTADOS_PERMITIDOS, true)) {
                $errores['estado'] = 'Estado de sector no reconocido.';
            } else {
                $dto->estado = $estado;
            }
        }

        // 12. precio_m2_inicial
        if (!isset($datos['precio_m2_inicial']) || !is_numeric($datos['precio_m2_inicial']) || (float) $datos['precio_m2_inicial'] <= 0) {
            $errores['precio_m2_inicial'] = 'El precio base inicial por m² es obligatorio y debe ser mayor a cero.';
        } else {
            $dto->precioM2Inicial = round((float) $datos['precio_m2_inicial'], 4);
        }

        // 13. motivo_precio_inicial
        if (isset($datos['motivo_precio_inicial']) && trim((string) $datos['motivo_precio_inicial']) !== '') {
            $motivo = trim((string) $datos['motivo_precio_inicial']);
            if (strlen($motivo) > 255) {
                $errores['motivo_precio_inicial'] = 'El motivo del precio inicial no puede exceder 255 caracteres.';
            } else {
                $dto->motivoPrecioInicial = $motivo;
            }
        }

        if (!empty($errores)) {
            $primerError = is_array(reset($errores)) ? reset($errores)[0] : reset($errores);
            throw new ValidacionExcepcion((string) $primerError, $errores, 422);
        }

        return $dto;
    }
}
