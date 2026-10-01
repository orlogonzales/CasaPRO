<?php

declare(strict_types=1);

namespace App\Repositorios;

use App\Core\ProveedorConexion;
use App\Modelos\MenuOpcion;
use PDO;

class MenuRepositorio
{
    private ProveedorConexion $proveedor;

    public function __construct(?ProveedorConexion $proveedor = null)
    {
        $this->proveedor = $proveedor ?? new ProveedorConexion();
    }

    private function resolverConexion(?PDO $conexion): PDO
    {
        return $conexion ?? $this->proveedor->obtenerConexion();
    }

    /**
     * @return array<int, array>
     */
    public function obtenerTodos(?PDO $conexion = null): array
    {
        $conn = $this->resolverConexion($conexion);
        $sql = "SELECT m.*, m.etiqueta AS titulo, p.codigo as privilegio_codigo, p.nombre as privilegio_nombre
                FROM `menu_opciones` m
                LEFT JOIN `privilegios` p ON m.privilegio_id = p.id
                ORDER BY m.padre_id ASC, m.orden ASC, m.id ASC";
        return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array>
     */
    public function obtenerActivosVisibles(?PDO $conexion = null): array
    {
        $conn = $this->resolverConexion($conexion);
        $sql = "SELECT m.*, m.etiqueta AS titulo, p.codigo as privilegio_codigo, p.nombre as privilegio_nombre
                FROM `menu_opciones` m
                LEFT JOIN `privilegios` p ON m.privilegio_id = p.id
                WHERE m.estado = 'ACTIVO' AND m.visible = 1
                ORDER BY m.padre_id ASC, m.orden ASC, m.id ASC";
        return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id, ?PDO $conexion = null): ?MenuOpcion
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("SELECT * FROM `menu_opciones` WHERE `id` = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? MenuOpcion::desdeArray($fila) : null;
    }

    public function buscarPorCodigo(string $codigo, ?PDO $conexion = null): ?MenuOpcion
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("SELECT * FROM `menu_opciones` WHERE `codigo` = :codigo LIMIT 1");
        $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? MenuOpcion::desdeArray($fila) : null;
    }

    public function contarHijos(int $id, ?PDO $conexion = null): int
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `menu_opciones` WHERE `padre_id` = :padre_id");
        $stmt->bindValue(':padre_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array{id: int, orden: int}>
     */
    public function obtenerHermanosOrdenados(?int $padreId, ?PDO $conexion = null): array
    {
        $conn = $this->resolverConexion($conexion);
        if ($padreId === null) {
            $sql = "SELECT id, orden FROM `menu_opciones` WHERE `padre_id` IS NULL ORDER BY `orden` ASC, `id` ASC";
            return $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $conn->prepare("SELECT id, orden FROM `menu_opciones` WHERE `padre_id` = :padre_id ORDER BY `orden` ASC, `id` ASC");
        $stmt->bindValue(':padre_id', $padreId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertar(MenuOpcion $opcion, ?PDO $conexion = null): int
    {
        $conn = $this->resolverConexion($conexion);
        $sql = "INSERT INTO `menu_opciones` 
                (`padre_id`, `tipo`, `codigo`, `etiqueta`, `ruta`, `icono`, `orden`, `privilegio_id`, `estado`, `visible`, `creado_en`)
                VALUES
                (:padre_id, :tipo, :codigo, :etiqueta, :ruta, :icono, :orden, :privilegio_id, :estado, :visible, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':padre_id', $opcion->obtenerPadreId(), $opcion->obtenerPadreId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo', $opcion->obtenerTipo(), PDO::PARAM_STR);
        $stmt->bindValue(':codigo', $opcion->obtenerCodigo(), PDO::PARAM_STR);
        $stmt->bindValue(':etiqueta', $opcion->obtenerEtiqueta(), PDO::PARAM_STR);
        $stmt->bindValue(':ruta', $opcion->obtenerRuta(), $opcion->obtenerRuta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':icono', $opcion->obtenerIcono(), $opcion->obtenerIcono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':orden', $opcion->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':privilegio_id', $opcion->obtenerPrivilegioId(), $opcion->obtenerPrivilegioId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $opcion->obtenerEstado(), PDO::PARAM_STR);
        $stmt->bindValue(':visible', $opcion->obtenerVisible(), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $conn->lastInsertId();
    }

    public function actualizar(MenuOpcion $opcion, ?PDO $conexion = null): bool
    {
        $conn = $this->resolverConexion($conexion);
        $sql = "UPDATE `menu_opciones` SET
                `padre_id` = :padre_id,
                `etiqueta` = :etiqueta,
                `ruta` = :ruta,
                `icono` = :icono,
                `orden` = :orden,
                `privilegio_id` = :privilegio_id,
                `visible` = :visible
                WHERE `id` = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $opcion->obtenerId(), PDO::PARAM_INT);
        $stmt->bindValue(':padre_id', $opcion->obtenerPadreId(), $opcion->obtenerPadreId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':etiqueta', $opcion->obtenerEtiqueta(), PDO::PARAM_STR);
        $stmt->bindValue(':ruta', $opcion->obtenerRuta(), $opcion->obtenerRuta() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':icono', $opcion->obtenerIcono(), $opcion->obtenerIcono() !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':orden', $opcion->obtenerOrden(), PDO::PARAM_INT);
        $stmt->bindValue(':privilegio_id', $opcion->obtenerPrivilegioId(), $opcion->obtenerPrivilegioId() ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':visible', $opcion->obtenerVisible(), PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function cambiarEstado(int $id, string $estado, ?PDO $conexion = null): bool
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("UPDATE `menu_opciones` SET `estado` = :estado WHERE `id` = :id");
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function actualizarJerarquiaYOrden(int $id, ?int $nuevoPadreId, int $nuevoOrden, ?PDO $conexion = null): bool
    {
        $conn = $this->resolverConexion($conexion);
        $sql = "UPDATE `menu_opciones` SET `padre_id` = :padre_id, `orden` = :orden WHERE `id` = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':padre_id', $nuevoPadreId, $nuevoPadreId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':orden', $nuevoOrden, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function actualizarOrden(int $id, int $nuevoOrden, ?PDO $conexion = null): bool
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("UPDATE `menu_opciones` SET `orden` = :orden WHERE `id` = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':orden', $nuevoOrden, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function eliminar(int $id, ?PDO $conexion = null): bool
    {
        $conn = $this->resolverConexion($conexion);
        $stmt = $conn->prepare("DELETE FROM `menu_opciones` WHERE `id` = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
