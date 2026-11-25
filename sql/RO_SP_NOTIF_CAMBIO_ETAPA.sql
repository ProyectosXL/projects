/*
    SP: dbo.RO_SP_NOTIF_CAMBIO_ETAPA

    Qué hace:
      Envía un mail (Database Mail, sp_send_dbmail) cuando un proyecto cambia
      de EtapaProyecto a través de la app (adminProyectos), a Solicitante y
      LiderProyecto, con copia fija a los destinatarios configurados en
      RO_T_DESTINATARIOS_MAIL para el tipo de notificación
      'CAMBIO_ETAPA_PROYECTO' (se administra desde el panel de
      adminNotificaciones, igual que AVISOS_ADMIN_PROYECTOS). A diferencia de
      RO_SP_NOTIF_ADMIN_PROYECTOS, este SP NO resuelve nombre -> mail contra
      dbo.PersonasProyectos para Solicitante/LiderProyecto: esos mails ya
      vienen resueltos desde PHP (Proyecto::resolverMailPorNombre(), en
      adminProyectos/class/proyecto.php, que busca en PersonasProyectos sin
      importar si la persona está activa ni qué roles tiene) y es PHP quien
      decide a quién notificar.

      Nota: el cambio a PersonasProyectos solo tocó este encabezado; el código
      del SP no cambió, así que no hace falta volver a ejecutar este script.

    Quién lo llama: Proyecto::actualizarProyecto() (adminProyectos/class/proyecto.php),
    justo después de actualizar EtapaProyecto en HistorialProyectos, cuando la
    etapa nueva es distinta de la anterior. Cubre los 3 flujos de la app que
    cambian etapa: aprobar/rechazar validación cliente
    (api/actualizarEtapaCliente.php) y edición manual (editarProyecto.php).

    El job diario de aceptación tácita (RO_SP_NOTIF_ADMIN_PROYECTOS) actualiza
    HistorialProyectos directo en SQL, sin pasar por PHP, así que no dispara
    este SP y no genera mails duplicados.

    Parámetro opcional @EmailPrueba: si se pasa, no envía el mail de negocio;
    solo valida el profile de Database Mail y el template (mismo mecanismo de
    "Probar" que RO_SP_NOTIF_ADMIN_PROYECTOS).
*/

-- ============================================================================
-- 1) Configuración de la notificación (idempotente)
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM RO_T_CONFIGURACION_MAIL WHERE TIPO_NOTIFICACION = 'CAMBIO_ETAPA_PROYECTO')
BEGIN
    INSERT INTO RO_T_CONFIGURACION_MAIL (TIPO_NOTIFICACION, PROFILE_NAME, EMAIL_SUBJECT)
    VALUES ('CAMBIO_ETAPA_PROYECTO', 'sistemas', 'Cambio de estado de proyecto');
END
GO

-- ============================================================================
-- 2) Stored Procedure
-- ============================================================================
CREATE OR ALTER PROCEDURE dbo.RO_SP_NOTIF_CAMBIO_ETAPA
    @IdProyecto      INT,
    @NombreProyecto  NVARCHAR(400),
    @EstadoNuevo     NVARCHAR(100),
    @MailSolicitante NVARCHAR(200) = NULL,
    @MailLider       NVARCHAR(200) = NULL,
    @EmailPrueba     NVARCHAR(200) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Profile NVARCHAR(100);
    DECLARE @CopiaFija NVARCHAR(MAX);

    BEGIN TRY
        SELECT @Profile = PROFILE_NAME
        FROM RO_T_CONFIGURACION_MAIL
        WHERE TIPO_NOTIFICACION = 'CAMBIO_ETAPA_PROYECTO';

        -- Copia fija configurable desde el admin de notificaciones
        -- (RO_T_DESTINATARIOS_MAIL, mismo mecanismo que usa AVISOS_ADMIN_PROYECTOS)
        SELECT @CopiaFija = COALESCE(@CopiaFija + ';', '') + EMAIL
        FROM RO_T_DESTINATARIOS_MAIL
        WHERE TIPO_NOTIFICACION = 'CAMBIO_ETAPA_PROYECTO' AND ES_COPIA = 1 AND ACTIVO = 1;
    END TRY
    BEGIN CATCH
        RETURN; -- sin configuración no se envía nada
    END CATCH

    IF @Profile IS NULL
        RETURN;

    -- ------------------------------------------------------------------
    -- Modo prueba: no depende de los demás parámetros, solo valida el envío
    -- ------------------------------------------------------------------
    IF @EmailPrueba IS NOT NULL
    BEGIN
        DECLARE @BodyPrueba NVARCHAR(MAX);
        SET @BodyPrueba =
            N'<html><body style="font-family: Arial, sans-serif;">' +
            N'<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:5px;">' +
            N'<h2 style="color:#856404;margin-top:0;">Prueba de Notificacion - CAMBIO_ETAPA_PROYECTO</h2>' +
            N'<p>Este es un correo de prueba de <b>RO_SP_NOTIF_CAMBIO_ETAPA</b>. Fecha/Hora: ' +
            CONVERT(NVARCHAR(30), GETDATE(), 120) + N'</p>' +
            N'<p>Si recibiste este correo, el profile de Database Mail (' + @Profile +
            N') y la configuración de RO_T_CONFIGURACION_MAIL están funcionando correctamente.</p>' +
            N'</div></body></html>';

        EXEC msdb.dbo.sp_send_dbmail
            @profile_name = @Profile,
            @recipients = @EmailPrueba,
            @subject = N'[PRUEBA] Cambio de estado de proyecto',
            @body = @BodyPrueba,
            @body_format = 'HTML';

        RETURN;
    END

    -- ------------------------------------------------------------------
    -- Armar destinatarios (Solicitante + LiderProyecto, sin duplicar)
    -- ------------------------------------------------------------------
    SET @MailSolicitante = NULLIF(LTRIM(RTRIM(@MailSolicitante)), '');
    SET @MailLider = NULLIF(LTRIM(RTRIM(@MailLider)), '');

    IF @MailLider IS NOT NULL AND @MailSolicitante IS NOT NULL
       AND LOWER(@MailLider) = LOWER(@MailSolicitante)
        SET @MailLider = NULL;

    DECLARE @Recipients NVARCHAR(MAX);
    SET @Recipients = @MailSolicitante;
    SET @Recipients = CASE
        WHEN @Recipients IS NULL THEN @MailLider
        WHEN @MailLider IS NULL THEN @Recipients
        ELSE @Recipients + N';' + @MailLider
    END;

    -- Si no se pudo resolver ni Solicitante ni LiderProyecto, al menos que llegue
    -- a la copia fija (sin copia adicional, ya que sería el propio destinatario)
    DECLARE @CopyRecipients NVARCHAR(MAX);
    IF @Recipients IS NULL
        SET @Recipients = @CopiaFija;
    ELSE
        SET @CopyRecipients = @CopiaFija;

    IF @Recipients IS NULL
        RETURN; -- no se pudo resolver ningún mail ni hay copia fija configurada

    -- ------------------------------------------------------------------
    -- Asunto y cuerpo
    -- ------------------------------------------------------------------
    DECLARE @Subject NVARCHAR(255), @Body NVARCHAR(MAX);

    SET @Subject = N'[Proyecto #' + CAST(@IdProyecto AS NVARCHAR(10)) + N'] ' +
                   @NombreProyecto + N' - Nuevo estado: ' + @EstadoNuevo;

    SET @Body =
        N'<html><body style="font-family: Arial, sans-serif; background-color:#f8f9fa; margin:0; padding:20px;">' +
        N'<div style="max-width:600px;margin:0 auto;background-color:white;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.1);">' +
        N'<div style="background:linear-gradient(135deg,#4e54c8,#8f94fb);color:white;padding:25px;border-radius:10px 10px 0 0;text-align:center;">' +
        N'<h2 style="margin:0;">Cambio de estado de proyecto</h2>' +
        N'<p style="margin:8px 0 0 0;opacity:0.9;">Nuevo estado: ' + @EstadoNuevo + N'</p>' +
        N'</div>' +
        N'<div style="padding:25px;">' +
        N'<p>El proyecto <b>' + @NombreProyecto + N'</b> cambió de estado.</p>' +
        N'<table style="width:100%;border-collapse:collapse;margin-top:10px;">' +
        N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Proyecto:</td>' +
        N'<td style="padding:6px 0;">' + @NombreProyecto + N'</td></tr>' +
        N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">ID:</td>' +
        N'<td style="padding:6px 0;">' + CAST(@IdProyecto AS NVARCHAR(10)) + N'</td></tr>' +
        N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Nuevo estado:</td>' +
        N'<td style="padding:6px 0;">' + @EstadoNuevo + N'</td></tr>' +
        N'<tr><td style="padding:6px 0;font-weight:bold;color:#666;">Fecha del cambio:</td>' +
        N'<td style="padding:6px 0;">' + CONVERT(NVARCHAR(20), GETDATE(), 103) + N' ' + CONVERT(NVARCHAR(8), GETDATE(), 108) + N'</td></tr>' +
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
        -- Un fallo de mail no debe propagar un error hacia quien llamó el SP
        PRINT 'RO_SP_NOTIF_CAMBIO_ETAPA: error enviando mail para IdProyecto=' + CAST(@IdProyecto AS NVARCHAR(10))
            + ' - ' + ERROR_MESSAGE();
    END CATCH
END
GO
