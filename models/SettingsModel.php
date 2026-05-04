<?php
class SettingsModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function get(string $key): ?string {
        try {
            $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key`=?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? $row['value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function set(string $key, string $value): void {
        $this->db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$key, $value]);
    }
}
