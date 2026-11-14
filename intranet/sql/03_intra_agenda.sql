/*
    FP_V_INTRA_AGENDA
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 02_intra_fechas.sql (FP_INTRA_FECHAS) y la tabla existente
              FP_VAC_FERIADOS.
    Usado por recursosHumanos/intranet/Class/Intranet.php (Próximas fechas en el hub, Agenda en la
    intranet y listado de consulta en la carga de fechas).

    PARA QUÉ: una sola agenda con feriados + fechas comerciales y operativas.

    Columnas:
      fecha_desde, fecha_hasta  eventos de un día: hasta = desde (así "próximo"
                                se resuelve siempre con fecha_hasta >= hoy).
      titulo                    descripción del feriado o título de la fecha.
      tipo                      FERIADO | COMERCIAL | OPERATIVA
      detalle                   solo fechas de la intranet (opcional).
      origen                    FERIADOS (FP_VAC_FERIADOS, se cargan por fuera)
                                | INTRANET (FP_INTRA_FECHAS, editables).
      origen_id                 id en la tabla de origen.

    Feriados: se toman los activos. activo vacío (NULL) cuenta como activo.

    Script idempotente: se puede correr más de una vez.
*/

CREATE OR ALTER VIEW dbo.FP_V_INTRA_AGENDA
AS
SELECT
    f.fecha                                 AS fecha_desde,
    f.fecha                                 AS fecha_hasta,
    CAST(f.descripcion AS NVARCHAR(150))    AS titulo,
    CAST('FERIADO' AS VARCHAR(10))          AS tipo,
    CAST(NULL AS NVARCHAR(300))             AS detalle,
    CAST('FERIADOS' AS VARCHAR(10))         AS origen,
    f.id                                    AS origen_id
FROM dbo.FP_VAC_FERIADOS f
WHERE ISNULL(f.activo, 1) = 1

UNION ALL

SELECT
    i.fecha_desde,
    ISNULL(i.fecha_hasta, i.fecha_desde),
    CAST(i.titulo AS NVARCHAR(150)),
    i.tipo,
    i.detalle,
    'INTRANET',
    i.id
FROM dbo.FP_INTRA_FECHAS i
WHERE i.activo = 1;
GO

/*
    CONTROL (solo lectura):

SELECT TOP 10 * FROM dbo.FP_V_INTRA_AGENDA
WHERE fecha_hasta >= CAST(GETDATE() AS DATE)
ORDER BY fecha_desde;
*/
