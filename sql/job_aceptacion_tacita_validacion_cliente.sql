/*
    Job de SQL Server Agent: aceptación tácita de la etapa "Validación cliente"

    Instalación manual en SSMS (Agent no se instala ni se dispara desde la app PHP):
      1. Reemplazar [NOMBRE_BASE_DE_DATOS] por la base de datos donde vive la tabla
         HistorialProyectos (variable de entorno DATABASE_CENTRAL de la app).
      2. Ejecutar antes v:\projects\sql\RO_SP_NOTIF_ADMIN_PROYECTOS.sql contra esa
         misma base, para crear el SP dbo.RO_SP_NOTIF_ADMIN_PROYECTOS.
      3. Ejecutar este script completo contra la instancia de SQL Server que aloja
         dicha base (msdb debe estar disponible).
      4. El job corre una vez por día y ejecuta dbo.RO_SP_NOTIF_ADMIN_PROYECTOS, que
         hace la transición de etapa y envía el mail de aviso (Database Mail).

    IMPORTANTE: el número "10" usado dentro del SP debe coincidir siempre con la
    constante Proyecto::DIAS_VENTANA_ACEPTACION definida en
    adminProyectos/class/proyecto.php. Si se cambia uno, hay que cambiar el otro.
*/

USE msdb;
GO

IF EXISTS (SELECT 1 FROM msdb.dbo.sysjobs WHERE name = N'Aceptacion tacita - Validacion cliente')
BEGIN
    EXEC msdb.dbo.sp_delete_job @job_name = N'Aceptacion tacita - Validacion cliente';
END
GO

EXEC msdb.dbo.sp_add_job
    @job_name = N'Aceptacion tacita - Validacion cliente',
    @description = N'Avanza a Implementacion los proyectos en Validacion cliente cuya ventana de 10 dias corridos vencio sin respuesta del cliente.',
    @enabled = 1,
    @owner_login_name = N'sa';
GO

EXEC msdb.dbo.sp_add_jobstep
    @job_name = N'Aceptacion tacita - Validacion cliente',
    @step_name = N'Aplicar aceptacion tacita',
    @subsystem = N'TSQL',
    @database_name = N'[NOMBRE_BASE_DE_DATOS]',
    @command = N'EXEC dbo.RO_SP_NOTIF_ADMIN_PROYECTOS;';
GO

EXEC msdb.dbo.sp_add_schedule
    @schedule_name = N'Diario - Aceptacion tacita',
    @freq_type = 4,          -- diario
    @freq_interval = 1,      -- cada 1 día
    @active_start_time = 060000; -- 06:00:00
GO

EXEC msdb.dbo.sp_attach_schedule
    @job_name = N'Aceptacion tacita - Validacion cliente',
    @schedule_name = N'Diario - Aceptacion tacita';
GO

EXEC msdb.dbo.sp_add_jobserver
    @job_name = N'Aceptacion tacita - Validacion cliente',
    @server_name = N'(LOCAL)';
GO
