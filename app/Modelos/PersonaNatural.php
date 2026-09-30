<?php

declare(strict_types=1);

namespace App\Modelos;

use InvalidArgumentException;

/**
 * Extensión de atributos específicos para Persona Natural.
 */
class PersonaNatural
{
    private int $personaId;
    private string $nombres;
    private string $apellidoPaterno;
    private ?string $apellidoMaterno;
    private ?string $fechaNacimiento;
    private ?int $sexoId;
    private ?int $estadoCivilId;
    private ?int $paisNacimientoId;
    private ?string $profesionOcupacion;
    private ?string $creadoEn;
    private ?string $actualizadoEn;

    public function __construct(
        int $personaId,
        string $nombres,
        string $apellidoPaterno,
        ?string $apellidoMaterno = null,
        ?string $fechaNacimiento = null,
        ?int $sexoId = null,
        ?int $estadoCivilId = null,
        ?int $paisNacimientoId = null,
        ?string $profesionOcupacion = null,
        ?string $creadoEn = null,
        ?string $actualizadoEn = null
    ) {
        if ($personaId <= 0) {
            throw new InvalidArgumentException("El identificador de persona debe ser mayor a 0.");
        }
        $nombresTrim = trim($nombres);
        $apePatTrim = trim($apellidoPaterno);

        if ($nombresTrim === '') {
            throw new InvalidArgumentException("Los nombres no pueden estar vacíos.");
        }
        if ($apePatTrim === '') {
            throw new InvalidArgumentException("El apellido paterno no puede estar vacío.");
        }

        $this->personaId = $personaId;
        $this->nombres = $nombresTrim;
        $this->apellidoPaterno = $apePatTrim;
        $this->apellidoMaterno = $apellidoMaterno !== null ? trim($apellidoMaterno) : null;
        $this->fechaNacimiento = $fechaNacimiento;
        $this->sexoId = $sexoId;
        $this->estadoCivilId = $estadoCivilId;
        $this->paisNacimientoId = $paisNacimientoId;
        $this->profesionOcupacion = $profesionOcupacion !== null ? trim($profesionOcupacion) : null;
        $this->creadoEn = $creadoEn;
        $this->actualizadoEn = $actualizadoEn;
    }

    public function obtenerPersonaId(): int
    {
        return $this->personaId;
    }

    public function obtenerNombres(): string
    {
        return $this->nombres;
    }

    public function establecerNombres(string $nombres): void
    {
        $trim = trim($nombres);
        if ($trim === '') {
            throw new InvalidArgumentException("Los nombres no pueden estar vacíos.");
        }
        $this->nombres = $trim;
    }

    public function obtenerApellidoPaterno(): string
    {
        return $this->apellidoPaterno;
    }

    public function establecerApellidoPaterno(string $apellidoPaterno): void
    {
        $trim = trim($apellidoPaterno);
        if ($trim === '') {
            throw new InvalidArgumentException("El apellido paterno no puede estar vacío.");
        }
        $this->apellidoPaterno = $trim;
    }

    public function obtenerApellidoMaterno(): ?string
    {
        return $this->apellidoMaterno;
    }

    public function establecerApellidoMaterno(?string $apellidoMaterno): void
    {
        $this->apellidoMaterno = $apellidoMaterno !== null ? trim($apellidoMaterno) : null;
    }

    public function obtenerNombreCompleto(): string
    {
        $nombre = "{$this->nombres} {$this->apellidoPaterno}";
        if ($this->apellidoMaterno !== null && $this->apellidoMaterno !== '') {
            $nombre .= " {$this->apellidoMaterno}";
        }
        return $nombre;
    }

    public function obtenerApellidosYNombre(): string
    {
        $apellidos = $this->apellidoPaterno;
        if ($this->apellidoMaterno !== null && $this->apellidoMaterno !== '') {
            $apellidos .= " {$this->apellidoMaterno}";
        }
        return "{$apellidos}, {$this->nombres}";
    }

    public function obtenerFechaNacimiento(): ?string
    {
        return $this->fechaNacimiento;
    }

    public function establecerFechaNacimiento(?string $fechaNacimiento): void
    {
        $this->fechaNacimiento = $fechaNacimiento;
    }

    public function obtenerSexoId(): ?int
    {
        return $this->sexoId;
    }

    public function establecerSexoId(?int $sexoId): void
    {
        $this->sexoId = $sexoId;
    }

    public function obtenerEstadoCivilId(): ?int
    {
        return $this->estadoCivilId;
    }

    public function establecerEstadoCivilId(?int $estadoCivilId): void
    {
        $this->estadoCivilId = $estadoCivilId;
    }

    public function obtenerPaisNacimientoId(): ?int
    {
        return $this->paisNacimientoId;
    }

    public function establecerPaisNacimientoId(?int $paisNacimientoId): void
    {
        $this->paisNacimientoId = $paisNacimientoId;
    }

    public function obtenerProfesionOcupacion(): ?string
    {
        return $this->profesionOcupacion;
    }

    public function establecerProfesionOcupacion(?string $profesionOcupacion): void
    {
        $this->profesionOcupacion = $profesionOcupacion !== null ? trim($profesionOcupacion) : null;
    }

    public function obtenerCreadoEn(): ?string
    {
        return $this->creadoEn;
    }

    public function obtenerActualizadoEn(): ?string
    {
        return $this->actualizadoEn;
    }

    /**
     * @param array<string, mixed> $datos
     * @return self
     */
    public static function desdeArreglo(array $datos): self
    {
        return new self(
            personaId: (int) ($datos['persona_id'] ?? 0),
            nombres: (string) ($datos['nombres'] ?? ''),
            apellidoPaterno: (string) ($datos['apellido_paterno'] ?? ''),
            apellidoMaterno: isset($datos['apellido_materno']) ? (string) $datos['apellido_materno'] : null,
            fechaNacimiento: isset($datos['fecha_nacimiento']) ? (string) $datos['fecha_nacimiento'] : null,
            sexoId: isset($datos['sexo_id']) ? (int) $datos['sexo_id'] : null,
            estadoCivilId: isset($datos['estado_civil_id']) ? (int) $datos['estado_civil_id'] : null,
            paisNacimientoId: isset($datos['pais_nacimiento_id']) ? (int) $datos['pais_nacimiento_id'] : null,
            profesionOcupacion: isset($datos['profesion_ocupacion']) ? (string) $datos['profesion_ocupacion'] : null,
            creadoEn: isset($datos['creado_en']) ? (string) $datos['creado_en'] : null,
            actualizadoEn: isset($datos['actualizado_en']) ? (string) $datos['actualizado_en'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'persona_id' => $this->personaId,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'nombre_completo' => $this->obtenerNombreCompleto(),
            'fecha_nacimiento' => $this->fechaNacimiento,
            'sexo_id' => $this->sexoId,
            'estado_civil_id' => $this->estadoCivilId,
            'pais_nacimiento_id' => $this->paisNacimientoId,
            'profesion_ocupacion' => $this->profesionOcupacion,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
        ];
    }
}
