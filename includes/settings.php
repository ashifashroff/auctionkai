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
