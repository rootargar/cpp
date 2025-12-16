<?php
/**
 * Archivo de verificación de autenticación y control de acceso
 * Incluir este archivo en todas las páginas protegidas
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica si el usuario está autenticado
 * Redirige al login si no lo está
 */
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
    
    // Verificar tiempo de inactividad (30 minutos)
    if (isset($_SESSION['ultimo_acceso'])) {
        $tiempoInactivo = time() - $_SESSION['ultimo_acceso'];
        
        if ($tiempoInactivo > 1800) { // 30 minutos
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    
    // Actualizar último acceso
    $_SESSION['ultimo_acceso'] = time();
    
    // Regenerar ID de sesión periódicamente (cada 30 minutos)
    if (!isset($_SESSION['ultima_regeneracion'])) {
        $_SESSION['ultima_regeneracion'] = time();
    } elseif (time() - $_SESSION['ultima_regeneracion'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['ultima_regeneracion'] = time();
    }
}

/**
 * Verifica si el usuario tiene un rol específico
 * @param int $rol_id ID del rol requerido
 * @return bool
 */
function tieneRol($rol_id) {
    return isset($_SESSION['rol_id']) && $_SESSION['rol_id'] == $rol_id;
}

/**
 * Verifica si el usuario tiene uno de varios roles
 * @param array $roles_permitidos Array de IDs de roles permitidos
 * @return bool
 */
function tieneAlgunRol($roles_permitidos) {
    if (!isset($_SESSION['rol_id'])) {
        return false;
    }
    
    return in_array($_SESSION['rol_id'], $roles_permitidos);
}

/**
 * Verifica si el usuario es Administrador
 * @return bool
 */
function esAdministrador() {
    return tieneRol(1); // 1 = Administrador
}

/**
 * Verifica si el usuario es Editor
 * @return bool
 */
function esEditor() {
    return tieneRol(2); // 2 = Editor
}

/**
 * Verifica si el usuario es Consultor
 * @return bool
 */
function esConsultor() {
    return tieneRol(3); // 3 = Consultor
}

/**
 * Verifica si el usuario es Aprobador
 * @return bool
 */
function esAprobador() {
    return tieneRol(4); // 4 = Aprobador
}

/**
 * Requiere que el usuario tenga un rol específico
 * Redirige si no tiene el rol
 * @param array $roles_permitidos Array de IDs de roles permitidos
 * @param string $mensaje_error Mensaje opcional de error
 */
function requiereRol($roles_permitidos, $mensaje_error = 'No tiene permisos para acceder a esta página') {
    if (!tieneAlgunRol($roles_permitidos)) {
        $_SESSION['error_mensaje'] = $mensaje_error;
        header('Location: index.php');
        exit();
    }
}

/**
 * Requiere que el usuario sea Administrador
 */
function requiereAdministrador() {
    requiereRol([1], 'Solo los administradores pueden acceder a esta página');
}

/**
 * Obtiene el nombre del usuario actual
 * @return string
 */
function obtenerNombreUsuario() {
    return $_SESSION['usuario_nombre'] ?? '';
}

/**
 * Obtiene el ID del usuario actual
 * @return int
 */
function obtenerUsuarioId() {
    return $_SESSION['usuario_id'] ?? 0;
}

/**
 * Obtiene el nombre del rol actual
 * @return string
 */
function obtenerNombreRol() {
    return $_SESSION['rol_nombre'] ?? '';
}

/**
 * Registra una acción en la auditoría
 * @param string $accion Nombre de la acción
 * @param string $descripcion Descripción de la acción
 * @param string $tabla_afectada Nombre de la tabla afectada (opcional)
 * @param int $registro_id ID del registro afectado (opcional)
 */
function registrarAuditoria($accion, $descripcion, $tabla_afectada = null, $registro_id = null) {
    require_once 'conexion.php';
    
    $conn = getConnection();
    
    if ($conn === false) {
        return false;
    }
    
    $usuario_id = obtenerUsuarioId();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $sql = "INSERT INTO Auditoria (usuario_id, accion, descripcion, tabla_afectada, registro_id, fecha, ip_address) 
            VALUES (?, ?, ?, ?, ?, GETDATE(), ?)";
    
    $params = array($usuario_id, $accion, $descripcion, $tabla_afectada, $registro_id, $ip_address);
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    return ($stmt !== false);
}

/**
 * Muestra un mensaje de error si existe
 */
function mostrarMensajeError() {
    if (isset($_SESSION['error_mensaje'])) {
        $mensaje = $_SESSION['error_mensaje'];
        unset($_SESSION['error_mensaje']);
        return '<div class="alert alert-error">' . htmlspecialchars($mensaje) . '</div>';
    }
    return '';
}

/**
 * Muestra un mensaje de éxito si existe
 */
function mostrarMensajeExito() {
    if (isset($_SESSION['exito_mensaje'])) {
        $mensaje = $_SESSION['exito_mensaje'];
        unset($_SESSION['exito_mensaje']);
        return '<div class="alert alert-success">' . htmlspecialchars($mensaje) . '</div>';
    }
    return '';
}

// Ejemplo de uso en páginas protegidas:
// require_once 'verificar_login.php';
// verificarLogin();
// 
// Para páginas que requieren rol específico:
// requiereAdministrador();
// o
// requiereRol([1, 2]); // Permite Administrador y Editor
?>
