<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Excepciones\ValidacionExcepcion;

class ReordenarMenuDTO
{
    /**
     * @param array<int, array{id: int, padre_id: ?int, orden: int}> $movimientos
     */
    public function __construct(
        public readonly array $movimientos
    ) {
    }

    public static function desdeArray(array $datos): self
    {
        $errores = [];

        $items = $datos['movimientos'] ?? $datos['opciones'] ?? $datos;
        if (!is_array($items) || empty($items)) {
            throw new ValidacionExcepcion('Se requiere una lista de movimientos jerárquicos.', ['movimientos' => 'Se requiere una lista de movimientos jerárquicos.']);
        }

        $movimientos = [];
        $idsVistos = [];

        foreach ($items as $indice => $item) {
            if (!is_array($item)) {
                $errores["movimientos.{$indice}"] = 'Formato inválido de elemento.';
                continue;
            }

            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0) {
                $errores["movimientos.{$indice}.id"] = 'ID de nodo requerido y debe ser entero positivo.';
            }

            if (isset($idsVistos[$id])) {
                $errores["movimientos.{$indice}.id"] = "ID {$id} duplicado en el payload de reordenamiento.";
            }
            $idsVistos[$id] = true;

            $padreRaw = $item['padre_id'] ?? $item['nuevo_padre_id'] ?? null;
            $padreId = ($padreRaw !== '' && $padreRaw !== null) ? (int) $padreRaw : null;

            $orden = isset($item['orden']) ? (int) $item['orden'] : 1;
            if ($orden <= 0) {
                $orden = 1;
            }

            $movimientos[] = [
                'id'       => $id,
                'padre_id' => $padreId,
                'orden'    => $orden
            ];
        }

        if (!empty($errores)) {
            throw new ValidacionExcepcion('Errores en la estructura de reordenamiento.', $errores);
        }

        return new self($movimientos);
    }
}
