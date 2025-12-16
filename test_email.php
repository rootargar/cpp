<?php
/**
 * Script de prueba para verificar la configuración de email
 *
 * Este script permite probar la configuración SMTP sin necesidad
 * de crear notificaciones en la base de datos.
 *
 * IMPORTANTE: Solo ejecutar desde CLI o como administrador desde navegador
 */

// Determinar si se ejecuta desde línea de comandos
$es_cli = (php_sapi_name() === 'cli');

// Si se ejecuta desde navegador, requerir autenticación
if (!$es_cli) {
    require_once 'verificar_login.php';
    verificarLogin();
    requiereRol([1], 'Solo administradores pueden ejecutar este script');
}

require_once __DIR__ . '/email_functions.php';

// Configuración del email de prueba
$email_destino = 'tu_email@empresa.com';  // CAMBIAR POR UN EMAIL VÁLIDO
$nombre_destino = 'Usuario de Prueba';

// Verificar si se proporcionó un email como parámetro
if ($es_cli && isset($argv[1])) {
    $email_destino = $argv[1];
    if (isset($argv[2])) {
        $nombre_destino = $argv[2];
    }
} elseif (!$es_cli && isset($_GET['email'])) {
    $email_destino = $_GET['email'];
    if (isset($_GET['nombre'])) {
        $nombre_destino = $_GET['nombre'];
    }
}

// HTML del navegador
if (!$es_cli) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Prueba de Email - Sistema DMS</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #027be3; border-bottom: 2px solid #027be3; padding-bottom: 10px; }
            .form-group { margin: 20px 0; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
            .btn { padding: 12px 30px; background: #027be3; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; margin-right: 10px; }
            .btn:hover { background: #0056b3; }
            .btn-secondary { background: #6c757d; }
            .btn-secondary:hover { background: #5a6268; }
            .info { background: #e6f3ff; padding: 15px; border-left: 4px solid #027be3; margin: 20px 0; }
            .success { background: #c6f6d5; padding: 15px; border-left: 4px solid #48bb78; margin: 20px 0; }
            .error { background: #fed7d7; padding: 15px; border-left: 4px solid #f56565; margin: 20px 0; }
            .config { background: #f7fafc; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧪 Prueba de Configuración de Email</h1>';

    // Mostrar formulario
    echo '<form method="GET" action="">
            <div class="form-group">
                <label>Email de destino:</label>
                <input type="email" name="email" value="' . htmlspecialchars($email_destino) . '" required>
            </div>
            <div class="form-group">
                <label>Nombre del destinatario:</label>
                <input type="text" name="nombre" value="' . htmlspecialchars($nombre_destino) . '" required>
            </div>
            <button type="submit" class="btn" name="enviar" value="1">📧 Enviar Email de Prueba</button>
            <a href="index.php" class="btn btn-secondary">← Volver</a>
          </form>';
}

// Ejecutar prueba solo si se solicita
$ejecutar_prueba = false;

if ($es_cli) {
    $ejecutar_prueba = true;
} elseif (isset($_GET['enviar']) && $_GET['enviar'] == '1') {
    $ejecutar_prueba = true;
}

if ($ejecutar_prueba) {
    // Mostrar configuración actual
    if ($es_cli) {
        echo "\n=== PRUEBA DE CONFIGURACIÓN DE EMAIL ===\n\n";
        echo "Configuración SMTP:\n";
        echo "  Servidor: " . SMTP_HOST . "\n";
        echo "  Puerto: " . SMTP_PORT . "\n";
        echo "  Seguridad: " . (SMTP_SECURE ?: 'Ninguna') . "\n";
        echo "  Usuario: " . SMTP_USERNAME . "\n";
        echo "  Remitente: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\n";
        echo "  Habilitado: " . (EMAIL_ENABLED ? 'Sí' : 'No') . "\n\n";
    } else {
        echo '<div class="info">
                <h3>Configuración SMTP Actual:</h3>
                <div class="config">
                    Servidor: ' . SMTP_HOST . '<br>
                    Puerto: ' . SMTP_PORT . '<br>
                    Seguridad: ' . (SMTP_SECURE ?: 'Ninguna') . '<br>
                    Usuario: ' . SMTP_USERNAME . '<br>
                    Remitente: ' . EMAIL_FROM_NAME . ' &lt;' . EMAIL_FROM . '&gt;<br>
                    Habilitado: ' . (EMAIL_ENABLED ? 'Sí' : 'No') . '
                </div>
              </div>';
    }

    // Verificar que EMAIL_ENABLED esté activo
    if (!EMAIL_ENABLED) {
        if ($es_cli) {
            echo "✗ ERROR: El envío de emails está deshabilitado en la configuración\n";
            echo "   Cambie EMAIL_ENABLED a true en email_config.php\n";
        } else {
            echo '<div class="error">
                    <strong>✗ Error:</strong> El envío de emails está deshabilitado en la configuración.<br>
                    Cambie <code>EMAIL_ENABLED</code> a <code>true</code> en <code>email_config.php</code>
                  </div>';
        }
    } else {
        // Preparar mensaje de prueba
        $asunto = "Prueba de Configuración - Sistema DMS CPP";

        $datos = [
            'nombre_documento' => 'Documento de Prueba',
            'codigo_documento' => 'TEST-001',
            'estado' => 'Aprobado',
            'mensaje' => 'Este es un email de prueba para verificar la configuración SMTP del sistema.',
        ];

        $html = obtenerTemplateEmail('Cambio Estado', $datos);

        // Intentar enviar
        if ($es_cli) {
            echo "Enviando email de prueba a: $email_destino\n";
            echo "Espere...\n\n";
        } else {
            echo '<div class="info">
                    📨 Enviando email de prueba a: <strong>' . htmlspecialchars($email_destino) . '</strong><br>
                    Espere un momento...
                  </div>';
        }

        $inicio = microtime(true);
        $resultado = enviarEmailSMTP($email_destino, $nombre_destino, $asunto, $html);
        $tiempo = round(microtime(true) - $inicio, 2);

        if ($resultado) {
            if ($es_cli) {
                echo "✓ Email enviado correctamente en $tiempo segundos\n";
                echo "  Revise la bandeja de entrada de $email_destino\n";
                echo "  (Revise también la carpeta de spam)\n\n";
            } else {
                echo '<div class="success">
                        <strong>✓ Email enviado correctamente</strong><br>
                        Tiempo de envío: ' . $tiempo . ' segundos<br>
                        Revise la bandeja de entrada de <strong>' . htmlspecialchars($email_destino) . '</strong><br>
                        <small>(Revise también la carpeta de spam)</small>
                      </div>';
            }
        } else {
            if ($es_cli) {
                echo "✗ Error al enviar el email\n";
                echo "  Tiempo transcurrido: $tiempo segundos\n";
                echo "  Revise los logs para más detalles:\n";
                echo "    tail -n 50 logs/email_errors.log\n\n";
            } else {
                echo '<div class="error">
                        <strong>✗ Error al enviar el email</strong><br>
                        Tiempo transcurrido: ' . $tiempo . ' segundos<br>
                        Revise los logs en <code>logs/email_errors.log</code> para más detalles.
                      </div>';

                // Mostrar últimas líneas del log si existe
                if (file_exists(EMAIL_LOG_FILE)) {
                    $log_content = file(EMAIL_LOG_FILE);
                    $ultimas_lineas = array_slice($log_content, -10);

                    echo '<div class="info">
                            <h3>Últimas 10 líneas del log:</h3>
                            <div class="config">';

                    foreach ($ultimas_lineas as $linea) {
                        echo htmlspecialchars($linea) . '<br>';
                    }

                    echo '</div></div>';
                }
            }
        }

        // Consejos de solución
        if (!$resultado) {
            if ($es_cli) {
                echo "Consejos de solución:\n";
                echo "  1. Verifique servidor, puerto y credenciales en email_config.php\n";
                echo "  2. Confirme que el firewall permita conexiones al puerto " . SMTP_PORT . "\n";
                echo "  3. Pruebe con diferentes configuraciones de cifrado (tls, ssl, '')\n";
                echo "  4. Verifique que el servidor SMTP esté activo\n\n";
            } else {
                echo '<div class="info">
                        <h3>Consejos de solución:</h3>
                        <ol>
                            <li>Verifique servidor, puerto y credenciales en <code>email_config.php</code></li>
                            <li>Confirme que el firewall permita conexiones al puerto ' . SMTP_PORT . '</li>
                            <li>Pruebe con diferentes configuraciones de cifrado (tls, ssl, sin cifrado)</li>
                            <li>Verifique que el servidor SMTP esté activo y accesible</li>
                            <li>Consulte con su departamento de TI sobre la configuración SMTP corporativa</li>
                        </ol>
                      </div>';
            }
        }
    }

    if ($es_cli) {
        echo "=== FIN DE LA PRUEBA ===\n";
    }
}

if (!$es_cli) {
    echo '</div></body></html>';
}
?>
