<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Obtener parámetros de búsqueda
$buscar = $_GET['buscar'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$area = $_GET['area'] ?? '';
$departamento = $_GET['departamento'] ?? '';
$estado = $_GET['estado'] ?? 'Aprobado'; // Por defecto solo aprobados
$responsable_id = $_GET['responsable_id'] ?? '';

// Construir consulta con filtros
$where_conditions = array("d.activo = 1");
$params = array();

if (!empty($buscar)) {
    $where_conditions[] = "(d.nombre LIKE ? OR d.codigo LIKE ? OR d.descripcion LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if (!empty($categoria)) {
    $where_conditions[] = "d.categoria = ?";
    $params[] = $categoria;
}

if (!empty($area)) {
    $where_conditions[] = "d.area = ?";
    $params[] = $area;
}

if (!empty($departamento)) {
    $where_conditions[] = "d.departamento = ?";
    $params[] = $departamento;
}

if (!empty($estado)) {
    $where_conditions[] = "d.estado = ?";
    $params[] = $estado;
}

if (!empty($responsable_id)) {
    $where_conditions[] = "d.responsable_id = ?";
    $params[] = $responsable_id;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener documentos
$sql = "SELECT
    d.id,
    d.nombre,
    d.codigo,
    d.categoria,
    d.area,
    d.departamento,
    d.responsable_id,
    d.estado,
    d.descripcion,
    d.fecha_creacion,
    d.fecha_modificacion,
    d.fecha_vencimiento,
    u.nombre as responsable_nombre,
    (SELECT TOP 1 numero_version FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as version_actual,
    (SELECT TOP 1 id FROM VersionesDocumento 
     WHERE documento_id = d.id ORDER BY fecha_subida DESC) as version_id,
    (SELECT COUNT(*) FROM VersionesDocumento WHERE documento_id = d.id) as total_versiones
FROM Documentos d
INNER JOIN Usuarios u ON d.responsable_id = u.id
WHERE $where_clause
ORDER BY d.fecha_modificacion DESC, d.nombre";

$stmt = sqlsrv_query($conn, $sql, $params);

// Obtener lista de responsables para filtro
$sqlResponsables = "SELECT DISTINCT u.id, u.nombre 
                    FROM Usuarios u
                    INNER JOIN Documentos d ON u.id = d.responsable_id
                    WHERE d.activo = 1
                    ORDER BY u.nombre";
$stmtResponsables = sqlsrv_query($conn, $sqlResponsables);
$responsables = array();
while ($resp = sqlsrv_fetch_array($stmtResponsables, SQLSRV_FETCH_ASSOC)) {
    $responsables[] = $resp;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Documentos - Sistema de Gestión Documental</title>
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
        
        .search-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .search-section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .search-grid {
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
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #027be3;
        }
        
        .search-actions {
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
        
        .results-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #027be3;
        }
        
        .results-header h2 {
            color: #333;
            font-size: 22px;
        }
        
        .results-count {
            color: #666;
            font-size: 14px;
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
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        .doc-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
            color: #666;
        }
        
        .highlight {
            background: #fef5e7;
            padding: 2px 4px;
            border-radius: 3px;
        }

        /* Responsive para pantallas pequeñas (laptop/tablet) */
        @media screen and (max-width: 1200px) {
            .hide-on-small {
                display: none !important;
            }

            .doc-description {
                display: none !important;
            }

            .action-buttons {
                display: flex;
                flex-direction: row;
                gap: 5px;
                flex-wrap: nowrap;
                white-space: nowrap;
            }

            .btn-small {
                padding: 5px 8px;
                font-size: 11px;
            }
        }

        @media screen and (max-width: 992px) {
            .search-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }

            table th,
            table td {
                padding: 8px;
                font-size: 13px;
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 0 10px;
            }

            .navbar {
                padding: 10px 15px;
            }

            .navbar h1 {
                font-size: 18px;
            }

            .search-section {
                padding: 15px;
            }

            .results-section {
                padding: 15px;
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Consultar Documentos</h1>
        <div class="user-info">
            <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
            <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
        </div>
    </div>
    
    <div class="container">
        <div class="nav-links">
            <a href="index.php">← Volver al Inicio</a>
            <a href="principal.php">Ver Documentos Aprobados</a>
            <?php if (tieneAlgunRol([1, 2])): ?>
                <a href="documentos.php">Gestionar Documentos</a>
            <?php endif; ?>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
        
        <div class="search-section">
            <h2>Buscar Documentos</h2>
            <form method="GET" action="">
                <div class="search-grid">
                    <div class="form-group">
                        <label for="buscar">Búsqueda General</label>
                        <input 
                            type="text" 
                            id="buscar" 
                            name="buscar" 
                            placeholder="Nombre, código o descripción..."
                            value="<?php echo htmlspecialchars($buscar); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria">
                            <option value="">Todas las categorías</option>
                            <option value="Proceso" <?php if ($categoria == 'Proceso'): ?>selected<?php endif; ?>>Proceso</option>
                            <option value="Politica" <?php if ($categoria == 'Politica'): ?>selected<?php endif; ?>>Política</option>
                            <option value="Procedimiento" <?php if ($categoria == 'Procedimiento'): ?>selected<?php endif; ?>>Procedimiento</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="area">Área</label>
                        <select id="area" name="area">
                            <option value="">Todas las áreas</option>
                            <option value="Administracion" <?php if ($area == 'Administracion'): ?>selected<?php endif; ?>>Administración</option>
                            <option value="Taller" <?php if ($area == 'Taller'): ?>selected<?php endif; ?>>Taller</option>
                            <option value="Refacciones" <?php if ($area == 'Refacciones'): ?>selected<?php endif; ?>>Refacciones</option>
                            <option value="Unidades" <?php if ($area == 'Unidades'): ?>selected<?php endif; ?>>Unidades</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <select id="departamento" name="departamento">
                            <option value="">Todos los departamentos</option>
                            <option value="Recursos Humanos" <?php if ($departamento == 'Recursos Humanos'): ?>selected<?php endif; ?>>Recursos Humanos</option>
                            <option value="Finanzas" <?php if ($departamento == 'Finanzas'): ?>selected<?php endif; ?>>Finanzas</option>
                            <option value="Sistemas" <?php if ($departamento == 'Sistemas'): ?>selected<?php endif; ?>>Sistemas</option>
                            <option value="Operaciones" <?php if ($departamento == 'Operaciones'): ?>selected<?php endif; ?>>Operaciones</option>
                            <option value="Comercial" <?php if ($departamento == 'Comercial'): ?>selected<?php endif; ?>>Comercial</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="Aprobado" <?php if ($estado == 'Aprobado'): ?>selected<?php endif; ?>>Aprobado</option>
                            <option value="Pendiente" <?php if ($estado == 'Pendiente'): ?>selected<?php endif; ?>>Pendiente</option>
                            <option value="Rechazado" <?php if ($estado == 'Rechazado'): ?>selected<?php endif; ?>>Rechazado</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="responsable_id">Responsable</label>
                        <select id="responsable_id" name="responsable_id">
                            <option value="">Todos los responsables</option>
                            <?php foreach ($responsables as $resp): ?>
                                <option 
                                    value="<?php echo $resp['id']; ?>"
                                    <?php if ($responsable_id == $resp['id']): ?>selected<?php endif; ?>
                                >
                                    <?php echo htmlspecialchars($resp['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                    <a href="consultar_documentos.php" class="btn btn-secondary">Limpiar Filtros</a>
                </div>
            </form>
        </div>
        
        <div class="results-section">
            <div class="results-header">
                <h2>Resultados de Búsqueda</h2>
                <?php
                $total_resultados = 0;
                if ($stmt) {
                    $resultados_temp = array();
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $resultados_temp[] = $row;
                        $total_resultados++;
                    }
                }
                ?>
                <span class="results-count">
                    <strong><?php echo $total_resultados; ?></strong> documento(s) encontrado(s)
                </span>
            </div>
            
            <?php if ($total_resultados > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Área</th>
                            <th>Estado</th>
                            <th>Versión</th>
                            <th>Fecha Mod.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados_temp as $doc): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($doc['codigo']); ?></strong></td>
                                <td><?php echo htmlspecialchars($doc['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($doc['categoria'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($doc['area'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($doc['estado']); ?>">
                                        <?php echo htmlspecialchars($doc['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($doc['version_actual']): ?>
                                        v<?php echo htmlspecialchars($doc['version_actual']); ?>
                                        <br>
                                        <small style="color: #999;">(<?php echo $doc['total_versiones']; ?> versión<?php echo $doc['total_versiones'] != 1 ? 'es' : ''; ?>)</small>
                                    <?php else: ?>
                                        <span style="color: #999;">Sin versiones</span>
                                    <?php endif; ?>
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
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($doc['version_id']): ?>
                                            <a href="visualizar_pdf.php?version_id=<?php echo $doc['version_id']; ?>"
                                               class="btn btn-primary btn-small"
                                               target="_blank"
                                               title="Ver PDF de la versión actual">
                                                Ver Doc
                                            </a>
                                        <?php endif; ?>
                                        <a href="ver_documento.php?id=<?php echo $doc['id']; ?>"
                                           class="btn btn-info btn-small"
                                           title="Ver detalles y todas las versiones">
                                            Detalles
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No se encontraron documentos</h3>
                    <p>Intenta modificar los criterios de búsqueda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
