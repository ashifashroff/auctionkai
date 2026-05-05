<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// CSRF check
$csrfToken = $data['_tok'] ?? '';
if (empty($_SESSION['tok']) || !hash_equals($_SESSION['tok'], $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$auctionId = (int)($data['auction_id'] ?? 0);
$memberId = (int)($data['member_id'] ?? 0);
$status = $data['status'] ?? 'unpaid';
$paidAmount = (float)($data['paid_amount'] ?? 0);
$notes = trim($data['notes'] ?? '');

if (!in_array($status, ['unpaid','paid','partial'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

if (!$auctionId || !$memberId) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Verify auction belongs to user
    $stmt = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?");
    $stmt->execute([$auctionId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Auction not found']);
        exit;
    }

    // Verify member belongs to user
    $stmt = $db->prepare("SELECT id, name FROM members WHERE id=? AND user_id=?");
    $stmt->execute([$memberId, $userId]);
    $member = $stmt->fetch();
    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }

    $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;

    $stmt = $db->prepare("
        INSERT INTO payment_status (auction_id, member_id, status, paid_amount, paid_at, notes, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            paid_amount = VALUES(paid_amount),
            paid_at = VALUES(paid_at),
            notes = VALUES(notes),
            updated_by = VALUES(updated_by)
    ");
    $stmt->execute([$auctionId, $memberId, $status, $paidAmount, $paidAt, $notes ?: null, $userId]);

    logActivity($db, $userId, 'payment.update', 'member', $memberId, "Payment status set to '{$status}' for member: {$member['name']}");

    echo json_encode(['success' => true, 'status' => $status, 'message' => 'Payment status updated']);

} catch (Exception $e) {
    logError($db, 'error', 'Payment update error: ' . $e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
