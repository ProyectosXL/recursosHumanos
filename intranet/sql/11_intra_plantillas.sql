/*
    Plantillas de comunicado
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 06, 07, 08 y 10 (permisos, estilo, comunicados v2 y comentarios/reacciones).
    Si falta alguno, el script avisa y no hace nada.

    PARA QUÉ: modelos de comunicado listos para usar ("Apertura de local", "Bienvenida"...).
    Una plantilla es una fila de FP_INTRA_COMUNICADOS marcada como plantilla: mismos campos
    (categoría, estilo, esquema, textos con formato, imágenes y PDF, link, aviso, comentarios y
    reacciones) más un nombre, una descripción y un orden.

    Columnas nuevas en FP_INTRA_COMUNICADOS:
      es_plantilla           1 = plantilla. NUNCA se publica: la intranet, el hub, el aviso y
                             el listado de comunicados las excluyen. Además se guardan con
                             estado BORRADOR, como segunda barrera. No usan vigencia.
      plantilla_nombre       nombre que ve el responsable al elegirla (hasta 80).
      plantilla_descripcion  cuándo usarla (hasta 200).
      plantilla_orden        orden en que se ofrecen (1, 2, 3...).
      plantilla_origen_id    en un comunicado: de qué plantilla salió (para la leyenda
                             "Basado en la plantilla"). Vacío si se empezó de cero.
      "activo" indica si la plantilla se ofrece (activa) o no (inactiva).

    Permiso nuevo (FP_PERMISOS, módulo INTRANET, mismo criterio que el 06):
      intranet.plantillas.administrar  alta, edición, orden y baja de plantillas.
      No se asigna a ningún rol: eso se hace desde Gestionusuarios.

    Plantillas iniciales: 9, activas y en orden. Las de esquema con imagen se cargan sin
    imágenes (se agregan al usarlas). Los datos a completar van entre corchetes: [Nombre].
    Si ya existe una plantilla con el mismo nombre, no se vuelve a cargar ni se pisa.

    Script idempotente: se puede correr más de una vez.
*/

SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'estilo') IS NULL
   OR COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'esquema') IS NULL
   OR COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'permite_comentarios') IS NULL
   OR NOT EXISTS (SELECT 1 FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET')
BEGIN
    RAISERROR('Antes de este script hay que correr 06, 07, 08 y 10. No se hizo ningún cambio.', 16, 1);
    SET NOEXEC ON;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'es_plantilla') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD es_plantilla BIT NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_es_plantilla DEFAULT (0);
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'plantilla_nombre') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD plantilla_nombre NVARCHAR(80) NULL;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'plantilla_descripcion') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD plantilla_descripcion NVARCHAR(200) NULL;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'plantilla_orden') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD plantilla_orden INT NULL;
END;
GO

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'plantilla_origen_id') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD plantilla_origen_id INT NULL
            CONSTRAINT FK_FP_INTRA_COMUNICADOS_plantilla_origen
                REFERENCES dbo.FP_INTRA_COMUNICADOS (id);
END;
GO

-- Una plantilla siempre tiene nombre
IF OBJECT_ID('dbo.CK_FP_INTRA_COMUNICADOS_plantilla', 'C') IS NULL
BEGIN
    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD CONSTRAINT CK_FP_INTRA_COMUNICADOS_plantilla
            CHECK (es_plantilla = 0 OR LEN(LTRIM(RTRIM(ISNULL(plantilla_nombre, N'')))) > 0);
END;
GO

-- Plantillas en orden (pantalla de carga)
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_FP_INTRA_COMUNICADOS_plantilla'
      AND object_id = OBJECT_ID('dbo.FP_INTRA_COMUNICADOS')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_FP_INTRA_COMUNICADOS_plantilla
        ON dbo.FP_INTRA_COMUNICADOS (es_plantilla, activo, plantilla_orden);
END;
GO

-- ==================== Permiso ====================

DECLARE @moduloId INT = (SELECT id FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET');

DECLARE @permisos TABLE (clave VARCHAR(100), nombre VARCHAR(100), descripcion VARCHAR(255));
INSERT INTO @permisos (clave, nombre, descripcion) VALUES
    ('intranet.plantillas.administrar', 'Administrar plantillas',
     '[Intranet XL] Alta, edición, orden y baja de plantillas de comunicado');

UPDATE p
   SET p.nombre = n.nombre,
       p.descripcion = n.descripcion
  FROM dbo.FP_PERMISOS p
  JOIN @permisos n ON n.clave = p.clave
 WHERE p.modulo_id = @moduloId;

INSERT INTO dbo.FP_PERMISOS (modulo_id, clave, nombre, descripcion)
SELECT @moduloId, n.clave, n.nombre, n.descripcion
  FROM @permisos n
 WHERE NOT EXISTS (SELECT 1 FROM dbo.FP_PERMISOS p WHERE p.clave = n.clave);
GO

-- ==================== Plantillas iniciales ====================
-- Los textos usan el formato de la carga: **negrita**, "- " viñetas. Los saltos de línea se
-- guardan como \n (se quita el \r por si el archivo tiene finales de línea de Windows).

DECLARE @p TABLE (
    orden INT, nombre NVARCHAR(80), descripcion NVARCHAR(200), categoria VARCHAR(10), estilo VARCHAR(15),
    esquema VARCHAR(12), titulo NVARCHAR(120), texto NVARCHAR(4000), texto_inferior NVARCHAR(4000),
    comentarios BIT, reacciones BIT
);

INSERT INTO @p VALUES
(1, N'Apertura de local', N'Para anunciar la inauguración de un local nuevo.',
 'COMERCIAL', 'CELEBRACION', 'ARRIBA',
 N'¡Abrimos [Nombre del local]!',
 N'Con mucha alegría les contamos que el **[Fecha de apertura]** abre sus puertas **[Nombre del local]**, en [Dirección].

Es un paso más en el crecimiento de XL, y es posible gracias al trabajo de todos los equipos.

¡Muchos éxitos al equipo del local! Si pasás por la zona, acercate a conocerlo.',
 NULL, 1, 1),

(2, N'Lanzamiento de colección o campaña', N'Para presentar una colección o campaña y lo que cambia en los locales.',
 'COMERCIAL', 'DESTACADO', 'ARRIBA',
 N'Llega [Nombre de la colección o campaña]',
 N'Desde el **[Fecha de inicio]** hasta el **[Fecha de fin]** está vigente **[Nombre de la colección o campaña]**.

Qué cambia en el local:
- [Cambio en vidriera o exhibición]
- [Productos o precios que se suman]
- [Indicación para el equipo de ventas]

Cualquier duda, consultá con tu supervisor. ¡Vamos con todo!',
 NULL, 1, 1),

(3, N'Bienvenida', N'Para presentar a una persona que se suma al equipo.',
 'RRHH', 'CELEBRACION', 'IZQUIERDA',
 N'Le damos la bienvenida a [Nombre]',
 N'Se suma a **[Área o local]** como **[Puesto]**.

[Una o dos líneas sobre su experiencia o lo que le gusta]

Si te cruzás con [Nombre], ¡dale una cálida bienvenida al equipo XL!',
 NULL, 1, 1),

(4, N'Reconocimiento o logro personal', N'Para celebrar un logro: se recibió, completó una capacitación, etc.',
 'RRHH', 'CELEBRACION', 'IZQUIERDA',
 N'¡Felicitaciones, [Nombre]!',
 N'Queremos compartir una gran noticia: **[Nombre]**, de [Área o local], [Logro: se recibió de…, completó la capacitación de…].

Es un logro que habla de su esfuerzo y compromiso. ¡Felicitaciones de parte de todo XL!

Dejale tu saludo en los comentarios.',
 NULL, 1, 1),

(5, N'Beneficio', N'Para comunicar un beneficio nuevo para el personal.',
 'RRHH', 'DESTACADO', 'ARRIBA',
 N'Nuevo beneficio: [Beneficio]',
 N'Tenemos un beneficio nuevo para todo el equipo: **[Beneficio]**.

Cómo acceder:
- [Paso 1]
- [Paso 2]

**Vigencia:** [Desde cuándo y hasta cuándo]

Si tenés dudas, escribile a RRHH.',
 NULL, 0, 0),

(6, N'Capacitación', N'Para convocar a una capacitación.',
 'RRHH', 'RECORDATORIO', 'SIN_IMAGEN',
 N'Capacitación: [Tema]',
 N'Te invitamos a la capacitación sobre **[Tema]**.

- **Fecha y horario:** [Fecha y horario]
- **Modalidad:** [Presencial en… / Virtual por…]
- **Dirigida a:** [A quién está dirigida]

Confirmá tu participación con [Persona o área de contacto].',
 NULL, 0, 0),

(7, N'Nueva aplicación o cambio de sistema', N'Para anunciar una aplicación nueva o un cambio en un sistema.',
 'SISTEMAS', 'DESTACADO', 'CENTRO',
 N'Novedades en [Nombre del sistema]',
 N'Desde el **[Fecha]**, [Qué cambia].',
 N'Qué tenés que saber:
- [Cambio 1]
- [Cambio 2]

Para entrar, usá este link: [Link]. Si algo no funciona como esperás, avisanos a Sistemas.',
 0, 0),

(8, N'Mantenimiento programado', N'Para avisar un corte o un mantenimiento de los sistemas.',
 'SISTEMAS', 'IMPORTANTE', 'SIN_IMAGEN',
 N'Mantenimiento programado: [Día]',
 N'El **[Día]**, de **[Horario]**, vamos a hacer un mantenimiento en los sistemas.

Durante ese horario no van a funcionar:
- [Sistema o función 1]
- [Sistema o función 2]

Te recomendamos organizarte con anticipación. Te avisamos apenas esté todo normalizado. ¡Gracias por la paciencia!',
 NULL, 0, 0),

(9, N'Mensaje de Dirección o fecha especial', N'Para un saludo de Dirección o una fecha especial, con un flyer.',
 'DIRECCION', 'NORMAL', 'FLYER',
 N'[Título del mensaje]',
 N'[Mensaje breve]

**[Firma]**',
 NULL, 0, 0);

-- Las nuevas van después de las que ya existan
DECLARE @base INT = ISNULL((SELECT MAX(plantilla_orden) FROM dbo.FP_INTRA_COMUNICADOS WHERE es_plantilla = 1), 0);

INSERT INTO dbo.FP_INTRA_COMUNICADOS
    (titulo, texto, texto_inferior, categoria, estilo, esquema, con_formato, estado,
     link_url, link_texto, fijado, vigente_desde, vigente_hasta, activo,
     aviso_emergente, aviso_hasta, permite_comentarios, permite_reacciones,
     es_plantilla, plantilla_nombre, plantilla_descripcion, plantilla_orden,
     creado_por, creado_en)
SELECT p.titulo, REPLACE(p.texto, CHAR(13), N''), REPLACE(p.texto_inferior, CHAR(13), N''),
       p.categoria, p.estilo, p.esquema, 1, 'BORRADOR',
       NULL, NULL, 0, CAST(GETDATE() AS DATE), NULL, 1,
       0, NULL, p.comentarios, p.reacciones,
       1, p.nombre, p.descripcion, @base + p.orden,
       N'SISTEMA', GETDATE()
  FROM @p p
 WHERE NOT EXISTS (
       SELECT 1 FROM dbo.FP_INTRA_COMUNICADOS c
        WHERE c.es_plantilla = 1 AND c.plantilla_nombre = p.nombre)
 ORDER BY p.orden;
GO

SET NOEXEC OFF;
GO

/*
    CONTROL (solo lectura):

SELECT id, plantilla_orden, plantilla_nombre, categoria, estilo, esquema, activo, estado,
       permite_comentarios, permite_reacciones, titulo
FROM dbo.FP_INTRA_COMUNICADOS
WHERE es_plantilla = 1
ORDER BY plantilla_orden, id;

SELECT p.clave, p.nombre FROM dbo.FP_PERMISOS p WHERE p.clave = 'intranet.plantillas.administrar';
*/
