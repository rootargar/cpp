-- =====================================================
-- Script de Optimización para Sistema de Notificaciones
-- Sistema de Gestión Documental CPP
-- =====================================================
-- Este script agrega índices y optimizaciones opcionales
-- a la tabla Notificaciones para mejorar el rendimiento
-- =====================================================

USE CPP;
GO

-- =====================================================
-- 1. VERIFICAR ESTRUCTURA DE LA TABLA NOTIFICACIONES
-- =====================================================

-- Verificar que la tabla existe y tiene todos los campos necesarios
IF EXISTS (SELECT * FROM sys.tables WHERE name = 'Notificaciones')
BEGIN
    PRINT '✓ La tabla Notificaciones existe'

    -- Mostrar estructura actual
    SELECT
        COLUMN_NAME,
        DATA_TYPE,
        CHARACTER_MAXIMUM_LENGTH,
        IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'Notificaciones'
    ORDER BY ORDINAL_POSITION;
END
ELSE
BEGIN
    PRINT '✗ ERROR: La tabla Notificaciones no existe. Debe crearla primero.'
END
GO

-- =====================================================
-- 2. AGREGAR ÍNDICES PARA MEJORAR RENDIMIENTO
-- =====================================================

-- Índice para búsquedas por estado de envío y fecha programada
-- (Usado por procesar_notificaciones.php)
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Notificaciones_Enviado_FechaProgramada')
BEGIN
    CREATE INDEX IX_Notificaciones_Enviado_FechaProgramada
    ON Notificaciones (enviado, fecha_programada)
    INCLUDE (documento_id, tipo_evento, mensaje, destinatarios);

    PRINT '✓ Índice IX_Notificaciones_Enviado_FechaProgramada creado'
END
ELSE
    PRINT '○ Índice IX_Notificaciones_Enviado_FechaProgramada ya existe'
GO

-- Índice para búsquedas por documento
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Notificaciones_DocumentoId')
BEGIN
    CREATE INDEX IX_Notificaciones_DocumentoId
    ON Notificaciones (documento_id, fecha_programada DESC);

    PRINT '✓ Índice IX_Notificaciones_DocumentoId creado'
END
ELSE
    PRINT '○ Índice IX_Notificaciones_DocumentoId ya existe'
GO

-- Índice para búsquedas por tipo de evento
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Notificaciones_TipoEvento')
BEGIN
    CREATE INDEX IX_Notificaciones_TipoEvento
    ON Notificaciones (tipo_evento, enviado);

    PRINT '✓ Índice IX_Notificaciones_TipoEvento creado'
END
ELSE
    PRINT '○ Índice IX_Notificaciones_TipoEvento ya existe'
GO

-- =====================================================
-- 3. AGREGAR COLUMNA OPCIONAL PARA LOG DE ERRORES
-- =====================================================

-- Esta columna es OPCIONAL y permite registrar errores de envío
-- Si no la necesita, puede omitir esta sección

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'Notificaciones' AND COLUMN_NAME = 'error_envio')
BEGIN
    ALTER TABLE Notificaciones
    ADD error_envio NVARCHAR(500) NULL;

    PRINT '✓ Columna error_envio agregada'
END
ELSE
    PRINT '○ Columna error_envio ya existe'
GO

-- =====================================================
-- 4. AGREGAR COLUMNA OPCIONAL PARA INTENTOS DE REENVÍO
-- =====================================================

-- Esta columna es OPCIONAL y permite llevar control de reintentos
-- Si no la necesita, puede omitir esta sección

IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_NAME = 'Notificaciones' AND COLUMN_NAME = 'intentos_envio')
BEGIN
    ALTER TABLE Notificaciones
    ADD intentos_envio INT DEFAULT 0;

    PRINT '✓ Columna intentos_envio agregada'
END
ELSE
    PRINT '○ Columna intentos_envio ya existe'
GO

-- =====================================================
-- 5. ÍNDICE EN COLUMNA EMAIL DE USUARIOS
-- =====================================================

-- Optimizar búsquedas de emails en tabla Usuarios
IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = 'IX_Usuarios_Email')
BEGIN
    CREATE INDEX IX_Usuarios_Email
    ON Usuarios (email)
    WHERE email IS NOT NULL AND email != '';

    PRINT '✓ Índice IX_Usuarios_Email creado'
END
ELSE
    PRINT '○ Índice IX_Usuarios_Email ya existe'
GO

-- =====================================================
-- 6. VISTA PARA CONSULTAR NOTIFICACIONES CON INFORMACIÓN COMPLETA
-- =====================================================

-- Crear o reemplazar vista para facilitar consultas
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_NotificacionesCompletas')
BEGIN
    DROP VIEW vw_NotificacionesCompletas;
    PRINT '○ Vista vw_NotificacionesCompletas eliminada para recrearla'
END
GO

CREATE VIEW vw_NotificacionesCompletas AS
SELECT
    n.id,
    n.documento_id,
    d.codigo as codigo_documento,
    d.nombre as nombre_documento,
    d.estado as estado_documento,
    n.tipo_evento,
    n.fecha_programada,
    n.enviado,
    n.fecha_envio,
    n.destinatarios,
    n.mensaje,
    u.nombre as responsable_nombre,
    u.email as responsable_email,
    CASE
        WHEN n.enviado = 1 THEN 'Enviado'
        WHEN n.fecha_programada > GETDATE() THEN 'Programado'
        ELSE 'Pendiente'
    END as estado_notificacion
FROM Notificaciones n
INNER JOIN Documentos d ON n.documento_id = d.id
LEFT JOIN Usuarios u ON d.responsable_id = u.id;
GO

PRINT '✓ Vista vw_NotificacionesCompletas creada'
GO

-- =====================================================
-- 7. PROCEDIMIENTO ALMACENADO PARA LIMPIAR NOTIFICACIONES ANTIGUAS
-- =====================================================

-- Crear procedimiento para limpiar notificaciones enviadas antiguas
IF EXISTS (SELECT * FROM sys.procedures WHERE name = 'sp_LimpiarNotificacionesAntiguas')
BEGIN
    DROP PROCEDURE sp_LimpiarNotificacionesAntiguas;
END
GO

CREATE PROCEDURE sp_LimpiarNotificacionesAntiguas
    @DiasAntiguedad INT = 90  -- Por defecto, eliminar notificaciones enviadas hace más de 90 días
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @FechaLimite DATETIME;
    DECLARE @RegistrosEliminados INT;

    SET @FechaLimite = DATEADD(DAY, -@DiasAntiguedad, GETDATE());

    DELETE FROM Notificaciones
    WHERE enviado = 1
    AND fecha_envio < @FechaLimite;

    SET @RegistrosEliminados = @@ROWCOUNT;

    PRINT 'Notificaciones eliminadas: ' + CAST(@RegistrosEliminados AS VARCHAR(10));

    RETURN @RegistrosEliminados;
END
GO

PRINT '✓ Procedimiento sp_LimpiarNotificacionesAntiguas creado'
GO

-- =====================================================
-- 8. CONSULTAS ÚTILES PARA MONITOREO
-- =====================================================

PRINT ''
PRINT '========================================='
PRINT 'CONSULTAS ÚTILES PARA MONITOREO'
PRINT '========================================='
PRINT ''

-- Estadísticas de notificaciones
PRINT '-- Ver estadísticas de notificaciones:'
PRINT 'SELECT '
PRINT '    tipo_evento,'
PRINT '    COUNT(*) as Total,'
PRINT '    SUM(CASE WHEN enviado = 1 THEN 1 ELSE 0 END) as Enviadas,'
PRINT '    SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as Pendientes'
PRINT 'FROM Notificaciones'
PRINT 'GROUP BY tipo_evento;'
PRINT ''

-- Notificaciones pendientes
PRINT '-- Ver notificaciones pendientes:'
PRINT 'SELECT * FROM vw_NotificacionesCompletas'
PRINT 'WHERE enviado = 0'
PRINT 'ORDER BY fecha_programada;'
PRINT ''

-- Últimas notificaciones enviadas
PRINT '-- Ver últimas 10 notificaciones enviadas:'
PRINT 'SELECT TOP 10 * FROM vw_NotificacionesCompletas'
PRINT 'WHERE enviado = 1'
PRINT 'ORDER BY fecha_envio DESC;'
PRINT ''

-- Limpiar notificaciones antiguas
PRINT '-- Ejecutar limpieza de notificaciones antiguas (90 días):'
PRINT 'EXEC sp_LimpiarNotificacionesAntiguas @DiasAntiguedad = 90;'
PRINT ''

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================

PRINT ''
PRINT '========================================='
PRINT '✓ SCRIPT COMPLETADO EXITOSAMENTE'
PRINT '========================================='
PRINT ''
PRINT 'Resumen de optimizaciones aplicadas:'
PRINT '- Índices creados para mejorar consultas'
PRINT '- Vista vw_NotificacionesCompletas creada'
PRINT '- Procedimiento de limpieza creado'
PRINT '- Sistema listo para uso'
PRINT ''
