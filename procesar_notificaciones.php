<?php
/**
 * Script para procesar notificaciones pendientes
 *
 * Este script puede ejecutarse de dos formas:
 * 1. Manualmente desde el navegador (requiere autenticación como administrador)
 * 2. Mediante CRON (ejecución automática)
 *
 * Configuración CRON recomendada (cada 15 minutos):
 * */15 * * * * /usr/bin/php /ruta/al/proyecto/procesar_notificaciones.php
 *
 * O cada hora:
 * 0 * * * * /usr/bin/php /ruta/al/proyecto/procesar_notificaciones.php
 */

// Determinar si se ejecuta desde línea de comandos o navegador
$es_cli = (php_sapi_name() === 'cli');

// Si se ejecuta desde navegador, requerir autenticación
if (!$es_cli) {
    require_once 'verificar_login.php';
    verificarLogin();
    requiereRol([1], 'Solo administradores pueden ejecutar este proceso');

    // Establecer tipo de contenido
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/conexion.php';

// Configuración
$LIMITE_NOTIFICACIONES = 100;  // Máximo de notificaciones a procesar por ejecución
$DIAS_ANTICIPACION_VENCIMIENTO = 7;  // Notificar con 7 días de anticipación

// Inicio del proceso
$inicio = microtime(true);
$fecha_hora = date('Y-m-d H:i:s');

if (!$es_cli) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Procesador de Notificaciones</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #027be3; border-bottom: 2px solid #027be3; padding-bottom: 10px; }
            h2 { color: #333; margin-top: 30px; }
            .info { background: #e6f3ff; padding: 15px; border-left: 4px solid #027be3; margin: 15px 0; }
            .success { background: #c6f6d5; padding: 15px; border-left: 4px solid #48bb78; margin: 15px 0; }
            .error { background: #fed7d7; padding: 15px; border-left: 4px solid #f56565; margin: 15px 0; }
            .warning { background: #feebc8; padding: 15px; border-left: 4px solid #f6ad55; margin: 15px 0; }
            .log { background: #f7fafc; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px; max-height: 400px; overflow-y: auto; }
            .log-entry { margin: 5px 0; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat-card { background: #f7fafc; padding: 20px; border-radius: 5px; text-align: center; border-left: 4px solid #027be3; }
            .stat-number { font-size: 32px; font-weight: bold; color: #027be3; }
            .stat-label { color: #666; font-size: 14px; }
            .btn { display: inline-block; padding: 10px 20px; background: #027be3; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📧 Procesador de Notificaciones por Email</h1>
            <div class="info">
                <strong>Inicio de procesamiento:</strong> ' . $fecha_hora . '
            </div>';
}

echo $es_cli ? "\n=== PROCESADOR DE NOTIFICACIONES ===\n" : "";
echo $es_cli ? "Inicio: $fecha_hora\n\n" : "";

// 1. Verificar documentos próximos a vencer
echo $es_cli ? "1. Verificando documentos próximos a vencer...\n" : "<h2>1. Verificación de Vencimientos</h2>";

$notificaciones_vencimiento = verificarDocumentosProximosVencer($DIAS_ANTICIPACION_VENCIMIENTO);

if ($es_cli) {
    echo "   ✓ Creadas $notificaciones_vencimiento notificaciones de vencimiento\n\n";
} else {
    echo "<div class='success'>✓ Se crearon <strong>$notificaciones_vencimiento</strong> notificaciones de vencimiento</div>";
}

// 2. Procesar notificaciones pendientes
echo $es_cli ? "2. Procesando notificaciones pendientes...\n" : "<h2>2. Procesamiento de Notificaciones Pendientes</h2>";

$resultados = procesarNotificacionesPendientes($LIMITE_NOTIFICACIONES);

if ($es_cli) {
    echo "   Procesadas: {$resultados['procesadas']}\n";
    echo "   Enviadas: {$resultados['enviadas']}\n";
    echo "   Errores: {$resultados['errores']}\n";
} else {
    echo '<div class="stats">
            <div class="stat-card">
                <div class="stat-number">' . $resultados['procesadas'] . '</div>
                <div class="stat-label">Procesadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $resultados['enviadas'] . '</div>
                <div class="stat-label">Enviadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $resultados['errores'] . '</div>
                <div class="stat-label">Errores</div>
            </div>
          </div>';
}

// 3. Mostrar detalles
if (!empty($resultados['detalles'])) {
    echo $es_cli ? "\n3. Detalles del procesamiento:\n" : "<h2>3. Detalles del Procesamiento</h2><div class='log'>";

    foreach ($resultados['detalles'] as $detalle) {
        if ($es_cli) {
            echo "   - $detalle\n";
        } else {
            $clase = strpos($detalle, 'Error') !== false ? 'error' : 'success';
            echo "<div class='log-entry'>• $detalle</div>";
        }
    }

    echo $es_cli ? "" : "</div>";
}

// 4. Estadísticas finales
$tiempo_ejecucion = round(microtime(true) - $inicio, 2);

echo $es_cli ? "\n" : "<h2>4. Resumen Final</h2>";

if ($es_cli) {
    echo "Proceso completado en $tiempo_ejecucion segundos\n";
    echo "Fin: " . date('Y-m-d H:i:s') . "\n";
    echo "=== FIN DEL PROCESO ===\n";
} else {
    $mensaje_final = "Proceso completado exitosamente";
    $clase_final = 'success';

    if ($resultados['errores'] > 0) {
        $mensaje_final = "Proceso completado con algunos errores";
        $clase_final = 'warning';
    }

    echo "<div class='$clase_final'>
            <strong>$mensaje_final</strong><br>
            Tiempo de ejecución: $tiempo_ejecucion segundos<br>
            Finalizado: " . date('Y-m-d H:i:s') . "
          </div>";

    echo '<div style="margin-top: 30px;">
            <a href="index.php" class="btn">← Volver al Inicio</a>
            <a href="' . $_SERVER['PHP_SELF'] . '" class="btn">🔄 Ejecutar Nuevamente</a>
          </div>';

    echo '</div></body></html>';
}

// Registrar en log
registrarLogEmail("=== EJECUCIÓN COMPLETADA ===");
registrarLogEmail("Notificaciones vencimiento: $notificaciones_vencimiento");
registrarLogEmail("Notificaciones procesadas: {$resultados['procesadas']}");
registrarLogEmail("Notificaciones enviadas: {$resultados['enviadas']}");
registrarLogEmail("Errores: {$resultados['errores']}");
registrarLogEmail("Tiempo: $tiempo_ejecucion segundos");

// Código de salida
exit($resultados['errores'] > 0 ? 1 : 0);
?>
