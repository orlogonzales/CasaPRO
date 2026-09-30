<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\PeticionIncorrectaExcepcion;

/**
 * DTO para la consulta y paginación de DataTables server-side con validación estricta y whitelist SQL.
 */
class ConsultaDataTablesDTO
{
    public const MAPA_COLUMNAS = [
        'id'                  => 'p.id',
        'tipo_persona'        => 'p.tipo_persona',
        'nombre_completo'     => 'nombre_completo',
        'documento_principal' => 'doc.numero_documento',
        'contacto_principal'  => 'con.valor',
        'estado'              => 'p.estado',
        'creado_en'           => 'p.creado_en'
    ];

    private const INDICES_A_COLUMNAS = [
        0 => 'id',
        1 => 'tipo_persona',
        2 => 'nombre_completo',
        3 => 'documento_principal',
        4 => 'contacto_principal',
        5 => 'estado',
        6 => 'creado_en'
    ];

    public int $draw = 1;
    public int $start = 0;
    public int $length = 10;
    public ?string $searchValue = null;
    public string $orderColumn = 'p.id';
    public string $orderDir = 'DESC';
    public ?string $filtroTipoPersona = null;
    public ?string $filtroEstado = null;

    public static function desdeArray(array $params): self
    {
        return self::desdePeticion($params);
    }

    public static function desdePeticion(array $params): self
    {
        $dto = new self();

        // 1. Validar draw
        if (isset($params['draw'])) {
            if (!is_numeric($params['draw']) || (int) $params['draw'] < 0) {
                throw new PeticionIncorrectaExcepcion("El parámetro 'draw' debe ser un entero positivo.", 400);
            }
            $dto->draw = (int) $params['draw'];
        }

        // 2. Validar start (offset)
        if (isset($params['start'])) {
            if (!is_numeric($params['start']) || (int) $params['start'] < 0) {
                throw new PeticionIncorrectaExcepcion("El parámetro 'start' debe ser un entero no negativo.", 400);
            }
            $dto->start = (int) $params['start'];
        }

        // 3. Validar length (limit)
        if (isset($params['length'])) {
            if (!is_numeric($params['length']) || (int) $params['length'] <= 0) {
                throw new PeticionIncorrectaExcepcion("El parámetro 'length' debe ser un entero positivo.", 400);
            }
            $lengthInt = (int) $params['length'];
            // Techo de seguridad: máximo 100 filas por página
            $dto->length = min($lengthInt, 100);
        }

        // 4. Validar search
        if (isset($params['search'])) {
            if (is_array($params['search']) && isset($params['search']['value'])) {
                $val = trim((string) $params['search']['value']);
                $dto->searchValue = $val !== '' ? $val : null;
            } elseif (is_string($params['search'])) {
                $val = trim($params['search']);
                $dto->searchValue = $val !== '' ? $val : null;
            }
        }

        // 5. Validar order (columna y dirección)
        $candidatoColumna = null;
        $candidatoDir = null;

        if (isset($params['order'])) {
            if (is_array($params['order'])) {
                $primerOrden = $params['order'][0] ?? $params['order'];
                if (is_array($primerOrden)) {
                    $candidatoColumna = $primerOrden['column'] ?? null;
                    $candidatoDir = $primerOrden['dir'] ?? null;
                }
            }
        }

        // Si se envió orden explícito vía query params alternativos
        if ($candidatoColumna === null && isset($params['order_column'])) {
            $candidatoColumna = $params['order_column'];
        }
        if ($candidatoDir === null && isset($params['order_dir'])) {
            $candidatoDir = $params['order_dir'];
        }

        if ($candidatoColumna !== null) {
            $columnaLimpia = is_numeric($candidatoColumna)
                ? (self::INDICES_A_COLUMNAS[(int) $candidatoColumna] ?? null)
                : (string) $candidatoColumna;

            if ($columnaLimpia === null || !array_key_exists($columnaLimpia, self::MAPA_COLUMNAS)) {
                throw new PeticionIncorrectaExcepcion("Columna de ordenamiento no permitida: '{$candidatoColumna}'.", 400);
            }

            $dto->orderColumn = self::MAPA_COLUMNAS[$columnaLimpia];
        }

        if ($candidatoDir !== null) {
            $dirUpper = strtoupper(trim((string) $candidatoDir));
            if (!in_array($dirUpper, ['ASC', 'DESC'], true)) {
                throw new PeticionIncorrectaExcepcion("Dirección de ordenamiento inválida: '{$candidatoDir}'. Solo se admite ASC o DESC.", 400);
            }
            $dto->orderDir = $dirUpper;
        }

        // 6. Filtros opcionales
        if (!empty($params['tipo_persona'])) {
            $tipoUpper = strtoupper(trim((string) $params['tipo_persona']));
            if (!in_array($tipoUpper, ['NATURAL', 'JURIDICA'], true)) {
                throw new PeticionIncorrectaExcepcion("Filtro tipo_persona inválido. Debe ser NATURAL o JURIDICA.", 400);
            }
            $dto->filtroTipoPersona = $tipoUpper;
        }

        if (!empty($params['estado'])) {
            $estadoUpper = strtoupper(trim((string) $params['estado']));
            if (!in_array($estadoUpper, ['ACTIVO', 'INACTIVO'], true)) {
                throw new PeticionIncorrectaExcepcion("Filtro estado inválido. Debe ser ACTIVO o INACTIVO.", 400);
            }
            $dto->filtroEstado = $estadoUpper;
        }

        return $dto;
    }
}
