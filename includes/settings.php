<?php
function loadSettings(PDO $db): array {
    try {
        $stmt = $db->query(
            "SELECT `key`, value FROM settings"
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}

function saveSetting(
    PDO $db, string $key, string $value
): void {
    $stmt = $db->prepare("
        INSERT INTO settings (`key`, value) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$key, $value]);
}

function saveSettings(PDO $db, array $data): void {
    foreach ($data as $key => $value) {
        saveSetting($db, $key, (string)$value);
    }
}
