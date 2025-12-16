<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Administradores, editores y aprobadores pueden gestionar documentos
requiereRol([1, 2, 4], 'No tiene permisos para gestionar documentos');

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $responsable_id = $_POST['responsable_id'] ?? '';
        $descripcion = trim($_POST['descripcion'] ?? '');

        // Convertir fecha a objeto DateTime de PHP (SOLUCIÓN al error de conversión)
        $fecha_elab_input = trim($_POST['fecha_elaboracion'] ?? '');
        if (empty($fecha_elab_input)) {
            $fecha_elaboracion = new DateTime(); // Fecha actual
        } else {
            $fecha_elaboracion = new DateTime($fecha_elab_input);
        }

        $fecha_vencimiento_input = trim($_POST['fecha_vencimiento'] ?? '');
        $fecha_vencimiento = null;
        if (!empty($fecha_vencimiento_input)) {
            $fecha_vencimiento = $fecha_vencimiento_input; // Mantener como string para tipo date
        }

        if (empty($nombre) || empty($codigo) || empty($responsable_id)) {
            $mensaje = 'Los campos nombre, código y responsable son obligatorios';
            $tipo_mensaje = 'error';
        } else {
            // Verificar si el código ya existe
            $sqlCheck = "SELECT COUNT(*) as total FROM Documentos WHERE codigo = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($codigo));
            $resultCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            
            if ($resultCheck['total'] > 0) {
                $mensaje = 'El código del documento ya existe';
                $tipo_mensaje = 'error';
            } else {
                $sql = "INSERT INTO Documentos (nombre, codigo, categoria, area, departamento, responsable_id,
                        descripcion, fecha_creacion, fecha_vencimiento, estado, activo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', 1)";

                $params = array($nombre, $codigo, $categoria, $area, $departamento, $responsable_id,
                               $descripcion, $fecha_elaboracion, $fecha_vencimiento);

                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt) {
                    $mensaje = 'Documento creado exitosamente';
                    $tipo_mensaje = 'success';
                    registrarAuditoria('Crear Documento', "Documento creado: $nombre ($codigo)", 'Documentos', null);
                } else {
                    $errors = sqlsrv_errors();
                    $mensaje = 'Error al crear el documento: ' . ($errors ? $errors[0]['message'] : 'Error desconocido');
                    $tipo_mensaje = 'error';
                }
            }
        }
    } elseif ($accion === 'editar') {
        $id = $_POST['id'] ?? '';
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $responsable_id = $_POST['responsable_id'] ?? '';
        $descripcion = trim($_POST['descripcion'] ?? '');

        // Convertir fecha a objeto DateTime de PHP (SOLUCIÓN al error de conversión)
        $fecha_elab_input = trim($_POST['fecha_elaboracion'] ?? '');
        if (empty($fecha_elab_input)) {
            $fecha_elaboracion = new DateTime(); // Fecha actual
        } else {
            $fecha_elaboracion = new DateTime($fecha_elab_input);
        }

        $fecha_vencimiento_input = trim($_POST['fecha_vencimiento'] ?? '');
        $fecha_vencimiento = null;
        if (!empty($fecha_vencimiento_input)) {
            $fecha_vencimiento = $fecha_vencimiento_input; // Mantener como string para tipo date
        }

        $estado = $_POST['estado'] ?? 'Pendiente';

        if (empty($id) || empty($nombre) || empty($codigo) || empty($responsable_id)) {
            $mensaje = 'Los campos nombre, código y responsable son obligatorios';
            $tipo_mensaje = 'error';
        } else {
            // Verificar si el código ya existe (excepto el actual)
            $sqlCheck = "SELECT COUNT(*) as total FROM Documentos WHERE codigo = ? AND id != ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($codigo, $id));
            $resultCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            
            if ($resultCheck['total'] > 0) {
                $mensaje = 'El código del documento ya existe';
                $tipo_mensaje = 'error';
            } else {
                // Obtener estado anterior para auditoría
                $sqlEstadoAnterior = "SELECT estado FROM Documentos WHERE id = ?";
                $stmtEstadoAnterior = sqlsrv_query($conn, $sqlEstadoAnterior, array($id));
                $docAnterior = sqlsrv_fetch_array($stmtEstadoAnterior, SQLSRV_FETCH_ASSOC);
                $estadoAnterior = $docAnterior['estado'] ?? '';

                $sql = "UPDATE Documentos SET nombre = ?, codigo = ?, categoria = ?, area = ?, departamento = ?,
                        responsable_id = ?, descripcion = ?, fecha_creacion = ?, fecha_modificacion = GETDATE(),
                        fecha_vencimiento = ?, estado = ?
                        WHERE id = ?";

                $params = array($nombre, $codigo, $categoria, $area, $departamento, $responsable_id,
                               $descripcion, $fecha_elaboracion, $fecha_vencimiento,
                               $estado, $id);

                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $mensaje = 'Documento actualizado exitosamente';
                    $tipo_mensaje = 'success';
                    
                    // Registrar edición
                    registrarAuditoria('Editar Documento', "Documento editado: $nombre ($codigo)", 'Documentos', $id);
                    
                    // Registrar cambio de estado si hubo cambio
                    if ($estadoAnterior != $estado) {
                        registrarAuditoria(
                            'Cambio de Estado', 
                            "Documento '$nombre' cambió de estado: $estadoAnterior → $estado", 
                            'Documentos', 
                            $id
                        );
                    }
                } else {
                    $errors = sqlsrv_errors();
                    $mensaje = 'Error al actualizar el documento: ' . ($errors ? $errors[0]['message'] : 'Error desconocido');
                    $tipo_mensaje = 'error';
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            $mensaje = 'ID de documento no válido';
            $tipo_mensaje = 'error';
        } else {
            // Marcar como inactivo en lugar de eliminar
            $sqlNombre = "SELECT nombre, codigo FROM Documentos WHERE id = ?";
            $stmtNombre = sqlsrv_query($conn, $sqlNombre, array($id));
            $resultNombre = sqlsrv_fetch_array($stmtNombre, SQLSRV_FETCH_ASSOC);
            
            $sql = "UPDATE Documentos SET activo = 0 WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($id));
            
            if ($stmt) {
                $mensaje = 'Documento eliminado exitosamente';
                $tipo_mensaje = 'success';
                $nombreDoc = $resultNombre['nombre'] ?? 'Desconocido';
                $codigoDoc = $resultNombre['codigo'] ?? '';
                registrarAuditoria('Eliminar Documento', "Documento eliminado: $nombreDoc ($codigoDoc)", 'Documentos', $id);
            } else {
                $mensaje = 'Error al eliminar el documento';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener lista de documentos
$sqlDocs = "SELECT d.id, d.nombre, d.codigo, d.categoria, d.area, d.estado, 
            d.fecha_creacion, d.fecha_modificacion, u.nombre as responsable_nombre,
            (SELECT COUNT(*) FROM VersionesDocumento WHERE documento_id = d.id) as total_versiones
            FROM Documentos d
            INNER JOIN Usuarios u ON d.responsable_id = u.id
            WHERE d.activo = 1
            ORDER BY d.fecha_modificacion DESC, d.nombre";
$stmtDocs = sqlsrv_query($conn, $sqlDocs);

// Obtener lista de usuarios para el select de responsables
$sqlUsuarios = "SELECT id, nombre FROM Usuarios WHERE estado = 1 ORDER BY nombre";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
$usuarios = array();
while ($user = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $user;
}

// Si se está editando, obtener los datos del documento
$documentoEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = $_GET['editar'];
    $sqlEditar = "SELECT * FROM Documentos WHERE id = ?";
    $stmtEditar = sqlsrv_query($conn, $sqlEditar, array($idEditar));
    $documentoEditar = sqlsrv_fetch_array($stmtEditar, SQLSRV_FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Documentos - Sistema de Gestión Documental</title>
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
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
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
        
        .form-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #027be3;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        }
        
        table th {
            background: #f7fafc;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
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
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .required {
            color: #f56565;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }

        /* Responsive para pantallas pequeñas (laptop/tablet) */
        @media screen and (max-width: 1200px) {
            .hide-on-small {
                display: none !important;
            }

            .actions {
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
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

            .form-section {
                padding: 15px;
            }

            table {
                min-width: 700px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Documentos</h1>
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
        
        <div class="form-section">
            <h2><?php echo $documentoEditar ? 'Editar Documento' : 'Crear Nuevo Documento'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?php echo $documentoEditar ? 'editar' : 'crear'; ?>">
                <?php if ($documentoEditar): ?>
                    <input type="hidden" name="id" value="<?php echo $documentoEditar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre del Documento <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            required 
                            maxlength="200"
                            value="<?php echo $documentoEditar ? htmlspecialchars($documentoEditar['nombre']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo">Código <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="codigo" 
                            name="codigo" 
                            required 
                            maxlength="50"
                            value="<?php echo $documentoEditar ? htmlspecialchars($documentoEditar['codigo']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria">
                            <option value="">Seleccione una categoría</option>
                            <option value="Proceso" <?php if ($documentoEditar && $documentoEditar['categoria'] == 'Proceso'): ?>selected<?php endif; ?>>Proceso</option>
                            <option value="Politica" <?php if ($documentoEditar && $documentoEditar['categoria'] == 'Politica'): ?>selected<?php endif; ?>>Política</option>
                            <option value="Procedimiento" <?php if ($documentoEditar && $documentoEditar['categoria'] == 'Procedimiento'): ?>selected<?php endif; ?>>Procedimiento</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="area">Área</label>
                        <select id="area" name="area">
                            <option value="">Seleccione un área</option>
                            <option value="Administracion" <?php if ($documentoEditar && $documentoEditar['area'] == 'Administracion'): ?>selected<?php endif; ?>>Administración</option>
                            <option value="Refacciones" <?php if ($documentoEditar && $documentoEditar['area'] == 'Refacciones'): ?>selected<?php endif; ?>>Refacciones</option>
                            <option value="Servicio" <?php if ($documentoEditar && $documentoEditar['area'] == 'Servicio'): ?>selected<?php endif; ?>>Servicio</option>
                            <option value="Unidades" <?php if ($documentoEditar && $documentoEditar['area'] == 'Unidades'): ?>selected<?php endif; ?>>Unidades</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <select id="departamento" name="departamento">
                            <option value="">Seleccione un departamento</option>
                            <option value="Crédito y Cobranza" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Crédito y Cobranza'): ?>selected<?php endif; ?>>Crédito y Cobranza</option>
                            <option value="Recursos Humanos" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Recursos Humanos'): ?>selected<?php endif; ?>>Recursos Humanos</option>
                            <option value="Contabilidad" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Contabilidad'): ?>selected<?php endif; ?>>Contabilidad</option>
                            <option value="Taller" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Taller'): ?>selected<?php endif; ?>>Taller</option>
                            <option value="Ventas" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Ventas'): ?>selected<?php endif; ?>>Ventas</option>
                            <option value="Mercadotecnia" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Mercadotecnia'): ?>selected<?php endif; ?>>Mercadotecnia</option>
                            <option value="Mejora Continua" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Mejora Continua'): ?>selected<?php endif; ?>>Mejora Continua</option>
                            <option value="Compras" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Compras'): ?>selected<?php endif; ?>>Compras</option>
                            <option value="Garantías" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Garantías'): ?>selected<?php endif; ?>>Garantías</option>
                            <option value="Sistemas" <?php if ($documentoEditar && $documentoEditar['departamento'] == 'Sistemas'): ?>selected<?php endif; ?>>Sistemas</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="responsable_id">Responsable <span class="required">*</span></label>
                        <select id="responsable_id" name="responsable_id" required>
                            <option value="">Seleccione un responsable</option>
                            <?php foreach ($usuarios as $user): ?>
                                <option
                                    value="<?php echo $user['id']; ?>"
                                    <?php if ($documentoEditar && $user['id'] == $documentoEditar['responsable_id']): ?>selected<?php endif; ?>
                                >
                                    <?php echo htmlspecialchars($user['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_elaboracion">Fecha de Elaboración</label>
                        <input
                            type="date"
                            id="fecha_elaboracion"
                            name="fecha_elaboracion"
                            value="<?php echo $documentoEditar && $documentoEditar['fecha_creacion'] ? date('Y-m-d', strtotime($documentoEditar['fecha_creacion'])) : date('Y-m-d'); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="fecha_vencimiento">Fecha de Vencimiento</label>
                        <input
                            type="date"
                            id="fecha_vencimiento"
                            name="fecha_vencimiento"
                            value="<?php echo $documentoEditar && $documentoEditar['fecha_vencimiento'] ? date('Y-m-d', strtotime($documentoEditar['fecha_vencimiento'])) : ''; ?>"
                        >
                    </div>
                    
                    <?php if ($documentoEditar && esAdministrador()): ?>
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="Pendiente" <?php if ($documentoEditar['estado'] == 'Pendiente'): ?>selected<?php endif; ?>>Pendiente</option>
                                <option value="Aprobado" <?php if ($documentoEditar['estado'] == 'Aprobado'): ?>selected<?php endif; ?>>Aprobado</option>
                                <option value="Rechazado" <?php if ($documentoEditar['estado'] == 'Rechazado'): ?>selected<?php endif; ?>>Rechazado</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group full-width">
                        <label for="descripcion">Descripción</label>
                        <textarea 
                            id="descripcion" 
                            name="descripcion" 
                            maxlength="500"
                        ><?php echo $documentoEditar ? htmlspecialchars($documentoEditar['descripcion']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <?php echo $documentoEditar ? 'Actualizar Documento' : 'Crear Documento'; ?>
                    </button>
                    <?php if ($documentoEditar): ?>
                        <a href="documentos.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <h2>Lista de Documentos</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Versiones</th>
                    <th>Fecha Mod.</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = sqlsrv_fetch_array($stmtDocs, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($doc['codigo']); ?></td>
                        <td><?php echo htmlspecialchars($doc['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($doc['categoria'] ?? '-'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($doc['estado']); ?>">
                                <?php echo htmlspecialchars($doc['estado']); ?>
                            </span>
                        </td>
                        <td><?php echo $doc['total_versiones']; ?></td>
                        <td><?php echo $doc['fecha_modificacion'] ? date('d/m/Y', strtotime($doc['fecha_modificacion'])) : '-'; ?></td>
                        <td>
                            <div class="actions">
                                <a href="ver_documento.php?id=<?php echo $doc['id']; ?>" class="btn btn-info btn-small">
                                    Ver
                                </a>
                                <a href="subir_documento.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary btn-small">
                                    Subir
                                </a>
                                <a href="?editar=<?php echo $doc['id']; ?>" class="btn btn-primary btn-small">
                                    Editar
                                </a>
                                <form method="POST" action="" style="display: inline;"
                                      onsubmit="return confirm('¿Está seguro de eliminar este documento?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
