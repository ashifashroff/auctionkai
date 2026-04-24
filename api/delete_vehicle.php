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

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'Invalid vehicle ID.']);
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

$stmt = $db->prepare("DELETE FROM vehicles WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true, 'message' => 'Vehicle removed.']);
