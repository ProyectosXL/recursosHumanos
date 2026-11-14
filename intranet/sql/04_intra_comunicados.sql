/*
    FP_INTRA_COMUNICADOS
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Usado por recursosHumanos/intranet/Class/Intranet.php:
      - lo leen la columna "Intranet XL" del hub y recursosHumanos/intranet/index.php;
      - lo escribe recursosHumanos/intranet/admin.php (solapa Comunicados), con el permiso
        intranet.comunicados.publicar.

    PARA QUÉ: comunicados internos para todo el personal logueado en el hub.

    Reglas:
      - Se muestra si activo = 1 y hoy está entre vigente_desde y vigente_hasta
        (vigente_hasta vacía = no vence).
      - vigente_desde futura = "programado": aparece solo ese día.
      - texto es texto plano; los saltos de línea se respetan al mostrar.
      - link_url, si está, empieza con https://, http:// o / (link interno).
      - La baja es lógica (activo = 0) y se puede reactivar.
      - Cualquier responsable con permiso edita cualquier comunicado;
        modificado_por registra quién fue.
      - "vigente_desde" lo manda siempre la app con la fecha de Buenos Aires;
        el DEFAULT es solo un respaldo.

    Las versiones nuevas de DocuGest NO se guardan acá: se leen en vivo
    desde la base central y se muestran como comunicados "automáticos".

    Script idempotente: se puede correr más de una vez.
*/

IF OBJECT_ID('dbo.FP_INTRA_COMUNICADOS', 'U') IS NULL
BEGIN

    CREATE TABLE dbo.FP_INTRA_COMUNICADOS (
        id              INT IDENTITY(1, 1) NOT NULL
                        CONSTRAINT PK_FP_INTRA_COMUNICADOS PRIMARY KEY,
        titulo          NVARCHAR(120)   NOT NULL,
        texto           NVARCHAR(2000)  NOT NULL,
        categoria       VARCHAR(10)     NOT NULL,
        link_url        NVARCHAR(500)   NULL,
        link_texto      NVARCHAR(60)    NULL,
        fijado          BIT             NOT NULL
                        CONSTRAINT DF_FP_INTRA_COMUNICADOS_fijado DEFAULT (0),
        vigente_desde   DATE            NOT NULL
                        CONSTRAINT DF_FP_INTRA_COMUNICADOS_vigente_desde DEFAULT (CAST(GETDATE() AS DATE)),
        vigente_hasta   DATE            NULL,
        activo          BIT             NOT NULL
                        CONSTRAINT DF_FP_INTRA_COMUNICADOS_activo DEFAULT (1),
        creado_por      NVARCHAR(100)   NOT NULL,
        creado_en       DATETIME        NOT NULL
                        CONSTRAINT DF_FP_INTRA_COMUNICADOS_creado_en DEFAULT (GETDATE()),
        modificado_por  NVARCHAR(100)   NULL,
        modificado_en   DATETIME        NULL,

        CONSTRAINT CK_FP_INTRA_COMUNICADOS_categoria
            CHECK (categoria IN ('RRHH', 'COMERCIAL', 'SISTEMAS', 'DIRECCION', 'GENERAL')),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_titulo
            CHECK (LEN(LTRIM(RTRIM(titulo))) > 0),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_texto
            CHECK (LEN(LTRIM(RTRIM(texto))) > 0),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_vigencia
            CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_link
            CHECK (link_url IS NULL
                   OR link_url LIKE 'https://%'
                   OR link_url LIKE 'http://%'
                   OR link_url LIKE '/%')
    );

END;
GO

-- Lectura principal: activos ordenados por vigente_desde (más nuevo primero).
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMUNICADOS_activo_desde'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS')
)
BEGIN

    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMUNICADOS_activo_desde
        ON dbo.FP_INTRA_COMUNICADOS (activo, vigente_desde DESC)
        INCLUDE (vigente_hasta, fijado, categoria, titulo);

END;
GO
