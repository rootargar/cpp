<?php
session_start();

// Registrar en auditoría antes de cerrar sesión
if (isset($_SESSION['usuario_id'])) {
    require_once 'conexion.php';
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $sqlAudit = "INSERT INTO Auditoria (usuario_id, accion, descripcion, fecha, ip_address) 
                VALUES (?, 'Logout', 'Cierre de sesión', GETDATE(), ?)";
    $params = array($_SESSION['usuario_id'], $ip_address);
    sqlsrv_query($conn, $sqlAudit, $params);
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit();
?>
