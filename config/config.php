<?php
date_default_timezone_set('Asia/Bangkok');

// 1. Simple Environment Variable Loader
$env_path = __DIR__ . '/../.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Strip quotes if any
            if ((strpos($val, '"') === 0 && strrpos($val, '"') === strlen($val) - 1) ||
                (strpos($val, "'") === 0 && strrpos($val, "'") === strlen($val) - 1)) {
                $val = substr($val, 1, -1);
            }
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Global Session Security Configuration
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Dynamic secure check for HTTPS (supports local development on HTTP and production on HTTPS)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
              
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax'
    ]);
}

// 3. Define Constants from Environment Variables
// JWT authentication is optional because the application primarily uses PHP
// sessions.  Do not provide a public fallback secret: a missing environment
// value must make bearer-token authentication unavailable rather than forgeable.
define('JWT_SECRET', getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? ''));
define('JWT_ISSUER', getenv('JWT_ISSUER') ?: ($_ENV['JWT_ISSUER'] ?? 'localhost'));
define('CORS_ALLOWED_ORIGINS', getenv('CORS_ALLOWED_ORIGINS') ?: ($_ENV['CORS_ALLOWED_ORIGINS'] ?? ''));
define('LINE_CHANNEL_SECRET', getenv('LINE_CHANNEL_SECRET') ?: ($_ENV['LINE_CHANNEL_SECRET'] ?? ''));
?>
