<?php
require_once 'verificar_login.php';
require_once 'conexion.php';

// Verificar que el usuario esté autenticado
verificarLogin();

// Solo los administradores pueden gestionar usuarios
requiereAdministrador();

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        $rol_id = $_POST['rol_id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $estado = isset($_POST['estado']) ? 1 : 0;
        
        if (empty($nombre) || empty($usuario) || empty($contrasena) || empty($rol_id)) {
            $mensaje = 'Todos los campos obligatorios deben ser completados';
            $tipo_mensaje = 'error';
        } else {
            // Verificar si el usuario ya existe
            $sqlCheck = "SELECT COUNT(*) as total FROM Usuarios WHERE usuario = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($usuario));
            $resultCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            
            if ($resultCheck['total'] > 0) {
                $mensaje = 'El nombre de usuario ya existe';
                $tipo_mensaje = 'error';
            } else {
                $sql = "INSERT INTO Usuarios (nombre, usuario, contrasena, rol_id, email, estado, fecha_creacion) 
                        VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
                $params = array($nombre, $usuario, $contrasena, $rol_id, $email, $estado);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $mensaje = 'Usuario creado exitosamente';
                    $tipo_mensaje = 'success';
                    registrarAuditoria('Crear Usuario', "Usuario creado: $usuario", 'Usuarios', null);
                } else {
                    $mensaje = 'Error al crear el usuario';
                    $tipo_mensaje = 'error';
                }
            }
        }
    } elseif ($accion === 'editar') {
        $id = $_POST['id'] ?? '';
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        $rol_id = $_POST['rol_id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $estado = isset($_POST['estado']) ? 1 : 0;
        
        if (empty($id) || empty($nombre) || empty($usuario) || empty($rol_id)) {
            $mensaje = 'Todos los campos obligatorios deben ser completados';
            $tipo_mensaje = 'error';
        } else {
            // Verificar si el usuario ya existe (excepto el actual)
            $sqlCheck = "SELECT COUNT(*) as total FROM Usuarios WHERE usuario = ? AND id != ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($usuario, $id));
            $resultCheck = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
            
            if ($resultCheck['total'] > 0) {
                $mensaje = 'El nombre de usuario ya existe';
                $tipo_mensaje = 'error';
            } else {
                if (!empty($contrasena)) {
                    $sql = "UPDATE Usuarios SET nombre = ?, usuario = ?, contrasena = ?, 
                            rol_id = ?, email = ?, estado = ? WHERE id = ?";
                    $params = array($nombre, $usuario, $contrasena, $rol_id, $email, $estado, $id);
                } else {
                    $sql = "UPDATE Usuarios SET nombre = ?, usuario = ?, 
                            rol_id = ?, email = ?, estado = ? WHERE id = ?";
                    $params = array($nombre, $usuario, $rol_id, $email, $estado, $id);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $mensaje = 'Usuario actualizado exitosamente';
                    $tipo_mensaje = 'success';
                    registrarAuditoria('Editar Usuario', "Usuario editado: $usuario", 'Usuarios', $id);
                } else {
                    $mensaje = 'Error al actualizar el usuario';
                    $tipo_mensaje = 'error';
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            $mensaje = 'ID de usuario no válido';
            $tipo_mensaje = 'error';
        } elseif ($id == obtenerUsuarioId()) {
            $mensaje = 'No puede eliminar su propio usuario';
            $tipo_mensaje = 'error';
        } else {
            // Obtener nombre del usuario antes de eliminar
            $sqlNombre = "SELECT usuario FROM Usuarios WHERE id = ?";
            $stmtNombre = sqlsrv_query($conn, $sqlNombre, array($id));
            $resultNombre = sqlsrv_fetch_array($stmtNombre, SQLSRV_FETCH_ASSOC);
            $nombreUsuario = $resultNombre['usuario'] ?? 'Desconocido';
            
            $sql = "DELETE FROM Usuarios WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($id));
            
            if ($stmt) {
                $mensaje = 'Usuario eliminado exitosamente';
                $tipo_mensaje = 'success';
                registrarAuditoria('Eliminar Usuario', "Usuario eliminado: $nombreUsuario", 'Usuarios', $id);
            } else {
                $mensaje = 'Error al eliminar el usuario';
                $tipo_mensaje = 'error';
            }
        }
    }
}

// Obtener lista de usuarios
$sqlUsuarios = "SELECT u.id, u.nombre, u.usuario, u.email, u.estado, u.fecha_creacion, 
                       r.nombre as rol_nombre, u.rol_id
                FROM Usuarios u
                INNER JOIN Roles r ON u.rol_id = r.id
                ORDER BY u.nombre";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);

// Obtener lista de roles
$sqlRoles = "SELECT id, nombre FROM Roles ORDER BY nombre";
$stmtRoles = sqlsrv_query($conn, $sqlRoles);
$roles = array();
while ($rol = sqlsrv_fetch_array($stmtRoles, SQLSRV_FETCH_ASSOC)) {
    $roles[] = $rol;
}

// Si se está editando, obtener los datos del usuario
$usuarioEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = $_GET['editar'];
    $sqlEditar = "SELECT * FROM Usuarios WHERE id = ?";
    $stmtEditar = sqlsrv_query($conn, $sqlEditar, array($idEditar));
    $usuarioEditar = sqlsrv_fetch_array($stmtEditar, SQLSRV_FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema de Gestión Documental</title>
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
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
        
        .badge-activo {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-inactivo {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
            <div class="user-info">
                <strong><?php echo htmlspecialchars(obtenerNombreUsuario()); ?></strong><br>
                <span><?php echo htmlspecialchars(obtenerNombreRol()); ?></span>
            </div>
        </div>
        
        <div class="nav-links">
            <a href="index.php">← Volver al Inicio</a>
            <a href="logout.php">Cerrar Sesión</a>
        </div>
        
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <h2><?php echo $usuarioEditar ? 'Editar Usuario' : 'Crear Nuevo Usuario'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?php echo $usuarioEditar ? 'editar' : 'crear'; ?>">
                <?php if ($usuarioEditar): ?>
                    <input type="hidden" name="id" value="<?php echo $usuarioEditar['id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            required 
                            maxlength="100"
                            value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['nombre']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="usuario">Usuario <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="usuario" 
                            name="usuario" 
                            required 
                            maxlength="50"
                            value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['usuario']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="contrasena">
                            Contraseña 
                            <?php if (!$usuarioEditar): ?>
                                <span class="required">*</span>
                            <?php else: ?>
                                (dejar en blanco para no cambiar)
                            <?php endif; ?>
                        </label>
                        <input 
                            type="password" 
                            id="contrasena" 
                            name="contrasena" 
                            maxlength="255"
                            <?php if (!$usuarioEditar): ?>required<?php endif; ?>
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            maxlength="100"
                            value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['email']) : ''; ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="rol_id">Rol <span class="required">*</span></label>
                        <select id="rol_id" name="rol_id" required>
                            <option value="">Seleccione un rol</option>
                            <?php foreach ($roles as $rol): ?>
                                <option 
                                    value="<?php echo $rol['id']; ?>"
                                    <?php if ($usuarioEditar && $rol['id'] == $usuarioEditar['rol_id']): ?>selected<?php endif; ?>
                                >
                                    <?php echo htmlspecialchars($rol['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-group">
                            <input 
                                type="checkbox" 
                                name="estado" 
                                <?php if (!$usuarioEditar || $usuarioEditar['estado']): ?>checked<?php endif; ?>
                            >
                            Usuario Activo
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <?php echo $usuarioEditar ? 'Actualizar Usuario' : 'Crear Usuario'; ?>
                    </button>
                    <?php if ($usuarioEditar): ?>
                        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <h2>Lista de Usuarios</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($usuario = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo $usuario['id']; ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['rol_nombre']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $usuario['estado'] ? 'activo' : 'inactivo'; ?>">
                                <?php echo $usuario['estado'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td><?php echo $usuario['fecha_creacion'] ? date('d/m/Y', strtotime($usuario['fecha_creacion'])) : '-'; ?></td>
                        <td>
                            <div class="actions">
                                <a href="?editar=<?php echo $usuario['id']; ?>" class="btn btn-primary btn-small">
                                    Editar
                                </a>
                                <?php if ($usuario['id'] != obtenerUsuarioId()): ?>
                                    <form method="POST" action="" style="display: inline;" 
                                          onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
