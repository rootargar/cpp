<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Obtener estadísticas del sistema
$sqlStats = "SELECT 
    (SELECT COUNT(*) FROM Usuarios WHERE estado = 1) as usuarios_activos,
    (SELECT COUNT(*) FROM Documentos WHERE activo = 1) as documentos_totales,
    (SELECT COUNT(*) FROM Documentos WHERE estado = 'Pendiente' AND activo = 1) as documentos_pendientes,
    (SELECT COUNT(*) FROM Documentos WHERE estado = 'Aprobado' AND activo = 1) as documentos_aprobados";
$stmtStats = sqlsrv_query($conn, $sqlStats);
$stats = sqlsrv_fetch_array($stmtStats, SQLSRV_FETCH_ASSOC);

// Obtener últimos documentos modificados
$sqlDocumentos = "SELECT TOP 5 d.nombre, d.codigo, d.estado, d.fecha_modificacion, u.nombre as responsable
                  FROM Documentos d
                  INNER JOIN Usuarios u ON d.responsable_id = u.id
                  WHERE d.activo = 1
                  ORDER BY d.fecha_modificacion DESC";
$stmtDocumentos = sqlsrv_query($conn, $sqlDocumentos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Sistema de Gestión Documental</title>
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
        
        .navbar .user-info strong {
            font-size: 16px;
        }
        
        .navbar .user-info span {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .menu-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card h3 {
            color: #027be3;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
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
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #027be3;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table th {
            background: #f7fafc;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            background: #027be3;
            color: white;
        }

        .btn:hover {
            background: #2196f3;
        }
        
        .logout-link {
            margin-top: 20px;
            text-align: center;
        }
        
        .logout-link a {
            color: #027be3;
            text-decoration: none;
        }
        
        .logout-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Sistema de Gestión Documental</h1>
        <div class="user-info">
            <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
            <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
        </div>
    </div>
    
    <div class="container">
        <?php echo mostrarMensajeError(); ?>
        <?php echo mostrarMensajeExito(); ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['usuarios_activos']; ?></div>
                <div class="label">Usuarios Activos</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['documentos_totales']; ?></div>
                <div class="label">Documentos Totales</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['documentos_pendientes']; ?></div>
                <div class="label">Documentos Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['documentos_aprobados']; ?></div>
                <div class="label">Documentos Aprobados</div>
            </div>
        </div>
        
        <div class="menu-cards">
            <?php if (esAdministrador()): ?>
                <a href="usuarios.php" class="card">
                    <h3>Gestión de Usuarios</h3>
                    <p>Administrar usuarios del sistema, roles y permisos</p>
                </a>
            <?php endif; ?>
            
            <?php if (tieneAlgunRol([1, 2, 4])): ?>
                <a href="documentos.php" class="card">
                    <h3>Gestión de Documentos</h3>
                    <p>Crear, editar y gestionar documentos del sistema</p>
                </a>
            <?php endif; ?>
            
            <a href="consultar_documentos.php" class="card">
                <h3>Consultar Documentos</h3>
                <p>Buscar y visualizar documentos aprobados</p>
            </a>
            
            <?php if (tieneAlgunRol([1, 2, 4])): ?>
                <a href="aprobar_documentos.php" class="card">
                    <h3>Aprobar Documentos</h3>
                    <p>Revisar y aprobar documentos pendientes</p>
                </a>
            <?php endif; ?>
            
            <?php if (esAdministrador()): ?>
                <a href="auditoria.php" class="card">
                    <h3>Auditoría</h3>
                    <p>Ver registro de actividades del sistema</p>
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($stmtDocumentos && sqlsrv_has_rows($stmtDocumentos)): ?>
        <div class="section">
            <h2>Documentos Recientemente Modificados</h2>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                        <th>Última Modificación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = sqlsrv_fetch_array($stmtDocumentos, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['codigo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($doc['responsable']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($doc['estado']); ?>">
                                    <?php echo htmlspecialchars($doc['estado']); ?>
                                </span>
                            </td>
                            <td><?php echo $doc['fecha_modificacion'] ? date('d/m/Y H:i', strtotime($doc['fecha_modificacion'])) : '-'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="logout-link">
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
