/*
    SP: dbo.RO_SP_NOTIF_ADMIN_PROYECTOS

    Reemplaza el UPDATE inline del job "Aceptacion tacita - Validacion cliente"
    (v:\projects\sql\job_aceptacion_tacita_validacion_cliente.sql).

    Qué hace:
      1. Pasa a "Implementación" los proyectos en "Validación cliente" cuya ventana
         de 10 días corridos desde FechaEntregaCliente ya venció sin respuesta del
         cliente (aceptación tácita) - misma condición que ya usaba el job.
      2. Por cada proyecto recién transicionado, envía un mail (Database Mail,
         sp_send_dbmail) al Solicitante, con copia fija a los destinatarios
         configurados en RO_T_DESTINATARIOS_MAIL para el tipo de notificación
         'AVISOS_ADMIN_PROYECTOS' (incluye ramiro.orozco@xl.com.ar) y, si se puede
         resolver, copia también al LiderProyecto.
      3. El profile de Database Mail y el asunto salen de RO_T_CONFIGURACION_MAIL
         (mismo mecanismo que usa adminNotificaciones para el resto de las
         notificaciones del sistema).

    Resolución de mails: tanto Solicitante como LiderProyecto son texto libre en
    HistorialProyectos; se intenta encontrar el mail matcheando ese texto contra
    RO_USUARIOS_CARGA_PROYECTOS.NOMBRE_APELLIDO (misma tabla que usa
    Proyecto::obtenerSolicitantes() en adminProyectos/class/proyecto.php). Si no
    hay coincidencia para el líder, el mail se envía igual, sin ese CC.

    IMPORTANTE: el número "10" debe coincidir siempre con la constante
    Proyecto::DIAS_VENTANA_ACEPTACION (adminProyectos/class/proyecto.php).

    Parámetro opcional @EmailPrueba: si se pasa, NO se toca HistorialProyectos;
    solo se envía un mail de prueba a esa dirección para validar el profile de
    Database Mail y el template (mismo mecanismo de "Probar" que usa el panel de
    adminNotificaciones para otras notificaciones).
*/

-- ============================================================================
-- 1) Configuración de la notificación (idempotente)
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM RO_T_CONFIGURACION_MAIL WHERE TIPO_NOTIFICACION = 'AVISOS_ADMIN_PROYECTOS')
BEGIN
    INSERT INTO RO_T_CONFIGURACION_MAIL (TIPO_NOTIFICACION, PROFILE_NAME, EMAIL_SUBJECT)
    VALUES ('AVISOS_ADMIN_PROYECTOS', 'sistemas', 'Aceptación tácita de proyecto - Validación cliente');
END
GO

IF NOT EXISTS (
    SELECT 1 FROM RO_T_DESTINATARIOS_MAIL
    WHERE TIPO_NOTIFICACION = 'AVISOS_ADMIN_PROYECTOS' AND EMAIL = 'ramiro.orozco@xl.com.ar'
)
BEGIN
    INSERT INTO RO_T_DESTINATARIOS_MAIL (TIPO_NOTIFICACION, EMAIL, ES_COPIA, ACTIVO)
    VALUES ('AVISOS_ADMIN_PROYECTOS', 'ramiro.orozco@xl.com.ar', 1, 1);
END
GO

-- ============================================================================
-- 2) Stored Procedure
-- ============================================================================
CREATE OR ALTER PROCEDURE dbo.RO_SP_NOTIF_ADMIN_PROYECTOS
    @EmailPrueba NVARCHAR(200) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Profile NVARCHAR(100);
    DECLARE @SubjectBase NVARCHAR(200);
    DECLARE @CopiaFija NVARCHAR(MAX);

    BEGIN TRY
        SELECT @Profile = PROFILE_NAME, @SubjectBase = EMAIL_SUBJECT
        FROM RO_T_CONFIGURACION_MAIL
        WHERE TIPO_NOTIFICACION = 'AVISOS_ADMIN_PROYECTOS';

        SELECT @CopiaFija = COALESCE(@CopiaFija + ';', '') + EMAIL
        FROM RO_T_DESTINATARIOS_MAIL
        WHERE TIPO_NOTIFICACION = 'AVISOS_ADMIN_PROYECTOS' AND ES_COPIA = 1 AND ACTIVO = 1;
    END TRY
    BEGIN CATCH
        RETURN; -- sin configuración no se envía nada
    END CATCH

    IF @Profile IS NULL
        RETURN;

    -- ------------------------------------------------------------------
    -- Modo prueba: no toca datos, solo valida el envío de mail
    -- ------------------------------------------------------------------
    IF @EmailPrueba IS NOT NULL
    BEGIN
        DECLARE @BodyPrueba NVARCHAR(MAX);
        SET @BodyPrueba =
            N'<html><body style="font-family: Arial, sans-serif;">' +
            N'<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:5px;">' +
            N'<h2 style="color:#856404;margin-top:0;">Prueba de Notificacion - AVISOS_ADMIN_PROYECTOS</h2>' +
            N'<p>Este es un correo de prueba de <b>RO_SP_NOTIF_ADMIN_PROYECTOS</b>. Fecha/Hora: ' +
            CONVERT(NVARCHAR(30), GETDATE(), 120) + N'</p>' +
            N'<p>Si recibiste este correo, el profile de Database Mail (' + @Profile +
            N') y la configuración de RO_T_CONFIGURACION_MAIL están funcionando correctamente.</p>' +
            N'</div></body></html>';

        EXEC msdb.dbo.sp_send_dbmail
            @profile_name = @Profile,
            @recipients = @EmailPrueba,
            @subject = N'[PRUEBA] ' + @SubjectBase,
            @body = @BodyPrueba,
            @body_format = 'HTML';

        RETURN;
    END

    -- ------------------------------------------------------------------
    -- 1) Transición de etapa (aceptación tácita)
    -- ------------------------------------------------------------------
    DECLARE @Afectados TABLE (
        RowId INT IDENTITY(1,1) PRIMARY KEY,
        ID INT,
        Desarrollo NVARCHAR(400),
        Solicitante NVARCHAR(200),
        LiderProyecto NVARCHAR(200),
        FechaEntregaCliente DATETIME
    );

    UPDATE HistorialProyectos
    SET EtapaProyecto = N'Implementación',
        FechaAprobacionCliente = GETDATE(),
        TipoAprobacionCliente = 'TACITA',
        UsuarioAprobacionCliente = 'SISTEMA'
    OUTPUT inserted.ID, inserted.Desarrollo, inserted.Solicitante,
           inserted.LiderProyecto, inserted.FechaEntregaCliente
    INTO @Afectados (ID, Desarrollo, Solicitante, LiderProyecto, FechaEntregaCliente)
    WHERE EtapaProyecto = N'Validación cliente'
      AND FechaAprobacionCliente IS NULL
      AND FechaEntregaCliente IS NOT NULL
      AND DATEADD(DAY, 10, FechaEntregaCliente) <= GETDATE(); -- 10 = Proyecto::DIAS_VENTANA_ACEPTACION

    IF NOT EXISTS (SELECT 1 FROM @Afectados)
        RETURN;

    -- ------------------------------------------------------------------
    -- 2) Un mail por proyecto afectado (cada uno tiene su propio
    --    solicitante/líder, así que no se puede mandar un único mail agregado)
    -- ------------------------------------------------------------------
    DECLARE @ID INT, @Desarrollo NVARCHAR(400), @Solicitante NVARCHAR(200),
            @LiderProyecto NVARCHAR(200), @FechaEntregaCliente DATETIME;
    DECLARE @MailSolicitante NVARCHAR(200), @MailLider NVARCHAR(200);
    DECLARE @Recipients NVARCHAR(MAX), @CopyRecipients NVARCHAR(MAX);
    DECLARE @Subject NVARCHAR(255), @Body NVARCHAR(MAX), @DiasTranscurridos INT;

    DECLARE curAfectados CURSOR LOCAL FAST_FORWARD FOR
        SELECT ID, Desarrollo, Solicitante, LiderProyecto, FechaEntregaCliente
        FROM @Afectados
        ORDER BY RowId;

    OPEN curAfectados;
    FETCH NEXT FROM curAfectados INTO @ID, @Desarrollo, @Solicitante, @LiderProyecto, @FechaEntregaCliente;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        SET @MailSolicitante = NULL;
        SET @MailLider = NULL;

        SELECT @MailSolicitante = MAIL
        FROM RO_USUARIOS_CARGA_PROYECTOS
        WHERE LTRIM(RTRIM(NOMBRE_APELLIDO)) COLLATE DATABASE_DEFAULT
            = LTRIM(RTRIM(@Solicitante)) COLLATE DATABASE_DEFAULT;

        IF @LiderProyecto IS NOT NULL AND LTRIM(RTRIM(@LiderProyecto)) <> ''
        BEGIN
            SELECT @MailLider = MAIL
            FROM RO_USUARIOS_CARGA_PROYECTOS
            WHERE LTRIM(RTRIM(NOMBRE_APELLIDO)) COLLATE DATABASE_DEFAULT
                = LTRIM(RTRIM(@LiderProyecto)) COLLATE DATABASE_DEFAULT;
        END

        -- Si no se pudo resolver el mail del solicitante, al menos que llegue a la copia fija
        SET @Recipients = COALESCE(@MailSolicitante, @CopiaFija);
        SET @CopyRecipients = @CopiaFija + CASE WHEN @MailLider IS NOT NULL THEN ';' + @MailLider ELSE '' END;

        SET @DiasTranscurridos = DATEDIFF(DAY, @FechaEntregaCliente, GETDATE());
        SET @Subject = @SubjectBase + N' - ' + @Desarrollo;

        SET @Body =
            N'<html><body style="font-family: Arial, sans-serif; background-color:#f8f9fa; margin:0; padding:20px;">' +
            N'<div style="max-width:600px;margin:0 auto;background-color:white;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.1);">' +
            N'<div style="background:linear-gradient(135deg,#28a745,#20c997);color:white;padding:25px;border-radius:10px 10px 0 0;text-align:center;">' +
            N'<h2 style="margin:0;">Aceptación tácita de proyecto</h2>' +
            N'<p style="margin:8px 0 0 0;opacity:0.9;">Validación cliente &rarr; Implementación</p>' +
            N'</div>' +
            N'<div style="padding:25px;">' +
            N'<p>El proyecto <b>' + @Desarrollo + N'</b> pasó automáticamente de <b>Validación cliente</b> a ' +
            N'<b>Implementación</b> por vencimiento de la ventana de 10 días corridos sin respuesta del cliente.</p>' +
            N'<table style="width:100%;border-collapse:collapse;margin-top:10px;">' +
            N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Fecha entrega al cliente:</td>' +
            N'<td style="padding:6px 0;">' + CONVERT(NVARCHAR(20), @FechaEntregaCliente, 103) + N'</td></tr>' +
            N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Días transcurridos:</td>' +
            N'<td style="padding:6px 0;">' + CAST(@DiasTranscurridos AS NVARCHAR(10)) + N'</td></tr>' +
            N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Fecha aceptación tácita:</td>' +
            N'<td style="padding:6px 0;">' + CONVERT(NVARCHAR(20), GETDATE(), 103) + N'</td></tr>' +
            N'</table>' +
            N'<p style="color:#666;font-size:13px;margin-top:20px;">Este correo se generó automáticamente. Por favor no responda a esta dirección.</p>' +
            N'</div></div></body></html>';

        BEGIN TRY
            EXEC msdb.dbo.sp_send_dbmail
                @profile_name = @Profile,
                @recipients = @Recipients,
                @copy_recipients = @CopyRecipients,
                @subject = @Subject,
                @body = @Body,
                @body_format = 'HTML';
        END TRY
        BEGIN CATCH
            -- Un fallo de mail para este proyecto no debe frenar el resto del loop
            PRINT 'RO_SP_NOTIF_ADMIN_PROYECTOS: error enviando mail para ID=' + CAST(@ID AS NVARCHAR(10))
                + ' - ' + ERROR_MESSAGE();
        END CATCH

        FETCH NEXT FROM curAfectados INTO @ID, @Desarrollo, @Solicitante, @LiderProyecto, @FechaEntregaCliente;
    END

    CLOSE curAfectados;
    DEALLOCATE curAfectados;
END
GO
