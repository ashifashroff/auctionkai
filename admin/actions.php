<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/constants.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// CSRF check
if (($_POST['_tok'] ?? '') !== ($_SESSION['tok'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

$action   = $_POST['action'] ?? '';
$targetId = (int)($_POST['user_id'] ?? 0);
$isSelf   = ($targetId === $userId);
$db       = db();

try {
    switch ($action) {

        case 'make_admin':
            if (!$isSelf && $targetId) {
                $db->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$targetId]);
            }
            break;

        case 'make_user':
            if (!$isSelf && $targetId) {
                $db->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$targetId]);
            }
            break;

        case 'disable_user':
            if (!$isSelf && $targetId) {
                $db->prepare("UPDATE users SET disabled=1 WHERE id=?")->execute([$targetId]);
            }
            break;

        case 'enable_user':
            if ($targetId) {
                $db->prepare("UPDATE users SET disabled=0 WHERE id=?")->execute([$targetId]);
            }
            break;

        case 'delete_user':
            if (!$isSelf && $targetId) {
                $db->prepare("DELETE FROM members WHERE user_id=?")->execute([$targetId]);
                $db->prepare("DELETE FROM users WHERE id=?")->execute([$targetId]);
            }
            break;

        case 'login_as':
            $target = $db->prepare("SELECT * FROM users WHERE id=?");
            $target->execute([$targetId]);
            $t = $target->fetch();
            if ($t && (int)$t['id'] !== $userId) {
                $_SESSION['original_admin_id']   = $userId;
                $_SESSION['original_admin_name'] = $userName;
                $_SESSION['user_id']             = (int)$t['id'];
                $_SESSION['user_name']           = $t['name'];
                $_SESSION['user_role']           = $t['role'];
                $_SESSION['user_username']       = $t['username'];
                header('Location: ../index.php');
                exit;
            }
            break;

        case 'return_to_admin':
            $origId   = (int)($_SESSION['original_admin_id'] ?? 0);
            if ($origId) {
                $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
                $stmt->execute([$origId]);
                $orig = $stmt->fetch();
                if ($orig) {
                    $_SESSION['user_id']       = (int)$orig['id'];
                    $_SESSION['user_name']     = $orig['name'];
                    $_SESSION['user_role']     = $orig['role'];
                    $_SESSION['user_username'] = $orig['username'];
                }
            }
            unset($_SESSION['original_admin_id'], $_SESSION['original_admin_name']);
            header('Location: index.php');
            exit;

        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            if ($username === '' || $name === '' || $password === '') {
                $_SESSION['admin_error'] = 'Username, name, and password are required.';
            } elseif (strlen($password) < 6) {
                $_SESSION['admin_error'] = 'Password must be at least 6 characters.';
            } else {
                $chk = $db->prepare("SELECT id FROM users WHERE username=?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    $_SESSION['admin_error'] = 'Username already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare("INSERT INTO users (username,password,name,email,role) VALUES (?,?,?,?,?)")->execute([$username, $hash, $name, $email, $role]);
                    $_SESSION['admin_success'] = 'User created successfully.';
                }
            }
            header('Location: index.php?tab=create');
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
                    $_SESSION['admin_error'] = 'Username already taken.';
                } else {
                    $db->prepare("UPDATE users SET username=?,name=?,email=?,role=? WHERE id=?")->execute([$username, $name, $email, $role, $id]);
                    $_SESSION['admin_success'] = 'User updated.';
                }
            } else {
                $_SESSION['admin_error'] = 'Username and name are required.';
            }
            header('Location: index.php?tab=users');
            exit;

        case 'suspend_user':
            $id     = (int)($_POST['user_id'] ?? 0);
            $days   = max(1, (int)($_POST['days'] ?? 1));
            $reason = trim($_POST['reason'] ?? 'No reason provided');
            if ($id && $id !== $userId) {
                $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
                $db->prepare("UPDATE users SET status='suspended',suspended_until=?,suspend_reason=? WHERE id=?")->execute([$until, $reason, $id]);
                $_SESSION['admin_success'] = "User suspended until {$until}.";
            } else {
                $_SESSION['admin_error'] = 'Cannot suspend yourself.';
            }
            header('Location: index.php?tab=users');
            exit;

        case 'unsuspend_user':
            $id = (int)($_POST['user_id'] ?? 0);
            if ($id) {
                $db->prepare("UPDATE users SET status='active',suspended_until=NULL,suspend_reason=NULL WHERE id=?")->execute([$id]);
                $_SESSION['admin_success'] = 'User reactivated.';
            }
            header('Location: index.php?tab=users');
            exit;

        case 'admin_settings':
            $newUsername = trim($_POST['username'] ?? '');
            $newName     = trim($_POST['name'] ?? '');
            $newEmail    = trim($_POST['email'] ?? '');
            $currentPass = $_POST['current_password'] ?? '';
            $newPass     = $_POST['new_password'] ?? '';
            if ($newUsername === '' || $newName === '') {
                $_SESSION['admin_error'] = 'Username and name are required.';
            } else {
                $chk = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
                $chk->execute([$newUsername, $userId]);
                if ($chk->fetch()) {
                    $_SESSION['admin_error'] = 'Username already taken.';
                } else {
                    if ($newPass !== '') {
                        $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch();
                        if (!$row || !password_verify($currentPass, $row['password'])) {
                            $_SESSION['admin_error'] = 'Current password is incorrect.';
                        } elseif (strlen($newPass) < 6) {
                            $_SESSION['admin_error'] = 'New password must be at least 6 characters.';
                        } else {
                            $hash = password_hash($newPass, PASSWORD_DEFAULT);
                            $db->prepare("UPDATE users SET username=?,name=?,email=?,password=? WHERE id=?")->execute([$newUsername, $newName, $newEmail, $hash, $userId]);
                            $_SESSION['user_name'] = $newName;
                            $_SESSION['admin_success'] = 'Profile and password updated.';
                        }
                    } else {
                        $db->prepare("UPDATE users SET username=?,name=?,email=? WHERE id=?")->execute([$newUsername, $newName, $newEmail, $userId]);
                        $_SESSION['user_name'] = $newName;
                        $_SESSION['admin_success'] = 'Profile updated.';
                    }
                }
            }
            header('Location: index.php?tab=settings');
            exit;

        case 'save_email_settings':
            require_once __DIR__ . '/../includes/settings.php';

            $existing = loadSettings($db);
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

            // Only update password if new one entered
            if (!empty($newPass)) {
                saveSetting($db, 'mail_password', $newPass);
            }

            $_SESSION['admin_success'] = 'Email settings saved successfully';
            header('Location: index.php#email-settings');
            exit;

        case 'test_email':
            require_once __DIR__ . '/../includes/settings.php';
            require_once __DIR__ . '/../includes/mailer.php';

            $testEmail = trim($_POST['test_email'] ?? '');
            if (empty($testEmail)) {
                $_SESSION['admin_error'] = 'Please enter a test email address';
                header('Location: index.php#email-settings');
                exit;
            }

            $adminUser = [
                'name'  => $_SESSION['user_name'] ?? 'Admin',
                'email' => $testEmail
            ];
            $testAuction = [
                'name' => 'AuctionKai Test',
                'date' => date('Y-m-d')
            ];
            $testHtml = "
            <div style='font-family:sans-serif;padding:32px;
              max-width:500px;margin:0 auto'>
              <h2 style='color:#D4A84B'>⚡ AuctionKai</h2>
              <p>This is a test email from AuctionKai.</p>
              <p style='color:#4CAF82;font-weight:600'>
                ✓ Your email configuration is working!
              </p>
              <hr style='border:none;border-top:1px solid #eee;
                margin:20px 0'>
              <p style='color:#999;font-size:12px'>
                Sent from AuctionKai Admin Panel<br>
                Designed &amp; Developed by
                Mirai Global Solutions
              </p>
            </div>";

            $result = sendSettlementEmail(
                $adminUser, $testAuction, $testHtml, $db
            );

            $_SESSION[$result['success']
                ? 'admin_success'
                : 'admin_error'] = $result['message'];

            header('Location: index.php#email-settings');
            exit;
    }
} catch (Exception $e) {
    error_log('Admin action error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
