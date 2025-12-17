<?php
/**
 * Funciones para envío de emails y procesamiento de notificaciones
 */

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/conexion.php';

/**
 * Envía un email usando SMTP
 *
 * @param string $to Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $mensaje_html Contenido HTML del mensaje
 * @return bool True si se envió correctamente, false en caso contrario
 */
function enviarEmail($to, $nombre_destinatario, $asunto, $mensaje_html) {
    // Verificar si el envío de emails está habilitado
    if (!EMAIL_ENABLED) {
        registrarLogEmail("Envío de emails deshabilitado en configuración");
        return false;
    }

    // Validar email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        registrarLogEmail("Email inválido: $to");
        return false;
    }

    try {
        // Headers del email
        $headers = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">";
        $headers[] = "Reply-To: " . EMAIL_FROM;
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "X-Priority: 3";

        // Configurar parámetros de mail
        $parametros_adicionales = '';
        if (defined('EMAIL_FROM') && EMAIL_FROM) {
            $parametros_adicionales = '-f' . EMAIL_FROM;
        }

        // Enviar email
        $resultado = mail($to, $asunto, $mensaje_html, implode("\r\n", $headers), $parametros_adicionales);

        if ($resultado) {
            registrarLogEmail("Email enviado exitosamente a: $to - Asunto: $asunto");
            return true;
        } else {
            registrarLogEmail("Error al enviar email a: $to - Asunto: $asunto");
            return false;
        }

    } catch (Exception $e) {
        registrarLogEmail("Excepción al enviar email: " . $e->getMessage());
        return false;
    }
}

/**
 * Envía un email usando SMTP con socket (alternativa más robusta)
 * Esta función conecta directamente al servidor SMTP
 *
 * @param string $to Email del destinatario
 * @param string $nombre_destinatario Nombre del destinatario
 * @param string $asunto Asunto del email
 * @param string $mensaje_html Contenido HTML del mensaje
 * @return bool True si se envió correctamente, false en caso contrario
 */
function enviarEmailSMTP($to, $nombre_destinatario, $asunto, $mensaje_html) {
    if (!EMAIL_ENABLED) {
        registrarLogEmail("Envío de emails deshabilitado en configuración");
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        registrarLogEmail("Email inválido: $to");
        return false;
    }

    try {
        // Preparar la conexión
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;

        // Crear socket
        $socket = fsockopen($host, $port, $errno, $errstr, SMTP_TIMEOUT);

        if (!$socket) {
            registrarLogEmail("Error conectando a SMTP: $errno - $errstr");
            return false;
        }

        // Leer respuesta del servidor
        $respuesta = fgets($socket, 515);

        // EHLO
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $respuesta = fgets($socket, 515);

        // STARTTLS si está configurado
        if (SMTP_SECURE == 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $respuesta = fgets($socket, 515);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $respuesta = fgets($socket, 515);
        }

        // Autenticación
        if (SMTP_AUTH) {
            fputs($socket, "AUTH LOGIN\r\n");
            $respuesta = fgets($socket, 515);

            fputs($socket, base64_encode($username) . "\r\n");
            $respuesta = fgets($socket, 515);

            fputs($socket, base64_encode($password) . "\r\n");
            $respuesta = fgets($socket, 515);

            if (substr($respuesta, 0, 3) != '235') {
                registrarLogEmail("Error en autenticación SMTP: $respuesta");
                fclose($socket);
                return false;
            }
        }

        // MAIL FROM
        fputs($socket, "MAIL FROM: <" . EMAIL_FROM . ">\r\n");
        $respuesta = fgets($socket, 515);

        // RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $respuesta = fgets($socket, 515);

        // DATA
        fputs($socket, "DATA\r\n");
        $respuesta = fgets($socket, 515);

        // Construir el mensaje
        $mensaje_completo = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
        $mensaje_completo .= "To: $nombre_destinatario <$to>\r\n";
        $mensaje_completo .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
        $mensaje_completo .= "MIME-Version: 1.0\r\n";
        $mensaje_completo .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mensaje_completo .= "Content-Transfer-Encoding: base64\r\n";
        $mensaje_completo .= "\r\n";
        $mensaje_completo .= chunk_split(base64_encode($mensaje_html));
        $mensaje_completo .= "\r\n.\r\n";

        fputs($socket, $mensaje_completo);
        $respuesta = fgets($socket, 515);

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        registrarLogEmail("Email SMTP enviado exitosamente a: $to - Asunto: $asunto");
        return true;

    } catch (Exception $e) {
        registrarLogEmail("Excepción en enviarEmailSMTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea una notificación en la base de datos
 *
 * @param int $documento_id ID del documento
 * @param string $tipo_evento Tipo de evento (Cambio Estado, Nueva Version, etc.)
 * @param string $mensaje Mensaje de la notificación
 * @param string $destinatarios Emails de destinatarios separados por coma (opcional)
 * @return bool True si se creó correctamente
 */
function crearNotificacion($documento_id, $tipo_evento, $mensaje, $destinatarios = '') {
    global $conn;

    $sql = "INSERT INTO Notificaciones
            (documento_id, tipo_evento, fecha_programada, enviado, mensaje, destinatarios)
            VALUES (?, ?, GETDATE(), 0, ?, ?)";

    $params = array($documento_id, $tipo_evento, $mensaje, $destinatarios);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        registrarLogEmail("Notificación creada - Documento ID: $documento_id, Tipo: $tipo_evento");
        return true;
    } else {
        registrarLogEmail("Error al crear notificación: " . print_r(sqlsrv_errors(), true));
        return false;
    }
}

/**
 * Obtiene el template HTML para un tipo de notificación
 *
 * @param string $tipo_evento Tipo de evento
 * @param array $datos Datos para reemplazar en el template
 * @return string HTML del email
 */
function obtenerTemplateEmail($tipo_evento, $datos) {
    // Template HTML básico
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: #027be3; color: white; padding: 20px; text-align: center; }
            .content { background: white; padding: 30px; margin: 20px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 12px 30px; background: #027be3; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .info-box { background: #f0f8ff; border-left: 4px solid #027be3; padding: 15px; margin: 15px 0; }
            .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
            .danger { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Sistema de Gestión Documental CPP</h1>
            </div>
            <div class="content">
    ';

    // Contenido según tipo de evento
    switch ($tipo_evento) {
        case 'Cambio Estado':
            $html .= '
                <h2>Cambio de Estado de Documento</h2>
                <div class="info-box">
                    <p><strong>Documento:</strong> ' . htmlspecialchars($datos['nombre_documento'] ?? '') . '</p>
                    <p><strong>Código:</strong> ' . htmlspecialchars($datos['codigo_documento'] ?? '') . '</p>
                    <p><strong>Nuevo Estado:</strong> <strong style="color: #027be3;">' . htmlspecialchars($datos['estado'] ?? '') . '</strong></p>
                </div>
                <p>' . htmlspecialchars($datos['mensaje'] ?? '') . '</p>
            ';
            break;

        case 'Nueva Version':
            $html .= '
                <h2>Nueva Versión de Documento Disponible</h2>
                <div class="info-box">
                    <p><strong>Documento:</strong> ' . htmlspecialchars($datos['nombre_documento'] ?? '') . '</p>
                    <p><strong>Código:</strong> ' . htmlspecialchars($datos['codigo_documento'] ?? '') . '</p>
                    <p><strong>Versión:</strong> ' . htmlspecialchars($datos['version'] ?? '') . '</p>
                    <p><strong>Subida por:</strong> ' . htmlspecialchars($datos['usuario'] ?? '') . '</p>
                </div>
                <p>' . htmlspecialchars($datos['mensaje'] ?? '') . '</p>
            ';
            break;

        case 'Documento Vencido':
            $html .= '
                <h2>⚠️ Documento Vencido</h2>
                <div class="danger">
                    <p><strong>Documento:</strong> ' . htmlspecialchars($datos['nombre_documento'] ?? '') . '</p>
                    <p><strong>Código:</strong> ' . htmlspecialchars($datos['codigo_documento'] ?? '') . '</p>
                    <p><strong>Fecha de Vencimiento:</strong> ' . htmlspecialchars($datos['fecha_vencimiento'] ?? '') . '</p>
                </div>
                <p>Este documento ha vencido y requiere atención inmediata.</p>
            ';
            break;

        case 'Proximo Vencimiento':
            $html .= '
                <h2>📅 Documento Próximo a Vencer</h2>
                <div class="alert">
                    <p><strong>Documento:</strong> ' . htmlspecialchars($datos['nombre_documento'] ?? '') . '</p>
                    <p><strong>Código:</strong> ' . htmlspecialchars($datos['codigo_documento'] ?? '') . '</p>
                    <p><strong>Fecha de Vencimiento:</strong> ' . htmlspecialchars($datos['fecha_vencimiento'] ?? '') . '</p>
                    <p><strong>Días restantes:</strong> ' . htmlspecialchars($datos['dias_restantes'] ?? '') . '</p>
                </div>
                <p>Por favor, revise este documento antes de su vencimiento.</p>
            ';
            break;

        default:
            $html .= '
                <h2>Notificación del Sistema</h2>
                <p>' . htmlspecialchars($datos['mensaje'] ?? '') . '</p>
            ';
    }

    // Cerrar template
    $html .= '
            </div>
            <div class="footer">
                <p>Este es un mensaje automático del Sistema de Gestión Documental CPP</p>
                <p>Por favor, no responda a este email</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return $html;
}

/**
 * Procesa las notificaciones pendientes y envía los emails
 *
 * @param int $limite Número máximo de notificaciones a procesar
 * @return array Resultados del procesamiento
 */
function procesarNotificacionesPendientes($limite = 50) {
    global $conn;

    $resultados = [
        'procesadas' => 0,
        'enviadas' => 0,
        'errores' => 0,
        'detalles' => []
    ];

    // Obtener notificaciones pendientes
    $sql = "SELECT TOP $limite
            n.id,
            n.documento_id,
            n.tipo_evento,
            n.mensaje,
            n.destinatarios,
            d.nombre as nombre_documento,
            d.codigo as codigo_documento,
            d.estado,
            d.responsable_id,
            d.fecha_vencimiento,
            u.email as responsable_email,
            u.nombre as responsable_nombre
            FROM Notificaciones n
            INNER JOIN Documentos d ON n.documento_id = d.id
            LEFT JOIN Usuarios u ON d.responsable_id = u.id
            WHERE n.enviado = 0
            AND n.fecha_programada <= GETDATE()
            ORDER BY n.fecha_programada ASC";

    $stmt = sqlsrv_query($conn, $sql);

    if (!$stmt) {
        registrarLogEmail("Error al obtener notificaciones pendientes: " . print_r(sqlsrv_errors(), true));
        return $resultados;
    }

    while ($notif = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $resultados['procesadas']++;

        // Determinar destinatarios
        $emails_destinatarios = [];

        // Si tiene destinatarios específicos
        if (!empty($notif['destinatarios'])) {
            $emails_destinatarios = array_map('trim', explode(',', $notif['destinatarios']));
        }
        // Si no, enviar al responsable del documento
        elseif (!empty($notif['responsable_email'])) {
            $emails_destinatarios[] = $notif['responsable_email'];
        }

        // Preparar datos para el template
        $datos = [
            'nombre_documento' => $notif['nombre_documento'],
            'codigo_documento' => $notif['codigo_documento'],
            'estado' => $notif['estado'],
            'mensaje' => $notif['mensaje'],
            'fecha_vencimiento' => $notif['fecha_vencimiento'] ? date('d/m/Y', strtotime($notif['fecha_vencimiento'])) : '',
        ];

        // Generar asunto
        $asunto = str_replace(
            ['[CODIGO]', '[ESTADO]'],
            [$notif['codigo_documento'], $notif['estado']],
            "DMS - " . $notif['tipo_evento'] . " - " . $notif['codigo_documento']
        );

        // Obtener HTML del email
        $html = obtenerTemplateEmail($notif['tipo_evento'], $datos);

        // Enviar a cada destinatario
        $envio_exitoso = false;
        foreach ($emails_destinatarios as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Intentar con función SMTP primero
                if (enviarEmailSMTP($email, $notif['responsable_nombre'] ?? 'Usuario', $asunto, $html)) {
                    $envio_exitoso = true;
                    $resultados['enviadas']++;
                    $resultados['detalles'][] = "Enviado a: $email - Notif ID: {$notif['id']}";
                } else {
                    $resultados['errores']++;
                    $resultados['detalles'][] = "Error enviando a: $email - Notif ID: {$notif['id']}";
                }
            }
        }

        // Marcar como enviado si al menos un email se envió exitosamente
        if ($envio_exitoso || empty($emails_destinatarios)) {
            $sqlUpdate = "UPDATE Notificaciones
                         SET enviado = 1, fecha_envio = GETDATE()
                         WHERE id = ?";
            sqlsrv_query($conn, $sqlUpdate, array($notif['id']));
        }
    }

    registrarLogEmail("Procesamiento completado: " . print_r($resultados, true));
    return $resultados;
}

/**
 * Obtiene emails de usuarios por rol
 *
 * @param array $roles Array de IDs de roles
 * @return array Array de emails
 */
function obtenerEmailsPorRol($roles) {
    global $conn;

    if (empty($roles)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT DISTINCT email
            FROM Usuarios
            WHERE rol_id IN ($placeholders)
            AND estado = 1
            AND email IS NOT NULL
            AND email != ''";

    $stmt = sqlsrv_query($conn, $sql, $roles);

    // Verificar si la consulta falló
    if ($stmt === false) {
        registrarLogEmail("Error en obtenerEmailsPorRol: " . print_r(sqlsrv_errors(), true));
        return [];
    }

    $emails = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if (!empty($row['email'])) {
            $emails[] = $row['email'];
        }
    }

    return $emails;
}

/**
 * Registra un mensaje en el log de emails
 *
 * @param string $mensaje Mensaje a registrar
 */
function registrarLogEmail($mensaje) {
    if (!EMAIL_LOG_ERRORS) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_mensaje = "[$timestamp] $mensaje\n";

    @file_put_contents(EMAIL_LOG_FILE, $log_mensaje, FILE_APPEND);
}

/**
 * Verifica documentos próximos a vencer y crea notificaciones
 *
 * @param int $dias_anticipacion Días de anticipación para notificar (por defecto 7)
 * @return int Número de notificaciones creadas
 */
function verificarDocumentosProximosVencer($dias_anticipacion = 7) {
    global $conn, $NOTIFICACIONES_POR_ROL;

    $sql = "SELECT d.id, d.nombre, d.codigo, d.fecha_vencimiento,
                   DATEDIFF(day, GETDATE(), d.fecha_vencimiento) as dias_restantes
            FROM Documentos d
            WHERE d.activo = 1
            AND d.fecha_vencimiento IS NOT NULL
            AND DATEDIFF(day, GETDATE(), d.fecha_vencimiento) > 0
            AND DATEDIFF(day, GETDATE(), d.fecha_vencimiento) <= ?
            AND NOT EXISTS (
                SELECT 1 FROM Notificaciones n
                WHERE n.documento_id = d.id
                AND n.tipo_evento = 'Proximo Vencimiento'
                AND n.fecha_programada >= DATEADD(day, -?, GETDATE())
            )";

    $stmt = sqlsrv_query($conn, $sql, array($dias_anticipacion, $dias_anticipacion));

    $contador = 0;
    while ($doc = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Obtener emails de roles autorizados
        $emails = obtenerEmailsPorRol($NOTIFICACIONES_POR_ROL['Proximo Vencimiento'] ?? [1]);
        $destinatarios = implode(',', $emails);

        $mensaje = "El documento vence en {$doc['dias_restantes']} días";

        if (crearNotificacion($doc['id'], 'Proximo Vencimiento', $mensaje, $destinatarios)) {
            $contador++;
        }
    }

    return $contador;
}

?>
