/*
    Estructura XL - 03: carga inicial del Directorio (áreas y temas ya definidos)
    Base: CENTRAL.
    Orden: 3 de 4. Después de 02_directorio_tablas.sql.

    Qué hace:
      - Crea las 6 áreas y sus temas, con el referente, el gerente y el jefe que ya están definidos.
      - Cada persona se busca por nombre en RO_T_INTERNOS_PERSONAL, sin distinguir mayúsculas ni
        tildes (dbo.RO_FN_DIR_CLAVE: "Martín Becker" encuentra "Martin Becker"), sólo entre las
        activas. Si no está, o hay dos activas con ese nombre, queda vacía ("A definir") y se
        informa con un PRINT al final. No se inventa ninguna persona.
      - "Equipo" en la tabla de temas = REFERENTE_EQUIPO = 1 ("Equipo de <área>").
      - Ninguna área tiene todavía celular de sector ni horario.

    Idempotente y SIN PISAR CAMBIOS: sólo agrega las áreas y los temas que no existen (activos,
    comparando con la misma clave). Si después alguien editó una ficha desde la pantalla y se
    vuelve a correr el script, no se toca nada de lo que ya está. Tampoco reactiva lo desactivado.

    Todo en una transacción: o se carga todo, o nada.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

-- Todo lo que sigue es un solo lote (sin GO) para que este RETURN corte de verdad: en SSMS un
-- RETURN sólo termina el lote en curso y los siguientes se ejecutarían igual.
IF OBJECT_ID('dbo.RO_T_DIR_AREAS', 'U') IS NULL OR OBJECT_ID('dbo.RO_T_DIR_TEMAS', 'U') IS NULL
BEGIN
    RAISERROR('Faltan las tablas: correr antes 02_directorio_tablas.sql.', 16, 1);
    RETURN;
END;

DECLARE @usuario NVARCHAR(100) = N'carga_inicial';

DECLARE @areas TABLE (
    ORDEN INT, NOMBRE NVARCHAR(100), COLOR CHAR(7), RESUELVE NVARCHAR(200),
    MAIL NVARCHAR(150), GERENTE NVARCHAR(100), JEFE NVARCHAR(100)
);
INSERT INTO @areas VALUES
 (1, N'Abastecimiento', '#2F5FB3', N'Pedidos, reposición, stock y precios de los locales.',                    N'abastecimiento@xl.com.ar', N'Martín Becker', N'Natalia Bontempo'),
 (2, N'Logística',      '#1F7A8C', N'Entregas, transporte, remitos y reclamos de envíos.',                     NULL,                        N'Martín Becker', N'Lucas Navarro'),
 (3, N'Administración', '#B23A2E', N'Facturación, cuentas corrientes, comprobantes y compras.',                NULL,                        N'Silvia Freire', NULL),
 (4, N'Tesorería',      '#8E3B6E', N'Depósitos, caja y efectivo, medios de cobro.',                            NULL,                        NULL,             NULL),
 (5, N'Innovación',     '#9A4A1F', N'Soporte de todas las aplicaciones de la empresa y pedidos de mejoras.',   N'proyectos@xl.com.ar',      NULL,             NULL),
 (6, N'Sistemas',       '#5B5BA8', N'Tango, equipos, impresoras, cajas, conectividad y accesos.',              NULL,                        NULL,             N'Daniel Aguero');

-- REFERENTE: nombre como figura en la definición, N'Equipo', o NULL (sin referente: "A definir").
-- ORDEN: el de la lista dentro de su área.
DECLARE @temas TABLE (AREA NVARCHAR(100), ORDEN INT, TEMA NVARCHAR(150), REFERENTE NVARCHAR(100), PALABRAS NVARCHAR(500));
INSERT INTO @temas VALUES
 (N'Abastecimiento',  1, N'Ingresos futuros',       N'Jessica Farias',    N'ingreso, mercadería nueva, cuándo entra'),
 (N'Abastecimiento',  2, N'Pedidos',                N'Jessica Farias',    N'pedido, pedir'),
 (N'Abastecimiento',  3, N'Reposición',             N'Jessica Farias',    N'reponer, reposición'),
 (N'Abastecimiento',  4, N'Stock',                  N'Mariana Blanco',    N'sin stock, no tengo stock'),
 (N'Abastecimiento',  5, N'Distribución',           N'Mariana Blanco',    N'distribución, asignación'),
 (N'Abastecimiento',  6, N'Rotaciones',             N'Mariana Blanco',    N'rotación, pasar mercadería a otro local, traspaso'),
 -- "mercadería" agregada (definido el 14/11/2026) para que "me falta mercadería" encuentre Faltantes
 (N'Abastecimiento',  7, N'Faltantes',              N'Jessica Farias',    N'me falta, faltante, no llegó, diferencia, mercadería'),
 (N'Abastecimiento',  8, N'Sobrantes',              N'Jessica Farias',    N'me sobra, sobrante, llegó de más'),
 (N'Abastecimiento',  9, N'Planificación',          N'Julieta D''Almeida', N'planificación'),
 (N'Abastecimiento', 10, N'CADS',                   N'Mariana Blanco',    N'cads'),
 (N'Abastecimiento', 11, N'Presupuestos',           N'Julieta D''Almeida', N'presupuesto'),
 (N'Abastecimiento', 12, N'Precios',                N'Jessica Farias',    N'precio mal cargado, etiqueta'),
 (N'Abastecimiento', 13, N'Codificación',           N'Jessica Farias',    N'código, artículo nuevo, codificación'),

 (N'Logística',  1, N'Distribución',  N'Claudia Otero', N'distribución'),
 (N'Logística',  2, N'Entregas',      N'Lucas Navarro', N'entrega, cuándo llega mi pedido, envío'),
 (N'Logística',  3, N'Transporte',    N'Lucas Navarro', N'transporte, camión, flete'),
 (N'Logística',  4, N'Seguimiento',   N'Claudia Otero', N'seguimiento, dónde está mi pedido'),
 (N'Logística',  5, N'Recepción',     N'Lucas Navarro', N'recepción, recibir'),
 (N'Logística',  6, N'Ruteo',         N'Lucas Navarro', N'ruteo, ruta, horario de entrega'),
 (N'Logística',  7, N'Tiempos',       N'Lucas Navarro', N'demora, cuándo llega'),
 (N'Logística',  8, N'Incidencias',   N'Claudia Otero', N'incidencia, caja rota, dañado'),
 (N'Logística',  9, N'Reclamos',      N'Claudia Otero', N'reclamo'),
 (N'Logística', 10, N'Remitos',       N'Claudia Otero', N'remito'),

 (N'Administración',  1, N'Facturación',              NULL, N'factura, facturación'),
 (N'Administración',  2, N'Pagos',                    NULL, N'pago'),
 (N'Administración',  3, N'Cobranzas',                NULL, N'cobranza, cobro'),
 (N'Administración',  4, N'Cuenta corriente',         NULL, N'cuenta corriente, saldo'),
 (N'Administración',  5, N'Comprobantes',             NULL, N'comprobante'),
 (N'Administración',  6, N'Notas de crédito/débito',  NULL, N'nota de crédito, nota de débito'),
 (N'Administración',  7, N'Contabilidad',             NULL, N'contabilidad'),
 (N'Administración',  8, N'Impuestos',                NULL, N'impuesto'),
 (N'Administración',  9, N'Documentación',            NULL, N'documentación'),
 (N'Administración', 10, N'Proveedores',              NULL, N'proveedor'),
 (N'Administración', 11, N'Seguros',                  NULL, N'seguro'),
 (N'Administración', 12, N'Compras',                  N'Diego Szmulewicz', N'compra, insumos'),
 (N'Administración', 13, N'Control sucursales',       N'Silvina Leguiza',  N'control de sucursal, arqueo'),

 (N'Tesorería',  1, N'Pagos a proveedores',      NULL, N'pago a proveedor'),
 (N'Tesorería',  2, N'Cronograma de pagos',      NULL, N'cronograma de pagos'),
 (N'Tesorería',  3, N'Cheques',                  NULL, N'cheque'),
 (N'Tesorería',  4, N'Transferencias bancarias', NULL, N'transferencia'),
 (N'Tesorería',  5, N'Cuentas bancarias',        NULL, N'cuenta bancaria'),
 (N'Tesorería',  6, N'Caja y efectivo',          NULL, N'caja, efectivo, cambio'),
 (N'Tesorería',  7, N'Depósitos de locales',     NULL, N'depósito'),
 (N'Tesorería',  8, N'Flujo de fondos',          NULL, N'flujo de fondos'),
 (N'Tesorería',  9, N'Medios de cobro',          NULL, N'medios de cobro, tarjeta'),
 (N'Tesorería', 10, N'Retenciones',              NULL, N'retención'),

 (N'Innovación', 1, N'Control de stock',                         N'Agustina Latorre', N'control de stock, conteo'),
 (N'Innovación', 2, N'Soporte de aplicaciones (uso y errores)',  N'Equipo',           N'app, aplicación, error, no anda, no funciona, XL Gestión, pantalla, cómo se hace'),
 (N'Innovación', 3, N'Pedidos de mejoras o nuevas herramientas', N'Equipo',           N'mejora, nueva herramienta, idea'),

 (N'Sistemas',  1, N'Sistema Tango',          NULL, N'tango'),
 (N'Sistemas',  2, N'Falla de facturación',   NULL, N'no factura, no puedo facturar'),
 (N'Sistemas',  3, N'Hardware',               NULL, N'computadora, pc, monitor'),
 (N'Sistemas',  4, N'Equipamiento',           NULL, N'equipo, equipamiento'),
 (N'Sistemas',  5, N'Impresoras',             NULL, N'impresora, no imprime, ticket'),
 (N'Sistemas',  6, N'Cajas / POS',            NULL, N'caja, pos, posnet'),
 (N'Sistemas',  7, N'Conectividad',           NULL, N'internet, wifi, sin conexión, red'),
 (N'Sistemas',  8, N'Usuarios y accesos',     NULL, N'usuario, contraseña, clave, acceso, olvidé'),
 (N'Sistemas',  9, N'Soporte técnico',        NULL, N'soporte técnico'),
 (N'Sistemas', 10, N'Incidencias',            NULL, N'incidencia de sistema');

/* Personas: una sola activa con esa clave → su ID; si no, NULL y aviso */
DECLARE @nombres TABLE (NOMBRE NVARCHAR(100) PRIMARY KEY, ID INT NULL, CANT_ACTIVAS INT, CANT_INACTIVAS INT);
INSERT INTO @nombres (NOMBRE)
SELECT n FROM (
    SELECT GERENTE AS n FROM @areas UNION SELECT JEFE FROM @areas
    UNION SELECT REFERENTE FROM @temas WHERE REFERENTE <> N'Equipo'
) x WHERE n IS NOT NULL;

UPDATE n SET
    CANT_ACTIVAS   = (SELECT COUNT(*) FROM dbo.RO_T_INTERNOS_PERSONAL p
                       WHERE ISNULL(p.INACTIVO, 0) = 0 AND dbo.RO_FN_DIR_CLAVE(p.NOMBRE_APELLIDO) = dbo.RO_FN_DIR_CLAVE(n.NOMBRE)),
    CANT_INACTIVAS = (SELECT COUNT(*) FROM dbo.RO_T_INTERNOS_PERSONAL p
                       WHERE ISNULL(p.INACTIVO, 0) = 1 AND dbo.RO_FN_DIR_CLAVE(p.NOMBRE_APELLIDO) = dbo.RO_FN_DIR_CLAVE(n.NOMBRE))
FROM @nombres n;

UPDATE n SET ID = (SELECT p.ID FROM dbo.RO_T_INTERNOS_PERSONAL p
                    WHERE ISNULL(p.INACTIVO, 0) = 0 AND dbo.RO_FN_DIR_CLAVE(p.NOMBRE_APELLIDO) = dbo.RO_FN_DIR_CLAVE(n.NOMBRE))
FROM @nombres n
WHERE n.CANT_ACTIVAS = 1;

DECLARE @areasCreadas INT = 0, @temasCreados INT = 0;

BEGIN TRANSACTION;

    INSERT INTO dbo.RO_T_DIR_AREAS (NOMBRE, COLOR, ORDEN, RESUELVE, MAIL_SECTOR, ID_GERENTE, ID_JEFE, USUARIO_ALTA)
    SELECT a.NOMBRE, a.COLOR, a.ORDEN, a.RESUELVE, a.MAIL, g.ID, j.ID, @usuario
      FROM @areas a
      LEFT JOIN @nombres g ON g.NOMBRE = a.GERENTE
      LEFT JOIN @nombres j ON j.NOMBRE = a.JEFE
     WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_DIR_AREAS x
                        WHERE x.NOMBRE_CLAVE = dbo.RO_FN_DIR_CLAVE(a.NOMBRE) AND x.ACTIVA = 1);
    SET @areasCreadas = @@ROWCOUNT;

    INSERT INTO dbo.RO_T_DIR_TEMAS (ID_AREA, TEMA, ORDEN, ID_REFERENTE, REFERENTE_EQUIPO, PALABRAS_CLAVE, USUARIO_ALTA)
    SELECT ar.ID, t.TEMA, t.ORDEN,
           CASE WHEN t.REFERENTE = N'Equipo' THEN NULL ELSE r.ID END,
           CASE WHEN t.REFERENTE = N'Equipo' THEN 1 ELSE 0 END,
           t.PALABRAS, @usuario
      FROM @temas t
      JOIN dbo.RO_T_DIR_AREAS ar ON ar.NOMBRE_CLAVE = dbo.RO_FN_DIR_CLAVE(t.AREA) AND ar.ACTIVA = 1
      LEFT JOIN @nombres r ON r.NOMBRE = t.REFERENTE
     WHERE NOT EXISTS (SELECT 1 FROM dbo.RO_T_DIR_TEMAS x
                        WHERE x.ID_AREA = ar.ID AND x.TEMA_CLAVE = dbo.RO_FN_DIR_CLAVE(t.TEMA) AND x.ACTIVO = 1);
    SET @temasCreados = @@ROWCOUNT;

COMMIT TRANSACTION;

PRINT CONCAT('Áreas creadas: ', @areasCreadas, ' · Temas creados: ', @temasCreados,
             ' (lo que ya existía no se tocó).');

/* Aviso de personas no resueltas (quedaron vacías: se ven como "A definir") */
DECLARE @aviso NVARCHAR(400);
DECLARE avisos CURSOR LOCAL FAST_FORWARD FOR
    SELECT CASE
             WHEN CANT_ACTIVAS > 1 THEN CONCAT(N'AVISO: "', NOMBRE, N'" está más de una vez activo en Internos: quedó vacío, elegilo desde la ficha.')
             WHEN CANT_INACTIVAS > 0 THEN CONCAT(N'AVISO: "', NOMBRE, N'" está inactivo en Internos: quedó vacío.')
             ELSE CONCAT(N'AVISO: "', NOMBRE, N'" no está en Internos: quedó vacío.')
           END
      FROM @nombres WHERE ID IS NULL ORDER BY NOMBRE;
OPEN avisos;
FETCH NEXT FROM avisos INTO @aviso;
IF @@FETCH_STATUS <> 0 PRINT 'Todas las personas se encontraron activas en Internos.';
WHILE @@FETCH_STATUS = 0
BEGIN
    PRINT @aviso;
    FETCH NEXT FROM avisos INTO @aviso;
END;
CLOSE avisos;
DEALLOCATE avisos;
GO

/*
    CONTROL (solo lectura):

SELECT a.ORDEN, a.NOMBRE, a.MAIL_SECTOR, g.NOMBRE_APELLIDO AS gerente, j.NOMBRE_APELLIDO AS jefe,
       (SELECT COUNT(*) FROM dbo.RO_T_DIR_TEMAS t WHERE t.ID_AREA = a.ID AND t.ACTIVO = 1) AS temas
  FROM dbo.RO_T_DIR_AREAS a
  LEFT JOIN dbo.RO_T_INTERNOS_PERSONAL g ON g.ID = a.ID_GERENTE
  LEFT JOIN dbo.RO_T_INTERNOS_PERSONAL j ON j.ID = a.ID_JEFE
 WHERE a.ACTIVA = 1
 ORDER BY a.ORDEN;
*/
