<?php
class AuctionModel {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getAll(): array {
        $stmt = $this->db->prepare("SELECT * FROM auction WHERE user_id=? ORDER BY date DESC, id DESC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->userId]);
        return $stmt->fetch();
    }

    public function getExpired(string $today): array {
        $stmt = $this->db->prepare("SELECT id FROM auction WHERE user_id=? AND expires_at < ?");
        $stmt->execute([$this->userId, $today]);
        return $stmt->fetchAll();
    }

    public function create(string $name, string $date, float $commissionFee, string $expiresAt): int {
        $stmt = $this->db->prepare("INSERT INTO auction (user_id, name, date, commission_fee, expires_at) VALUES (?,?,?,?,?)");
        $stmt->execute([$this->userId, $name, $date, $commissionFee, $expiresAt]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $date, float $commissionFee): void {
        $stmt = $this->db->prepare("UPDATE auction SET name=?, date=?, commission_fee=? WHERE id=? AND user_id=?");
        $stmt->execute([$name, $date, $commissionFee, $id, $this->userId]);
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM auction WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->userId]);
    }

    public function deleteExpired(array $ids): void {
        if (empty($ids)) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->prepare("DELETE FROM auction WHERE id IN ($placeholders)")->execute($ids);
    }
}
