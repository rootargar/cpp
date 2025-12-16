<?php
require_once 'verificar_login.php';
require_once 'conexion.php';
require_once 'config.php';

// Verificar que el usuario esté autenticado
verificarLogin();

$version_id = $_GET['version_id'] ?? '';

if (empty($version_id)) {
    die('ID de versión no válido');
}

// Obtener información de la versión
$sql = "SELECT v.*, d.nombre as documento_nombre, d.codigo as documento_codigo
        FROM VersionesDocumento v
        INNER JOIN Documentos d ON v.documento_id = d.id
        WHERE v.id = ?";

$stmt = sqlsrv_query($conn, $sql, array($version_id));
$version = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$version) {
    die('Versión no encontrada');
}

$rutaArchivo = UPLOAD_DIR . $version['ruta_archivo'];

// Verificar que el archivo existe
if (!file_exists($rutaArchivo)) {
    die('Archivo no encontrado');
}

// Registrar visualización en auditoría
registrarAuditoria(
    'Visualizar Documento', 
    "Visualización de versión " . $version['numero_version'] . " del documento: " . $version['documento_nombre'],
    'VersionesDocumento',
    $version_id
);

// Configurar headers para visualización en el navegador
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $version['documento_codigo'] . '_v' . $version['numero_version'] . '.pdf"');
header('Content-Length: ' . filesize($rutaArchivo));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Limpiar buffer de salida
ob_clean();
flush();

// Enviar archivo
readfile($rutaArchivo);
exit();
?>
