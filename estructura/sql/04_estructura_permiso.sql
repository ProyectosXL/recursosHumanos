/*
    Estructura XL - 04: permiso de administración del Directorio
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env). OJO: no es la central.
    Orden: 4 de 4. Independiente de 01-03 (otra base); se puede correr antes o después.

    - Módulo ESTRUCTURA en FP_MODULOS con activo = 0, A PROPÓSITO (igual que INTRANET):
        * el hub muestra como app todo módulo con activo = 1, y estructura no es una app del hub:
          se entra desde XL Gestión;
        * la matriz de permisos por rol de Gestionusuarios lista todos los módulos, activos o no,
          así que el permiso se asigna desde ahí como cualquier otro.
      NO ACTIVAR este módulo: aparecería como app suelta en el hub.

    - Permiso (FP_PERMISOS, colgado del módulo ESTRUCTURA):
        estructura.administrar   alta, baja y edición de áreas y de sus fichas, importación de
                                 Excel y panel de búsquedas sin resultado.
      No empieza con "hub.app.": no toca qué apps ve cada rol en el hub.
      El administrador total lo tiene (Auth::puede devuelve true).
      Ojo: ya existe "estructura.ver" en el módulo NODO (la estructura organizacional de Nodo).
      No tiene relación con este; por eso éste cuelga de su propio módulo y dice [Directorio].

    - NO asigna el permiso a ningún rol: Ramiro lo asigna a los roles de Innovación desde el hub.

    - Etapa siguiente (no se crea ahora): un permiso estructura.editar_ficha para los responsables
      de cada área. Se suma a esta lista y a Permisos::puedeEditarArea(); no hay que rehacer nada.

    Script idempotente: se puede correr más de una vez. Si el módulo o el permiso ya existen,
    sólo actualiza nombre y descripción del permiso.
*/

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.FP_MODULOS WHERE codigo = 'ESTRUCTURA')
BEGIN

    INSERT INTO dbo.FP_MODULOS
        (codigo, nombre, categoria, ruta_url, icono, color, descripcion, orden, target_blank, activo)
    VALUES
        ('ESTRUCTURA', 'Estructura XL (Directorio)', NULL, '/recursosHumanos/estructura/', 'fa-sitemap', NULL,
         N'Permisos de la Guía de Consultas y Contactos por área. Inactivo a propósito: no es una app del hub, se entra desde XL Gestión.',
         999, 0, 0);

END;
GO

DECLARE @moduloId INT = (SELECT id FROM dbo.FP_MODULOS WHERE codigo = 'ESTRUCTURA');

DECLARE @permisos TABLE (clave VARCHAR(100), nombre VARCHAR(100), descripcion VARCHAR(255));
INSERT INTO @permisos (clave, nombre, descripcion) VALUES
    ('estructura.administrar', 'Administrar áreas del Directorio',
     '[Directorio] Alta, baja y edición de áreas y fichas, importación de Excel y búsquedas sin resultado');

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

/*
    CONTROL (solo lectura):

SELECT m.id AS modulo_id, m.codigo, m.activo, p.id AS permiso_id, p.clave, p.nombre
FROM dbo.FP_MODULOS m
LEFT JOIN dbo.FP_PERMISOS p ON p.modulo_id = m.id
WHERE m.codigo = 'ESTRUCTURA';
*/
