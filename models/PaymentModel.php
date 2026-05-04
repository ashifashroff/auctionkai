<?php
class PaymentModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getByAuction(int $auctionId): array {
        $stmt = $this->db->prepare("SELECT member_id, status, paid_amount, paid_at, notes FROM payment_status WHERE auction_id=?");
        $stmt->execute([$auctionId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['member_id']] = $row;
        }
        return $result;
    }
}
