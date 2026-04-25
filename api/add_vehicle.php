<?php
require_once __DIR__ . '/../includes/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

$memberId   = (int)($input['memberId'] ?? 0);
$make       = trim($input['make'] ?? '');
$model      = trim($input['model'] ?? '');
$lot        = trim($input['lot'] ?? '');
$soldPrice  = (float)($input['soldPrice'] ?? 0);
$recycleFee = (float)($input['recycleFee'] ?? 0);
$listingFee = (float)($input['listingFee'] ?? 0);
$soldFee    = (float)($input['soldFee'] ?? 0);
$nagareFee  = (float)($input['nagareFee'] ?? 0);
$sold       = !empty($input['sold']) ? 1 : 0;
$auctionId  = (int)($input['auctionId'] ?? 0);

// Validation
$errors = [];
if (!$memberId) $errors[] = 'Member is required.';
if ($make === '') $errors[] = 'Make is required.';
if (!$auctionId) $errors[] = 'No active auction.';

// Verify auction belongs to user
$aucCheck = $db->prepare("SELECT id FROM auction WHERE id = ? AND user_id = ?");
$aucCheck->execute([$auctionId, $userId]);
if (!$aucCheck->fetch()) $errors[] = 'Invalid auction.';

// Verify member belongs to user
$memCheck = $db->prepare("SELECT id FROM members WHERE id = ? AND user_id = ?");
$memCheck->execute([$memberId, $userId]);
if (!$memCheck->fetch()) $errors[] = 'Invalid member.';

if (!$sold) {
    $soldPrice = 0;
    $recycleFee = 0;
    $listingFee = 0;
    $soldFee = 0;
}

// Check for duplicate lot number in same auction
if ($lot !== '') {
    $dupLot = $db->prepare("SELECT COUNT(*) FROM vehicles WHERE auction_id = ? AND lot = ? AND lot != ''");
    $dupLot->execute([$auctionId, $lot]);
    if ((int)$dupLot->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Lot number already exists in this auction']);
        exit;
    }
}

if (!empty($errors)) {
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

$stmt = $db->prepare("INSERT INTO vehicles (auction_id, member_id, make, model, lot, sold_price, recycle_fee, listing_fee, sold_fee, nagare_fee, sold) VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$auctionId, $memberId, $make, $model, $lot, $soldPrice, $recycleFee, $listingFee, $soldFee, $nagareFee, $sold]);

echo json_encode(['success' => true, 'message' => 'Vehicle added successfully.']);
