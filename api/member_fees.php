<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// CSRF check for write actions
$action = $data['action'] ?? '';
if (in_array($action, ['add', 'delete', 'edit'])) {
    $csrfToken = $data['_tok'] ?? '';
    if (empty($_SESSION['tok']) || !hash_equals($_SESSION['tok'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
}
$auctionId = (int)($data['auction_id'] ?? 0);
$memberId = (int)($data['member_id'] ?? 0);

if (!$auctionId || !$memberId) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?");
    $stmt->execute([$auctionId, $userId]);
    if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Auction not found']); exit; }

    $stmt = $db->prepare("SELECT id, name FROM members WHERE id=? AND user_id=?");
    $stmt->execute([$memberId, $userId]);
    $member = $stmt->fetch();
    if (!$member) { echo json_encode(['success' => false, 'message' => 'Member not found']); exit; }

    // ADD FEE
    if ($action === 'add') {
        $feeName = trim($data['fee_name'] ?? '');
        $amount = (float)($data['amount'] ?? 0);
        $feeType = $data['fee_type'] ?? 'deduction';
        $notes = trim($data['notes'] ?? '');

        if (empty($feeName)) { echo json_encode(["success" => false, "message" => "Fee name is required"]); exit; }
        if (strlen($feeName) > 200) { echo json_encode(["success" => false, "message" => "Fee name too long"]); exit; }
        if ($amount <= 0) { echo json_encode(["success" => false, "message" => "Amount must be greater than 0"]); exit; }
        if ($amount > 999999999) { echo json_encode(["success" => false, "message" => "Amount exceeds maximum"]); exit; }
        if (strlen($notes) > 500) { echo json_encode(["success" => false, "message" => "Notes too long"]); exit; }
        if (!in_array($feeType, ['deduction', 'addition'])) $feeType = 'deduction';

        $stmt = $db->prepare("INSERT INTO member_fees (auction_id, member_id, fee_name, amount, fee_type, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$auctionId, $memberId, $feeName, $amount, $feeType, $notes ?: null]);
        $newId = (int)$db->lastInsertId();

        logActivity($db, $userId, 'member_fee.add', 'member', $memberId, "Added {$feeType} fee '{$feeName}' ¥" . number_format($amount) . " for: {$member['name']}");

        echo json_encode(['success' => true, 'fee' => ['id' => $newId, 'fee_name' => $feeName, 'amount' => $amount, 'fee_type' => $feeType, 'notes' => $notes], 'message' => 'Fee added successfully']);
        exit;
    }

    // DELETE FEE
    if ($action === 'delete') {
        $feeId = (int)($data['fee_id'] ?? 0);
        if (!$feeId) { echo json_encode(['success' => false, 'message' => 'Missing fee ID']); exit; }

        $stmt = $db->prepare("DELETE FROM member_fees WHERE id=? AND auction_id=? AND member_id=?");
        $stmt->execute([$feeId, $auctionId, $memberId]);

        logActivity($db, $userId, 'member_fee.delete', 'member', $memberId, "Deleted special fee ID:{$feeId} for: {$member['name']}");

        echo json_encode(['success' => true, 'message' => 'Fee deleted']);
        exit;
    }

    // EDIT FEE
    if ($action === 'edit') {
        $feeId = (int)($data['fee_id'] ?? 0);
        $feeName = trim($data['fee_name'] ?? '');
        $amount = (float)($data['amount'] ?? 0);
        $feeType = $data['fee_type'] ?? 'deduction';
        $notes = trim($data['notes'] ?? '');

        if (!$feeId) { echo json_encode(['success' => false, 'message' => 'Missing fee ID']); exit; }
        if (empty($feeName)) { echo json_encode(["success" => false, "message" => "Fee name is required"]); exit; }
        if (strlen($feeName) > 200) { echo json_encode(["success" => false, "message" => "Fee name too long"]); exit; }
        if ($amount <= 0) { echo json_encode(["success" => false, "message" => "Amount must be greater than 0"]); exit; }
        if ($amount > 999999999) { echo json_encode(["success" => false, "message" => "Amount exceeds maximum"]); exit; }
        if (strlen($notes) > 500) { echo json_encode(["success" => false, "message" => "Notes too long"]); exit; }
        if (!in_array($feeType, ['deduction', 'addition'])) $feeType = 'deduction';

        $stmt = $db->prepare("UPDATE member_fees SET fee_name=?, amount=?, fee_type=?, notes=? WHERE id=? AND auction_id=? AND member_id=?");
        $stmt->execute([$feeName, $amount, $feeType, $notes ?: null, $feeId, $auctionId, $memberId]);

        logActivity($db, $userId, 'member_fee.edit', 'member', $memberId, "Edited fee '{$feeName}' ¥" . number_format($amount) . " for: {$member['name']}");

        echo json_encode(['success' => true, 'fee' => ['id' => $feeId, 'fee_name' => $feeName, 'amount' => $amount, 'fee_type' => $feeType, 'notes' => $notes], 'message' => 'Fee updated']);
        exit;
    }

    // LIST FEES
    if ($action === 'list') {
        $stmt = $db->prepare("SELECT * FROM member_fees WHERE auction_id=? AND member_id=? ORDER BY created_at ASC");
        $stmt->execute([$auctionId, $memberId]);
        echo json_encode(['success' => true, 'fees' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (Exception $e) {
    logError($db, 'error', 'Member fees error: ' . $e->getMessage(), __FILE__, __LINE__);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
