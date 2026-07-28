<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once '../includes/helpers.php';
require_once '../includes/activity.php';

header('Content-Type: application/json');

$data = $GLOBALS['_json_input'] ?? json_decode(
 file_get_contents('php://input'), true
) ?? [];
$memberId = (int)($data['member_id'] ?? 0);
$auctionId = (int)($data['auction_id'] ?? 0);

if (!$memberId || !$auctionId) {
 echo json_encode([
 'success' => false,
 'message' => 'Missing parameters'
 ]);
 exit;
}

try {
 // Verify auction belongs to user
 $stmt = $db->prepare(
 "SELECT id FROM auction 
 WHERE id=? AND user_id=?"
 );
 $stmt->execute([$auctionId, $userId]);
 if (!$stmt->fetch()) {
 echo json_encode([
 'success' => false,
 'message' => 'Auction not found'
 ]);
 exit;
 }

 // Verify member belongs to user
 $stmt = $db->prepare(
 "SELECT id, name, phone 
 FROM members 
 WHERE id=? AND user_id=?"
 );
 $stmt->execute([$memberId, $userId]);
 $member = $stmt->fetch();
 if (!$member) {
 echo json_encode([
 'success' => false,
 'message' => 'Member not found'
 ]);
 exit;
 }

 // Generate a random 6-digit PIN — no longer derived from member data
 $pin = (string)random_int(100000, 999999);
 $pinHash = password_hash($pin, PASSWORD_DEFAULT);

 // Check if valid link already exists for this member + auction
 $stmt = $db->prepare("
 SELECT token, expires_at, views
 FROM statement_links
 WHERE member_id=? 
 AND auction_id=?
 AND expires_at > NOW()
 ORDER BY created_at DESC
 LIMIT 1
 ");
 $stmt->execute([$memberId, $auctionId]);
 $existing = $stmt->fetch();

 if ($existing) {
 // Return existing valid link — PIN is not retrievable, operator must regenerate
 $baseUrl = appUrl() . '/statement.php';

 echo json_encode([
 'success' => true,
 'token' => $existing['token'],
 'url' => $baseUrl . '?token=' . $existing['token'],
 'pin' => null,
 'expires_at' => $existing['expires_at'],
 'views' => $existing['views'],
 'is_new' => false,
 'message' => 'Existing link retrieved. PIN was set when link was created.'
 ]);
 exit;
 }

 // Generate new secure token
 $token = bin2hex(random_bytes(32));
 // Expiry: 72 hours (was 14 days)
 $expiresAt = date(
 'Y-m-d H:i:s', 
 strtotime('+' . STATEMENT_LINK_EXPIRY_HOURS . ' hours')
 );

 // Save to database
 $stmt = $db->prepare("
 INSERT INTO statement_links
 (token, auction_id, member_id, 
 user_id, pin, expires_at)
 VALUES (?,?,?,?,?,?)
 ");
 $stmt->execute([
 $token, $auctionId, $memberId,
 $userId, $pinHash, $expiresAt
 ]);

 // Build full URL
 $baseUrl = appUrl() . '/statement.php';

 $fullUrl = $baseUrl . '?token=' . $token;

 // Log activity
 logActivity($db, $userId,
 'statement.link_generated',
 'member', $memberId,
 "Shareable link generated for: {$member['name']} (expires: {$expiresAt})"
 );

 echo json_encode([
 'success' => true,
 'token' => $token,
 'url' => $fullUrl,
 'pin' => $pin,
 'expires_at' => $expiresAt,
 'views' => 0,
 'is_new' => true,
 'message' => 'Link generated successfully. PIN is shown once — deliver it to the member separately.'
 ]);

} catch (Exception $e) {
 error_log('Generate link error: ' . $e->getMessage());
 logError($db, 'error', 'Generate link error: ' . $e->getMessage(), __FILE__, __LINE__);
 echo json_encode([
 'success' => false,
 'message' => 'Database error'
 ]);
}
