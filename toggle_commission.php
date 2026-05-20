<?php
require_once __DIR__ . '/includes/api_bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = $GLOBALS['_json_input'] ?? [];
$memberId = (int)($data['member_id'] ?? 0);
$auctionId = (int)($data['auction_id'] ?? 0);
$charge = ($data['charge'] ?? '0') === '1' ? 1 : 0;
$userId = (int)$_SESSION['user_id'];

if (!$memberId || !$auctionId) {
    echo json_encode(['success' => false, 'error' => 'Invalid IDs']);
    exit;
}

// Verify member and auction belong to this user
$memberOk = $db->prepare("SELECT id FROM members WHERE id=? AND user_id=?");
$memberOk->execute([$memberId, $userId]);
$auctionOk = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?");
$auctionOk->execute([$auctionId, $userId]);

if (!$memberOk->fetch() || !$auctionOk->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// Upsert the flag
$db->prepare("
    INSERT INTO member_auction_flags (user_id, member_id, auction_id, charge_commission)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE charge_commission = VALUES(charge_commission)
")->execute([$userId, $memberId, $auctionId, $charge]);

echo json_encode(['success' => true, 'charge' => $charge]);
