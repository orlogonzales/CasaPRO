<?php

declare(strict_types=1);

namespace App\Servicios;

/**
 * Interfaz que define el contrato abstracto para proveedores de consulta documental (DNI / RUC).
 * Permite desacoplar el dominio de proveedores específicos (como APIsPERU) y facilitar
 * la inyección de dobles de prueba deterministas (mocks/stubs) en suites de pruebas automatizadas.
 */
interface ProveedorDocumentoInterface
{
    /**
     * Consulta información de una persona natural por su DNI.
     *
     * @param string $dni Número de DNI (8 dígitos)
     * @return array{exito: bool, codigo: string, mensaje: string, datos?: array<string, mixed>}
     */
    public function consultarDni(string $dni): array;

    /**
     * Consulta información de una persona jurídica o contribuyente por su RUC.
     *
     * @param string $ruc Número de RUC (11 dígitos)
     * @return array{exito: bool, codigo: string, mensaje: string, datos?: array<string, mixed>}
     */
    public function consultarRuc(string $ruc): array;
}
