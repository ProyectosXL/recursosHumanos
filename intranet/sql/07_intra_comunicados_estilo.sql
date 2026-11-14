/*
    FP_INTRA_COMUNICADOS.estilo
    Base: XL-APPS (la de ConexionApps, DATABASE_APPS del .env).
    Requiere: 04_intra_comunicados.sql.

    PARA QUÉ: cada comunicado elige un estilo predeterminado de tarjeta:
        NORMAL        Clásico (blanco)
        DESTACADO     Destacado (índigo)
        IMPORTANTE    Importante (rojo)
        CELEBRACION   Celebración (rosa y ámbar)
        RECORDATORIO  Recordatorio (celeste)
    Los comunicados existentes quedan en NORMAL.

    La app funciona aunque este script no se haya corrido: muestra todos los
    comunicados como Clásico y no ofrece el selector de estilo.
    Si se agrega un estilo nuevo, hay que sumarlo al CHECK y a
    Intranet::ESTILOS (recursosHumanos/intranet/Class/Intranet.php).

    Script idempotente: se puede correr más de una vez.
*/

IF COL_LENGTH('dbo.FP_INTRA_COMUNICADOS', 'estilo') IS NULL
BEGIN

    ALTER TABLE dbo.FP_INTRA_COMUNICADOS
        ADD estilo VARCHAR(15) NOT NULL
            CONSTRAINT DF_FP_INTRA_COMUNICADOS_estilo DEFAULT ('NORMAL')
            CONSTRAINT CK_FP_INTRA_COMUNICADOS_estilo
                CHECK (estilo IN ('NORMAL', 'DESTACADO', 'IMPORTANTE', 'CELEBRACION', 'RECORDATORIO'));

END;
GO

/*
    CONTROL (solo lectura):

SELECT id, titulo, estilo FROM dbo.FP_INTRA_COMUNICADOS ORDER BY id DESC;
*/
