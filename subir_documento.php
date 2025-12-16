<?php
require_once 'verificar_login.php';
require_once 'conexion.php';
require_once 'config.php';
require_once 'email_functions.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Administradores, editores y aprobadores pueden subir documentos
requiereRol([1, 2, 4], 'No tiene permisos para subir documentos');

$mensaje = '';
$tipo_mensaje = '';
$documento_id = $_GET['id'] ?? '';

if (empty($documento_id)) {
    header('Location: documentos.php');
    exit();
}

// Obtener información del documento
$sqlDoc = "SELECT d.*, u.nombre as responsable_nombre 
           FROM Documentos d
           INNER JOIN Usuarios u ON d.responsable_id = u.id
           WHERE d.id = ? AND d.activo = 1";
$stmtDoc = sqlsrv_query($conn, $sqlDoc, array($documento_id));
$documento = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);

if (!$documento) {
    header('Location: documentos.php');
    exit();
}

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $comentario = trim($_POST['comentario'] ?? '');
    $archivo = $_FILES['archivo'];
    
    // Validar que se haya subido un archivo
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $mensaje = 'Error al subir el archivo';
        $tipo_mensaje = 'error';
    } 
    // Validar extensión
    elseif (!validarExtension($archivo['name'])) {
        $mensaje = 'Solo se permiten archivos PDF';
        $tipo_mensaje = 'error';
    }
    // Validar tamaño
    elseif (!validarTamanoArchivo($archivo['size'])) {
        $mensaje = 'El archivo excede el tamaño máximo permitido de ' . obtenerTamanoMaximo();
        $tipo_mensaje = 'error';
    }
    // Validar tipo MIME
    elseif (!validarMimeType($archivo['type'])) {
        $mensaje = 'Tipo de archivo no válido';
        $tipo_mensaje = 'error';
    }
    else {
        // Obtener la última versión
        $sqlUltimaVersion = "SELECT TOP 1 numero_version FROM VersionesDocumento 
                             WHERE documento_id = ? 
                             ORDER BY CAST(numero_version AS FLOAT) DESC";
        $stmtVersion = sqlsrv_query($conn, $sqlUltimaVersion, array($documento_id));
        $ultimaVersion = sqlsrv_fetch_array($stmtVersion, SQLSRV_FETCH_ASSOC);
        
        // Calcular nueva versión
        if ($ultimaVersion) {
            $versionActual = floatval($ultimaVersion['numero_version']);
            $nuevaVersion = number_format($versionActual + 0.1, 1);
        } else {
            $nuevaVersion = '1.0';
        }
        
        // Generar nombre único para el archivo
        $nombreArchivo = generarNombreArchivoUnico($archivo['name']);
        $rutaDestino = UPLOAD_DIR . $nombreArchivo;
        
        // Mover archivo a la carpeta de uploads
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            // Insertar registro en VersionesDocumento
            $sqlInsert = "INSERT INTO VersionesDocumento 
                         (documento_id, numero_version, usuario_id, fecha_subida, comentario, 
                          ruta_archivo, tamano_archivo, tipo_archivo) 
                         VALUES (?, ?, ?, GETDATE(), ?, ?, ?, ?)";
            
            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $params = array(
                $documento_id,
                $nuevaVersion,
                obtenerUsuarioId(),
                $comentario,
                $nombreArchivo,
                $archivo['size'],
                $extension
            );
            
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);
            
            if ($stmtInsert) {
                // Obtener ID de la versión insertada
                $sqlId = "SELECT @@IDENTITY AS id";
                $stmtId = sqlsrv_query($conn, $sqlId);
                $resultId = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC);
                $version_id = $resultId['id'];
                
                // Actualizar fecha de modificación del documento
                $sqlUpdate = "UPDATE Documentos SET fecha_modificacion = GETDATE() WHERE id = ?";
                sqlsrv_query($conn, $sqlUpdate, array($documento_id));
                
                $mensaje = "Versión $nuevaVersion subida exitosamente";
                $tipo_mensaje = 'success';
                
                registrarAuditoria(
                    'Subir Versión', 
                    "Nueva versión $nuevaVersion del documento: " . $documento['nombre'], 
                    'VersionesDocumento', 
                    $version_id
                );
                
                // Crear notificación de nueva versión
                $mensaje_notif = "Se ha subido la versión $nuevaVersion del documento '{$documento['nombre']}'";
                if (!empty($comentario)) {
                    $mensaje_notif .= " - Comentario: $comentario";
                }

                // Obtener email del responsable del documento
                $sqlResponsable = "SELECT u.email FROM Usuarios u
                                  INNER JOIN Documentos d ON u.id = d.responsable_id
                                  WHERE d.id = ?";
                $stmtResp = sqlsrv_query($conn, $sqlResponsable, array($documento_id));
                $respData = sqlsrv_fetch_array($stmtResp, SQLSRV_FETCH_ASSOC);

                // Obtener emails de aprobadores y editores
                $emails_notificar = obtenerEmailsPorRol([1, 2, 4]); // Admin, Editor, Aprobador

                // Agregar email del responsable si existe
                if (!empty($respData['email'])) {
                    $emails_notificar[] = $respData['email'];
                }

                $destinatarios = implode(',', array_unique($emails_notificar));

                // Usar la nueva función para crear notificación
                crearNotificacion($documento_id, 'Nueva Version', $mensaje_notif, $destinatarios);
            } else {
                // Si falla la inserción, eliminar el archivo
                unlink($rutaDestino);
                $mensaje = 'Error al registrar la versión del documento';
                $tipo_mensaje = 'error';
            }
        } else {
            $mensaje = 'Error al guardar el archivo';
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener versiones del documento
$sqlVersiones = "SELECT v.*, u.nombre as usuario_nombre 
                 FROM VersionesDocumento v
                 INNER JOIN Usuarios u ON v.usuario_id = u.id
                 WHERE v.documento_id = ?
                 ORDER BY CAST(v.numero_version AS FLOAT) DESC";
$stmtVersiones = sqlsrv_query($conn, $sqlVersiones, array($documento_id));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Documento - Sistema de Gestión Documental</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #027be3;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .doc-info {
            background: #f7fafc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .doc-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .doc-info strong {
            color: #333;
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
        }
        
        .btn-primary {
            background: #027be3;
            color: white;
        }

        .btn-primary:hover {
            background: #2196f3;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
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
        
        .upload-section {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .upload-section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            background: white;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
        }
        
        .versions-section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th,
        table td {
            padding: 12px;
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
        
        .nav-links {
            margin-bottom: 20px;
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            margin-right: 15px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-vigente {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-obsoleta {
            background: #e2e8f0;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Subir Nueva Versión</h1>
        </div>
        
        <div class="nav-links">
            <a href="documentos.php">← Volver a Documentos</a>
            <a href="ver_documento.php?id=<?php echo $documento_id; ?>">Ver Documento</a>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <div class="doc-info">
            <p><strong>Documento:</strong> <?php echo htmlspecialchars($documento['nombre']); ?></p>
            <p><strong>Código:</strong> <?php echo htmlspecialchars($documento['codigo']); ?></p>
            <p><strong>Responsable:</strong> <?php echo htmlspecialchars($documento['responsable_nombre']); ?></p>
        </div>
        
        <div class="upload-section">
            <h2>Subir Nueva Versión</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="archivo">Archivo PDF *</label>
                    <input 
                        type="file" 
                        id="archivo" 
                        name="archivo" 
                        accept=".pdf,application/pdf" 
                        required
                    >
                    <div class="file-info">
                        Tamaño máximo: <?php echo obtenerTamanoMaximo(); ?> | Solo archivos PDF
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comentario">Comentario</label>
                    <textarea 
                        id="comentario" 
                        name="comentario" 
                        maxlength="500"
                        placeholder="Describa los cambios de esta versión..."
                    ></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Subir Versión</button>
                    <a href="documentos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
        
        <div class="versions-section">
            <h2>Historial de Versiones</h2>
            
            <?php if ($stmtVersiones && sqlsrv_has_rows($stmtVersiones)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Versión</th>
                            <th>Fecha de Subida</th>
                            <th>Usuario</th>
                            <th>Tamaño</th>
                            <th>Comentario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 0;
                        while ($version = sqlsrv_fetch_array($stmtVersiones, SQLSRV_FETCH_ASSOC)): 
                            $contador++;
                        ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($version['numero_version']); ?>
                                    <?php if ($contador === 1): ?>
                                        <span class="badge badge-vigente">Vigente</span>
                                    <?php else: ?>
                                        <span class="badge badge-obsoleta">Obsoleta</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    echo $version['fecha_subida'] ? date('d/m/Y H:i', strtotime($version['fecha_subida'])) : '-';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($version['usuario_nombre']); ?></td>
                                <td><?php echo formatearTamano($version['tamano_archivo']); ?></td>
                                <td><?php echo htmlspecialchars($version['comentario'] ?? '-'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="visualizar_pdf.php?version_id=<?php echo $version['id']; ?>" 
                                           class="btn btn-info" 
                                           style="padding: 6px 12px; font-size: 12px;"
                                           target="_blank"
                                           title="Ver PDF en el navegador">
                                            👁️ Ver
                                        </a>
                                        <a href="descargar_documento.php?version_id=<?php echo $version['id']; ?>" 
                                           class="btn btn-primary" 
                                           style="padding: 6px 12px; font-size: 12px;"
                                           title="Descargar PDF">
                                            ⬇️ Descargar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">
                    No hay versiones subidas de este documento
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
