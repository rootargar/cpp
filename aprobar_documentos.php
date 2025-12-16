<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Administradores, editores y aprobadores pueden aprobar documentos
requiereRol([1, 2, 4], 'No tiene permisos para aprobar documentos');

$mensaje = '';
$tipo_mensaje = '';

// Procesar aprobación o rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $documento_id = $_POST['documento_id'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    
    if (empty($documento_id) || empty($accion)) {
        $mensaje = 'Datos incompletos';
        $tipo_mensaje = 'error';
    } else {
        // Obtener estado anterior
        $sqlEstadoAnterior = "SELECT nombre, codigo, estado FROM Documentos WHERE id = ?";
        $stmtEstadoAnterior = sqlsrv_query($conn, $sqlEstadoAnterior, array($documento_id));
        $docAnterior = sqlsrv_fetch_array($stmtEstadoAnterior, SQLSRV_FETCH_ASSOC);
        $estado_anterior = $docAnterior['estado'] ?? '';
        $nombre_doc = $docAnterior['nombre'] ?? '';
        $codigo_doc = $docAnterior['codigo'] ?? '';
        
        if ($accion === 'aprobar') {
            $nuevo_estado = 'Aprobado';
            $descripcion_accion = "Documento aprobado: $nombre_doc ($codigo_doc)";
            $mensaje_exito = 'Documento aprobado exitosamente';
        } elseif ($accion === 'rechazar') {
            $nuevo_estado = 'Rechazado';
            $descripcion_accion = "Documento rechazado: $nombre_doc ($codigo_doc)";
            if (!empty($comentario)) {
                $descripcion_accion .= " - Motivo: $comentario";
            }
            $mensaje_exito = 'Documento rechazado';
        } else {
            $mensaje = 'Acción no válida';
            $tipo_mensaje = 'error';
        }
        
        if (empty($mensaje)) {
            // Actualizar estado del documento
            $sqlUpdate = "UPDATE Documentos 
                         SET estado = ?, fecha_modificacion = GETDATE() 
                         WHERE id = ?";
            $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, array($nuevo_estado, $documento_id));
            
            if ($stmtUpdate) {
                $mensaje = $mensaje_exito;
                $tipo_mensaje = 'success';
                
                // Registrar en auditoría
                registrarAuditoria('Cambio de Estado', $descripcion_accion, 'Documentos', $documento_id);
                
                // Crear notificación para el responsable
                if ($estado_anterior != $nuevo_estado) {
                    $mensaje_notif = "El documento ha sido $nuevo_estado";
                    if (!empty($comentario)) {
                        $mensaje_notif .= " - $comentario";
                    }
                    
                    $sqlNotif = "INSERT INTO Notificaciones 
                                (documento_id, tipo_evento, fecha_programada, enviado, mensaje)
                                VALUES (?, 'Cambio Estado', GETDATE(), 0, ?)";
                    sqlsrv_query($conn, $sqlNotif, array($documento_id, $mensaje_notif));
                }
            } else {
                $mensaje = 'Error al actualizar el documento';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener documentos pendientes de aprobación
$sql = "SELECT
    d.id,
    d.nombre,
    d.codigo,
    d.categoria,
    d.area,
    d.departamento,
    d.descripcion,
    d.estado,
    d.fecha_creacion,
    d.fecha_modificacion,
    d.fecha_vencimiento,
    u.nombre as responsable_nombre,
    u.email as responsable_email,
    (SELECT TOP 1 numero_version FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as version_actual,
    (SELECT TOP 1 fecha_subida FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as fecha_ultima_version,
    (SELECT COUNT(*) FROM VersionesDocumento WHERE documento_id = d.id) as total_versiones
FROM Documentos d
INNER JOIN Usuarios u ON d.responsable_id = u.id
WHERE d.estado = 'Pendiente' AND d.activo = 1
ORDER BY d.fecha_creacion ASC";

$stmtPendientes = sqlsrv_query($conn, $sql);

// Obtener documentos recientemente procesados (últimos 10)
$sqlRecientes = "SELECT 
    d.id,
    d.nombre,
    d.codigo,
    d.estado,
    d.fecha_modificacion,
    u.nombre as responsable_nombre
FROM Documentos d
INNER JOIN Usuarios u ON d.responsable_id = u.id
WHERE d.estado IN ('Aprobado', 'Rechazado') AND d.activo = 1
ORDER BY d.fecha_modificacion DESC
OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY";

$stmtRecientes = sqlsrv_query($conn, $sqlRecientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobar Documentos - Sistema de Gestión Documental</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #027be3;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
        }
        
        .user-info {
            text-align: right;
            color: #666;
            font-size: 14px;
        }
        
        .nav-links {
            margin-bottom: 20px;
        }
        
        .nav-links a {
            color: #027be3;
            text-decoration: none;
            margin-right: 15px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #027be3;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #027be3;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .document-card {
            background: #f9f9f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .document-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .document-title {
            flex: 1;
        }
        
        .document-title h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .document-title .code {
            color: #027be3;
            font-weight: 600;
            font-size: 14px;
        }
        
        .document-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            font-size: 14px;
        }
        
        .meta-item label {
            color: #666;
            font-size: 12px;
            display: block;
            margin-bottom: 3px;
        }
        
        .meta-item span {
            color: #333;
            font-weight: 500;
        }
        
        .document-description {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        
        .version-info {
            background: #e6f3ff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0066cc;
        }
        
        .approval-form {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border: 2px dashed #e2e8f0;
        }
        
        .approval-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-height: 80px;
            margin-bottom: 10px;
            resize: vertical;
        }
        
        .approval-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-info {
            background: #4299e1;
            color: white;
        }
        
        .btn-info:hover {
            background: #3182ce;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pendiente {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .badge-aprobado {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-rechazado {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #48bb78;
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        table th {
            background: #f7fafc;
            color: #2d3748;
            font-weight: 600;
        }
        
        table tr:hover {
            background: #f7fafc;
        }

        /* Responsive para pantallas pequeñas (laptop/tablet) */
        @media screen and (max-width: 1200px) {
            .hide-on-small {
                display: none !important;
            }

            .document-description {
                display: none !important;
            }

            .btn-small {
                padding: 5px 8px;
                font-size: 11px;
            }
        }

        @media screen and (max-width: 992px) {
            .stats-bar {
                grid-template-columns: 1fr;
            }

            .document-meta {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            table th,
            table td {
                padding: 8px;
                font-size: 13px;
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 15px;
                overflow-x: auto;
            }

            .header h1 {
                font-size: 20px;
            }

            .document-card {
                padding: 15px;
            }

            .approval-actions {
                flex-direction: column;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Aprobar Documentos</h1>
            <div class="user-info">
                <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
                <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
            </div>
        </div>
        
        <div class="nav-links">
            <a href="index.php">← Volver al Inicio</a>
            <a href="principal.php">Ver Documentos Aprobados</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Contar documentos pendientes
        $sqlCount = "SELECT COUNT(*) as total FROM Documentos WHERE estado = 'Pendiente' AND activo = 1";
        $stmtCount = sqlsrv_query($conn, $sqlCount);
        $countResult = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        $total_pendientes = $countResult['total'];
        ?>
        
        <div class="stats-bar">
            <div class="stat-card">
                <div class="number"><?php echo $total_pendientes; ?></div>
                <div class="label">Documentos Pendientes</div>
            </div>
        </div>
        
        <div class="section">
            <h2>Documentos Pendientes de Aprobación</h2>
            
            <?php if ($stmtPendientes && sqlsrv_has_rows($stmtPendientes)): ?>
                <?php while ($doc = sqlsrv_fetch_array($stmtPendientes, SQLSRV_FETCH_ASSOC)): ?>
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-title">
                                <h3><?php echo htmlspecialchars($doc['nombre']); ?></h3>
                                <span class="code"><?php echo htmlspecialchars($doc['codigo']); ?></span>
                            </div>
                            <a href="ver_documento.php?id=<?php echo $doc['id']; ?>" 
                               class="btn btn-info btn-small" 
                               target="_blank">
                                Ver Completo
                            </a>
                        </div>
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <label>Categoría:</label>
                                <span><?php echo htmlspecialchars($doc['categoria'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Área:</label>
                                <span><?php echo htmlspecialchars($doc['area'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Departamento:</label>
                                <span><?php echo htmlspecialchars($doc['departamento'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Responsable:</label>
                                <span><?php echo htmlspecialchars($doc['responsable_nombre']); ?></span>
                            </div>
                            <div class="meta-item">
                                <label>Fecha Creación:</label>
                                <span>
                                    <?php 
                                    echo $doc['fecha_creacion'] ? date('d/m/Y', strtotime($doc['fecha_creacion'])) : '-';
                                    ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($doc['total_versiones'] > 0): ?>
                            <div class="version-info">
                                📄 Versión actual: <strong>v<?php echo htmlspecialchars($doc['version_actual']); ?></strong>
                                (<?php echo $doc['total_versiones']; ?> versión<?php echo $doc['total_versiones'] != 1 ? 'es' : ''; ?> subida<?php echo $doc['total_versiones'] != 1 ? 's' : ''; ?>)
                                <?php if ($doc['fecha_ultima_version']): ?>
                                    - Última actualización: <?php echo date('d/m/Y H:i', strtotime($doc['fecha_ultima_version'])); ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="version-info" style="background: #fff3cd; color: #856404;">
                                ⚠️ Este documento aún no tiene versiones subidas
                            </div>
                        <?php endif; ?>
                        
                        <div class="approval-form">
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="documento_id" value="<?php echo $doc['id']; ?>">
                                
                                <textarea 
                                    name="comentario" 
                                    placeholder="Comentario u observaciones (opcional para aprobación, recomendado para rechazo)..."
                                ></textarea>
                                
                                <div class="approval-actions">
                                    <button 
                                        type="submit" 
                                        name="accion" 
                                        value="aprobar" 
                                        class="btn btn-success"
                                        onclick="return confirm('¿Confirmar la aprobación de este documento?');"
                                    >
                                        ✓ Aprobar
                                    </button>
                                    <button 
                                        type="submit" 
                                        name="accion" 
                                        value="rechazar" 
                                        class="btn btn-danger"
                                        onclick="return confirm('¿Confirmar el rechazo de este documento?');"
                                    >
                                        ✗ Rechazar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>✓ ¡Excelente!</h3>
                    <p>No hay documentos pendientes de aprobación</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($stmtRecientes && sqlsrv_has_rows($stmtRecientes)): ?>
            <div class="section">
                <h2>Documentos Procesados Recientemente</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Fecha de Decisión</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = sqlsrv_fetch_array($stmtRecientes, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($doc['nombre']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($doc['estado']); ?>">
                                        <?php echo htmlspecialchars($doc['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    echo $doc['fecha_modificacion'] ? date('d/m/Y H:i', strtotime($doc['fecha_modificacion'])) : '-';
                                    ?>
                                </td>
                                <td>
                                    <a href="ver_documento.php?id=<?php echo $doc['id']; ?>"
                                       class="btn btn-info btn-small">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
