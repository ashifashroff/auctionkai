<?php
class VehicleModel {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getByAuction(int $auctionId): array {
        $stmt = $this->db->prepare("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=? AND m.user_id=? ORDER BY v.lot ASC, v.id ASC");
        $stmt->execute([$auctionId, $this->userId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.id=? AND m.user_id=?");
        $stmt->execute([$id, $this->userId]);
        return $stmt->fetch();
    }

    public function deleteByAuctions(array $auctionIds): void {
        if (empty($auctionIds)) return;
        $placeholders = implode(',', array_fill(0, count($auctionIds), '?'));
        $this->db->prepare("DELETE FROM vehicles WHERE auction_id IN ($placeholders)")->execute($auctionIds);
    }
}
