<?php
/**
 * CampusCoin — application configuration.
 * Loads the optional .env file and defines global constants.
 */

/** Read a setting from .env (or the server environment), with a default. */
function env(string $key, $default = null)
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $v = preg_replace('/\s+#.*$/', '', $v);          // strip trailing comments
                $vars[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    if (array_key_exists($key, $vars)) {
        return $vars[$key];
    }
    $sys = getenv($key);
    return $sys !== false ? $sys : $default;
}

define('APP_NAME', 'CampusCoin');
define('APP_TAGLINE', 'Smart Money Management for Students');
define('APP_ENV', (string) env('APP_ENV', 'development'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', APP_ENV === 'development' ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN));
define('CURRENCY', 'Rs.');
define('ROOT_PATH', dirname(__DIR__));

date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Karachi'));

/* URL path of the project, e.g. "/CampusCoin" (auto-detected from the document root). */
$__base = env('APP_BASE_PATH');
if ($__base === null) {
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root    = str_replace('\\', '/', (string) realpath(ROOT_PATH));
    $__base  = ($docRoot !== '' && stripos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
}
define('BASE_URL', rtrim((string) $__base, '/'));
unset($__base);

/* Upload limits */
define('AVATAR_MAX_BYTES', 2 * 1024 * 1024);
define('AVATAR_DIR', ROOT_PATH . '/assets/uploads/avatars');

/* Error reporting */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

/* Baseline security headers */
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
