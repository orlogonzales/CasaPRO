<?php

declare(strict_types=1);

namespace App\Servicios;

use App\Core\ProveedorConexion;
use App\Core\ContextoPeticion;
use App\Repositorios\MenuRepositorio;
use App\Repositorios\RolRepositorio;
use App\Repositorios\UsuarioRepositorio;
use App\Modelos\MenuOpcion;
use App\DTOs\CrearMenuOpcionDTO;
use App\DTOs\ActualizarMenuOpcionDTO;
use App\DTOs\ReordenarMenuDTO;
use App\Excepciones\ReglaNegocioExcepcion;
use App\Excepciones\RecursoNoEncontradoExcepcion;
use PDO;
use Throwable;

class MenuServicio
{
    private ProveedorConexion $proveedor;
    private MenuRepositorio $menuRepo;
    private AuditoriaServicio $auditoria;
    private ?RolRepositorio $rolRepo;
    private ?UsuarioRepositorio $usuarioRepo;

    public function __construct(
        ?ProveedorConexion $proveedor = null,
        ?MenuRepositorio $menuRepo = null,
        ?AuditoriaServicio $auditoria = null,
        ?RolRepositorio $rolRepo = null,
        ?UsuarioRepositorio $usuarioRepo = null
    ) {
        $this->proveedor = $proveedor ?? new ProveedorConexion();
        $this->menuRepo = $menuRepo ?? new MenuRepositorio($this->proveedor);
        $this->auditoria = $auditoria ?? new AuditoriaServicio($this->proveedor);
        $this->rolRepo = $rolRepo ?? new RolRepositorio($this->proveedor);
        $this->usuarioRepo = $usuarioRepo ?? new UsuarioRepositorio($this->proveedor);
    }

    /**
     * Obtiene el árbol de navegación visible podado según los privilegios RBAC del usuario.
     * Implementa la regla central: MENÚ ≠ AUTORIZACIÓN y poda Fail-Closed.
     *
     * @return array<int, array>
     */
    public function obtenerArbolParaUsuario(int $usuarioId): array
    {
        $conn = $this->proveedor->obtenerConexion();
        $todas = $this->menuRepo->obtenerActivosVisibles($conn);

        // 1. Determinar si el usuario tiene rol SUPERADMIN (Bypass RBAC en renderizado)
        $esSuperadmin = $this->esUsuarioSuperadmin($usuarioId, $conn);

        // 2. Obtener lista de IDs de privilegios concedidos al usuario
        $privilegiosConcedidos = [];
        if (!$esSuperadmin) {
            $stmtPriv = $conn->prepare("
                SELECT DISTINCT p.id 
                FROM `usuario_roles` ur
                INNER JOIN `rol_privilegios` rp ON rp.rol_id = ur.rol_id
                INNER JOIN `privilegios` p ON p.id = rp.privilegio_id
                WHERE ur.usuario_id = :uid
            ");
            $stmtPriv->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
            $stmtPriv->execute();
            $privilegiosConcedidos = array_column($stmtPriv->fetchAll(PDO::FETCH_ASSOC), 'id');
            $privilegiosConcedidos = array_map('intval', $privilegiosConcedidos);
        }

        // 3. Catálogo de todos los privilegios existentes en BD para validar existencia (Fail-Closed)
        $todosPrivilegiosIds = array_map('intval', $conn->query("SELECT id FROM `privilegios`")->fetchAll(PDO::FETCH_COLUMN));

        // 4. Filtrado individual Fail-Closed
        $visiblesPorId = [];
        foreach ($todas as $nodo) {
            $privId = $nodo['privilegio_id'] !== null ? (int) $nodo['privilegio_id'] : null;

            if ($privId === null) {
                // Opción sin requisito RBAC específico: visible a cualquier autenticado
                $visiblesPorId[(int) $nodo['id']] = $nodo;
                continue;
            }

            if ($esSuperadmin) {
                // SUPERADMIN ve todas las opciones activas
                $visiblesPorId[(int) $nodo['id']] = $nodo;
                continue;
            }

            // Validar que el privilegio exista en catálogo activo (Fail-Closed si es inconsistente)
            if (!in_array($privId, $todosPrivilegiosIds, true)) {
                // Inconsistencia técnica: privilegio referenciado no existe -> Ocultar
                continue;
            }

            // Validar si el usuario lo tiene concedido
            if (in_array($privId, $privilegiosConcedidos, true)) {
                $visiblesPorId[(int) $nodo['id']] = $nodo;
            }
        }

        // 5. Construcción del árbol jerárquico y Poda de Ramas Vacías (Bottom-up)
        return $this->construirArbolPodado($visiblesPorId);
    }

    /**
     * Construye la jerarquía completa del menú para visualización y administración.
     *
     * @return array<int, array>
     */
    public function obtenerArbolCompleto(): array
    {
        $conn = $this->proveedor->obtenerConexion();
        $todas = $this->menuRepo->obtenerTodos($conn);
        $porId = [];
        foreach ($todas as $nodo) {
            $porId[(int) $nodo['id']] = $nodo;
        }

        return $this->ensamblarEstructuraArbol($porId);
    }

    public function obtenerPorId(int $id): ?array
    {
        $opcion = $this->menuRepo->buscarPorId($id);
        return $opcion ? $opcion->aArray() : null;
    }

    public function crearOpcion(CrearMenuOpcionDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedor->obtenerConexion();

        // 1. Validar unicidad del código
        $existente = $this->menuRepo->buscarPorCodigo($dto->codigo, $conn);
        if ($existente !== null) {
            throw new ReglaNegocioExcepcion("Ya existe una opción de menú con el código '{$dto->codigo}'.");
        }

        // 2. Validar jerarquía y profundidad
        $nivel = $this->calcularYValidarNivel($dto->padreId, $dto->tipo, null, $conn);

        // 3. Validar privilegio si viene
        if ($dto->privilegioId !== null) {
            $this->validarExistenciaPrivilegio($dto->privilegioId, $conn);
        }

        $conn->beginTransaction();
        try {
            $opcion = new MenuOpcion(
                null,
                $dto->padreId,
                $dto->tipo,
                $dto->codigo,
                $dto->etiqueta,
                $dto->ruta,
                $dto->icono,
                $dto->orden,
                $dto->privilegioId,
                $dto->estado,
                $dto->visible
            );

            $id = $this->menuRepo->insertar($opcion, $conn);

            // 4. Normalizar orden entre hermanos determinísticamente con PHP/PDO
            $this->normalizarSecuenciaHermanos($dto->padreId, $conn);

            $datosNuevos = $opcion->aArray();
            $datosNuevos['id'] = $id;

            // 5. Auditoría transversal
            $this->auditoria->registrar([
                'modulo'           => 'menu',
                'entidad'          => 'menu_opciones',
                'registro_id'      => $id,
                'accion'           => 'CREAR',
                'datos_anteriores' => null,
                'datos_nuevos'     => $datosNuevos,
                'metadatos'        => ['operador_id' => $operadorId, 'nivel' => $nivel]
            ], $conn);

            $conn->commit();

            return $datosNuevos;
        } catch (Throwable $t) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $t;
        }
    }

    public function actualizarOpcion(int $id, ActualizarMenuOpcionDTO $dto, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedor->obtenerConexion();
        $existente = $this->menuRepo->buscarPorId($id, $conn);
        if ($existente === null) {
            throw new RecursoNoEncontradoExcepcion("La opción de menú con ID {$id} no existe.");
        }

        $padreOriginal = $existente->obtenerPadreId();
        $cambiaPadre = ($padreOriginal !== $dto->padreId);

        // 1. Validar anti-auto-parent y anti-ciclo indirecto
        if ($cambiaPadre && $dto->padreId !== null) {
            $this->validarAntiCiclo($id, $dto->padreId, $conn);
        }

        // 2. Validar jerarquía y profundidad considerando sub-árbol
        $nivel = $this->calcularYValidarNivel($dto->padreId, $existente->obtenerTipo(), $id, $conn);

        // 3. Validar privilegio si viene
        if ($dto->privilegioId !== null) {
            $this->validarExistenciaPrivilegio($dto->privilegioId, $conn);
        }

        $conn->beginTransaction();
        try {
            $opcionActualizada = new MenuOpcion(
                $id,
                $dto->padreId,
                $existente->obtenerTipo(),
                $existente->obtenerCodigo(),
                $dto->etiqueta,
                $dto->ruta,
                $dto->icono,
                $dto->orden,
                $dto->privilegioId,
                $existente->obtenerEstado(),
                $dto->visible
            );

            $this->menuRepo->actualizar($opcionActualizada, $conn);

            // 4. Normalizar orden en padre destino y origen si cambió
            if ($cambiaPadre) {
                $this->normalizarSecuenciaHermanos($padreOriginal, $conn);
            }
            $this->normalizarSecuenciaHermanos($dto->padreId, $conn);

            // 5. Auditoría transversal
            $this->auditoria->registrar([
                'modulo'           => 'menu',
                'entidad'          => 'menu_opciones',
                'registro_id'      => $id,
                'accion'           => 'ACTUALIZAR',
                'datos_anteriores' => $existente->aArray(),
                'datos_nuevos'     => $opcionActualizada->aArray(),
                'metadatos'        => ['operador_id' => $operadorId, 'nivel' => $nivel]
            ], $conn);

            $conn->commit();

            return $opcionActualizada->aArray();
        } catch (Throwable $t) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $t;
        }
    }

    public function cambiarEstado(int $id, string $estado, int $operadorId, ContextoPeticion $contexto): array
    {
        $conn = $this->proveedor->obtenerConexion();
        $opcion = $this->menuRepo->buscarPorId($id, $conn);
        if ($opcion === null) {
            throw new RecursoNoEncontradoExcepcion("La opción de menú con ID {$id} no existe.");
        }

        $estadoUpper = strtoupper(trim($estado));
        if (!in_array($estadoUpper, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ReglaNegocioExcepcion("Estado no permitido. Debe ser ACTIVO o INACTIVO.");
        }

        $conn->beginTransaction();
        try {
            $this->menuRepo->cambiarEstado($id, $estadoUpper, $conn);

            $this->auditoria->registrar([
                'modulo'           => 'menu',
                'entidad'          => 'menu_opciones',
                'registro_id'      => $id,
                'accion'           => 'CAMBIAR_ESTADO',
                'datos_anteriores' => ['estado' => $opcion->obtenerEstado()],
                'datos_nuevos'     => ['estado' => $estadoUpper],
                'metadatos'        => ['operador_id' => $operadorId]
            ], $conn);

            $conn->commit();

            return ['id' => $id, 'estado' => $estadoUpper];
        } catch (Throwable $t) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $t;
        }
    }

    public function reordenar(ReordenarMenuDTO $dto, int $operadorId, ContextoPeticion $contexto): bool
    {
        $conn = $this->proveedor->obtenerConexion();

        $conn->beginTransaction();
        try {
            $padresAfectados = [];

            // Fase 1: Pre-validar todos los movimientos antes de aplicar
            foreach ($dto->movimientos as $mov) {
                $id = $mov['id'];
                $nuevoPadreId = $mov['padre_id'];
                $orden = $mov['orden'];

                $nodo = $this->menuRepo->buscarPorId($id, $conn);
                if ($nodo === null) {
                    throw new RecursoNoEncontradoExcepcion("No se encontró el nodo ID {$id} en la jerarquía.");
                }

                $padreActual = $nodo->obtenerPadreId();
                $padresAfectados[$padreActual ?? 'raiz'] = $padreActual;
                $padresAfectados[$nuevoPadreId ?? 'raiz'] = $nuevoPadreId;

                if ($nuevoPadreId !== $padreActual) {
                    if ($nuevoPadreId !== null) {
                        $this->validarAntiCiclo($id, $nuevoPadreId, $conn);
                    }
                    $this->calcularYValidarNivel($nuevoPadreId, $nodo->obtenerTipo(), $id, $conn);
                }

                $this->menuRepo->actualizarJerarquiaYOrden($id, $nuevoPadreId, $orden, $conn);
            }

            // Fase 2: Normalizar determinísticamente cada grupo de hermanos afectado en PHP
            foreach ($padresAfectados as $pId) {
                $this->normalizarSecuenciaHermanos($pId, $conn);
            }

            // Fase 3: Auditoría transversal del lote
            $this->auditoria->registrar([
                'modulo'           => 'menu',
                'entidad'          => 'menu_opciones',
                'registro_id'      => 0,
                'accion'           => 'REORDENAR',
                'datos_anteriores' => null,
                'datos_nuevos'     => $dto->movimientos,
                'metadatos'        => ['operador_id' => $operadorId, 'total_items' => count($dto->movimientos)]
            ], $conn);

            $conn->commit();
            return true;
        } catch (Throwable $t) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $t;
        }
    }

    public function eliminarOpcion(int $id, int $operadorId, ContextoPeticion $contexto): bool
    {
        $conn = $this->proveedor->obtenerConexion();
        $opcion = $this->menuRepo->buscarPorId($id, $conn);
        if ($opcion === null) {
            throw new RecursoNoEncontradoExcepcion("La opción de menú con ID {$id} no existe.");
        }

        // Validación de Dominio: Solo hojas terminales (sin hijos)
        $totalHijos = $this->menuRepo->contarHijos($id, $conn);
        if ($totalHijos > 0) {
            throw new ReglaNegocioExcepcion(
                "No es posible eliminar una opción de menú que contiene sub-elementos. Reubique o elimine los {$totalHijos} hijos primero."
            );
        }

        $padreId = $opcion->obtenerPadreId();

        $conn->beginTransaction();
        try {
            // Snapshot previo en auditoría
            $this->auditoria->registrar([
                'modulo'           => 'menu',
                'entidad'          => 'menu_opciones',
                'registro_id'      => $id,
                'accion'           => 'ELIMINAR',
                'datos_anteriores' => $opcion->aArray(),
                'datos_nuevos'     => null,
                'metadatos'        => ['operador_id' => $operadorId]
            ], $conn);

            $this->menuRepo->eliminar($id, $conn);

            // Normalizar hermanos
            $this->normalizarSecuenciaHermanos($padreId, $conn);

            $conn->commit();
            return true;
        } catch (Throwable $t) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $t;
        }
    }

    /**
     * Resecuencia determinísticamente en PHP/PDO los hermanos de un padre a 1..N sin huecos.
     */
    public function normalizarSecuenciaHermanos(?int $padreId, ?PDO $conn = null): void
    {
        $conexion = $conn ?? $this->proveedor->obtenerConexion();
        $hermanos = $this->menuRepo->obtenerHermanosOrdenados($padreId, $conexion);
        $secuencia = 1;

        foreach ($hermanos as $h) {
            $hId = (int) $h['id'];
            $ordenActual = (int) $h['orden'];

            if ($ordenActual !== $secuencia) {
                $this->menuRepo->actualizarOrden($hId, $secuencia, $conexion);
            }
            $secuencia++;
        }
    }

    public function normalizarOrdenHermanos(?int $padreId, ?PDO $conn = null): void
    {
        $this->normalizarSecuenciaHermanos($padreId, $conn);
    }

    /**
     * Calcula y valida la profundidad de nivel (0, 1, 2) y restringe el Nivel 3+.
     * Si el nodo actual tiene descendientes, verifica que ningún descendiente exceda Nivel 2.
     */
    private function calcularYValidarNivel(?int $padreId, string $tipo, ?int $nodoId, PDO $conn): int
    {
        if ($padreId === null) {
            // Nivel 0 (Raíz)
            $nivel = 0;
        } else {
            $padre = $this->menuRepo->buscarPorId($padreId, $conn);
            if ($padre === null) {
                throw new ReglaNegocioExcepcion("El nodo padre especificado (ID {$padreId}) no existe.");
            }

            if ($padre->esEnlace()) {
                throw new ReglaNegocioExcepcion("Una opción de tipo ENLACE no puede ser padre de otros elementos.");
            }

            // Calcular nivel del padre
            $nivelPadre = $this->calcularNivelNodo($padreId, $conn);
            $nivel = $nivelPadre + 1;
        }

        if ($nivel > 2) {
            throw new ReglaNegocioExcepcion(
                "Profundidad máxima excedida: El sistema CasaPRO permite un máximo de 3 niveles jerárquicos (Nivel 0, 1 y 2). Nivel {$nivel} no permitido."
            );
        }

        if ($nivel === 2 && $tipo === MenuOpcion::TIPO_AGRUPADOR) {
            throw new ReglaNegocioExcepcion(
                "En el Nivel 2 (hoja final) solo se permiten opciones de tipo ENLACE. No se admiten agrupadores de tercer nivel."
            );
        }

        // Si el nodo ya existía y tiene hijos, validar que su altura acumulada no sobrepase el nivel 2
        if ($nodoId !== null) {
            $alturaSubarbol = $this->calcularAlturaSubarbol($nodoId, $conn);
            if ($nivel + $alturaSubarbol > 2) {
                throw new ReglaNegocioExcepcion(
                    "Movimiento inválido: Los descendientes del nodo excederían la profundidad máxima de 3 niveles."
                );
            }
        }

        return $nivel;
    }

    private function calcularNivelNodo(int $nodoId, PDO $conn): int
    {
        $nivel = 0;
        $actualId = $nodoId;
        $visitados = [];

        while ($actualId !== null) {
            if (isset($visitados[$actualId])) {
                throw new ReglaNegocioExcepcion("Ciclo detectado en la jerarquía existente.");
            }
            $visitados[$actualId] = true;

            $nodo = $this->menuRepo->buscarPorId($actualId, $conn);
            if ($nodo === null || $nodo->obtenerPadreId() === null) {
                break;
            }

            $nivel++;
            $actualId = $nodo->obtenerPadreId();
        }

        return $nivel;
    }

    private function calcularAlturaSubarbol(int $nodoId, PDO $conn): int
    {
        $stmtHijos = $conn->prepare("SELECT id FROM `menu_opciones` WHERE `padre_id` = :pid");
        $stmtHijos->bindValue(':pid', $nodoId, PDO::PARAM_INT);
        $stmtHijos->execute();
        $hijos = $stmtHijos->fetchAll(PDO::FETCH_COLUMN);

        if (empty($hijos)) {
            return 0;
        }

        $maxAltura = 0;
        foreach ($hijos as $hijoId) {
            $altura = 1 + $this->calcularAlturaSubarbol((int) $hijoId, $conn);
            if ($altura > $maxAltura) {
                $maxAltura = $altura;
            }
        }

        return $maxAltura;
    }

    private function validarAntiCiclo(int $nodoId, int $nuevoPadreId, PDO $conn): void
    {
        if ($nodoId === $nuevoPadreId) {
            throw new ReglaNegocioExcepcion("Un nodo no puede ser su propio padre (auto-paternidad prohibida).");
        }

        // Recorrer hacia arriba desde el nuevo padre; si se encuentra nodoId -> ciclo indirecto
        $actual = $nuevoPadreId;
        $visitados = [];

        while ($actual !== null) {
            if ($actual === $nodoId) {
                throw new ReglaNegocioExcepcion(
                    "Operación inválida: Ciclo indirecto detectado. No se puede asignar como padre a uno de los descendientes del nodo."
                );
            }

            if (isset($visitados[$actual])) {
                break;
            }
            $visitados[$actual] = true;

            $padre = $this->menuRepo->buscarPorId($actual, $conn);
            $actual = $padre ? $padre->obtenerPadreId() : null;
        }
    }

    private function validarExistenciaPrivilegio(int $privilegioId, PDO $conn): void
    {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `privilegios` WHERE `id` = :id");
        $stmt->bindValue(':id', $privilegioId, PDO::PARAM_INT);
        $stmt->execute();
        if (((int) $stmt->fetchColumn()) === 0) {
            throw new ReglaNegocioExcepcion("El privilegio especificado (ID {$privilegioId}) no existe en el catálogo.");
        }
    }

    private function esUsuarioSuperadmin(int $usuarioId, PDO $conn): bool
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM `usuario_roles` ur
            INNER JOIN `roles` r ON r.id = ur.rol_id
            WHERE ur.usuario_id = :uid AND r.codigo = 'SUPERADMIN'
        ");
        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function construirArbolPodado(array $visiblesPorId): array
    {
        // Agrupar hijos por padre
        $hijosPorPadre = [];
        foreach ($visiblesPorId as $nodo) {
            $pId = $nodo['padre_id'] !== null ? (int) $nodo['padre_id'] : 'raiz';
            $hijosPorPadre[$pId][] = $nodo;
        }

        $arbol = [];
        $raices = $hijosPorPadre['raiz'] ?? [];

        foreach ($raices as $raiz) {
            $raizId = (int) $raiz['id'];

            if ($raiz['tipo'] === MenuOpcion::TIPO_ENLACE) {
                // Enlace raíz directo (ej: /inicio)
                $raiz['hijos'] = [];
                $arbol[] = $raiz;
                continue;
            }

            // Es agrupador de Nivel 0: buscar hijos de Nivel 1
            $hijosNivel1 = $hijosPorPadre[$raizId] ?? [];
            $hijosNivel1Podados = [];

            foreach ($hijosNivel1 as $n1) {
                $n1Id = (int) $n1['id'];

                if ($n1['tipo'] === MenuOpcion::TIPO_ENLACE) {
                    $n1['hijos'] = [];
                    $hijosNivel1Podados[] = $n1;
                    continue;
                }

                // Es agrupador de Nivel 1: buscar hijos de Nivel 2
                $hijosNivel2 = $hijosPorPadre[$n1Id] ?? [];
                if (!empty($hijosNivel2)) {
                    $n1['hijos'] = $hijosNivel2;
                    $hijosNivel1Podados[] = $n1;
                }
            }

            // Poda en cascada: Si el agrupador de Nivel 0 tiene al menos un hijo visible
            if (!empty($hijosNivel1Podados)) {
                $raiz['hijos'] = $hijosNivel1Podados;
                $arbol[] = $raiz;
            }
        }

        return $arbol;
    }

    private function ensamblarEstructuraArbol(array $porId): array
    {
        $hijosPorPadre = [];
        foreach ($porId as $nodo) {
            $pId = $nodo['padre_id'] !== null ? (int) $nodo['padre_id'] : 'raiz';
            $hijosPorPadre[$pId][] = $nodo;
        }

        $arbol = [];
        $raices = $hijosPorPadre['raiz'] ?? [];

        foreach ($raices as $raiz) {
            $raizId = (int) $raiz['id'];
            $hijosN1 = $hijosPorPadre[$raizId] ?? [];

            $h1ConHijos = [];
            foreach ($hijosN1 as $n1) {
                $n1Id = (int) $n1['id'];
                $n1['hijos'] = $hijosPorPadre[$n1Id] ?? [];
                $h1ConHijos[] = $n1;
            }

            $raiz['hijos'] = $h1ConHijos;
            $arbol[] = $raiz;
        }

        return $arbol;
    }
}
