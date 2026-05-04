<?php
class MemberModel {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getAll(): array {
        $stmt = $this->db->prepare("SELECT * FROM members WHERE user_id=? ORDER BY name ASC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM members WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->userId]);
        return $stmt->fetch();
    }

    public function create(string $name, string $phone, string $email): int {
        $stmt = $this->db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
        $stmt->execute([$this->userId, $name, $phone, $email]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $phone, string $email): void {
        $stmt = $this->db->prepare("UPDATE members SET name=?, phone=?, email=? WHERE id=? AND user_id=?");
        $stmt->execute([$name, $phone, $email, $id, $this->userId]);
    }

    public function delete(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM members WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->userId]);
    }
}
