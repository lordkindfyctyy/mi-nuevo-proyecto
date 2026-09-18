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
define('APP_NAME', app_config($overrides, 'APP_NAME', 'Mi Nuevo Proyecto'));

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
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) == 443
        ? 'https'
        : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    define('BASE_URL', app_config($overrides, 'APP_BASE_URL', "$scheme://$host"));
    define('DB_HOST', app_config($overrides, 'DB_HOST', 'localhost'));
    define('DB_NAME', app_config($overrides, 'DB_NAME', ''));
    define('DB_USER', app_config($overrides, 'DB_USER', ''));
    define('DB_PASS', app_config($overrides, 'DB_PASS', ''));
}

define('DB_CHARSET', 'utf8mb4');

// Comando de voz por IA (public/vender.php): sin API key configurada, el
// endpoint api/procesar_comando_voz.php responde con un error claro en vez
// de fallar a medias. La clave nunca tiene un valor por defecto: se
// configura en config/config.local.php (gitignored) o como variable de
// entorno real.
define('AI_VOICE_PROVIDER', app_config($overrides, 'AI_VOICE_PROVIDER', 'gemini'));
define('AI_VOICE_API_KEY', app_config($overrides, 'AI_VOICE_API_KEY', ''));
define('AI_VOICE_MODEL', app_config($overrides, 'AI_VOICE_MODEL', 'gemini-2.0-flash'));

error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'local' ? '1' : '0');
ini_set('log_errors', '1');

session_start();
