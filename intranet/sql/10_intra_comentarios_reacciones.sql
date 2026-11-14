/*
    Comentarios y reacciones de los comunicados
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 04_intra_comunicados.sql (y conviene tener corridos el 07, 08 y 09).

    PARA QUÉ: que el personal comente y reaccione en los comunicados que lo tengan habilitado.

    Columnas nuevas en FP_INTRA_COMUNICADOS:
      permite_comentarios  1 = se puede comentar. Los existentes quedan en 0.
      permite_reacciones   1 = se puede reaccionar. Los existentes quedan en 0.
      Si se desmarcan en un comunicado que ya tenía comentarios o reacciones, la
      app los oculta pero NO los borra: si se vuelven a marcar, reaparecen.

    FP_INTRA_COMENTARIOS: un comentario por fila.
      usuario          username de quien comentó (en un local, el usuario compartido).
      nombre_mostrado  el nombre que se mostró al comentar (persona o local). Se guarda
                       para que no cambie si después se modifica el usuario.
      texto            texto plano, hasta 500 caracteres. Nunca se interpreta HTML.
      borrado          baja lógica hecha por el autor (borrado_en: cuándo). No se ve más.
      oculto           lo ocultó un responsable de carga (intranet.comunicados.publicar):
                       oculto_por / oculto_por_nombre / oculto_en registran quién y cuándo.
                       mostrado_por / mostrado_en: último "Mostrar de nuevo".
      Los comentarios no se editan.

    FP_INTRA_REACCIONES: una sola fila por comunicado y usuario (la clave lo garantiza).
      tipo             ME_GUSTA, APLAUSOS o ME_ENCANTA. Tocar la misma reacción la borra;
                       tocar otra la cambia (modificado_en).
      nombre_mostrado  el nombre con el que aparece en "Ver quiénes reaccionaron" (persona
                       o local), guardado al reaccionar, igual que en los comentarios.

    La app funciona aunque este script no se haya corrido: no ofrece las casillas ni
    muestra el bloque de comentarios y reacciones.

    Script idempotente: se puede correr más de una vez.
*/

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'permite_comentarios') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD permite_comentarios BIT NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_permite_comentarios DEFAULT (0);
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'permite_reacciones') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD permite_reacciones BIT NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_permite_reacciones DEFAULT (0);
END;
GO

IF OBJECT_ID('dbo.FP_INTRA_COMENTARIOS', 'U') IS NULL
BEGIN

    CREATE TABLE dbo.FP_INTRA_COMENTARIOS (
        id                 INT IDENTITY(1, 1) NOT NULL
                           CONSTRAINT PK_FP_INTRA_COMENTARIOS PRIMARY KEY,
        comunicado_id      INT             NOT NULL
                           CONSTRAINT FK_FP_INTRA_COMENTARIOS_comunicado
                               REFERENCES dbo.FP_INTRA_COMUNICADOS (id),
        usuario            NVARCHAR(100)   NOT NULL,
        nombre_mostrado    NVARCHAR(150)   NOT NULL,
        texto              NVARCHAR(500)   NOT NULL,
        creado_en          DATETIME        NOT NULL
                           CONSTRAINT DF_FP_INTRA_COMENTARIOS_creado_en DEFAULT (GETDATE()),
        borrado            BIT             NOT NULL
                           CONSTRAINT DF_FP_INTRA_COMENTARIOS_borrado DEFAULT (0),
        borrado_en         DATETIME        NULL,
        oculto             BIT             NOT NULL
                           CONSTRAINT DF_FP_INTRA_COMENTARIOS_oculto DEFAULT (0),
        oculto_por         NVARCHAR(100)   NULL,
        oculto_por_nombre  NVARCHAR(150)   NULL,
        oculto_en          DATETIME        NULL,
        mostrado_por       NVARCHAR(100)   NULL,
        mostrado_en        DATETIME        NULL,

        CONSTRAINT CK_FP_INTRA_COMENTARIOS_texto
            CHECK (LEN(LTRIM(RTRIM(texto))) > 0)
    );

END;
GO

-- Comentarios de un comunicado, en orden (intranet y contadores)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMENTARIOS_comunicado'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMENTARIOS')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMENTARIOS_comunicado
        ON dbo.FP_INTRA_COMENTARIOS (comunicado_id, borrado, creado_en)
        INCLUDE (oculto);
END;
GO

-- Último comentario de un usuario (no más de uno cada 10 segundos)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMENTARIOS_usuario'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMENTARIOS')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMENTARIOS_usuario
        ON dbo.FP_INTRA_COMENTARIOS (usuario, creado_en DESC);
END;
GO

IF OBJECT_ID('dbo.FP_INTRA_REACCIONES', 'U') IS NULL
BEGIN

    CREATE TABLE dbo.FP_INTRA_REACCIONES (
        comunicado_id  INT             NOT NULL
                       CONSTRAINT FK_FP_INTRA_REACCIONES_comunicado
                           REFERENCES dbo.FP_INTRA_COMUNICADOS (id),
        usuario        NVARCHAR(100)   NOT NULL,
        nombre_mostrado NVARCHAR(150)  NULL,
        tipo           VARCHAR(10)     NOT NULL,
        creado_en      DATETIME        NOT NULL
                       CONSTRAINT DF_FP_INTRA_REACCIONES_creado_en DEFAULT (GETDATE()),
        modificado_en  DATETIME        NULL,

        CONSTRAINT PK_FP_INTRA_REACCIONES PRIMARY KEY (comunicado_id, usuario),
        CONSTRAINT CK_FP_INTRA_REACCIONES_tipo
            CHECK (tipo IN ('ME_GUSTA', 'APLAUSOS', 'ME_ENCANTA'))
    );

END;
GO

-- Por si la tabla se creó con una versión anterior de este script (sin esta columna la app funciona
-- igual, pero "Ver quiénes reaccionaron" muestra el nombre actual del usuario, no el guardado)
IF COL_LENGTH('dbo.FP_INTRA_REACCIONES', 'nombre_mostrado') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_REACCIONES
        ADD nombre_mostrado NVARCHAR(150) NULL;
END;
GO

/*
    CONTROL (solo lectura):

SELECT id, titulo, permite_comentarios, permite_reacciones
FROM dbo.FP_INTRA_COMUNICADOS ORDER BY id DESC;

SELECT TOP 50 * FROM dbo.FP_INTRA_COMENTARIOS ORDER BY id DESC;

SELECT comunicado_id, tipo, COUNT(*) AS cantidad
FROM dbo.FP_INTRA_REACCIONES GROUP BY comunicado_id, tipo ORDER BY comunicado_id, tipo;
*/
