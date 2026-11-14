/*
    Permisos de la Intranet XL
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).

    - Módulo INTRANET en FP_MODULOS con activo = 0, A PROPÓSITO:
        * el hub (hub/index.php) muestra como app todo módulo con activo = 1,
          y la intranet no es una app: se entra por Accesos Directos;
        * la matriz de permisos por rol de Gestionusuarios lista todos los
          módulos, activos o no, así que los permisos se asignan desde ahí
          como cualquier otro.
      NO ACTIVAR este módulo: aparecería como app suelta en el hub.

    - Permisos (FP_PERMISOS, colgados del módulo INTRANET):
        intranet.comunicados.publicar  alta, edición y baja de comunicados
        intranet.fechas.publicar       alta, edición y baja de fechas
      Ninguno empieza con "hub.app.", así que no tocan la lógica de qué apps
      ve cada rol en el hub.
      El administrador total los tiene todos (Auth::puede devuelve true).

    - No asigna permisos a ningún rol: eso se hace desde Gestionusuarios.

    Script idempotente: se puede correr más de una vez. Si el módulo o los
    permisos ya existen, solo actualiza nombre y descripción de los permisos.
*/

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET')
BEGIN

    INSERT INTO dbo.FP_MODULOS
        (codigo, nombre, categoria, ruta_url, icono, color, descripcion, orden, target_blank, activo)
    VALUES
        ('INTRANET', 'Intranet XL', NULL, '/recursosHumanos/intranet/index.php', 'fa-newspaper', NULL,
         N'Permisos de carga de la Intranet XL. Inactivo a propósito: no es una app del hub, se entra por Accesos Directos.',
         999, 0, 0);

END;
GO

DECLARE @moduloId INT = (SELECT id FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET');

DECLARE @permisos TABLE (clave VARCHAR(100), nombre VARCHAR(100), descripcion VARCHAR(255));
INSERT INTO @permisos (clave, nombre, descripcion) VALUES
    ('intranet.comunicados.publicar', 'Publicar comunicados',
     '[Intranet XL] Alta, edición y baja de comunicados'),
    ('intranet.fechas.publicar', 'Publicar fechas',
     '[Intranet XL] Alta, edición y baja de fechas comerciales y operativas');

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
WHERE m.codigo = 'INTRANET';
*/
