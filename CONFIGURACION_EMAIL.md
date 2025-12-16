# Configuración del Sistema de Notificaciones por Email

## 📋 Descripción

Este sistema de notificaciones por email permite enviar automáticamente alertas sobre eventos importantes del sistema DMS, tales como:

- **Cambios de estado** de documentos (Aprobado/Rechazado)
- **Nuevas versiones** de documentos subidas
- **Documentos próximos a vencer** (alerta con anticipación)
- **Documentos vencidos** (alerta de vencimiento)

## 📁 Archivos del Sistema

El sistema de notificaciones está compuesto por los siguientes archivos:

1. **email_config.php** - Archivo de configuración SMTP
2. **email_functions.php** - Funciones para envío de emails
3. **procesar_notificaciones.php** - Script procesador de notificaciones
4. **aprobar_documentos.php** - (Modificado) Incluye notificaciones al aprobar/rechazar
5. **subir_documento.php** - (Modificado) Incluye notificaciones al subir nuevas versiones

## ⚙️ Configuración Inicial

### Paso 1: Configurar el Servidor SMTP

Edite el archivo `email_config.php` y configure los siguientes parámetros según su servidor de correo corporativo:

```php
// Servidor SMTP
define('SMTP_HOST', 'mail.suempresa.com');     // Dirección del servidor SMTP
define('SMTP_PORT', 587);                       // Puerto SMTP (587 para TLS, 465 para SSL, 25 sin cifrado)
define('SMTP_SECURE', 'tls');                   // Tipo de cifrado: 'tls', 'ssl', o '' para sin cifrado

// Autenticación SMTP
define('SMTP_USERNAME', 'notificaciones@suempresa.com');  // Usuario del correo
define('SMTP_PASSWORD', 'su_contraseña_aqui');            // Contraseña del correo
define('SMTP_AUTH', true);                                 // true si requiere autenticación

// Información del remitente
define('EMAIL_FROM', 'notificaciones@suempresa.com');     // Email del remitente
define('EMAIL_FROM_NAME', 'Sistema DMS - CPP');            // Nombre del remitente
```

### Paso 2: Ejemplos de Configuración por Tipo de Servidor

#### Servidor SMTP Corporativo con TLS (Puerto 587)
```php
define('SMTP_HOST', 'mail.miempresa.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'notificaciones@miempresa.com');
define('SMTP_PASSWORD', 'contraseña_segura');
```

#### Servidor SMTP con SSL (Puerto 465)
```php
define('SMTP_HOST', 'smtp.miempresa.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'sistema@miempresa.com');
define('SMTP_PASSWORD', 'contraseña_segura');
```

#### Servidor SMTP sin cifrado (Puerto 25)
```php
define('SMTP_HOST', 'mail.servidor-local.com');
define('SMTP_PORT', 25);
define('SMTP_SECURE', '');  // Sin cifrado
define('SMTP_USERNAME', 'notificaciones@servidor-local.com');
define('SMTP_PASSWORD', 'contraseña');
```

### Paso 3: Verificar que los Usuarios Tengan Emails

El sistema envía notificaciones a los emails registrados en la tabla `Usuarios`. Asegúrese de que todos los usuarios tengan un email válido:

```sql
-- Verificar usuarios sin email
SELECT id, nombre, usuario, email
FROM Usuarios
WHERE email IS NULL OR email = ''
ORDER BY nombre;

-- Actualizar email de un usuario
UPDATE Usuarios
SET email = 'usuario@empresa.com'
WHERE id = 1;
```

### Paso 4: Configurar Permisos de Archivos

Asegúrese de que el directorio `logs/` tenga permisos de escritura:

```bash
# Crear directorio de logs si no existe
mkdir -p logs

# Dar permisos de escritura
chmod 755 logs
```

## 🚀 Ejecución del Sistema

### Opción 1: Ejecución Manual desde el Navegador

Para probar el sistema o ejecutarlo manualmente:

1. Acceda como **Administrador** al sistema
2. Navegue a: `http://su-servidor/procesar_notificaciones.php`
3. Verá una interfaz con el resultado del procesamiento

### Opción 2: Ejecución Automática con CRON (Recomendado)

Para que las notificaciones se envíen automáticamente, configure una tarea CRON:

#### Ejecutar cada 15 minutos:
```bash
*/15 * * * * /usr/bin/php /ruta/completa/al/proyecto/procesar_notificaciones.php >> /ruta/logs/cron_notificaciones.log 2>&1
```

#### Ejecutar cada hora:
```bash
0 * * * * /usr/bin/php /ruta/completa/al/proyecto/procesar_notificaciones.php >> /ruta/logs/cron_notificaciones.log 2>&1
```

#### Ejecutar una vez al día (9:00 AM):
```bash
0 9 * * * /usr/bin/php /ruta/completa/al/proyecto/procesar_notificaciones.php >> /ruta/logs/cron_notificaciones.log 2>&1
```

**Para configurar CRON en Linux:**
```bash
# Editar crontab
crontab -e

# Agregar la línea deseada y guardar
```

**Para configurar en Windows (Programador de Tareas):**
1. Abrir "Programador de tareas"
2. Crear nueva tarea básica
3. Configurar desencadenador (horario)
4. Acción: Iniciar programa
5. Programa: `C:\php\php.exe`
6. Argumentos: `C:\ruta\al\proyecto\procesar_notificaciones.php`

## 📧 Tipos de Notificaciones

### 1. Cambio de Estado
- **Cuándo se envía**: Al aprobar o rechazar un documento
- **Destinatarios**: Responsable del documento + Administradores + Editores + Aprobadores
- **Asunto**: "DMS - Cambio Estado - [CÓDIGO]"

### 2. Nueva Versión
- **Cuándo se envía**: Al subir una nueva versión de un documento
- **Destinatarios**: Responsable del documento + Administradores + Editores + Aprobadores
- **Asunto**: "DMS - Nueva Version - [CÓDIGO]"

### 3. Próximo a Vencer
- **Cuándo se envía**: 7 días antes del vencimiento del documento
- **Destinatarios**: Administradores + Editores + Aprobadores
- **Asunto**: "AVISO: Documento [CÓDIGO] Próximo a Vencer"

### 4. Documento Vencido
- **Cuándo se envía**: Cuando un documento ha vencido
- **Destinatarios**: Administradores + Editores
- **Asunto**: "ALERTA: Documento [CÓDIGO] ha Vencido"

## 🔧 Configuración Avanzada

### Habilitar/Deshabilitar Envío de Emails

En `email_config.php`:
```php
// Cambiar a false para deshabilitar el envío (útil para pruebas)
define('EMAIL_ENABLED', true);
```

### Modificar Días de Anticipación para Vencimientos

En `email_config.php`:
```php
// En procesar_notificaciones.php, línea ~25
$DIAS_ANTICIPACION_VENCIMIENTO = 7;  // Cambiar según necesidad
```

### Modificar Roles que Reciben Notificaciones

En `email_config.php`, edite el array `$NOTIFICACIONES_POR_ROL`:
```php
$NOTIFICACIONES_POR_ROL = [
    'Cambio Estado' => [1, 2, 4],      // Roles: Admin(1), Editor(2), Aprobador(4)
    'Nueva Version' => [1, 2, 4],
    'Documento Vencido' => [1, 2],
    'Proximo Vencimiento' => [1, 2, 4]
];
```

### Ver Logs de Email

Los logs se guardan en `logs/email_errors.log`:
```bash
# Ver últimas 50 líneas del log
tail -n 50 logs/email_errors.log

# Ver log en tiempo real
tail -f logs/email_errors.log
```

## 🧪 Pruebas

### Probar Configuración SMTP

Cree un archivo `test_email.php`:
```php
<?php
require_once 'email_functions.php';

$resultado = enviarEmailSMTP(
    'su_email@empresa.com',
    'Su Nombre',
    'Prueba de Configuración SMTP',
    '<h1>Email de Prueba</h1><p>Si recibe este email, la configuración es correcta.</p>'
);

if ($resultado) {
    echo "✓ Email enviado correctamente";
} else {
    echo "✗ Error al enviar email. Revise los logs.";
}
?>
```

Ejecute: `php test_email.php` o acceda desde el navegador.

### Crear Notificación de Prueba

```sql
-- Crear una notificación de prueba
INSERT INTO Notificaciones
(documento_id, tipo_evento, fecha_programada, enviado, mensaje, destinatarios)
VALUES
(1, 'Cambio Estado', GETDATE(), 0, 'Mensaje de prueba', 'su_email@empresa.com');

-- Luego ejecute procesar_notificaciones.php para enviarla
```

## ❗ Solución de Problemas

### No se envían emails

1. **Verificar configuración SMTP** en `email_config.php`
2. **Revisar logs** en `logs/email_errors.log`
3. **Verificar que EMAIL_ENABLED esté en true**
4. **Comprobar que los usuarios tengan emails** en la base de datos
5. **Verificar firewall** - Asegúrese de que el puerto SMTP no esté bloqueado

### Emails van a spam

1. Configure **SPF** y **DKIM** en su servidor de correo
2. Use un **email corporativo válido** como remitente
3. Evite palabras como "urgente", "gratis", etc. en asuntos

### Error de autenticación SMTP

1. Verifique **usuario y contraseña** en `email_config.php`
2. Confirme que el servidor **requiera autenticación** (SMTP_AUTH = true)
3. Verifique que el **puerto y tipo de cifrado** sean correctos

### No se crean notificaciones

1. Verifique que la **tabla Notificaciones exista**
2. Revise **permisos de escritura** en la base de datos
3. Compruebe que los **archivos estén incluidos** correctamente

## 📊 Monitoreo

### Ver Notificaciones Pendientes
```sql
SELECT COUNT(*) as Pendientes
FROM Notificaciones
WHERE enviado = 0;
```

### Ver Últimas Notificaciones Enviadas
```sql
SELECT TOP 10
    n.id,
    d.codigo,
    d.nombre,
    n.tipo_evento,
    n.fecha_envio,
    n.destinatarios
FROM Notificaciones n
INNER JOIN Documentos d ON n.documento_id = d.id
WHERE n.enviado = 1
ORDER BY n.fecha_envio DESC;
```

### Estadísticas de Notificaciones
```sql
SELECT
    tipo_evento,
    COUNT(*) as Total,
    SUM(CASE WHEN enviado = 1 THEN 1 ELSE 0 END) as Enviadas,
    SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as Pendientes
FROM Notificaciones
GROUP BY tipo_evento;
```

## 📞 Soporte

Para más información sobre la configuración de su servidor SMTP corporativo, contacte a su departamento de TI.

---

**Sistema de Gestión Documental CPP**
*Sistema de Notificaciones por Email v1.0*
