# 05 — Modelo de Dominio y Entidades

## 1. Identidad Central Desacoplada: Matriz Persona

El diseño del modelo de dominio de CasaPRO resuelve de raíz el error clásico de duplicar datos de personas en múltiples tablas. Se establece la entidad **Persona** como la raíz de identidad única del sistema:

```mermaid
erDiagram
    PERSONA ||--o| PERSONA_NATURAL : "se extiende como"
    PERSONA ||--o| PERSONA_JURIDICA : "se extiende como"
    PERSONA ||--o{ PERSONA_DOCUMENTO : "posee"
    PERSONA ||--o{ PERSONA_CONTACTO : "tiene"
    PERSONA ||--o{ PERSONA_DIRECCION : "reside en"
    PERSONA_JURIDICA ||--o{ PERSONA_REPRESENTANTE : "es representada por"
    PERSONA_NATURAL ||--o{ PERSONA_REPRESENTANTE : "actúa como representante"

    PERSONA ||--o| CLIENTE : "actúa como (Fase 2)"
    PERSONA ||--o| PERSONAL : "actúa como (Fase 4)"
    PERSONA ||--o| USUARIO : "tiene credenciales (Fase 4)"
    PERSONA ||--o| SOCIO_APV : "es miembro (Fase 7)"

    PERSONA {
        bigint id PK
        string tipo_persona "NATURAL | JURIDICA"
        string estado "ACTIVO | INACTIVO"
        text notas
        timestamp creado_en
        timestamp actualizado_en
    }

    PERSONA_NATURAL {
        bigint persona_id PK,FK
        string nombres
        string apellido_paterno
        string apellido_materno
        date fecha_nacimiento
        int sexo_id FK
        int estado_civil_id FK
        int pais_nacimiento_id FK
        string profesion_ocupacion
    }

    PERSONA_JURIDICA {
        bigint persona_id PK,FK
        string razon_social
        string nombre_comercial
        date fecha_constitucion
        text objeto_social
    }

    PERSONA_DOCUMENTO {
        bigint id PK
        bigint persona_id FK
        int tipo_documento_id FK
        string numero_documento UK
        boolean es_principal
        int pais_emision_id FK
        date fecha_emision
        date fecha_vencimiento
        string estado "ACTIVO | INACTIVO"
    }

    PERSONA_CONTACTO {
        bigint id PK
        bigint persona_id FK
        int tipo_contacto_id FK
        string valor
        string etiqueta
        boolean es_principal
        string estado "ACTIVO | INACTIVO"
    }

    PERSONA_DIRECCION {
        bigint id PK
        bigint persona_id FK
        int tipo_direccion_id FK
        int distrito_id FK
        string direccion
        string referencia
        string codigo_postal
        boolean es_principal
        string estado "ACTIVO | INACTIVO"
    }

    PERSONA_REPRESENTANTE {
        bigint id PK
        bigint persona_juridica_id FK
        bigint persona_natural_id FK
        string cargo
        string partida_registral
        date fecha_inicio
        date fecha_fin
        boolean es_representante_actual
        string estado "ACTIVO | INACTIVO"
    }
```

### Reglas de Dominio de Identidad:
1. **Identidad Raíz Pura:** `Persona` concentra exclusivamente los atributos compartidos por cualquier actor humano o corporativo. Sus extensiones `persona_natural` y `persona_juridica` preservan los campos exclusivos de cada naturaleza.
2. **Desacoplamiento de Roles Satélite:** `Persona != Cliente != Personal != Usuario != Socio APV`. Los roles de negocio se relacionan mediante claves foráneas apuntando a `personas(id)`.
3. **Múltiples Documentos por Persona:** Los documentos de identidad (DNI, RUC, CE, Pasaporte) se gestionan en `persona_documentos`. El RUC de una empresa no se guarda en `persona_juridica`, sino como documento oficial con clave `UNIQUE (tipo_documento_id, numero_documento)`.
4. **Estados de Persona:** Exclusivamente `ACTIVO` o `INACTIVO`. El estado `BLOQUEADO` está prohibido para identidad civil; queda reservado para futuras credenciales y cuentas de usuario. Una persona inactiva conserva íntegro su historial y relaciones referenciales.
5. **Representación Legal Trazable:** `persona_representantes` modela formalmente la relación entre una Persona Jurídica y la Persona Natural que ejerce como representante legal, apoderado o gerente general, con vigencia y partida registral.

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
