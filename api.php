<?php
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['_tok']) || $input['_tok'] !== ($_SESSION['tok'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token mismatch']);
        exit;
    }
}

$db = db();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $input['action'] ?? '';

// Input length limits
$maxStrLen = 255;
foreach ($input as $key => $val) {
    if (is_string($val) && strlen($val) > $maxStrLen && $key !== '_tok') {
        echo json_encode(['error' => 'Input too long.']);
        exit;
    }
}

// ─── ADD AUCTION ────────────────────────────────────
if ($action === 'add_auction') {
    $name = trim($input['name'] ?? '');
    $date = trim($input['date'] ?? '');
    if ($name === '' || $date === '') {
        echo json_encode(['error' => 'Name and date are required.']);
        exit;
    }
    $stmt = $db->prepare("INSERT INTO auction (user_id, name, date, commission_fee, expires_at) VALUES (?,?,?,3300,DATE_ADD(?, INTERVAL 14 DAY))");
    $stmt->execute([$userId, $name, $date, $date]);
    $newId = (int)$db->lastInsertId();
    echo json_encode(['success' => true, 'message' => 'Auction created.', 'auction_id' => $newId]);
    exit;
}

// ─── ADD MEMBER ─────────────────────────────────────
if ($action === 'add_member') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    if ($name === '') {
        echo json_encode(['error' => 'Name is required.']);
        exit;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Invalid email address.']);
        exit;
    }
    $dup = $db->prepare("SELECT id FROM members WHERE user_id=? AND name=?");
    $dup->execute([$userId, $name]);
    if ($dup->fetch()) {
        echo json_encode(['error' => 'A member with this name already exists.']);
        exit;
    }
    $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $name, trim($input['phone'] ?? ''), $email]);
    echo json_encode(['success' => true, 'message' => 'Member added.', 'id' => (int)$db->lastInsertId()]);
    exit;
}

// ─── SAVE AUCTION ───────────────────────────────────
if ($action === 'save_auction') {
    $id = (int)($input['auction_id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $date = trim($input['date'] ?? '');
    $fee = (float)($input['commissionFee'] ?? 3300);
    if (!$id || $name === '' || $date === '') {
        echo json_encode(['error' => 'All fields required.']);
        exit;
    }
    $stmt = $db->prepare("UPDATE auction SET name=?, date=?, commission_fee=? WHERE id=? AND user_id=?");
    $stmt->execute([$name, $date, $fee, $id, $userId]);
    echo json_encode(['success' => true, 'message' => 'Auction updated.']);
    exit;
}

// ─── REMOVE MEMBER ──────────────────────────────────
if ($action === 'remove_member') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid ID.']); exit; }
    $db->prepare("DELETE FROM vehicles WHERE member_id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)")->execute([$id, $userId]);
    $stmt = $db->prepare("DELETE FROM members WHERE id=? AND user_id=?");
    $stmt->execute([$id, $userId]);
    echo json_encode(['success' => true, 'message' => 'Member removed.']);
    exit;
}

// ─── TOGGLE SOLD ────────────────────────────────────
if ($action === 'toggle_sold') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Invalid ID.']); exit; }
    $stmt = $db->prepare("UPDATE vehicles SET sold = NOT sold WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
    $stmt->execute([$id, $userId]);
    echo json_encode(['success' => true, 'message' => 'Status toggled.']);
    exit;
}

echo json_encode(['error' => 'Unknown action.']);
