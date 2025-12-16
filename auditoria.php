<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Solo administradores pueden ver auditoría
requiereAdministrador();

// Obtener filtros
$filtro_usuario = $_GET['usuario_id'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 50;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir consulta con filtros
$where_conditions = array();
$params = array();

if (!empty($filtro_usuario)) {
    $where_conditions[] = "a.usuario_id = ?";
    $params[] = $filtro_usuario;
}

if (!empty($filtro_accion)) {
    $where_conditions[] = "a.accion LIKE ?";
    $params[] = "%$filtro_accion%";
}

if (!empty($filtro_fecha_inicio)) {
    $where_conditions[] = "CAST(a.fecha AS DATE) >= ?";
    $params[] = $filtro_fecha_inicio;
}

if (!empty($filtro_fecha_fin)) {
    $where_conditions[] = "CAST(a.fecha AS DATE) <= ?";
    $params[] = $filtro_fecha_fin;
}

$where_clause = '';
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Contar total de registros
$sqlCount = "SELECT COUNT(*) as total 
             FROM Auditoria a 
             $where_clause";
$stmtCount = sqlsrv_query($conn, $sqlCount, $params);
$totalRegistros = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $registros_por_pagina);

// Obtener registros de auditoría con paginación
$sql = "SELECT 
    a.id,
    a.usuario_id,
    u.nombre as usuario_nombre,
    u.usuario as usuario_user,
    a.accion,
    a.descripcion,
    a.tabla_afectada,
    a.registro_id,
    a.fecha,
    a.ip_address
FROM Auditoria a
LEFT JOIN Usuarios u ON a.usuario_id = u.id
$where_clause
ORDER BY a.fecha DESC
OFFSET ? ROWS
FETCH NEXT ? ROWS ONLY";

$params_paginacion = array_merge($params, array($offset, $registros_por_pagina));
$stmt = sqlsrv_query($conn, $sql, $params_paginacion);

// Obtener lista de usuarios para el filtro
$sqlUsuarios = "SELECT DISTINCT u.id, u.nombre 
                FROM Usuarios u
                INNER JOIN Auditoria a ON u.id = a.usuario_id
                ORDER BY u.nombre";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
$usuarios = array();
while ($user = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $user;
}

// Obtener lista de acciones para el filtro
$sqlAcciones = "SELECT DISTINCT accion FROM Auditoria ORDER BY accion";
$stmtAcciones = sqlsrv_query($conn, $sqlAcciones);
$acciones = array();
while ($accion = sqlsrv_fetch_array($stmtAcciones, SQLSRV_FETCH_ASSOC)) {
    $acciones[] = $accion['accion'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - Sistema de Gestión Documental</title>
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
            max-width: 1600px;
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
        
        .filters-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .filters-section h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 13px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #027be3;
        }
        
        .btn {
            padding: 8px 20px;
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
        
        .btn-secondary {
            background: #718096;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4a5568;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .stats-bar {
            background: #f7fafc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-bar .info {
            color: #666;
            font-size: 14px;
        }
        
        .stats-bar .info strong {
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
        }
        
        table th,
        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        table th {
            background: #f7fafc;
            color: #2d3748;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        table tr:hover {
            background: #f7fafc;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-login {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-logout {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-crear {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .badge-editar {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .badge-eliminar {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-estado {
            background: #e9d8fd;
            color: #44337a;
        }
        
        .badge-descarga {
            background: #fef5e7;
            color: #7c6a46;
        }
        
        .badge-default {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }
        
        .pagination a:hover {
            background: #f7fafc;
            border-color: #667eea;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
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
        
        .descripcion-cell {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Registro de Auditoría</h1>
            <div class="user-info">
                <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
                <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
            </div>
        </div>
        
        <div class="nav-links">
            <a href="index.php">← Volver al Inicio</a>
            <a href="usuarios.php">Gestionar Usuarios</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
        
        <div class="filters-section">
            <h3>Filtros de Búsqueda</h3>
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="usuario_id">Usuario</label>
                        <select id="usuario_id" name="usuario_id">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $user): ?>
                                <option 
                                    value="<?php echo $user['id']; ?>"
                                    <?php if ($filtro_usuario == $user['id']): ?>selected<?php endif; ?>
                                >
                                    <?php echo htmlspecialchars($user['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="accion">Acción</label>
                        <select id="accion" name="accion">
                            <option value="">Todas las acciones</option>
                            <?php foreach ($acciones as $accion): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($accion); ?>"
                                    <?php if ($filtro_accion == $accion): ?>selected<?php endif; ?>
                                >
                                    <?php echo htmlspecialchars($accion); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input 
                            type="date" 
                            id="fecha_inicio" 
                            name="fecha_inicio"
                            value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input 
                            type="date" 
                            id="fecha_fin" 
                            name="fecha_fin"
                            value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>"
                        >
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    <a href="auditoria.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>
        </div>
        
        <div class="stats-bar">
            <div class="info">
                <strong><?php echo number_format($totalRegistros); ?></strong> registros encontrados
            </div>
            <div class="info">
                Página <strong><?php echo $pagina; ?></strong> de <strong><?php echo $totalPaginas; ?></strong>
            </div>
        </div>
        
        <?php if ($stmt && sqlsrv_has_rows($stmt)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>Tabla</th>
                        <th>Registro ID</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $registro['id']; ?></td>
                            <td>
                                <?php 
                                if ($registro['fecha']) {
                                    echo date('d/m/Y H:i:s', strtotime($registro['fecha']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($registro['usuario_nombre']) {
                                    echo htmlspecialchars($registro['usuario_nombre']);
                                    echo '<br><small style="color: #999;">' . htmlspecialchars($registro['usuario_user']) . '</small>';
                                } else {
                                    echo '<em>Sistema</em>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $accion_lower = strtolower($registro['accion']);
                                $badge_class = 'badge-default';
                                
                                if (strpos($accion_lower, 'login') !== false && strpos($accion_lower, 'fallido') === false) {
                                    $badge_class = 'badge-login';
                                } elseif (strpos($accion_lower, 'logout') !== false) {
                                    $badge_class = 'badge-logout';
                                } elseif (strpos($accion_lower, 'crear') !== false) {
                                    $badge_class = 'badge-crear';
                                } elseif (strpos($accion_lower, 'editar') !== false || strpos($accion_lower, 'modificar') !== false) {
                                    $badge_class = 'badge-editar';
                                } elseif (strpos($accion_lower, 'eliminar') !== false) {
                                    $badge_class = 'badge-eliminar';
                                } elseif (strpos($accion_lower, 'estado') !== false) {
                                    $badge_class = 'badge-estado';
                                } elseif (strpos($accion_lower, 'descarga') !== false) {
                                    $badge_class = 'badge-descarga';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($registro['accion']); ?>
                                </span>
                            </td>
                            <td class="descripcion-cell" title="<?php echo htmlspecialchars($registro['descripcion']); ?>">
                                <?php echo htmlspecialchars($registro['descripcion'] ?? '-'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($registro['tabla_afectada'] ?? '-'); ?></td>
                            <td><?php echo $registro['registro_id'] ?? '-'; ?></td>
                            <td><?php echo htmlspecialchars($registro['ip_address'] ?? '-'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <?php if ($totalPaginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=1<?php echo !empty($filtro_usuario) ? '&usuario_id='.$filtro_usuario : ''; ?><?php echo !empty($filtro_accion) ? '&accion='.$filtro_accion : ''; ?><?php echo !empty($filtro_fecha_inicio) ? '&fecha_inicio='.$filtro_fecha_inicio : ''; ?><?php echo !empty($filtro_fecha_fin) ? '&fecha_fin='.$filtro_fecha_fin : ''; ?>">Primera</a>
                        <a href="?pagina=<?php echo $pagina - 1; ?><?php echo !empty($filtro_usuario) ? '&usuario_id='.$filtro_usuario : ''; ?><?php echo !empty($filtro_accion) ? '&accion='.$filtro_accion : ''; ?><?php echo !empty($filtro_fecha_inicio) ? '&fecha_inicio='.$filtro_fecha_inicio : ''; ?><?php echo !empty($filtro_fecha_fin) ? '&fecha_fin='.$filtro_fecha_fin : ''; ?>">Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio_pag = max(1, $pagina - 2);
                    $fin_pag = min($totalPaginas, $pagina + 2);
                    
                    for ($i = $inicio_pag; $i <= $fin_pag; $i++):
                    ?>
                        <?php if ($i == $pagina): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?php echo $i; ?><?php echo !empty($filtro_usuario) ? '&usuario_id='.$filtro_usuario : ''; ?><?php echo !empty($filtro_accion) ? '&accion='.$filtro_accion : ''; ?><?php echo !empty($filtro_fecha_inicio) ? '&fecha_inicio='.$filtro_fecha_inicio : ''; ?><?php echo !empty($filtro_fecha_fin) ? '&fecha_fin='.$filtro_fecha_fin : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?pagina=<?php echo $pagina + 1; ?><?php echo !empty($filtro_usuario) ? '&usuario_id='.$filtro_usuario : ''; ?><?php echo !empty($filtro_accion) ? '&accion='.$filtro_accion : ''; ?><?php echo !empty($filtro_fecha_inicio) ? '&fecha_inicio='.$filtro_fecha_inicio : ''; ?><?php echo !empty($filtro_fecha_fin) ? '&fecha_fin='.$filtro_fecha_fin : ''; ?>">Siguiente</a>
                        <a href="?pagina=<?php echo $totalPaginas; ?><?php echo !empty($filtro_usuario) ? '&usuario_id='.$filtro_usuario : ''; ?><?php echo !empty($filtro_accion) ? '&accion='.$filtro_accion : ''; ?><?php echo !empty($filtro_fecha_inicio) ? '&fecha_inicio='.$filtro_fecha_inicio : ''; ?><?php echo !empty($filtro_fecha_fin) ? '&fecha_fin='.$filtro_fecha_fin : ''; ?>">Última</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No hay registros de auditoría</h3>
                <p>No se encontraron registros con los filtros aplicados</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
