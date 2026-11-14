/*
    Estructura XL - 01: clave de las personas de Internos
    Base: CENTRAL (la de conectar('central'), donde está RO_T_INTERNOS_PERSONAL).
    Orden: 1 de 4. Se corre antes que 02, 03 y 04.

    Por qué existe: las fichas del Directorio guardan referentes, gerentes y jefes. Si el vínculo
    fuera por NOMBRE_APELLIDO (como identifica hoy el ABM de Internos), se rompería al corregir
    un nombre. Las tablas nuevas guardan el ID de la persona.

    Relevado el 14/11/2026: RO_T_INTERNOS_PERSONAL YA TIENE la columna ID INT IDENTITY, que es
    su clave primaria (PK clustered). Por eso este script NO modifica la tabla: sólo verifica
    que la clave siga estando y corta si no. Hay un objeto que depende de la tabla
    (RO_USUARIOS_CARGA_PROYECTOS), otra razón para no tocarla.

    Solo lectura. Se puede correr las veces que haga falta.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID('dbo.RO_T_INTERNOS_PERSONAL', 'U') IS NULL
BEGIN
    RAISERROR('No existe dbo.RO_T_INTERNOS_PERSONAL en esta base: ¿es la base central?', 16, 1);
    RETURN;
END;

IF COLUMNPROPERTY(OBJECT_ID('dbo.RO_T_INTERNOS_PERSONAL'), 'ID', 'IsIdentity') IS NULL
   OR COLUMNPROPERTY(OBJECT_ID('dbo.RO_T_INTERNOS_PERSONAL'), 'ID', 'IsIdentity') <> 1
BEGIN
    RAISERROR('RO_T_INTERNOS_PERSONAL no tiene la columna ID IDENTITY: avisar a Innovación antes de seguir.', 16, 1);
    RETURN;
END;

IF NOT EXISTS (
    SELECT 1
      FROM sys.indexes i
      JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
     WHERE i.object_id = OBJECT_ID('dbo.RO_T_INTERNOS_PERSONAL')
       AND i.is_unique = 1
       AND COL_NAME(ic.object_id, ic.column_id) = 'ID'
)
BEGIN
    RAISERROR('RO_T_INTERNOS_PERSONAL.ID no tiene un índice único: avisar a Innovación antes de seguir.', 16, 1);
    RETURN;
END;

PRINT 'OK: RO_T_INTERNOS_PERSONAL.ID es IDENTITY y único. Se puede seguir con 02_directorio_tablas.sql.';
GO
