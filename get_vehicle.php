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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['error' => 'Invalid vehicle ID']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT v.*, m.name AS member_name, m.phone AS member_phone
        FROM vehicles v
        LEFT JOIN members m ON v.member_id = m.id
        LEFT JOIN auction a ON v.auction_id = a.id
        WHERE v.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$id, $userId]);
    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        echo json_encode(['error' => 'Vehicle not found']);
        exit;
    }

    echo json_encode([
        'id'          => (int)$vehicle['id'],
        'member_id'   => (int)$vehicle['member_id'],
        'member_name' => $vehicle['member_name'] ?? '',
        'make'        => $vehicle['make'],
        'model'       => $vehicle['model'],
        'lot'         => $vehicle['lot'],
        'sold_price'  => (float)$vehicle['sold_price'],
        'recycle_fee' => (float)($vehicle['recycle_fee'] ?? 0),
        'listing_fee' => (float)($vehicle['listing_fee'] ?? 0),
        'sold_fee'    => (float)($vehicle['sold_fee'] ?? 0),
        'nagare_fee'  => (float)($vehicle['nagare_fee'] ?? 0),
        'other_fee'   => (float)($vehicle['other_fee'] ?? 0),
        'sold'        => (bool)$vehicle['sold'],
    ]);
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
