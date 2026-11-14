/*
    FP_INTRA_COMUNICADOS - Comunicados v2
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 04_intra_comunicados.sql (y conviene tener corrido el 07).

    PARA QUÉ: esquemas con imagen, texto con formato, borradores y aviso
    emergente al entrar al hub.

    Columnas nuevas:
      esquema          dónde van las imágenes: SIN_IMAGEN (como hoy), ARRIBA,
                       IZQUIERDA, DERECHA, CENTRO, ABAJO, FLYER.
                       Los existentes quedan en SIN_IMAGEN.
      texto_inferior   texto después de la imagen (solo esquema CENTRO).
      estado           BORRADOR o PUBLICADO. Los existentes quedan en PUBLICADO.
                       Un borrador nunca se muestra. Es independiente de
                       "activo" y de la vigencia.
      aviso_emergente  1 = se muestra en una ventana al entrar al hub.
      aviso_hasta      último día del aviso (vacío = mientras esté vigente).
      con_formato      1 = el texto tiene marcas de formato (**negrita**,
                       *cursiva*, listas, links). Los existentes quedan en 0 y
                       se siguen mostrando como texto plano, igual que hoy.

    Cambios en columnas existentes:
      texto            pasa de NVARCHAR(2000) a NVARCHAR(4000) y se quita el
                       CHECK que lo exigía no vacío: con FLYER y con borradores
                       puede quedar vacío. La regla vive en la app
                       (IntranetAdmin::validarComunicado).

      categoria        admite vacío: un borrador puede guardarse sin categoría.
                       Para publicar, la app la sigue exigiendo. El CHECK de
                       valores se mantiene. Para cambiarla hay que quitar y
                       volver a crear su CHECK y el índice que la incluye.

    La app funciona aunque este script no se haya corrido: no ofrece esquemas,
    formato, borradores ni aviso, y el texto sigue limitado a 2000.
    Si se agrega un esquema nuevo, hay que sumarlo al CHECK y a
    Intranet::ESQUEMAS (recursosHumanos/intranet/Class/Intranet.php).

    Script idempotente: se puede correr más de una vez.
*/

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'esquema') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD esquema VARCHAR(12) NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_esquema DEFAULT ('SIN_IMAGEN')
            CONSTRAINT CK_FP_INTRA_COMUNICADOS_esquema
                CHECK (esquema IN ('SIN_IMAGEN', 'ARRIBA', 'IZQUIERDA', 'DERECHA', 'CENTRO', 'ABAJO', 'FLYER'));
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'texto_inferior') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD texto_inferior NVARCHAR(4000) NULL;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'estado') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD estado VARCHAR(10) NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_estado DEFAULT ('PUBLICADO')
            CONSTRAINT CK_FP_INTRA_COMUNICADOS_estado
                CHECK (estado IN ('BORRADOR', 'PUBLICADO'));
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'aviso_emergente') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD aviso_emergente BIT NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_aviso_emergente DEFAULT (0);
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'aviso_hasta') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD aviso_hasta DATE NULL;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'con_formato') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD con_formato BIT NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_con_formato DEFAULT (0);
END;
GO

-- texto: puede quedar vacío (FLYER y borradores). La regla pasa a la app.
IF OBJECT_ID('dbo.CK_FP_INTRA_COMUNICADOS_texto', 'C') IS NOT NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        DROP CONSTRAINT CK_FP_INTRA_COMUNICADOS_texto;
END;
GO

-- texto: hasta 4000 caracteres (max_length está en bytes: 4000 caracteres = 8000)
IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS')
      AND name = 'texto'
      AND max_length < 8000
)
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ALTER COLUMN texto NVARCHAR(4000) NOT NULL;
END;
GO

-- categoria: admite vacío (borradores). Se quitan y se vuelven a crear su CHECK y el índice
IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS')
      AND name = 'categoria'
      AND is_nullable = 0
)
BEGIN
    IF OBJECT_ID('dbo.CK_FP_INTRA_COMUNICADOS_categoria', 'C') IS NOT NULL
        ALTER TABLE dbo.FP_INTRA_COMUNICADOS DROP CONSTRAINT CK_FP_INTRA_COMUNICADOS_categoria;

    IF EXISTS (
        SELECT 1 FROM sys.indexes
        WHERE name = 'IX_FP_INTRA_COMUNICADOS_activo_desde'
          AND object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS')
    )
        DROP INDEX IX_FP_INTRA_COMUNICADOS_activo_desde ON dbo.FP_INTRA_COMUNICADOS;

    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ALTER COLUMN categoria VARCHAR(10) NULL;
END;
GO

IF OBJECT_ID('dbo.CK_FP_INTRA_COMUNICADOS_categoria', 'C') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD CONSTRAINT CK_FP_INTRA_COMUNICADOS_categoria
            CHECK (categoria IN ('RRHH', 'COMERCIAL', 'SISTEMAS', 'DIRECCION', 'GENERAL'));
END;
GO

-- Mismo índice que en 04 (se recrea si se quitó arriba)
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

/*
    CONTROL (solo lectura):

SELECT id, titulo, esquema, estado, aviso_emergente, aviso_hasta, con_formato,
       LEN(texto) AS largo_texto, LEN(texto_inferior) AS largo_inferior
FROM dbo.FP_INTRA_COMUNICADOS
ORDER BY id DESC;
*/
