<?php
session_start();

// Si ya está autenticado, redirigir al inicio
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'conexion.php';
    
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    
    if (empty($usuario) || empty($contrasena)) {
        $error = 'Por favor complete todos los campos';
    } else {
        $sql = "SELECT u.id, u.nombre, u.usuario, u.contrasena, u.rol_id, u.estado, 
                       r.nombre as rol_nombre
                FROM Usuarios u
                INNER JOIN Roles r ON u.rol_id = r.id
                WHERE u.usuario = ? AND u.estado = 1";
        
        $params = array($usuario);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            $error = 'Error en la consulta';
        } else {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($user && $contrasena === $user['contrasena']) {
                // Regenerar el ID de sesión por seguridad
                session_regenerate_id(true);
                
                // Guardar datos en sesión
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_user'] = $user['usuario'];
                $_SESSION['rol_id'] = $user['rol_id'];
                $_SESSION['rol_nombre'] = $user['rol_nombre'];
                $_SESSION['ultimo_acceso'] = time();
                
                // Registrar en auditoría
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                $sqlAudit = "INSERT INTO Auditoria (usuario_id, accion, descripcion, fecha, ip_address) 
                            VALUES (?, 'Login', 'Inicio de sesión exitoso', GETDATE(), ?)";
                $paramsAudit = array($user['id'], $ip_address);
                sqlsrv_query($conn, $sqlAudit, $paramsAudit);
                
                // Actualizar último acceso
                $sqlUpdate = "UPDATE Usuarios SET fecha_ultimo_acceso = GETDATE() WHERE id = ?";
                sqlsrv_query($conn, $sqlUpdate, array($user['id']));

                // Redirigir según el rol del usuario
                if ($user['rol_id'] == 3) { // Consultor
                    header('Location: consultar_documentos.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
                
                // Registrar intento fallido
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                $sqlAudit = "INSERT INTO Auditoria (usuario_id, accion, descripcion, fecha, ip_address) 
                            VALUES (NULL, 'Login Fallido', 'Intento de login: " . $usuario . "', GETDATE(), ?)";
                sqlsrv_query($conn, $sqlAudit, array($ip_address));
            }
            
            sqlsrv_free_stmt($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Gestión Documental</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #027be3 0%, #2196f3 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #027be3;
        }
        
        .form-group input:required:invalid {
            border-color: #ddd;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #027be3 0%, #2196f3 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Sistema de Gestión Documental</h1>
            <p>Ingrese sus credenciales para continuar</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input 
                    type="text" 
                    id="usuario" 
                    name="usuario" 
                    required 
                    autofocus 
                    maxlength="50"
                    value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input 
                    type="password" 
                    id="contrasena" 
                    name="contrasena" 
                    required 
                    maxlength="255"
                >
            </div>
            
            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
        
        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> KW-DAF Sinaloense
        </div>
    </div>
</body>
</html>
