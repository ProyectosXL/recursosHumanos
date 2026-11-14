/*
    Intranet XL como Acceso Directo del hub (sidebar izquierdo)
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 06 (módulo INTRANET). Si falta, el script avisa y no hace nada.

    PARA QUÉ: que Intranet XL deje de estar fija en el código del hub y sea un
    Acceso Directo más, configurable por rol desde Gestionusuarios.

    - Permiso nuevo (FP_PERMISOS, módulo INTRANET):
        hub.acceso.intranet  ver Intranet XL en Accesos Directos del hub.
      NO se asigna a ningún rol: queda OCULTO para todos los usuarios, salvo el
      administrador total (el hub le muestra todos los accesos directos).
      Para mostrarlo, asignarlo al rol desde Gestionusuarios.

    - Módulo INTRANET pasa a activo = 1. Es seguro: el hub (hub/index.php)
      excluye de las apps a todo módulo con un permiso "hub.acceso.%", así que
      NO aparece como app suelta; solo como Acceso Directo.
      Por eso el UPDATE se hace solo si el permiso existe.

    - orden = 0: primero de la lista de Accesos Directos.

    Script idempotente: se puede correr más de una vez.
*/

SET NOCOUNT ON;
GO

DECLARE @moduloId INT = (SELECT id FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET');

IF @moduloId IS NULL
BEGIN
    RAISERROR('Falta el módulo INTRANET: correr primero 06_intra_permisos.sql. No se cambió nada.', 16, 1);
    RETURN;
END;

BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM dbo.FP_PERMISOS WHERE clave = 'hub.acceso.intranet')
    INSERT INTO dbo.FP_PERMISOS (modulo_id, clave, nombre, descripcion)
    VALUES (@moduloId, 'hub.acceso.intranet', 'Acceso directo Intranet XL',
            '[Hub] Ver Intranet XL en Accesos Directos');
ELSE
    UPDATE dbo.FP_PERMISOS
       SET modulo_id = @moduloId,
           nombre = 'Acceso directo Intranet XL',
           descripcion = '[Hub] Ver Intranet XL en Accesos Directos'
     WHERE clave = 'hub.acceso.intranet';

-- Activar solo si el permiso hub.acceso quedó colgado del módulo (si no, aparecería como app)
IF EXISTS (SELECT 1 FROM dbo.FP_PERMISOS WHERE clave = 'hub.acceso.intranet' AND modulo_id = @moduloId)
    UPDATE dbo.FP_MODULOS
       SET activo = 1,
           orden = 0,
           ruta_url = '/recursosHumanos/intranet/index.php',
           icono = 'fa-newspaper',
           color = 'linear-gradient(135deg, #4f46e5 0%, #312e81 100%)',
           target_blank = 0,
           descripcion = N'Intranet XL: comunicados, cumpleaños y fechas importantes. Acceso Directo del hub (permiso hub.acceso.intranet); no aparece como app.'
     WHERE id = @moduloId;

COMMIT TRANSACTION;
GO

/*
    CONTROL (solo lectura):

SELECT m.id AS modulo_id, m.codigo, m.activo, m.orden, p.id AS permiso_id, p.clave, p.nombre
FROM dbo.FP_MODULOS m
LEFT JOIN dbo.FP_PERMISOS p ON p.modulo_id = m.id
WHERE m.codigo = 'INTRANET';

    Roles que lo tienen asignado (debería dar vacío):

SELECT rp.rol_id FROM dbo.FP_ROL_PERMISO rp
JOIN dbo.FP_PERMISOS p ON p.id = rp.permiso_id
WHERE p.clave = 'hub.acceso.intranet';
*/
