<?php
/**
 * Archivo de conexión a SQL Server
 * Base de datos: CPP
 * Servidor: KWSERVIFACT
 */

// Configuración de la conexión
$serverName = "KWSERVIFACT";
$connectionOptions = array(
    "Database" => "CPP",
    "Uid" => "sa",
    "PWD" => "f4cturAs",
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true
);

// Variable global para la conexión
$conn = null;

/**
 * Obtiene la conexión a la base de datos
 * @return resource|false Conexión activa o false en caso de error
 */
function getConnection() {
    global $conn, $serverName, $connectionOptions;
    
    // Si ya existe una conexión activa, retornarla
    if ($conn !== null && $conn !== false) {
        return $conn;
    }
    
    // Intentar establecer la conexión
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    
    // Manejo de errores
    if ($conn === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error al conectar con la base de datos:\n";
        
        if ($errors !== null) {
            foreach ($errors as $error) {
                $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . "\n";
                $errorMsg .= "Código: " . $error['code'] . "\n";
                $errorMsg .= "Mensaje: " . $error['message'] . "\n";
            }
        }
        
        error_log($errorMsg);
        return false;
    }
    
    return $conn;
}

/**
 * Cierra la conexión a la base de datos
 */
function closeConnection() {
    global $conn;
    
    if ($conn !== null && $conn !== false) {
        sqlsrv_close($conn);
        $conn = null;
    }
}

// Establecer la conexión inicial
$conn = getConnection();

// Verificar si la conexión fue exitosa
if ($conn === false) {
    die("No se pudo establecer la conexión con la base de datos. Revise los logs para más información.");
}
?>
