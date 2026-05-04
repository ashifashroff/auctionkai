<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/activity.php';

header('Content-Type: application/json');

$db = db();
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch ($action) {

    case 'update_profile':
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');

        if (empty($name)) { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }
        if (empty($email)) { echo json_encode(['success' => false, 'message' => 'Email is required']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success' => false, 'message' => 'Invalid email address']); exit; }
        if (empty($username)) { echo json_encode(['success' => false, 'message' => 'Username is required']); exit; }
        if (strlen($username) < 3) { echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']); exit; }

        // Check duplicate email (excluding current user)
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Email address is already in use']); exit; }

        // Check duplicate username (excluding current user)
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $userId]);
        if ($chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Username is already taken']); exit; }

        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?");
        $stmt->execute([$name, $email, $username, $userId]);

        $_SESSION['user_name'] = $name;
        $_SESSION['user_username'] = $username;

        logActivity($db, $userId, 'profile.update', 'user', $userId, "Updated profile: name={$name}, email={$email}, username={$username}");

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        break;

    case 'change_password':
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($currentPassword)) { echo json_encode(['success' => false, 'message' => 'Current password is required']); exit; }
        if (empty($newPassword)) { echo json_encode(['success' => false, 'message' => 'New password is required']); exit; }
        if (strlen($newPassword) < 8) { echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']); exit; }
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain uppercase, lowercase, and numbers']);
            exit;
        }

        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            logActivity($db, $userId, 'password.change_failed', 'user', $userId, 'Incorrect current password');
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        if (password_verify($newPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'New password must be different from current password']);
            exit;
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $userId]);

        logActivity($db, $userId, 'password.change', 'user', $userId, 'Password changed successfully');

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
