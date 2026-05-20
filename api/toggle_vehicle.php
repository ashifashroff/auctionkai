<?php
/**
 * Toggle vehicle sold/unsold status
 * POST: id, _tok (CSRF)
 */
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

$data = $GLOBALS['_json_input'] ?? [];
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing vehicle ID']);
    exit;
}

// Verify vehicle belongs to this user
$stmt = $db->prepare("SELECT v.*, m.user_id FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.id = ?");
$stmt->execute([$id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle || (int)$vehicle['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'error' => 'Vehicle not found']);
    exit;
}

// Toggle sold status
$newSold = $vehicle['sold'] ? 0 : 1;

$db->prepare("UPDATE vehicles SET sold = ? WHERE id = ?")->execute([$newSold, $id]);

// Log
logActivity($db, $userId, 'vehicle.toggle', 'vehicle', $id, ($newSold ? 'Marked sold' : 'Marked unsold'));

echo json_encode(['success' => true, 'id' => $id, 'sold' => (bool)$newSold]);
