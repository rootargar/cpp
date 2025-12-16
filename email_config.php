<?php
/**
 * Configuración de Email SMTP
 * Configure estos parámetros según su servidor de correo corporativo
 */

// ============================================
// CONFIGURACIÓN SMTP
// ============================================

// Servidor SMTP
define('SMTP_HOST', 'mail.suempresa.com');        // Ejemplo: mail.empresa.com, smtp.empresa.com
define('SMTP_PORT', 587);                          // Puertos comunes: 587 (TLS), 465 (SSL), 25 (sin cifrado)
define('SMTP_SECURE', 'tls');                      // Opciones: 'tls', 'ssl', o '' para sin cifrado

// Autenticación SMTP
define('SMTP_USERNAME', 'notificaciones@suempresa.com');  // Usuario del correo
define('SMTP_PASSWORD', 'su_contraseña_aqui');            // Contraseña del correo
define('SMTP_AUTH', true);                                 // true si requiere autenticación

// Información del remitente
define('EMAIL_FROM', 'notificaciones@suempresa.com');     // Email del remitente
define('EMAIL_FROM_NAME', 'Sistema DMS - CPP');            // Nombre del remitente

// ============================================
// CONFIGURACIÓN DE NOTIFICACIONES
// ============================================

// Habilitar/deshabilitar envío de emails
define('EMAIL_ENABLED', true);  // Cambiar a false para deshabilitar envíos

// Tiempo de espera para conexión SMTP (segundos)
define('SMTP_TIMEOUT', 30);

// Registro de errores
define('EMAIL_LOG_ERRORS', true);
define('EMAIL_LOG_FILE', __DIR__ . '/logs/email_errors.log');

// Crear directorio de logs si no existe
if (EMAIL_LOG_ERRORS && !file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// ============================================
// PLANTILLAS DE EMAIL
// ============================================

/**
 * Configuración de plantillas de email según tipo de evento
 */
$EMAIL_TEMPLATES = [
    'Cambio Estado' => [
        'asunto' => 'Documento [CODIGO] - Cambio de Estado a [ESTADO]',
        'plantilla' => 'email_cambio_estado.html'
    ],
    'Nueva Version' => [
        'asunto' => 'Documento [CODIGO] - Nueva Versión Disponible',
        'plantilla' => 'email_nueva_version.html'
    ],
    'Documento Vencido' => [
        'asunto' => 'ALERTA: Documento [CODIGO] ha Vencido',
        'plantilla' => 'email_vencimiento.html'
    ],
    'Proximo Vencimiento' => [
        'asunto' => 'AVISO: Documento [CODIGO] Próximo a Vencer',
        'plantilla' => 'email_proximo_vencer.html'
    ]
];

// ============================================
// DESTINATARIOS POR ROL
// ============================================

/**
 * Define qué roles reciben notificaciones según el tipo de evento
 */
$NOTIFICACIONES_POR_ROL = [
    'Cambio Estado' => [1, 2, 4],      // Administrador, Editor, Aprobador
    'Nueva Version' => [1, 2, 4],       // Administrador, Editor, Aprobador
    'Documento Vencido' => [1, 2],      // Administrador, Editor
    'Proximo Vencimiento' => [1, 2, 4]  // Administrador, Editor, Aprobador
];

?>
