<?php
require_once 'verificar_login.php';
require_once 'conexion.php';
require_once 'config.php';

// Verificar que el usuario esté autenticado
verificarLogin();

$documento_id = $_GET['id'] ?? '';

if (empty($documento_id)) {
    header('Location: principal.php');
    exit();
}

// Obtener información del documento
$sqlDoc = "SELECT d.*, u.nombre as responsable_nombre, u.email as responsable_email
           FROM Documentos d
           INNER JOIN Usuarios u ON d.responsable_id = u.id
           WHERE d.id = ? AND d.activo = 1";
$stmtDoc = sqlsrv_query($conn, $sqlDoc, array($documento_id));
$documento = sqlsrv_fetch_array($stmtDoc, SQLSRV_FETCH_ASSOC);

if (!$documento) {
    header('Location: principal.php');
    exit();
}

// Obtener versiones del documento
$sqlVersiones = "SELECT v.*, u.nombre as usuario_nombre 
                 FROM VersionesDocumento v
                 INNER JOIN Usuarios u ON v.usuario_id = u.id
                 WHERE v.documento_id = ?
                 ORDER BY CAST(v.numero_version AS FLOAT) DESC";
$stmtVersiones = sqlsrv_query($conn, $sqlVersiones, array($documento_id));

// Registrar consulta en auditoría
registrarAuditoria('Ver Documento', "Documento consultado: " . $documento['nombre'], 'Documentos', $documento_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($documento['nombre']); ?> - Sistema de Gestión Documental</title>
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
        
        .badge {
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
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
        
        .badge-vigente {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-obsoleta {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .nav-links {
            margin-bottom: 20px;
        }
        
        .nav-links a {
            color: #027be3;
            text-decoration: none;
            margin-right: 15px;
            font-size: 14px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #027be3;
        }

        .info-box h3 {
            color: #027be3;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item {
            margin-bottom: 12px;
        }
        
        .info-item label {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .info-item p {
            color: #333;
            font-size: 14px;
        }
        
        .description-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .description-box h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .description-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .versions-section h2 {
            color: #333;
            font-size: 22px;
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .actions-bar {
            background: #f7fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($documento['nombre']); ?></h1>
            <span class="badge badge-<?php echo strtolower($documento['estado']); ?>">
                <?php echo htmlspecialchars($documento['estado']); ?>
            </span>
        </div>
        
        <div class="nav-links">
            <?php if (tieneAlgunRol([1, 2])): ?>
                <a href="documentos.php">← Volver a Documentos</a>
            <?php else: ?>
                <a href="principal.php">← Volver a Listado</a>
            <?php endif; ?>
            <a href="index.php">Ir al Inicio</a>
        </div>
        
        <?php if (tieneAlgunRol([1, 2])): ?>
            <div class="actions-bar">
                <a href="subir_documento.php?id=<?php echo $documento_id; ?>" class="btn btn-success">
                    Subir Nueva Versión
                </a>
                <a href="documentos.php?editar=<?php echo $documento_id; ?>" class="btn btn-primary">
                    Editar Información
                </a>
            </div>
        <?php endif; ?>
        
        <div class="info-grid">
            <div class="info-box">
                <h3>Información General</h3>
                <div class="info-item">
                    <label>Código</label>
                    <p><?php echo htmlspecialchars($documento['codigo']); ?></p>
                </div>
                <div class="info-item">
                    <label>Categoría</label>
                    <p><?php echo htmlspecialchars($documento['categoria'] ?? '-'); ?></p>
                </div>
                <div class="info-item">
                    <label>Área</label>
                    <p><?php echo htmlspecialchars($documento['area'] ?? '-'); ?></p>
                </div>
                <div class="info-item">
                    <label>Departamento</label>
                    <p><?php echo htmlspecialchars($documento['departamento'] ?? '-'); ?></p>
                </div>
            </div>

            <div class="info-box">
                <h3>Responsable</h3>
                <div class="info-item">
                    <label>Nombre</label>
                    <p><?php echo htmlspecialchars($documento['responsable_nombre']); ?></p>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($documento['responsable_email'] ?? '-'); ?></p>
                </div>
            </div>
            
            <div class="info-box">
                <h3>Fechas</h3>
                <div class="info-item">
                    <label>Fecha de Creación</label>
                    <p>
                        <?php 
                        echo $documento['fecha_creacion'] ? date('d/m/Y H:i', strtotime($documento['fecha_creacion'])) : '-';
                        ?>
                    </p>
                </div>
                <div class="info-item">
                    <label>Última Modificación</label>
                    <p>
                        <?php 
                        echo $documento['fecha_modificacion'] ? date('d/m/Y H:i', strtotime($documento['fecha_modificacion'])) : '-';
                        ?>
                    </p>
                </div>
                <div class="info-item">
                    <label>Fecha de Vencimiento</label>
                    <p>
                        <?php 
                        echo $documento['fecha_vencimiento'] ? date('d/m/Y', strtotime($documento['fecha_vencimiento'])) : '-';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <?php if (!empty($documento['descripcion'])): ?>
            <div class="description-box">
                <h3>Descripción</h3>
                <p><?php echo nl2br(htmlspecialchars($documento['descripcion'])); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="versions-section">
            <h2>Versiones del Documento</h2>
            
            <?php if ($stmtVersiones && sqlsrv_has_rows($stmtVersiones)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Versión</th>
                            <th>Fecha de Subida</th>
                            <th>Subido por</th>
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
                                           class="btn btn-info btn-small"
                                           target="_blank"
                                           title="Ver PDF en el navegador">
                                            👁️ Ver
                                        </a>
                                        <a href="descargar_documento.php?version_id=<?php echo $version['id']; ?>" 
                                           class="btn btn-primary btn-small"
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
                <div class="empty-state">
                    <p>No hay versiones disponibles de este documento</p>
                    <?php if (tieneAlgunRol([1, 2])): ?>
                        <p style="margin-top: 10px;">
                            <a href="subir_documento.php?id=<?php echo $documento_id; ?>" class="btn btn-success">
                                Subir Primera Versión
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
