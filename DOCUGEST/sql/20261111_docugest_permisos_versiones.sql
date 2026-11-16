/* =====================================================================
   DocuGest - Permisos, versiones, bajas y novedades
   Rama: feature/docugest-permisos-versiones

   - Correr en la base CENTRAL (la que usa Conexion->conectar('central')).
   - Idempotente: se puede correr más de una vez sin romper nada.
   - Correrlo ANTES de subir el PHP de esta rama (el PHP usa estas columnas).
   - No borra ni modifica datos existentes, salvo completar
     fecha_ultima_version en los documentos que ya existen.
   ===================================================================== */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   1) Politicas_Procedimientos
   --------------------------------------------------------------------- */

-- Fecha de alta o de la última versión subida (para "Novedades", "Nuevo" y "Actualizado").
-- No se usa fecha_actualizacion porque también cambia con bajas y ediciones de datos.
IF COL_LENGTH('dbo.Politicas_Procedimientos', 'fecha_ultima_version') IS NULL
    ALTER TABLE dbo.Politicas_Procedimientos
        ADD fecha_ultima_version DATETIME NULL
            CONSTRAINT DF_PoliticasProcedimientos_fecha_ultima_version DEFAULT (GETDATE());
GO

-- Baja de documentos (estado = 'inactivo'): cuándo, quién y por qué
IF COL_LENGTH('dbo.Politicas_Procedimientos', 'fecha_baja') IS NULL
    ALTER TABLE dbo.Politicas_Procedimientos ADD fecha_baja DATETIME NULL;
GO

IF COL_LENGTH('dbo.Politicas_Procedimientos', 'usuario_baja') IS NULL
    ALTER TABLE dbo.Politicas_Procedimientos ADD usuario_baja NVARCHAR(100) NULL;
GO

IF COL_LENGTH('dbo.Politicas_Procedimientos', 'motivo_baja') IS NULL
    ALTER TABLE dbo.Politicas_Procedimientos ADD motivo_baja NVARCHAR(500) NULL;
GO

/* ---------------------------------------------------------------------
   2) Historial_Versiones
   --------------------------------------------------------------------- */

-- Tipo de cambio: 'version' (PDF nuevo), 'edicion' (datos), 'baja', 'reactivacion'
IF COL_LENGTH('dbo.Historial_Versiones', 'tipo_cambio') IS NULL
    ALTER TABLE dbo.Historial_Versiones
        ADD tipo_cambio VARCHAR(20) NOT NULL
            CONSTRAINT DF_HistorialVersiones_tipo_cambio DEFAULT ('version');
GO

-- Versión que tenía el documento antes del cambio
IF COL_LENGTH('dbo.Historial_Versiones', 'version_anterior') IS NULL
    ALTER TABLE dbo.Historial_Versiones ADD version_anterior NVARCHAR(20) NULL;
GO

-- Ruta en disco del PDF anterior archivado (documentos/versiones/{id}/...).
-- No se muestra en pantalla ni se sirve por ningún endpoint.
IF COL_LENGTH('dbo.Historial_Versiones', 'ruta_archivo_anterior') IS NULL
    ALTER TABLE dbo.Historial_Versiones ADD ruta_archivo_anterior NVARCHAR(500) NULL;
GO

-- Id del usuario del hub (FP_SOF_USUARIOS.id). El username sigue en la columna usuario.
IF COL_LENGTH('dbo.Historial_Versiones', 'usuario_id') IS NULL
    ALTER TABLE dbo.Historial_Versiones ADD usuario_id INT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_HistorialVersiones_documento_fecha'
                 AND object_id = OBJECT_ID('dbo.Historial_Versiones'))
    CREATE INDEX IX_HistorialVersiones_documento_fecha
        ON dbo.Historial_Versiones (documento_id, fecha DESC);
GO

/* ---------------------------------------------------------------------
   3) Completar fecha_ultima_version de los documentos existentes
      (fecha de la última versión registrada; si no hay, la fecha de alta)
   --------------------------------------------------------------------- */

UPDATE p
   SET p.fecha_ultima_version = COALESCE(h.ultima_version, p.fecha_creacion, p.fecha_actualizacion, GETDATE())
  FROM dbo.Politicas_Procedimientos p
 OUTER APPLY (SELECT MAX(hv.fecha) AS ultima_version
                FROM dbo.Historial_Versiones hv
               WHERE hv.documento_id = p.id
                 AND hv.tipo_cambio = 'version') h
 WHERE p.fecha_ultima_version IS NULL;
GO

/* ---------------------------------------------------------------------
   4) Control (solo lectura)
   --------------------------------------------------------------------- */

SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
  FROM INFORMATION_SCHEMA.COLUMNS
 WHERE (TABLE_NAME = 'Politicas_Procedimientos'
        AND COLUMN_NAME IN ('fecha_ultima_version', 'fecha_baja', 'usuario_baja', 'motivo_baja'))
    OR (TABLE_NAME = 'Historial_Versiones'
        AND COLUMN_NAME IN ('tipo_cambio', 'version_anterior', 'ruta_archivo_anterior', 'usuario_id'))
 ORDER BY TABLE_NAME, COLUMN_NAME;
GO
