/*
    Intranet XL se muda de sistemas/hub/intranet a recursosHumanos/intranet
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 06 (módulo INTRANET). Si falta, el script avisa y no hace nada.

    PARA QUÉ: el Acceso Directo "Intranet XL" del hub toma la URL de
    FP_MODULOS.ruta_url (módulo INTRANET). Los scripts 06 y 12 ya cargan la
    ruta nueva, pero en una base donde se corrieron antes quedó la vieja:
        /sistemas/hub/intranet/index.php  ->  /recursosHumanos/intranet/index.php

    No toca permisos ni roles: las claves (intranet.*, hub.acceso.intranet,
    hub.panel.intranet) siguen iguales.

    Correrlo junto con la publicación de recursosHumanos/intranet y del
    hub/index.php nuevo: la carpeta vieja deja de existir.

    Script idempotente: se puede correr más de una vez.
*/

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.FP_MODULOS WHERE codigo = 'INTRANET')
BEGIN
    RAISERROR('Falta el módulo INTRANET: correr primero 06_intra_permisos.sql. No se cambió nada.', 16, 1);
    RETURN;
END;

UPDATE dbo.FP_MODULOS
   SET ruta_url = '/recursosHumanos/intranet/index.php'
 WHERE codigo = 'INTRANET'
   AND ISNULL(ruta_url, '') <> '/recursosHumanos/intranet/index.php';
GO

/*
    CONTROL (solo lectura):

SELECT id, codigo, nombre, ruta_url, activo, orden
FROM dbo.FP_MODULOS
WHERE codigo = 'INTRANET';

    Otras rutas viejas que hubieran quedado cargadas (debería dar vacío):

SELECT id, codigo, ruta_url FROM dbo.FP_MODULOS WHERE ruta_url LIKE '%/sistemas/hub/intranet/%';
*/
