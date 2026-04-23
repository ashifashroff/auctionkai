<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];
$memberId = (int)($_GET['member_id'] ?? 0);
$auctionId = (int)($_GET['auction_id'] ?? 0);

if (!$memberId || !$auctionId) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Verify member belongs to user
$memCheck = $db->prepare("SELECT * FROM members WHERE id = ? AND user_id = ?");
$memCheck->execute([$memberId, $userId]);
$member = $memCheck->fetch();
if (!$member) {
    echo json_encode(['error' => 'Member not found']);
    exit;
}

// Verify auction belongs to user
$aucCheck = $db->prepare("SELECT * FROM auction WHERE id = ? AND user_id = ?");
$aucCheck->execute([$auctionId, $userId]);
$auction = $aucCheck->fetch();
if (!$auction) {
    echo json_encode(['error' => 'Auction not found']);
    exit;
}

// Get vehicles
$stmt = $db->prepare("SELECT * FROM vehicles WHERE auction_id = ? AND member_id = ? ORDER BY sold DESC, id ASC");
$stmt->execute([$auctionId, $memberId]);
$vehicles = $stmt->fetchAll();

$sold = array_values(array_filter($vehicles, fn($v) => $v['sold']));
$unsold = array_values(array_filter($vehicles, fn($v) => !$v['sold']));

echo json_encode([
    'member' => [
        'id' => (int)$member['id'],
        'name' => $member['name'],
        'phone' => $member['phone'],
        'email' => $member['email'],
    ],
    'auction' => [
        'id' => (int)$auction['id'],
        'name' => $auction['name'],
        'date' => $auction['date'],
    ],
    'sold' => array_map(fn($v) => [
        'id' => (int)$v['id'],
        'lot' => $v['lot'],
        'make' => $v['make'],
        'model' => $v['model'],
        'sold_price' => (float)$v['sold_price'],
        'tax' => round((float)$v['sold_price'] * 0.10),
        'recycle_fee' => (float)($v['recycle_fee'] ?? 0),
        'listing_fee' => (float)($v['listing_fee'] ?? 0),
        'sold_fee' => (float)($v['sold_fee'] ?? 0),
        'other_fee' => (float)($v['other_fee'] ?? 0),
        'net' => (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0) - (float)($v['other_fee'] ?? 0),
    ], $sold),
    'unsold' => array_map(fn($v) => [
        'id' => (int)$v['id'],
        'lot' => $v['lot'],
        'make' => $v['make'],
        'model' => $v['model'],
        'nagare_fee' => (float)($v['nagare_fee'] ?? 0),
        'other_fee' => (float)($v['other_fee'] ?? 0),
    ], $unsold),
    'soldCount' => count($sold),
    'unsoldCount' => count($unsold),
]);
