/*
    Estructura XL - 02: tablas del Directorio (Guía de Consultas y Contactos por área)
    Base: CENTRAL (junto a RO_T_INTERNOS_PERSONAL, porque las fichas apuntan a sus personas).
    Orden: 2 de 4. Después de 01_internos_id.sql y antes de 03_directorio_carga_inicial.sql.

    Crea:
      dbo.RO_FN_DIR_CLAVE              texto → clave de comparación (ver abajo)
      dbo.RO_T_DIR_AREAS               áreas: qué resuelven, mail, celular y horario del sector,
                                       gerente y jefe
      dbo.RO_T_DIR_TEMAS               temas de cada área, con su referente y palabras clave
      dbo.RO_T_DIR_BUSQUEDAS           registro de lo que se busca en la Guía (base del chatbot)
      dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS  búsquedas sin resultado descartadas del panel
      dbo.RO_T_DIR_IMPORTACIONES       registro de cada importación de Excel

    Reglas que viven en la base (además de en la app, que es la que explica el motivo):
      - No hay bajas físicas: ACTIVA / ACTIVO = 0 es la baja, con USUARIO_BAJA y FECHA_BAJA.
      - El nombre de un área es único entre las activas, sin distinguir mayúsculas ni tildes
        ("Logistica" = "Logística"); el tema, único dentro de su área entre los activos.
        Se resuelve con columnas calculadas NOMBRE_CLAVE / TEMA_CLAVE = RO_FN_DIR_CLAVE(...)
        y un índice único filtrado.
      - Un referente es una persona de Internos O "el equipo del área", nunca las dos (CHECK).
      - Las personas se guardan por ID de RO_T_INTERNOS_PERSONAL, nunca por nombre. Sin FK a
        Internos a propósito: no queremos que otra app no pueda borrar una fila de Internos por
        culpa del Directorio. La app trata un ID que no existe o está inactivo como "A definir".
      - RO_T_DIR_BUSQUEDAS no se edita nunca (es un registro): descartar una búsqueda del panel
        se guarda aparte, en RO_T_DIR_BUSQUEDAS_DESCARTADAS.

    Script idempotente: se puede correr más de una vez. Crea lo que falta y no toca datos.
*/

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;   -- obligatorio para índices filtrados y columnas calculadas indexadas
GO

/*
    Clave de comparación de nombres: minúsculas, sin tildes, todo lo que no es letra o número
    pasa a ser un espacio, y espacios simples sin bordes. "Cajas / POS" → "cajas pos".
    ES EL MISMO ALGORITMO que Reglas::clave() en estructura/Class/Reglas.php (la app compara con
    esa; la base usa esta para el índice único y para la carga inicial). Si se cambia una, se
    cambia la otra. La tabla de tildes es la misma (Reglas::TILDES).

    Se crea sólo si no existe (y no con CREATE OR ALTER): una vez que la usan las columnas
    calculadas PERSISTED, SQL Server no deja modificarla, y el script dejaría de ser reejecutable.
    Para cambiarla hay que sacar antes los índices y las columnas calculadas que la usan.
*/
IF OBJECT_ID('dbo.RO_FN_DIR_CLAVE', 'FN') IS NULL
    EXEC (N'
CREATE FUNCTION dbo.RO_FN_DIR_CLAVE (@texto NVARCHAR(400))
RETURNS NVARCHAR(400)
WITH SCHEMABINDING
AS
BEGIN
    DECLARE @desde NVARCHAR(40) = N''áàâäãåéèêëíìîïóòôöõúùûüñç'';
    DECLARE @hasta NVARCHAR(40) = N''aaaaaaeeeeiiiiooooouuuunc'';
    DECLARE @s NVARCHAR(400) = LOWER(ISNULL(@texto, N''''));
    DECLARE @r NVARCHAR(400) = N'''';
    DECLARE @i INT = 1, @n INT = LEN(@s + N''x'') - 1;   -- LEN ignora espacios finales
    DECLARE @c NCHAR(1), @p INT, @u INT, @espacio BIT = 0;

    WHILE @i <= @n
    BEGIN
        SET @c = SUBSTRING(@s, @i, 1);
        -- Collation binaria: con la de la base (CI_AI) "a" encontraría "á" en la primera posición
        SET @p = CHARINDEX(@c COLLATE Latin1_General_BIN2, @desde COLLATE Latin1_General_BIN2);
        IF @p > 0 SET @c = SUBSTRING(@hasta, @p, 1);
        SET @u = UNICODE(@c);
        IF (@u BETWEEN 97 AND 122) OR (@u BETWEEN 48 AND 57)
        BEGIN
            IF @espacio = 1 AND LEN(@r) > 0 SET @r = @r + N'' '';
            SET @r = @r + @c;
            SET @espacio = 0;
        END
        ELSE
            SET @espacio = 1;
        SET @i = @i + 1;
    END;

    RETURN @r;
END;');
GO

/* ---------------------------------------------------------------- Áreas */
IF OBJECT_ID('dbo.RO_T_DIR_AREAS', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.RO_T_DIR_AREAS (
        ID              INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_RO_T_DIR_AREAS PRIMARY KEY,
        NOMBRE          NVARCHAR(100) NOT NULL,
        NOMBRE_CLAVE    AS dbo.RO_FN_DIR_CLAVE(NOMBRE) PERSISTED,
        COLOR           CHAR(7)       NOT NULL,            -- #RRGGBB
        ORDEN           INT           NOT NULL,
        RESUELVE        NVARCHAR(200) NULL,                -- una línea: qué resuelve el área
        MAIL_SECTOR     NVARCHAR(150) NULL,
        CELULAR_SECTOR  VARCHAR(10)   NULL,                -- sólo dígitos: código de área + número, sin 0 ni 15
        HORARIO         NVARCHAR(100) NULL,
        ID_GERENTE      INT           NULL,                -- RO_T_INTERNOS_PERSONAL.ID
        ID_JEFE         INT           NULL,                -- RO_T_INTERNOS_PERSONAL.ID
        ACTIVA          BIT           NOT NULL CONSTRAINT DF_RO_T_DIR_AREAS_ACTIVA DEFAULT (1),
        USUARIO_ALTA    NVARCHAR(100) NOT NULL,
        FECHA_ALTA      DATETIME      NOT NULL CONSTRAINT DF_RO_T_DIR_AREAS_FECHA_ALTA DEFAULT (GETDATE()),
        USUARIO_MODIF   NVARCHAR(100) NULL,
        FECHA_MODIF     DATETIME      NULL,
        USUARIO_BAJA    NVARCHAR(100) NULL,
        FECHA_BAJA      DATETIME      NULL,
        CONSTRAINT CK_RO_T_DIR_AREAS_COLOR CHECK (COLOR LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]'),
        CONSTRAINT CK_RO_T_DIR_AREAS_CELULAR CHECK (CELULAR_SECTOR IS NULL OR (LEN(CELULAR_SECTOR) = 10 AND CELULAR_SECTOR NOT LIKE '%[^0-9]%'))
    );
    PRINT 'Creada dbo.RO_T_DIR_AREAS';
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_RO_T_DIR_AREAS_NOMBRE' AND object_id = OBJECT_ID('dbo.RO_T_DIR_AREAS'))
    CREATE UNIQUE INDEX UX_RO_T_DIR_AREAS_NOMBRE ON dbo.RO_T_DIR_AREAS (NOMBRE_CLAVE) WHERE ACTIVA = 1;
GO

/* ---------------------------------------------------------------- Temas */
IF OBJECT_ID('dbo.RO_T_DIR_TEMAS', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.RO_T_DIR_TEMAS (
        ID                INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_RO_T_DIR_TEMAS PRIMARY KEY,
        ID_AREA           INT           NOT NULL CONSTRAINT FK_RO_T_DIR_TEMAS_AREA REFERENCES dbo.RO_T_DIR_AREAS (ID),
        TEMA              NVARCHAR(150) NOT NULL,
        TEMA_CLAVE        AS dbo.RO_FN_DIR_CLAVE(TEMA) PERSISTED,
        ORDEN             INT           NOT NULL,
        ID_REFERENTE      INT           NULL,              -- RO_T_INTERNOS_PERSONAL.ID
        REFERENTE_EQUIPO  BIT           NOT NULL CONSTRAINT DF_RO_T_DIR_TEMAS_EQUIPO DEFAULT (0),
        PALABRAS_CLAVE    NVARCHAR(500) NULL,              -- separadas por coma
        ACTIVO            BIT           NOT NULL CONSTRAINT DF_RO_T_DIR_TEMAS_ACTIVO DEFAULT (1),
        USUARIO_ALTA      NVARCHAR(100) NOT NULL,
        FECHA_ALTA        DATETIME      NOT NULL CONSTRAINT DF_RO_T_DIR_TEMAS_FECHA_ALTA DEFAULT (GETDATE()),
        USUARIO_MODIF     NVARCHAR(100) NULL,
        FECHA_MODIF       DATETIME      NULL,
        USUARIO_BAJA      NVARCHAR(100) NULL,
        FECHA_BAJA        DATETIME      NULL,
        -- Persona o equipo, nunca las dos (sin ninguna: "A definir")
        CONSTRAINT CK_RO_T_DIR_TEMAS_REFERENTE CHECK (NOT (ID_REFERENTE IS NOT NULL AND REFERENTE_EQUIPO = 1))
    );
    PRINT 'Creada dbo.RO_T_DIR_TEMAS';
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_RO_T_DIR_TEMAS_TEMA' AND object_id = OBJECT_ID('dbo.RO_T_DIR_TEMAS'))
    CREATE UNIQUE INDEX UX_RO_T_DIR_TEMAS_TEMA ON dbo.RO_T_DIR_TEMAS (ID_AREA, TEMA_CLAVE) WHERE ACTIVO = 1;
GO

/* ---------------------------------------------------------------- Búsquedas */
IF OBJECT_ID('dbo.RO_T_DIR_BUSQUEDAS', 'U') IS NULL
BEGIN
    -- Registro: se inserta y no se edita. Sin columnas de modificación ni de baja a propósito.
    CREATE TABLE dbo.RO_T_DIR_BUSQUEDAS (
        ID                 INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_RO_T_DIR_BUSQUEDAS PRIMARY KEY,
        TEXTO              NVARCHAR(200) NOT NULL,         -- como lo escribió el usuario
        TEXTO_NORMALIZADO  NVARCHAR(200) NOT NULL,         -- Reglas::normalizarBusqueda()
        CANT_RESULTADOS    INT           NOT NULL,
        ID_AREA_FILTRO     INT           NULL,             -- el chip de área elegido, si había
        USUARIO            NVARCHAR(100) NULL,             -- sesión de XL Gestión, si había
        COD_CLIENT         VARCHAR(20)   NULL,             -- $_SESSION['codClient'] de XL Gestión
        FECHA              DATETIME      NOT NULL CONSTRAINT DF_RO_T_DIR_BUSQUEDAS_FECHA DEFAULT (GETDATE())
    );
    PRINT 'Creada dbo.RO_T_DIR_BUSQUEDAS';
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RO_T_DIR_BUSQUEDAS_FECHA' AND object_id = OBJECT_ID('dbo.RO_T_DIR_BUSQUEDAS'))
    CREATE INDEX IX_RO_T_DIR_BUSQUEDAS_FECHA ON dbo.RO_T_DIR_BUSQUEDAS (FECHA) INCLUDE (CANT_RESULTADOS, TEXTO_NORMALIZADO, TEXTO);
GO

/* ---------------------------------------------------------------- Búsquedas descartadas */
IF OBJECT_ID('dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS', 'U') IS NULL
BEGIN
    -- Una fila por texto normalizado. El panel "Búsquedas sin resultado" esconde las búsquedas
    -- de ese texto hechas hasta FECHA_DESCARTE; si alguien lo vuelve a buscar después, reaparece
    -- (es una señal nueva). Volver a descartar actualiza FECHA_DESCARTE.
    CREATE TABLE dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS (
        ID                 INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_RO_T_DIR_BUSQUEDAS_DESCARTADAS PRIMARY KEY,
        TEXTO_NORMALIZADO  NVARCHAR(200) NOT NULL CONSTRAINT UX_RO_T_DIR_BUSQUEDAS_DESCARTADAS UNIQUE,
        FECHA_DESCARTE     DATETIME      NOT NULL,
        USUARIO_ALTA       NVARCHAR(100) NOT NULL,
        FECHA_ALTA         DATETIME      NOT NULL CONSTRAINT DF_RO_T_DIR_BUSQ_DESC_FECHA_ALTA DEFAULT (GETDATE()),
        USUARIO_MODIF      NVARCHAR(100) NULL,
        FECHA_MODIF        DATETIME      NULL,
        USUARIO_BAJA       NVARCHAR(100) NULL,
        FECHA_BAJA         DATETIME      NULL
    );
    PRINT 'Creada dbo.RO_T_DIR_BUSQUEDAS_DESCARTADAS';
END;
GO

/* ---------------------------------------------------------------- Importaciones */
IF OBJECT_ID('dbo.RO_T_DIR_IMPORTACIONES', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.RO_T_DIR_IMPORTACIONES (
        ID                  INT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_RO_T_DIR_IMPORTACIONES PRIMARY KEY,
        ARCHIVO             NVARCHAR(255) NOT NULL,
        AREAS_CREADAS       INT           NOT NULL,
        AREAS_ACTUALIZADAS  INT           NOT NULL,
        TEMAS_CREADOS       INT           NOT NULL,
        TEMAS_ACTUALIZADOS  INT           NOT NULL,
        FILAS_OMITIDAS      INT           NOT NULL,
        AVISOS              INT           NOT NULL,
        DETALLE             NVARCHAR(MAX) NULL,            -- JSON con los cambios y los avisos
        USUARIO_ALTA        NVARCHAR(100) NOT NULL,        -- quién importó
        FECHA_ALTA          DATETIME      NOT NULL CONSTRAINT DF_RO_T_DIR_IMPORTACIONES_FECHA_ALTA DEFAULT (GETDATE()),
        USUARIO_MODIF       NVARCHAR(100) NULL,
        FECHA_MODIF         DATETIME      NULL,
        USUARIO_BAJA        NVARCHAR(100) NULL,
        FECHA_BAJA          DATETIME      NULL
    );
    PRINT 'Creada dbo.RO_T_DIR_IMPORTACIONES';
END;
GO

PRINT 'OK: tablas del Directorio listas. Seguir con 03_directorio_carga_inicial.sql.';
GO

/*
    CONTROL (solo lectura):

SELECT name, create_date FROM sys.objects WHERE name LIKE 'RO_T_DIR%' OR name = 'RO_FN_DIR_CLAVE';
SELECT dbo.RO_FN_DIR_CLAVE(N'  Logística / Envíos ') AS clave;   -- "logistica envios"
*/
