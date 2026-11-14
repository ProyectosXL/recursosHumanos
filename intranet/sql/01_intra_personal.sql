/*
    FP_V_INTRA_PERSONAL (+ función FP_FN_INTRA_NOMBRE_PROPIO)
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Fuente: [XL-SUELDOS].LAKERS_CORP_SA.dbo.RO_LEGAJOS_PERSONAL_ALL
            (servidor vinculado desde XL-APPS).
    Usado por recursosHumanos/intranet/Class/Intranet.php (cumpleaños, bienvenidas y aniversarios
    en el hub y en recursosHumanos/intranet/index.php). La app lee SIEMPRE esta vista y
    nunca la de Sueldos: si la fuente cambia, solo se toca este script.

    PRIVACIDAD (no negociable): la vista NO expone el año de nacimiento ni la
    fecha de nacimiento completa. Solo día y mes, por separado.

    Reglas:
      - Solo activos: HABILITADO = 'S'.
      - La vista de Sueldos puede traer más de una fila por legajo (reingresos,
        con distinta FECHA_INGRESO). Se queda una fila por legajo: la de la
        FECHA_INGRESO más reciente (sector vigente), pero fecha_ingreso es la
        del PRIMER ingreso: los años en XL se cuentan desde ahí (definido con
        el negocio el 11/11/2026). Un reingreso de este mes no aparece en
        "Bienvenidas" si ya había ingresado antes.
      - Fechas vacías o de relleno (1900-01-01 o anteriores) se tratan como
        vacías: esa persona no aparece en el bloque que necesita esa fecha,
        pero sí en los demás.
      - Nombre y sector vienen en mayúsculas desde Sueldos; se pasan a
        "Nombre Propio" con FP_FN_INTRA_NOMBRE_PROPIO. Sueldos no guarda
        tildes, así que tampoco las va a haber acá ("Comercializacion").

    Script idempotente: se puede correr más de una vez.
*/

CREATE OR ALTER FUNCTION dbo.FP_FN_INTRA_NOMBRE_PROPIO (@texto NVARCHAR(200))
RETURNS NVARCHAR(200)
AS
BEGIN
    -- "JUAN PABLO  PEREZ-GOMEZ" -> "Juan Pablo Perez-Gomez"
    DECLARE @s NVARCHAR(200) = LOWER(LTRIM(RTRIM(ISNULL(@texto, N''))));

    WHILE CHARINDEX(N'  ', @s) > 0
        SET @s = REPLACE(@s, N'  ', N' ');

    DECLARE @i INT = 1,
            @largo INT = LEN(@s),
            @anterior NCHAR(1) = N' ';

    WHILE @i <= @largo
    BEGIN
        IF @anterior IN (N' ', N'-', N'''', N'.')
            SET @s = STUFF(@s, @i, 1, UPPER(SUBSTRING(@s, @i, 1)));

        SET @anterior = SUBSTRING(@s, @i, 1);
        SET @i = @i + 1;
    END;

    RETURN NULLIF(@s, N'');
END;
GO

CREATE OR ALTER VIEW dbo.FP_V_INTRA_PERSONAL
AS
WITH legajos AS (
    SELECT
        l.NRO_LEGAJO,
        LTRIM(RTRIM(ISNULL(l.NOMBRE, '')))   AS nombre,
        LTRIM(RTRIM(ISNULL(l.APELLIDO, ''))) AS apellido,
        l.DESC_DEPARTAMENTO,
        CASE WHEN l.FECHA_NACIMIENTO > '19000101' THEN l.FECHA_NACIMIENTO END AS fecha_nac,
        -- Primer ingreso del legajo (MIN ignora las fechas vacías o de relleno)
        MIN(CASE WHEN l.FECHA_INGRESO > '19000101' THEN l.FECHA_INGRESO END)
            OVER (PARTITION BY l.NRO_LEGAJO) AS fecha_ing,
        ROW_NUMBER() OVER (PARTITION BY l.NRO_LEGAJO
                           ORDER BY l.FECHA_INGRESO DESC) AS fila
    FROM [XL-SUELDOS].LAKERS_CORP_SA.dbo.RO_LEGAJOS_PERSONAL_ALL l
    WHERE l.HABILITADO = 'S'
)
SELECT
    NRO_LEGAJO                                                     AS legajo,
    dbo.FP_FN_INTRA_NOMBRE_PROPIO(nombre + N' ' + apellido)        AS nombre_mostrar,
    -- Primer nombre, para avisos cortos ("Hoy cumple Lucía")
    dbo.FP_FN_INTRA_NOMBRE_PROPIO(LEFT(nombre, CHARINDEX(' ', nombre + ' ') - 1)) AS nombre_pila,
    dbo.FP_FN_INTRA_NOMBRE_PROPIO(DESC_DEPARTAMENTO)               AS sector,
    DAY(fecha_nac)                                                 AS nacimiento_dia,
    MONTH(fecha_nac)                                               AS nacimiento_mes,
    CAST(fecha_ing AS DATE)                                        AS fecha_ingreso
FROM legajos
WHERE fila = 1
  AND nombre + apellido <> '';
GO

/*
    CONTROL (solo lectura). Correr después del script:

SELECT COUNT(*) AS activos,
       SUM(CASE WHEN nacimiento_dia IS NULL THEN 1 ELSE 0 END) AS sin_nacimiento,
       SUM(CASE WHEN fecha_ingreso  IS NULL THEN 1 ELSE 0 END) AS sin_ingreso
FROM dbo.FP_V_INTRA_PERSONAL;

SELECT TOP 10 * FROM dbo.FP_V_INTRA_PERSONAL ORDER BY nacimiento_mes, nacimiento_dia;
*/
