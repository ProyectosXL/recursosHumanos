/*
    FP_INTRA_FECHAS
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Usado por recursosHumanos/intranet/Class/Intranet.php:
      - lo leen la columna "Intranet XL" del hub y recursosHumanos/intranet/index.php
        (a través de la vista FP_V_INTRA_AGENDA, script 03);
      - lo escribe recursosHumanos/intranet/admin.php (solapa Fechas), con el permiso
        intranet.fechas.publicar.

    PARA QUÉ: fechas comerciales y operativas que cargan los responsables
    (por ejemplo "Black Friday del 27 al 29/11"). Los feriados NO van acá:
    siguen en FP_VAC_FERIADOS y se cargan por fuera de esta app.

    - fecha_hasta vacía = evento de un solo día.
    - La baja es lógica (activo = 0) y se puede reactivar.
    - Auditoría con el username del hub (FP_SOF_USUARIOS.username).

    Script idempotente: se puede correr más de una vez.
*/

IF OBJECT_ID('dbo.FP_INTRA_FECHAS', 'U') IS NULL
BEGIN

    CREATE TABLE dbo.FP_INTRA_FECHAS (
        id              INT IDENTITY(1, 1) NOT NULL
                        CONSTRAINT PK_FP_INTRA_FECHAS PRIMARY KEY,
        fecha_desde     DATE           NOT NULL,
        fecha_hasta     DATE           NULL,
        titulo          NVARCHAR(120)  NOT NULL,
        tipo            VARCHAR(10)    NOT NULL,
        detalle         NVARCHAR(300)  NULL,
        activo          BIT            NOT NULL
                        CONSTRAINT DF_FP_INTRA_FECHAS_activo DEFAULT (1),
        creado_por      NVARCHAR(100)  NOT NULL,
        creado_en       DATETIME       NOT NULL
                        CONSTRAINT DF_FP_INTRA_FECHAS_creado_en DEFAULT (GETDATE()),
        modificado_por  NVARCHAR(100)  NULL,
        modificado_en   DATETIME       NULL,

        CONSTRAINT CK_FP_INTRA_FECHAS_tipo
            CHECK (tipo IN ('COMERCIAL', 'OPERATIVA')),
        CONSTRAINT CK_FP_INTRA_FECHAS_titulo
            CHECK (LEN(LTRIM(RTRIM(titulo))) > 0),
        CONSTRAINT CK_FP_INTRA_FECHAS_rango
            CHECK (fecha_hasta IS NULL OR fecha_hasta >= fecha_desde)
    );

END;
GO

-- Lectura principal: "fechas activas desde hoy en adelante", ordenadas.
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_FECHAS_activo_desde'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_FECHAS')
)
BEGIN

    CREATE NONCLUSTERED INDEX IX_FP_INTRA_FECHAS_activo_desde
        ON dbo.FP_INTRA_FECHAS (activo, fecha_desde)
        INCLUDE (fecha_hasta, titulo, tipo);

END;
GO
