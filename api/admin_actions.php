<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/settings.php';

header('Content-Type: application/json');

// CSRF check
if (($_POST['_tok'] ?? '') !== ($_SESSION['tok'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action   = $_POST['action'] ?? '';
$targetId = (int)($_POST['user_id'] ?? 0);
$isSelf   = ($targetId === $userId);
$db       = db();

switch ($action) {

    case 'admin_settings':
        $newUsername = trim($_POST['username'] ?? '');
        $newName     = trim($_POST['name'] ?? '');
        $newEmail    = trim($_POST['email'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        if ($newUsername === '' || $newName === '') {
            echo json_encode(['success' => false, 'message' => 'Username and name are required.']);
            exit;
        }
        $chk = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $chk->execute([$newUsername, $userId]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already taken.']);
            exit;
        }
        if ($newPass !== '') {
            $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($currentPass, $row['password'])) {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                exit;
            }
            if (strlen($newPass) < 6) {
                echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
                exit;
            }
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET username=?,name=?,email=?,password=? WHERE id=?")->execute([$newUsername, $newName, $newEmail, $hash, $userId]);
            $_SESSION['user_name'] = $newName;
            echo json_encode(['success' => true, 'message' => 'Profile and password updated.']);
        } else {
            $db->prepare("UPDATE users SET username=?,name=?,email=? WHERE id=?")->execute([$newUsername, $newName, $newEmail, $userId]);
            $_SESSION['user_name'] = $newName;
            echo json_encode(['success' => true, 'message' => 'Profile updated.']);
        }
        exit;

    case 'create_user':
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($username === '' || $name === '' || $password === '') {
            echo json_encode(['success' => false, 'message' => 'Username, name, and password are required.']);
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
            exit;
        }
        $chk = $db->prepare("SELECT id FROM users WHERE username=?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Username already exists.']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username,password,name,email,role) VALUES (?,?,?,?,?)")->execute([$username, $hash, $name, $email, $role]);
        echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        exit;

    case 'save_email_settings':
        ensureSettingsTable($db);
        $newPass = trim($_POST['mail_password'] ?? '');
        saveSettings($db, [
            'mail_enabled'    => isset($_POST['mail_enabled']) ? '1' : '0',
            'mail_provider'   => trim($_POST['mail_provider'] ?? 'smtp'),
            'mail_host'       => trim($_POST['mail_host'] ?? ''),
            'mail_port'       => trim($_POST['mail_port'] ?? '587'),
            'mail_encryption' => trim($_POST['mail_encryption'] ?? 'tls'),
            'mail_username'   => trim($_POST['mail_username'] ?? ''),
            'mail_from_email' => trim($_POST['mail_from_email'] ?? ''),
            'mail_from_name'  => trim($_POST['mail_from_name'] ?? 'AuctionKai Settlement System'),
        ]);
        if (!empty($newPass)) {
            saveSetting($db, 'mail_password', $newPass);
        }
        echo json_encode(['success' => true, 'message' => 'Email settings saved successfully']);
        exit;

    case 'test_email':
        require_once __DIR__ . '/../includes/mailer.php';
        $testEmail = trim($_POST['test_email'] ?? '');
        if (empty($testEmail)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a test email address']);
            exit;
        }
        $adminUser = ['name' => $_SESSION['user_name'] ?? 'Admin', 'email' => $testEmail];
        $testAuction = ['name' => 'AuctionKai Test', 'date' => date('Y-m-d')];
        $testHtml = "<div style='font-family:sans-serif;padding:32px;max-width:500px;margin:0 auto'><h2 style='color:#D4A84B'>⚡ AuctionKai</h2><p>This is a test email from AuctionKai.</p><p style='color:#4CAF82;font-weight:600'>✓ Your email configuration is working!</p><hr style='border:none;border-top:1px solid #eee;margin:20px 0'><p style='color:#999;font-size:12px'>Sent from AuctionKai Admin Panel<br>Designed &amp; Developed by Mirai Global Solutions</p></div>";
        $result = sendSettlementEmail($adminUser, $testAuction, $testHtml, $db);
        echo json_encode($result);
        exit;

    case 'edit_user':
        $id       = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        if ($id && $username !== '' && $name !== '') {
            $chk = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $chk->execute([$username, $id]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Username already taken.']);
                exit;
            }
            $db->prepare("UPDATE users SET username=?,name=?,email=?,role=? WHERE id=?")->execute([$username, $name, $email, $role, $id]);
            echo json_encode(['success' => true, 'message' => 'User updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Username and name are required.']);
        }
        exit;

    case 'suspend_user':
        $id     = (int)($_POST['user_id'] ?? 0);
        $days   = max(1, (int)($_POST['days'] ?? 1));
        $reason = trim($_POST['reason'] ?? 'No reason provided');
        if ($id && $id !== $userId) {
            $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $db->prepare("UPDATE users SET status='suspended',suspended_until=?,suspend_reason=? WHERE id=?")->execute([$until, $reason, $id]);
            echo json_encode(['success' => true, 'message' => "User suspended until {$until}."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot suspend yourself.']);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}
