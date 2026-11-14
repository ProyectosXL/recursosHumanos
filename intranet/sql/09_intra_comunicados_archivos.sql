/*
    FP_INTRA_COMUNICADOS_ARCHIVOS
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 04_intra_comunicados.sql.

    PARA QUÉ: imágenes (hasta 6) y PDF adjuntos (hasta 3) de cada comunicado.
    Los archivos NO se guardan en la base: van a la carpeta INTRANET_ARCHIVOS
    del .env (fuera de la carpeta pública del sitio) con un nombre generado.
    Acá queda el registro: tipo, orden, portada, epígrafe o nombre, nombre
    original (solo para mostrar), rutas relativas a esa carpeta, pesos y medidas.
    Los entrega recursosHumanos/intranet/archivo.php, que controla sesión y permisos.

    Reglas:
      - Recién subido (antes de guardar el comunicado): comunicado_id vacío y
        "lote" con el código del formulario. Así no se pierde si el formulario
        vuelve con errores. Al guardar, pasa a comunicado_id y lote queda vacío.
        Lo que nunca se guardó se borra solo a las 24 horas (fila y archivo).
      - es_portada: solo imágenes, una por comunicado (lo controla la app).
      - epigrafe (imágenes, opcional, 150) / nombre (PDF, obligatorio, 120).
      - peso_original: lo que pesaba al subirlo. peso_final: lo que pesa
        guardado (imágenes comprimidas; en PDF es el mismo).
      - mime: el del archivo guardado (image/webp, image/jpeg, image/png o
        application/pdf). La miniatura usa el mismo.
      - Quitar un archivo de un comunicado es baja lógica (activo = 0, con
        modificado_por / modificado_en); el archivo físico se borra cuando el
        comunicado se guarda sin él.

    La app funciona aunque este script no se haya corrido: no ofrece imágenes,
    adjuntos ni esquemas con imagen.

    Script idempotente: se puede correr más de una vez.
*/

IF OBJECT_ID('dbo.FP_INTRA_COMUNICADOS_ARCHIVOS', 'U') IS NULL
BEGIN

    CREATE TABLE dbo.FP_INTRA_COMUNICADOS_ARCHIVOS (
        id               INT IDENTITY(1, 1) NOT NULL
                         CONSTRAINT PK_FP_INTRA_COMUNICADOS_ARCHIVOS PRIMARY KEY,
        comunicado_id    INT             NULL
                         CONSTRAINT FK_FP_INTRA_COMUNICADOS_ARCHIVOS_comunicado
                             REFERENCES dbo.FP_INTRA_COMUNICADOS (id),
        lote             CHAR(32)        NULL,
        tipo             VARCHAR(6)      NOT NULL,
        orden            INT             NOT NULL
                         CONSTRAINT DF_FP_INTRA_COMUNICADOS_ARCHIVOS_orden DEFAULT (0),
        es_portada       BIT             NOT NULL
                         CONSTRAINT DF_FP_INTRA_COMUNICADOS_ARCHIVOS_es_portada DEFAULT (0),
        epigrafe         NVARCHAR(150)   NULL,
        nombre           NVARCHAR(120)   NULL,
        nombre_original  NVARCHAR(255)   NOT NULL,
        ruta_relativa    NVARCHAR(260)   NOT NULL,
        ruta_miniatura   NVARCHAR(260)   NULL,
        mime             VARCHAR(50)     NOT NULL,
        peso_original    INT             NOT NULL,
        peso_final       INT             NOT NULL,
        ancho            INT             NULL,
        alto             INT             NULL,
        activo           BIT             NOT NULL
                         CONSTRAINT DF_FP_INTRA_COMUNICADOS_ARCHIVOS_activo DEFAULT (1),
        creado_por       NVARCHAR(100)   NOT NULL,
        creado_en        DATETIME        NOT NULL
                         CONSTRAINT DF_FP_INTRA_COMUNICADOS_ARCHIVOS_creado_en DEFAULT (GETDATE()),
        modificado_por   NVARCHAR(100)   NULL,
        modificado_en    DATETIME        NULL,

        CONSTRAINT CK_FP_INTRA_COMUNICADOS_ARCHIVOS_tipo
            CHECK (tipo IN ('IMAGEN', 'PDF')),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_ARCHIVOS_destino
            CHECK (comunicado_id IS NOT NULL OR lote IS NOT NULL),
        CONSTRAINT CK_FP_INTRA_COMUNICADOS_ARCHIVOS_portada
            CHECK (es_portada = 0 OR tipo = 'IMAGEN')
    );

END;
GO

-- Archivos de un comunicado (lectura de la intranet, el hub y la carga)
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMUNICADOS_ARCHIVOS_comunicado'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS_ARCHIVOS')
)
BEGIN

    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMUNICADOS_ARCHIVOS_comunicado
        ON dbo.FP_INTRA_COMUNICADOS_ARCHIVOS (comunicado_id, activo)
        INCLUDE (tipo, orden, es_portada);

END;
GO

-- Archivos recién subidos, todavía sin comunicado (formulario abierto y limpieza)
IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMUNICADOS_ARCHIVOS_lote'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS_ARCHIVOS')
)
BEGIN

    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMUNICADOS_ARCHIVOS_lote
        ON dbo.FP_INTRA_COMUNICADOS_ARCHIVOS (lote, creado_en);

END;
GO

/*
    CONTROL (solo lectura):

SELECT a.id, a.comunicado_id, a.lote, a.tipo, a.orden, a.es_portada,
       a.nombre_original, a.ruta_relativa, a.peso_original, a.peso_final,
       a.ancho, a.alto, a.activo, a.creado_por, a.creado_en
FROM dbo.FP_INTRA_COMUNICADOS_ARCHIVOS a
ORDER BY a.id DESC;
*/
