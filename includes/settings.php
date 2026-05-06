<?php
function loadSettings(PDO $db): array {
    try {
        $stmt = $db->query("SELECT `key`, value FROM settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // Table might not exist yet — return defaults
        return [
            'mail_enabled' => '0',
            'mail_provider' => 'smtp',
            'mail_host' => '',
            'mail_port' => '587',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_email' => '',
            'mail_from_name' => 'AuctionKai Settlement System',
            'mail_encryption' => 'tls',
        ];
    }
}

function saveSetting(PDO $db, string $key, string $value): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO settings (`key`, value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist
    }
}

function saveSettings(PDO $db, array $data): void {
    foreach ($data as $key => $value) {
        saveSetting($db, $key, (string)$value);
    }
}

/**
 * Ensure the settings table exists — call this once during setup
 */
function ensureSettingsTable(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Load reCAPTCHA settings from DB and define constants
 * Called after DB is available (in db.php or auth_check.php)
 */
function loadRecaptchaSettings(PDO $db): void {
    if (defined('RECAPTCHA_SITE_KEY_LOADED')) return;
    define('RECAPTCHA_SITE_KEY_LOADED', true);

    $siteKey = getSetting($db, 'recaptcha_site_key', '');
    $secretKey = getSetting($db, 'recaptcha_secret_key', '');
    $enabled = getSetting($db, 'recaptcha_enabled', '0');

    // Override .env values with DB values (DB takes priority)
    if (!empty($siteKey) || !empty($secretKey)) {
        // Redefine constants if not already defined, or override if from .env
        if (defined('RECAPTCHA_SITE_KEY')) {
            // Can't redefine constants, so we use a different approach
            // We'll check DB directly in the auth/login.php instead
        } else {
            define('RECAPTCHA_SITE_KEY', $siteKey);
            define('RECAPTCHA_SECRET_KEY', $secretKey);
        }
    }

    define('RECAPTCHA_ENABLED', $enabled === '1');
}

function getSetting(PDO $db, string $key, string $default = ''): string {
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (string)$val : $default;
}

function setSetting(PDO $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

/**
 * Get reCAPTCHA settings from DB (preferred) or .env fallback
 */
function recaptchaSiteKey(): string {
    static $val = null;
    if ($val !== null) return $val;
    try {
        $db = db();
        $val = getSetting($db, 'recaptcha_site_key', '');
    } catch (Exception $e) {
        $val = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
    }
    return $val;
}

function recaptchaSecretKey(): string {
    static $val = null;
    if ($val !== null) return $val;
    try {
        $db = db();
        $val = getSetting($db, 'recaptcha_secret_key', '');
    } catch (Exception $e) {
        $val = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';
    }
    return $val;
}

function recaptchaEnabled(): bool {
    static $val = null;
    if ($val !== null) return $val;
    try {
        $db = db();
        $val = getSetting($db, 'recaptcha_enabled', '0') === '1';
    } catch (Exception $e) {
        $val = false;
    }
    return $val;
}
