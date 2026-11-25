/*
    Tabla: dbo.PersonasProyectos  (+ carga inicial)

    Listado único de personas de Gestión de Proyectos (adminProyectos). Cada
    persona tiene tres switches que indican en qué combo aparece:
      - EsSolicitante   -> combo Solicitante (Cargar Proyecto y Editar Proyecto)
      - EsLider         -> combo Líder de Proyecto (Editar Proyecto)
      - EsDesarrollador -> combo Desarrollador (Editar Proyecto)
    Se administra desde adminProyectos/parametros.php (pestaña Parámetros ›
    Personas). No se borran personas: solo se desactivan (Activo = 0).

    HistorialProyectos NO cambia: Solicitante, LiderProyecto y Desarrollador se
    siguen guardando como texto (el Nombre de esta tabla), para no romper
    filtros, estadísticas ni avisos existentes.

    Mails: se usan para los avisos automáticos (Proyecto::resolverMailPorNombre()
    y dbo.RO_SP_NOTIF_ADMIN_PROYECTOS), buscando por Nombre sin importar si la
    persona está activa ni qué roles tiene.

    Nombre único: la columna usa Modern_Spanish_CI_AI (la intercalación de la
    base), así que el índice único no distingue mayúsculas ni acentos
    ("José Pérez" = "jose perez"). Los espacios al inicio/fin se recortan al
    guardar y el CHECK impide que entre un nombre vacío o con espacios al inicio.

    Carga inicial (sección 2):
      - Copia UNA sola vez el contenido de la vista RO_USUARIOS_CARGA_PROYECTOS
        (NOMBRE_APELLIDO, MAIL) con EsSolicitante = 1. De ahí en adelante la
        lista se administra solo desde la app; la vista deja de usarse en
        adminProyectos.
      - Suma los valores distintos no vacíos de HistorialProyectos.LiderProyecto
        (EsLider = 1) y HistorialProyectos.Desarrollador (EsDesarrollador = 1):
        si el nombre ya existe solo se prende el switch; si no, se inserta sin mail.
      - Idempotente: si la tabla ya tiene filas la carga NO se vuelve a correr
        (para no pisar lo que se haya editado desde la app). Además cada paso
        compara contra lo existente, así que tampoco duplicaría.
      - Corre en una única transacción: o se carga todo o nada.

    Al final (sección 3) hay dos SELECT de control: todo lo cargado y las
    personas Solicitante/Líder que quedaron sin mail, para completarlas.

    Orden de despliegue: este script va PRIMERO, antes del script actualizado de
    RO_SP_NOTIF_ADMIN_PROYECTOS (que ya lee de esta tabla).
*/

-- ============================================================================
-- 1) Tabla (idempotente)
-- ============================================================================
IF OBJECT_ID('dbo.PersonasProyectos', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.PersonasProyectos (
        ID                INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_PersonasProyectos PRIMARY KEY,
        Nombre            NVARCHAR(255) COLLATE Modern_Spanish_CI_AI NOT NULL,
        Mail              NVARCHAR(200) NULL,
        EsSolicitante     BIT NOT NULL CONSTRAINT DF_PersonasProyectos_EsSolicitante   DEFAULT 0,
        EsLider           BIT NOT NULL CONSTRAINT DF_PersonasProyectos_EsLider         DEFAULT 0,
        EsDesarrollador   BIT NOT NULL CONSTRAINT DF_PersonasProyectos_EsDesarrollador DEFAULT 0,
        Activo            BIT NOT NULL CONSTRAINT DF_PersonasProyectos_Activo          DEFAULT 1,
        FechaCreacion     DATETIME NOT NULL CONSTRAINT DF_PersonasProyectos_FechaCreacion DEFAULT GETDATE(),
        CreadoPor         NVARCHAR(100) NULL,
        FechaModificacion DATETIME NULL,
        ModificadoPor     NVARCHAR(100) NULL,
        CONSTRAINT UQ_PersonasProyectos_Nombre UNIQUE (Nombre),
        CONSTRAINT CK_PersonasProyectos_Nombre CHECK (LEN(Nombre) > 0 AND Nombre = LTRIM(Nombre))
    );
END
GO

-- ============================================================================
-- 2) Carga inicial (solo si la tabla está vacía)
-- ============================================================================
SET XACT_ABORT ON;

IF EXISTS (SELECT 1 FROM dbo.PersonasProyectos)
BEGIN
    PRINT 'PersonasProyectos ya tiene datos: no se vuelve a correr la carga inicial.';
END
ELSE
BEGIN
    BEGIN TRY
        BEGIN TRANSACTION;

        DECLARE @Usuario NVARCHAR(100) = N'CARGA_INICIAL';

        -- --------------------------------------------------------------
        -- 2.1) Solicitantes: copia de la vista RO_USUARIOS_CARGA_PROYECTOS
        --      (si la vista repite un nombre, se toma un único mail)
        -- --------------------------------------------------------------
        ;WITH Origen AS (
            SELECT CAST(LTRIM(RTRIM(NOMBRE_APELLIDO)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI AS Nombre,
                   CAST(NULLIF(LTRIM(RTRIM(MAIL)), '') AS NVARCHAR(200)) AS Mail
            FROM RO_USUARIOS_CARGA_PROYECTOS
            WHERE LTRIM(RTRIM(ISNULL(NOMBRE_APELLIDO, ''))) <> ''
        ),
        Agrupado AS (
            SELECT MIN(Nombre) AS Nombre, MAX(Mail) AS Mail
            FROM Origen
            GROUP BY Nombre
        )
        UPDATE p
        SET p.EsSolicitante = 1,
            p.Mail = COALESCE(p.Mail, a.Mail),
            p.FechaModificacion = GETDATE(),
            p.ModificadoPor = @Usuario
        FROM dbo.PersonasProyectos p
        INNER JOIN Agrupado a ON a.Nombre = p.Nombre
        WHERE p.EsSolicitante = 0 OR (p.Mail IS NULL AND a.Mail IS NOT NULL);

        ;WITH Origen AS (
            SELECT CAST(LTRIM(RTRIM(NOMBRE_APELLIDO)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI AS Nombre,
                   CAST(NULLIF(LTRIM(RTRIM(MAIL)), '') AS NVARCHAR(200)) AS Mail
            FROM RO_USUARIOS_CARGA_PROYECTOS
            WHERE LTRIM(RTRIM(ISNULL(NOMBRE_APELLIDO, ''))) <> ''
        ),
        Agrupado AS (
            SELECT MIN(Nombre) AS Nombre, MAX(Mail) AS Mail
            FROM Origen
            GROUP BY Nombre
        )
        INSERT INTO dbo.PersonasProyectos (Nombre, Mail, EsSolicitante, Activo, FechaCreacion, CreadoPor)
        SELECT a.Nombre, a.Mail, 1, 1, GETDATE(), @Usuario
        FROM Agrupado a
        WHERE NOT EXISTS (SELECT 1 FROM dbo.PersonasProyectos p WHERE p.Nombre = a.Nombre);

        -- --------------------------------------------------------------
        -- 2.2) Líderes: valores distintos de HistorialProyectos.LiderProyecto
        -- --------------------------------------------------------------
        ;WITH Origen AS (
            SELECT MIN(CAST(LTRIM(RTRIM(LiderProyecto)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI) AS Nombre
            FROM HistorialProyectos
            WHERE LTRIM(RTRIM(ISNULL(LiderProyecto, ''))) <> ''
            GROUP BY CAST(LTRIM(RTRIM(LiderProyecto)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI
        )
        UPDATE p
        SET p.EsLider = 1,
            p.FechaModificacion = GETDATE(),
            p.ModificadoPor = @Usuario
        FROM dbo.PersonasProyectos p
        INNER JOIN Origen o ON o.Nombre = p.Nombre
        WHERE p.EsLider = 0;

        ;WITH Origen AS (
            SELECT MIN(CAST(LTRIM(RTRIM(LiderProyecto)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI) AS Nombre
            FROM HistorialProyectos
            WHERE LTRIM(RTRIM(ISNULL(LiderProyecto, ''))) <> ''
            GROUP BY CAST(LTRIM(RTRIM(LiderProyecto)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI
        )
        INSERT INTO dbo.PersonasProyectos (Nombre, Mail, EsLider, Activo, FechaCreacion, CreadoPor)
        SELECT o.Nombre, NULL, 1, 1, GETDATE(), @Usuario
        FROM Origen o
        WHERE NOT EXISTS (SELECT 1 FROM dbo.PersonasProyectos p WHERE p.Nombre = o.Nombre);

        -- --------------------------------------------------------------
        -- 2.3) Desarrolladores: valores distintos de HistorialProyectos.Desarrollador
        -- --------------------------------------------------------------
        ;WITH Origen AS (
            SELECT MIN(CAST(LTRIM(RTRIM(Desarrollador)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI) AS Nombre
            FROM HistorialProyectos
            WHERE LTRIM(RTRIM(ISNULL(Desarrollador, ''))) <> ''
            GROUP BY CAST(LTRIM(RTRIM(Desarrollador)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI
        )
        UPDATE p
        SET p.EsDesarrollador = 1,
            p.FechaModificacion = GETDATE(),
            p.ModificadoPor = @Usuario
        FROM dbo.PersonasProyectos p
        INNER JOIN Origen o ON o.Nombre = p.Nombre
        WHERE p.EsDesarrollador = 0;

        ;WITH Origen AS (
            SELECT MIN(CAST(LTRIM(RTRIM(Desarrollador)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI) AS Nombre
            FROM HistorialProyectos
            WHERE LTRIM(RTRIM(ISNULL(Desarrollador, ''))) <> ''
            GROUP BY CAST(LTRIM(RTRIM(Desarrollador)) AS NVARCHAR(255)) COLLATE Modern_Spanish_CI_AI
        )
        INSERT INTO dbo.PersonasProyectos (Nombre, Mail, EsDesarrollador, Activo, FechaCreacion, CreadoPor)
        SELECT o.Nombre, NULL, 1, 1, GETDATE(), @Usuario
        FROM Origen o
        WHERE NOT EXISTS (SELECT 1 FROM dbo.PersonasProyectos p WHERE p.Nombre = o.Nombre);

        COMMIT TRANSACTION;
        PRINT 'Carga inicial de PersonasProyectos completada.';
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        PRINT 'Error en la carga inicial de PersonasProyectos (no se cargó nada): ' + ERROR_MESSAGE();
        THROW;
    END CATCH
END
GO

-- ============================================================================
-- 3) Control
-- ============================================================================

-- 3.1) Todo lo cargado
SELECT ID, Nombre, Mail, EsSolicitante, EsLider, EsDesarrollador, Activo,
       FechaCreacion, CreadoPor, FechaModificacion, ModificadoPor
FROM dbo.PersonasProyectos
ORDER BY Nombre;

-- 3.2) Solicitantes o líderes sin mail (reciben avisos automáticos: completarlos
--      desde la pantalla Parámetros › Personas o con un UPDATE)
SELECT ID, Nombre, EsSolicitante, EsLider, EsDesarrollador, Activo
FROM dbo.PersonasProyectos
WHERE (EsSolicitante = 1 OR EsLider = 1)
  AND NULLIF(LTRIM(RTRIM(ISNULL(Mail, ''))), '') IS NULL
ORDER BY Nombre;
GO
