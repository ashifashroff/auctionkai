<?php
require_once __DIR__ . "/../includes/api_bootstrap.php";
require_once '../includes/auth_check.php';
require_once '../includes/helpers.php';
require_once '../includes/mailer.php';


$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$memberId = (int)($data['member_id'] ?? 0);
$auctionId = (int)($data['auction_id'] ?? 0);

if (!$memberId || !$auctionId) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Verify member belongs to this user
$stmt = $db->prepare(
    "SELECT * FROM members WHERE id=? AND user_id=?"
);
$stmt->execute([$memberId, $userId]);
$member = $stmt->fetch();
if (!$member) {
    echo json_encode([
        'success' => false,
        'message' => 'Member not found'
    ]);
    exit;
}

// Verify auction belongs to this user
$stmt = $db->prepare(
    "SELECT * FROM auction WHERE id=? AND user_id=?"
);
$stmt->execute([$auctionId, $userId]);
$auction = $stmt->fetch();
if (!$auction) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction not found'
    ]);
    exit;
}

// Fetch vehicles
$stmt = $db->prepare(
    "SELECT * FROM vehicles
     WHERE member_id=? AND auction_id=?
     ORDER BY lot"
);
$stmt->execute([$memberId, $auctionId]);
$vehicles = $stmt->fetchAll();

// Fetch fees
$fees = $db->query(
    "SELECT * FROM fees ORDER BY id LIMIT 1"
)->fetch();
$fees['customDeductions'] = $db->query(
    "SELECT * FROM custom_deductions ORDER BY id"
)->fetchAll();

// Calculate
$s = calcStatement($memberId, $vehicles, $fees);

if ($s['count'] === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No sold vehicles for this member'
    ]);
    exit;
}

$htmlBody = buildEmailBody($member, $auction, $s, $fees);
$result = sendSettlementEmail(
    $member, $auction, $htmlBody, $db
);

echo json_encode($result);
