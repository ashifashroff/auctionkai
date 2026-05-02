<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$auctionId = (int)($data['auction_id'] ?? 0);
$memberId = (int)($data['member_id'] ?? 0);
$action = $data['action'] ?? 'pdf';
$netPayout = (float)($data['net_payout'] ?? 0);

if (!$auctionId || !$memberId || !in_array($action, ['pdf', 'email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?");
    $stmt->execute([$auctionId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Auction not found']);
        exit;
    }

    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);

    $db->prepare("INSERT INTO statement_history (auction_id, member_id, user_id, action, net_payout, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$auctionId, $memberId, $userId, $action, $netPayout, $ip]);

    $memberName = $db->prepare("SELECT name FROM members WHERE id=?");
    $memberName->execute([$memberId]);
    $name = $memberName->fetchColumn() ?? 'Unknown';

    logActivity($db, $userId, $action === 'pdf' ? 'pdf.generate' : 'email.send', 'member', $memberId, ucfirst($action) . " statement for: {$name}");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Statement log error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
