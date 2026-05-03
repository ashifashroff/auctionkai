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

// Rate limiting: max 3 attempts per 5 minutes
$rateKey = 'delacct_' . $userId;
$attempts = (int)($_SESSION[$rateKey . '_count'] ?? 0);
$lastAttempt = (int)($_SESSION[$rateKey . '_time'] ?? 0);
if ($attempts >= 3 && (time() - $lastAttempt) < 300) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Try again in 5 minutes.']);
    exit;
}

$password = $data['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

try {
    // Verify password
    $stmt = $db->prepare("SELECT id, password, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION[$rateKey . '_count'] = $attempts + 1;
        $_SESSION[$rateKey . '_time'] = time();
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }

    // Reset rate limit on successful password
    unset($_SESSION[$rateKey . '_count'], $_SESSION[$rateKey . '_time']);

    // Prevent admin from deleting their account if they are the only admin
    if ($user['role'] === 'admin') {
        $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND disabled=0")->fetchColumn();
        if ($adminCount <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the only admin account. Assign another admin first.']);
            exit;
        }
    }

    // Log before deleting
    logActivity($db, $userId, 'account.delete', 'user', $userId, "User deleted their own account");

    // Delete all user data in correct order (respecting foreign keys)

    // 1. Get all auction IDs for this user
    $auctionIds = $db->prepare("SELECT id FROM auction WHERE user_id = ?");
    $auctionIds->execute([$userId]);
    $auctions = $auctionIds->fetchAll(PDO::FETCH_COLUMN);

    // 2. Delete vehicles for all auctions
    if (!empty($auctions)) {
        $placeholders = implode(',', array_fill(0, count($auctions), '?'));
        $db->prepare("DELETE FROM vehicles WHERE auction_id IN ($placeholders)")->execute($auctions);
    }

    // 3. Delete auctions
    $db->prepare("DELETE FROM auction WHERE user_id = ?")->execute([$userId]);

    // 4. Delete members
    $db->prepare("DELETE FROM members WHERE user_id = ?")->execute([$userId]);

    // 5. Delete login history
    $db->prepare("DELETE FROM login_history WHERE user_id = ?")->execute([$userId]);

    // 6. Delete activity log
    $db->prepare("DELETE FROM activity_log WHERE user_id = ?")->execute([$userId]);

    // 7. Delete password resets
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();
    if ($userEmail) {
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$userEmail]);
    }

    // 8. Finally delete the user
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    // Destroy session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);

} catch (Exception $e) {
    error_log('Account deletion error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
