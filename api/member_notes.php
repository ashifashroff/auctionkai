<?php
/**
 * Member Notes API — Global + Per-Auction Notes
 * GET  — Fetch notes for a member (in current auction context)
 * POST — Save both global and auction notes
 */
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$memberId = (int)($_GET['member_id'] ?? ($GLOBALS['_json_input']['member_id'] ?? 0));
$auctionId = (int)($_GET['auction_id'] ?? ($GLOBALS['_json_input']['auction_id'] ?? $activeAuctionId ?? 0));

if (!$memberId) {
    echo json_encode(['success' => false, 'message' => 'Member ID required']);
    exit;
}

// Verify member belongs to this user
$check = $db->prepare("SELECT id, notes FROM members WHERE id = ? AND user_id = ?");
$check->execute([$memberId, $userId]);
$member = $check->fetch(PDO::FETCH_ASSOC);
if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch both global and auction notes
    $globalNote = $member['notes'] ?? '';

    $auctionNote = '';
    if ($auctionId) {
        $stmt = $db->prepare("SELECT notes FROM member_auction_notes WHERE auction_id = ? AND member_id = ?");
        $stmt->execute([$auctionId, $memberId]);
        $auctionNote = $stmt->fetchColumn() ?? '';
    }

    echo json_encode([
        'success' => true,
        'global_note' => $globalNote,
        'auction_note' => $auctionNote,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $GLOBALS['_json_input'];
    $globalNote = trim($data['global_note'] ?? '');
    $auctionNote = trim($data['auction_note'] ?? '');

    // Save global note
    $db->prepare("UPDATE members SET notes = ? WHERE id = ? AND user_id = ?")
       ->execute([$globalNote ?: null, $memberId, $userId]);

    // Save auction note
    if ($auctionId) {
        if ($auctionNote) {
            $db->prepare("
                INSERT INTO member_auction_notes (auction_id, member_id, notes)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE notes = VALUES(notes)
            ")->execute([$auctionId, $memberId, $auctionNote]);
        } else {
            $db->prepare("DELETE FROM member_auction_notes WHERE auction_id = ? AND member_id = ?")
               ->execute([$auctionId, $memberId]);
        }
    }

    // Activity log
    require_once __DIR__ . '/../includes/activity.php';
    logActivity($db, $userId, 'member.notes', 'member', $memberId, 'Updated notes');

    echo json_encode([
        'success' => true,
        'message' => 'Notes saved',
        'global_note' => $globalNote,
        'auction_note' => $auctionNote,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
