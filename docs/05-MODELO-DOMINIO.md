# 05 — Modelo de Dominio y Entidades

## 1. Identidad Central Desacoplada: Matriz Persona

El diseño del modelo de dominio de CasaPRO resuelve de raíz el error clásico de duplicar datos de personas en múltiples tablas. Se establece la entidad **Persona** como la raíz de identidad única del sistema:

```mermaid
erDiagram
    PERSONA ||--o| CLIENTE : "actúa como"
    PERSONA ||--o| PERSONAL : "actúa como"
    PERSONA ||--o| USUARIO : "tiene credenciales"
    PERSONA ||--o| SOCIO_APV : "es miembro"

    PERSONA {
        bigint id PK
        string tipo_persona "NATURAL | JURIDICA"
        string tipo_documento "DNI | RUC | CE | PASAPORTE"
        string numero_documento UK
        string nombres
        string apellido_paterno
        string apellido_materno
        string razon_social
        string email
        string telefono_principal
        string direccion_fiscal
        string estado "ACTIVO | INACTIVO"
    }

    CLIENTE {
        bigint id PK
        bigint persona_id FK
        string codigo_cliente UK
        string calificacion_crediticia
        datetime fecha_primer_contacto
    }

    PERSONAL {
        bigint id PK
        bigint persona_id FK
        bigint empresa_id FK
        string cargo
        string tipo_contrato
        date fecha_ingreso
        decimal porcentaje_comision
        string estado "ACTIVO | CESADO"
    }

    USUARIO {
        bigint id PK
        bigint persona_id FK
        string username UK
        string password_hash
        bigint rol_id FK
        string scope_tipo "GLOBAL | EMPRESA | PROYECTO | SECTOR"
        boolean activo
    }
```

### Reglas de Dominio de Identidad:
1. Si un cliente pasa a ser asesor de ventas (personal), se crea un registro en `personal` vinculado al mismo `persona_id`.
2. Si un asesor accede al software para ingresar visitas, se crea un registro en `usuarios` vinculado a su `persona_id`.
3. Los cambios de domicilio o teléfono de una persona se actualizan una sola vez y se reflejan instantáneamente en sus expedientes de cliente, usuario y personal.

---

## 2. Entidades Catastrales y Territoriales

```mermaid
erDiagram
    EMPRESA ||--o{ PROYECTO : "desarrolla"
    PROYECTO ||--o{ SECTOR : "se subdivide en"
    SECTOR ||--o{ MANZANA : "contiene"
    MANZANA ||--o{ LOTE : "agrupa"

    PROYECTO {
        bigint id PK
        bigint empresa_id FK
        string codigo UK
        string nombre
        text descripcion
        decimal latitud
        decimal longitud
        string estado "PLANIFICACION | EN_VENTA | CONSOLIDADO | CERRADO"
    }

    SECTOR {
        bigint id PK
        bigint proyecto_id FK
        string nombre "Etapa 1, Sector Norte"
        integer orden
    }

    MANZANA {
        bigint id PK
        bigint sector_id FK
        string codigo "Mz. A, Mz. B"
    }

    LOTE {
        bigint id PK
        bigint manzana_id FK
        string numero_lote "Lote 01, Lote 02"
        decimal area_m2
        decimal frente_m
        decimal fondo_m
        decimal izquierda_m
        decimal derecha_m
        decimal precio_base_m2
        decimal precio_lista
        string estado "DISPONIBLE | BLOQUEADO | COTIZADO | RESERVADO | VENDIDO | ENTREGADO"
        text coordenadas_geojson
    }
```

---

## 3. Entidades Comerciales y Operaciones de Venta

```mermaid
erDiagram
    CLIENTE ||--o{ COTIZACION : "solicita"
    LOTE ||--o{ COTIZACION : "se cotiza en"
    COTIZACION ||--o| RESERVA : "puede derivar en"
    RESERVA ||--o| VENTA : "se formaliza en"
    VENTA ||--o{ CRONOGRAMA_CUOTA : "genera"
    CRONOGRAMA_CUOTA ||--o{ PAGO_CUOTA : "se cancela con"
    CAJA ||--o{ PAGO_CUOTA : "recauda"

    COTIZACION {
        bigint id PK
        bigint cliente_id FK
        bigint lote_id FK
        bigint asesor_id FK
        decimal precio_pactado
        decimal cuota_inicial
        integer numero_cuotas
        decimal tasa_interes
        date fecha_emision
        date fecha_vencimiento
        string estado "VIGENTE | VENCIDA | RECHAZADA | CERRADA"
    }

    RESERVA {
        bigint id PK
        bigint cotizacion_id FK
        decimal monto_reserva
        date fecha_limite
        string estado "VIGENTE | CADUCADA | APLICADA_VENTA | DEVUELTA"
    }

    VENTA {
        bigint id PK
        bigint reserva_id FK
        bigint cliente_id FK
        bigint lote_id FK
        string codigo_contrato UK
        string modalidad "CONTADO | FINANCIADO"
        decimal precio_final
        decimal cuota_inicial_total
        integer total_cuotas
        date fecha_contrato
        string estado "VIGENTE | RESUELTA | CANCELADA_TOTAL"
    }

    CRONOGRAMA_CUOTA {
        bigint id PK
        bigint venta_id FK
        integer numero_cuota
        date fecha_vencimiento
        decimal monto_capital
        decimal monto_interes
        decimal monto_total
        decimal monto_pagado
        decimal saldo_cuota
        string estado "PENDIENTE | PARCIAL | PAGADA | EN_MORA"
    }
```

---

## 4. Entidades Financieras y de Tesorería

- **Caja (`cajas`):** Puntos de recaudación físicos o lógicos (Caja Principal, Caja Caseta Proyecto A, BCP Recaudación).
- **Apertura y Cierre de Caja (`apertura_cierre_cajas`):** Registro de arqueo inicial, transacciones durante el turno y arqueo de balance de cierre por usuario cajero.
- **Movimiento de Caja (`movimientos_caja`):** Todo ingreso por cuotas, reservas o pagos varios, y egresos autorizados (viáticos, servicios de caseta).

---

## 5. Entidades de Postventa, Entrega y APV

- **Acta de Entrega (`actas_entrega`):** Documento formal con fecha, participantes, checklist de inspección y estado de posesión.
- **Ticket Postventa (`tickets_postventa`):** Solicitudes de atención por garantías de habilitación urbana (afirmado, hitos, conexiones de agua/luz) con seguimiento de estado (`ABIERTO`, `EN_PROCESO`, `ATENDIDO`, `CERRADO`).
- **Libro de Reclamaciones (`reclamaciones`):** Registro normativo oficial con código único correlativo por año, tipo (queja o reclamo), detalle y descargo documentado de la empresa.
- **APV (`apv_asociaciones`, `apv_socios`):** Gestión asociativa vecinal desacoplada de la empresa operadora para administración comunal.
