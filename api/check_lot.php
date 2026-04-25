<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$lot = trim($data['lot'] ?? '');
$auctionId = (int)($data['auction_id'] ?? 0);
$excludeId = (int)($data['exclude_id'] ?? 0); // for edit mode

if (!$lot || !$auctionId) {
    echo json_encode(['duplicate' => false]);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT v.id FROM vehicles v
        JOIN auction a ON v.auction_id = a.id
        WHERE v.lot = ?
        AND v.auction_id = ?
        AND a.user_id = ?
        AND v.id != ?
    ");
    $stmt->execute([$lot, $auctionId, $userId, $excludeId]);

    $exists = $stmt->fetch();

    echo json_encode([
        'duplicate' => (bool)$exists,
        'message' => $exists
            ? "Lot number \"$lot\" already exists in this auction"
            : null
    ]);

} catch (Exception $e) {
    echo json_encode(['duplicate' => false]);
}
