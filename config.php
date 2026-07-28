<?php
// Load environment variables from .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip surrounding quotes
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[$key] = $value;
    }
}

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'auctionkai');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
define('APP_URL', $_ENV['APP_URL'] ?? '');

// reCAPTCHA — loaded from DB in includes/settings.php
// Fallback to .env if DB not yet available
if (!defined('RECAPTCHA_SITE_KEY')) {
    define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? '');
    define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? '');
}

// Secret key for encrypting sensitive settings (SMTP passwords, etc.)
// Read from $_ENV first (populated by .env parser above), then getenv() as fallback
$_secret = $_ENV['APP_SECRET_KEY'] ?? getenv('APP_SECRET_KEY') ?: '';
// Fail closed if missing or still a placeholder — never boot with a known default
if ($_secret === '' || str_starts_with($_secret, 'change-this') || str_starts_with($_secret, 'your-random')) {
    error_log('CRITICAL: APP_SECRET_KEY is not configured or is still a placeholder. Application cannot start safely.');
    http_response_code(500);
    exit('Application configuration error. Contact the administrator.');
}
define('APP_SECRET_KEY', $_secret);
unset($_secret);
