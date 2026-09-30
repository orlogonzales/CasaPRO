<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Excepciones\ValidacionExcepcion;

/**
 * PoliticaContrasenaServicio — Autoridad soberana centralizada para la validación
 * y generación de contraseñas seguras en CasaPRO.
 *
 * Utilizada unánimemente en:
 * - First Bootstrap CLI (casapro-bootstrap-admin.php)
 * - Alta de Usuario (POST /api/usuarios)
 * - Reset Administrativo (POST /api/usuarios/{id}/reset-password)
 * - Cambio Personal (POST /api/mi-cuenta/cambiar-password)
 */
class PoliticaContrasenaServicio
{
    public const LONGITUD_MINIMA = 8;
    public const LONGITUD_MAXIMA = 128;

    /**
     * Evalúa una contraseña y retorna la lista de errores detectados (array vacío si cumple).
     *
     * @return array<string>
     */
    public static function obtenerErrores(string $password): array
    {
        $errores = [];

        if ($password !== trim($password)) {
            $errores[] = 'La contraseña no puede iniciar ni terminar con espacios en blanco.';
        }

        $longitud = mb_strlen($password);

        if ($longitud < self::LONGITUD_MINIMA) {
            $errores[] = sprintf('La contraseña debe contener al menos %d caracteres.', self::LONGITUD_MINIMA);
        }

        if ($longitud > self::LONGITUD_MAXIMA) {
            $errores[] = sprintf('La contraseña no puede exceder los %d caracteres.', self::LONGITUD_MAXIMA);
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errores[] = 'La contraseña debe incluir al menos una letra mayúscula (A-Z).';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errores[] = 'La contraseña debe incluir al menos una letra minúscula (a-z).';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errores[] = 'La contraseña debe incluir al menos un dígito numérico (0-9).';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $password)) {
            $errores[] = 'La contraseña debe incluir al menos un carácter especial o símbolo (!@#$%^&*...).';
        }

        return $errores;
    }

    /**
     * Valida de manera estricta que una contraseña cumpla con la política soberana de CasaPRO.
     *
     * @param string $password
     * @param string $nombreCampo Nombre del campo para reporte de errores (por defecto 'password')
     * @throws ValidacionExcepcion Si la contraseña no satisface la totalidad de criterios.
     */
    public static function validar(string $password, string $nombreCampo = 'password'): void
    {
        $errores = self::obtenerErrores($password);

        if (!empty($errores)) {
            throw new ValidacionExcepcion(
                'La contraseña especificada no cumple con la política de seguridad requerida.',
                [$nombreCampo => $errores],
                422
            );
        }
    }


    /**
     * Genera una contraseña provisoria aleatoria criptográficamente segura
     * garantizando el cumplimiento íntegro de la política.
     *
     * @param int $longitud Longitud deseada (mínimo 12 caracteres)
     * @return string
     */
    public static function generarTemporal(int $longitud = 12): string
    {
        $longitud = max(self::LONGITUD_MINIMA, max(12, $longitud));

        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minusculas = 'abcdefghijkmnpqrstuvwxyz';
        $numeros    = '23456789';
        $simbolos   = '!@#$%*-_=+';

        // Garantizar al menos uno de cada grupo obligatorio
        $passwordArr = [
            $mayusculas[random_int(0, strlen($mayusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $numeros[random_int(0, strlen($numeros) - 1)],
            $simbolos[random_int(0, strlen($simbolos) - 1)],
        ];

        $todos = $mayusculas . $minusculas . $numeros . $simbolos;
        $maxTodos = strlen($todos) - 1;

        while (count($passwordArr) < $longitud) {
            $passwordArr[] = $todos[random_int(0, $maxTodos)];
        }

        // Mezclar orden para evitar predictibilidad de posiciones
        for ($i = count($passwordArr) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $passwordArr[$i];
            $passwordArr[$i] = $passwordArr[$j];
            $passwordArr[$j] = $tmp;
        }

        $password = implode('', $passwordArr);

        // Validación de sanity check soberano
        self::validar($password);

        return $password;
    }
}
