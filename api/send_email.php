<?php
require_once __DIR__ . "/../includes/api_bootstrap.php";
require_once '../includes/auth_check.php';
require_once '../includes/helpers.php';
require_once '../includes/mailer.php';


$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$memberId = (int)($data['member_id'] ?? 0);
$auctionId = (int)($data['auction_id'] ?? 0);

if (!$memberId || !$auctionId) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Rate limiting: max 10 emails per minute per user
$rateKey = 'email_rate_' . $userId;
$now = time();
$window = 60; // 1 minute
$maxAttempts = 10;

if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = [];
}
// Clean expired entries
$_SESSION[$rateKey] = array_values(array_filter($_SESSION[$rateKey], fn($t) => ($now - $t) < $window));

if (count($_SESSION[$rateKey]) >= $maxAttempts) {
    echo json_encode([
        'success' => false,
        'message' => 'Too many emails sent. Please wait a minute and try again.'
    ]);
    exit;
}

// Record this attempt
$_SESSION[$rateKey][] = $now;

// Verify member belongs to this user
$stmt = $db->prepare(
    "SELECT * FROM members WHERE id=? AND user_id=?"
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

// Verify auction belongs to this user
$stmt = $db->prepare(
    "SELECT * FROM auction WHERE id=? AND user_id=?"
);
$stmt->execute([$auctionId, $userId]);
$auction = $stmt->fetch();
if (!$auction) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction not found'
    ]);
    exit;
}

// Fetch vehicles
$stmt = $db->prepare(
    "SELECT * FROM vehicles
     WHERE member_id=? AND auction_id=?
     ORDER BY lot"
);
$stmt->execute([$memberId, $auctionId]);
$vehicles = $stmt->fetchAll();

// Fetch fees
$fees = $db->query(
    "SELECT * FROM fees ORDER BY id LIMIT 1"
)->fetch();
$fees['customDeductions'] = $db->query(
    "SELECT * FROM custom_deductions ORDER BY id"
)->fetchAll();

// Calculate
$s = calcStatement($memberId, $vehicles, $fees);

if ($s['count'] === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No sold vehicles for this member'
    ]);
    exit;
}

$htmlBody = buildEmailBody($member, $auction, $s, $fees);

// Build PDF HTML for attachment
$pdfHtml = '';
if (class_exists('Dompdf\Dompdf')) {
    $pdfHtml = buildPdfHtml($member, $auction, $s, $fees, $brand);
}

$result = sendSettlementEmail(
    $member, $auction, $htmlBody, $db, $pdfHtml, true
);

// Log email to statement_history
if (!empty($result['success'])) {
    try {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
        $db->prepare("INSERT INTO statement_history (auction_id, member_id, user_id, action, net_payout, ip_address) VALUES (?, ?, ?, 'email', ?, ?)")->execute([$auctionId, $memberId, $userId, $s['netPayout'], $ip]);
    } catch (Exception $e) {}
}

echo json_encode($result);
