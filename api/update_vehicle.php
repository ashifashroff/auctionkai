<?php
require_once 'config.php';
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

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);

$id        = (int)($input['id'] ?? 0);
$memberId  = (int)($input['memberId'] ?? 0);
$make      = trim($input['make'] ?? '');
$model     = trim($input['model'] ?? '');
$lot       = trim($input['lot'] ?? '');
$soldPrice = (float)($input['soldPrice'] ?? 0);
$recycleFee= (float)($input['recycleFee'] ?? 0);
$listingFee= (float)($input['listingFee'] ?? 0);
$soldFee   = (float)($input['soldFee'] ?? 0);
$nagareFee = (float)($input['nagareFee'] ?? 0);
$otherFee  = (float)($input['otherFee'] ?? 0);
$sold      = !empty($input['sold']) ? 1 : 0;

// Validation
$errors = [];

if (!$id) $errors[] = 'Invalid vehicle ID.';
if (!$memberId) $errors[] = 'Member is required.';
if ($make === '') $errors[] = 'Make is required.';
if (!$sold) {
    // Unsold: sold price, recycle, listing, sold fee not applicable
    $soldPrice = 0;
    $recycleFee = 0;
    $listingFee = 0;
    $soldFee = 0;
} else {
    if ($soldPrice < 0) $errors[] = 'Sold price cannot be negative.';
}
if ($nagareFee < 0) $errors[] = 'Nagare fee cannot be negative.';
if ($otherFee < 0) $errors[] = 'Other fee cannot be negative.';

if (!empty($errors)) {
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Verify vehicle belongs to user's auction
$check = $db->prepare("
    SELECT v.id FROM vehicles v
    JOIN auction a ON v.auction_id = a.id
    WHERE v.id = ? AND a.user_id = ?
");
$check->execute([$id, $userId]);
if (!$check->fetch()) {
    echo json_encode(['error' => 'Vehicle not found or access denied.']);
    exit;
}

// Verify member belongs to user
$memCheck = $db->prepare("SELECT id FROM members WHERE id = ? AND user_id = ?");
$memCheck->execute([$memberId, $userId]);
if (!$memCheck->fetch()) {
    echo json_encode(['error' => 'Invalid member.']);
    exit;
}

// Update
$stmt = $db->prepare("UPDATE vehicles SET member_id=?, make=?, model=?, lot=?, sold_price=?, recycle_fee=?, listing_fee=?, sold_fee=?, nagare_fee=?, other_fee=?, sold=? WHERE id=?");
$stmt->execute([$memberId, $make, $model, $lot, $soldPrice, $recycleFee, $listingFee, $soldFee, $nagareFee, $otherFee, $sold, $id]);

echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully.']);
