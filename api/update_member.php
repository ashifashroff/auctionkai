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
    if (!$id) { echo json_encode(['error' => 'Invalid member ID']); exit; }

    $stmt = $db->prepare("SELECT * FROM members WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $member = $stmt->fetch();
    if (!$member) { echo json_encode(['error' => 'Member not found']); exit; }

    echo json_encode([
        'id' => (int)$member['id'],
        'name' => $member['name'],
        'phone' => $member['phone'],
        'email' => $member['email'],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $email = trim($input['email'] ?? '');

    if (!$id) { echo json_encode(['error' => 'Invalid member ID.']); exit; }
    if ($name === '') { echo json_encode(['error' => 'Name is required.']); exit; }

    $check = $db->prepare("SELECT id FROM members WHERE id = ? AND user_id = ?");
    $check->execute([$id, $userId]);
    if (!$check->fetch()) { echo json_encode(['error' => 'Member not found.']); exit; }

    $stmt = $db->prepare("UPDATE members SET name=?, phone=?, email=? WHERE id=?");
    $stmt->execute([$name, $phone, $email, $id]);

    echo json_encode(['success' => true, 'message' => 'Member updated.']);
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
