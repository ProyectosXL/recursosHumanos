/*
    Panel derecho de Intranet XL en el hub, por permiso
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 06 (módulo INTRANET). Si falta, el script avisa y no hace nada.

    PARA QUÉ: que el panel derecho de Intranet XL del hub (comunicados, cumpleaños
    y fechas) y el aviso emergente de comunicados importantes se puedan habilitar
    por rol desde Gestionusuarios, como los Accesos Directos.

    - Permiso nuevo (FP_PERMISOS, módulo INTRANET):
        hub.panel.intranet  ver el panel derecho de Intranet XL y el aviso emergente.
      NO se asigna a ningún rol: queda OCULTO para todos los usuarios, salvo el
      administrador total (el hub se lo muestra siempre).
      Para mostrarlo, asignarlo al rol desde Gestionusuarios.

    - No toca FP_MODULOS: un permiso "hub.panel.%" no cambia qué apps ni qué
      Accesos Directos ve cada rol.

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

IF NOT EXISTS (SELECT 1 FROM dbo.FP_PERMISOS WHERE clave = 'hub.panel.intranet')
    INSERT INTO dbo.FP_PERMISOS (modulo_id, clave, nombre, descripcion)
    VALUES (@moduloId, 'hub.panel.intranet', 'Panel Intranet XL en el hub',
            '[Hub] Ver el panel derecho de Intranet XL y el aviso de comunicados importantes');
ELSE
    UPDATE dbo.FP_PERMISOS
       SET modulo_id = @moduloId,
           nombre = 'Panel Intranet XL en el hub',
           descripcion = '[Hub] Ver el panel derecho de Intranet XL y el aviso de comunicados importantes'
     WHERE clave = 'hub.panel.intranet';
GO

/*
    CONTROL (solo lectura):

SELECT p.id AS permiso_id, p.clave, p.nombre, m.codigo AS modulo
FROM dbo.FP_PERMISOS p
JOIN dbo.FP_MODULOS m ON m.id = p.modulo_id
WHERE p.clave = 'hub.panel.intranet';

    Roles que lo tienen asignado (debería dar vacío):

SELECT rp.rol_id FROM dbo.FP_ROL_PERMISO rp
JOIN dbo.FP_PERMISOS p ON p.id = rp.permiso_id
WHERE p.clave = 'hub.panel.intranet';
*/
