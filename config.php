<?php
/**
 * Archivo de configuración del sistema
 */

// Configuración de archivos
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10485760); // 10 MB en bytes
define('ALLOWED_EXTENSIONS', ['pdf']);
define('ALLOWED_MIME_TYPES', ['application/pdf']);

// Crear directorio de uploads si no existe
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Valida el tamaño del archivo
 * @param int $size Tamaño del archivo en bytes
 * @return bool
 */
function validarTamanoArchivo($size) {
    return $size <= MAX_FILE_SIZE;
}

/**
 * Valida la extensión del archivo
 * @param string $filename Nombre del archivo
 * @return bool
 */
function validarExtension($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_EXTENSIONS);
}

/**
 * Valida el tipo MIME del archivo
 * @param string $mimeType Tipo MIME del archivo
 * @return bool
 */
function validarMimeType($mimeType) {
    return in_array($mimeType, ALLOWED_MIME_TYPES);
}

/**
 * Genera un nombre de archivo único
 * @param string $originalName Nombre original del archivo
 * @return string
 */
function generarNombreArchivoUnico($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Formatea el tamaño del archivo
 * @param int $bytes Tamaño en bytes
 * @return string
 */
function formatearTamano($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Obtiene el tamaño máximo formateado
 * @return string
 */
function obtenerTamanoMaximo() {
    return formatearTamano(MAX_FILE_SIZE);
}
?>
