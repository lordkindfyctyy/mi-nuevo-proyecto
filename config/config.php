<?php

/**
 * Configuración general del sistema.
 *
 * Este archivo detecta automáticamente si la app corre en local
 * (localhost / php -S / XAMPP) o en producción (Hostinger u otro
 * hosting) y define las constantes correspondientes. No contiene
 * credenciales reales: en producción, los valores se toman de
 * variables de entorno configuradas en el panel del hosting, con un
 * archivo opcional `config/config.local.php` como respaldo para
 * cuando el hosting no permite configurar variables de entorno.
 *
 * Por eso este archivo SÍ se versiona en git (a diferencia de
 * `config/config.local.php`, que está en .gitignore).
 */

/**
 * Detecta si la petición actual llegó por HTTPS. Chequea los indicadores
 * directos de Apache/PHP y, como respaldo, el header que agrega un proxy
 * cuando termina el TLS antes de reenviar la petición al servidor de
 * origen (algunos hostings/CDNs hacen esto, incluyendo posibles capas de
 * Hostinger) — sin esto, un `$_SERVER['HTTPS']` vacío en esa configuración
 * haría que la app crea que la conexión es insegura aunque el visitante sí
 * esté en https://.
 */
function app_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return true;
    }

    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function app_detect_environment(): string
{
    $override = getenv('APP_ENV');
    if ($override === 'local' || $override === 'production') {
        return $override;
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $hostname = explode(':', $host)[0];

    // Sin host (ej. scripts de consola como seed.php) o dominio local conocido.
    if ($host === '' || in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)) {
        return 'local';
    }

    return 'production';
}

/**
 * Lee overrides desde config/config.local.php (si existe) y luego desde
 * variables de entorno reales, en ese orden de prioridad. Sirve tanto para
 * personalizar el entorno local como para producción cuando el hosting no
 * permite definir variables de entorno.
 */
function app_config(array $overrides, string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $overrides) && $overrides[$key] !== null && $overrides[$key] !== '') {
        return $overrides[$key];
    }

    $envValue = getenv($key);

    return $envValue !== false && $envValue !== '' ? $envValue : $default;
}

$localOverridesFile = __DIR__ . '/config.local.php';
$overrides = is_file($localOverridesFile) ? (require $localOverridesFile) : [];
if (!is_array($overrides)) {
    $overrides = [];
}

define('APP_ENV', app_detect_environment());

// HTTPS forzado en producción: si alguien llega por http:// (o un enlace
// viejo, o un bot), lo mandamos a la versión https:// antes de procesar
// nada más. En local no aplica: XAMPP no sirve TLS.
if (APP_ENV === 'production' && !app_request_is_https() && PHP_SAPI !== 'cli') {
    $httpsUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $httpsUrl, true, 301);
    exit;
}

define('APP_NAME', app_config($overrides, 'APP_NAME', 'SixSeven'));
define('CONTACT_NOTIFICATION_EMAIL', app_config($overrides, 'CONTACT_NOTIFICATION_EMAIL', 'sixsevenweb@gmail.com'));

if (APP_ENV === 'local') {
    define('BASE_URL', app_config($overrides, 'APP_BASE_URL', 'http://localhost:8000'));
    define('DB_HOST', app_config($overrides, 'DB_HOST', 'localhost'));
    define('DB_NAME', app_config($overrides, 'DB_NAME', 'mi_nuevo_proyecto'));
    define('DB_USER', app_config($overrides, 'DB_USER', 'root'));
    define('DB_PASS', app_config($overrides, 'DB_PASS', ''));
} else {
    // Producción: BASE_URL se arma solo con el dominio real de la petición
    // (no hace falta hardcodear el dominio de Hostinger). Las credenciales
    // de la base de datos NO tienen valor por defecto: deben configurarse
    // como variables de entorno en el hosting, o en config/config.local.php.
    $scheme = app_request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define('BASE_URL', app_config($overrides, 'APP_BASE_URL', "$scheme://$host"));
    define('DB_HOST', app_config($overrides, 'DB_HOST', 'localhost'));
    define('DB_NAME', app_config($overrides, 'DB_NAME', ''));
    define('DB_USER', app_config($overrides, 'DB_USER', ''));
    define('DB_PASS', app_config($overrides, 'DB_PASS', ''));
}

define('DB_CHARSET', 'utf8mb4');

/**
 * SMTP para mails transaccionales (recuperación de contraseña, etc.). Sin
 * SMTP_HOST configurado, includes/mailer.php cae a mail() nativo (y, si
 * tampoco funciona esa, deja el mail en el log de errores) — así que en
 * local, sin configurar nada, el flujo se puede seguir probando igual.
 * En producción hay que configurar estas variables de entorno (o
 * config/config.local.php) con los datos de un buzón real del hosting
 * (ver README_DESPLIEGUE.md).
 */
define('SMTP_HOST', app_config($overrides, 'SMTP_HOST', ''));
define('SMTP_PORT', (int) app_config($overrides, 'SMTP_PORT', '587'));
define('SMTP_USER', app_config($overrides, 'SMTP_USER', ''));
define('SMTP_PASS', app_config($overrides, 'SMTP_PASS', ''));
define('SMTP_ENCRYPTION', app_config($overrides, 'SMTP_ENCRYPTION', 'tls'));
define('SMTP_FROM_EMAIL', app_config($overrides, 'SMTP_FROM_EMAIL', SMTP_USER));
define('SMTP_FROM_NAME', app_config($overrides, 'SMTP_FROM_NAME', APP_NAME));

error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'local' ? '1' : '0');
ini_set('log_errors', '1');

// Cookie de sesión reforzada: "Secure" solo cuando la conexión es
// realmente HTTPS (si no, el navegador la descartaría directamente y
// rompería el login en local, que corre en http:// plano), "HttpOnly"
// siempre (JS no puede leer el ID de sesión) y "SameSite=Lax" para
// mitigar CSRF sin romper la navegación normal del sitio.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => app_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
