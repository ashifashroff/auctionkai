<?php
class MemberFeesModel {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getByAuction(int $auctionId): array {
        $stmt = $this->db->prepare("SELECT * FROM member_fees WHERE auction_id=? ORDER BY member_id, created_at ASC");
        $stmt->execute([$auctionId]);
        $result = [];
        foreach ($stmt->fetchAll() as $fee) {
            $result[$fee['member_id']][] = $fee;
        }
        return $result;
    }
}
