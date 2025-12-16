<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Obtener lista de documentos aprobados
$sql = "SELECT 
    d.id,
    d.nombre,
    d.codigo,
    d.categoria,
    d.area,
    d.responsable_id,
    d.estado,
    d.fecha_creacion,
    d.fecha_modificacion,
    u.nombre as responsable_nombre,
    (SELECT TOP 1 numero_version FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as version_actual,
    (SELECT TOP 1 id FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as version_id
FROM Documentos d
INNER JOIN Usuarios u ON d.responsable_id = u.id
WHERE d.estado = 'Aprobado' AND d.activo = 1
ORDER BY d.fecha_modificacion DESC, d.nombre";

$stmt = sqlsrv_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos Aprobados - Sistema de Gestión Documental</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #027be3 0%, #2196f3 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            text-align: right;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .nav-links {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .nav-links a {
            color: #027be3;
            text-decoration: none;
            margin-right: 20px;
            font-size: 14px;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
        
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .content-box h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #027be3;
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
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Documentos Aprobados</h1>
        <div class="user-info">
            <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
            <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
        </div>
    </div>
    
    <div class="container">
        <div class="nav-links">
            <a href="index.php">← Volver al Inicio</a>
            <?php if (tieneAlgunRol([1, 2])): ?>
                <a href="documentos.php">Gestionar Documentos</a>
            <?php endif; ?>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
        
        <div class="content-box">
            <h2>Listado de Documentos</h2>
            
            <?php if ($stmt && sqlsrv_has_rows($stmt)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Fecha de Creación</th>
                            <th>Fecha de Modificación</th>
                            <th>Área</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($doc['categoria'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    if ($doc['fecha_creacion']) {
                                        echo date('d/m/Y', strtotime($doc['fecha_creacion']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($doc['fecha_modificacion']) {
                                        echo date('d/m/Y', strtotime($doc['fecha_modificacion']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($doc['area'] ?? '-'); ?></td>
                                <td>Aprobado</td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <?php if ($doc['version_id']): ?>
                                            <a href="visualizar_pdf.php?version_id=<?php echo $doc['version_id']; ?>"
                                               class="btn btn-primary"
                                               target="_blank"
                                               title="Ver PDF en el navegador">
                                                👁️ Ver PDF
                                            </a>
                                        <?php endif; ?>
                                        <a href="ver_documento.php?id=<?php echo $doc['id']; ?>"
                                           class="btn btn-primary"
                                           title="Ver detalles y todas las versiones">
                                            📄 Detalles
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay documentos aprobados</h3>
                    <p>Aún no se han aprobado documentos en el sistema</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
