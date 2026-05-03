<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/constants.php';

header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

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
                // Prevent admin-to-admin impersonation
                if ($t['role'] === 'admin') {
                    $_SESSION['admin_error'] = 'Cannot impersonate other admins.';
                    header('Location: index.php?tab=users');
                    exit;
                }
                $_SESSION['original_admin_id']   = $userId;
                $_SESSION['original_admin_name'] = $userName;
                $_SESSION['impersonate_started'] = time(); // Track start time
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

            $_SESSION['admin_success'] = 'Email settings saved (' . ucfirst($provider) . ')';
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

        case 'clear_old_logs':
            $days = (int)($_POST['days'] ?? 90);
            if ($days < 30) $days = 30;

            $stmt = $db->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            $deleted = $db->query("SELECT ROW_COUNT()")->fetchColumn();

            require_once __DIR__ . '/../includes/activity.php';
            logActivity($db, $userId, 'admin.clear_logs', 'system', 0, "Cleared {$deleted} log entries older than {$days} days");

            $_SESSION['admin_success'] = "Cleared {$deleted} log entries older than {$days} days";
            header('Location: index.php?tab=activity#activity-log');
            exit;

        case 'save_session_settings':
            require_once __DIR__ . '/../includes/settings.php';

            $enabled = isset($_POST['session_timeout_enabled']) ? '1' : '0';
            $minutes = max(5, min(480, (int)($_POST['session_timeout_minutes'] ?? 30)));
            $warnMins = max(1, min(10, (int)($_POST['session_timeout_warn_minutes'] ?? 2)));

            saveSettings($db, [
                'session_timeout_enabled'     => $enabled,
                'session_timeout_minutes'     => (string)$minutes,
                'session_timeout_warn_minutes' => (string)$warnMins,
            ]);

            require_once __DIR__ . '/../includes/activity.php';
            logActivity($db, $userId, 'settings.session', 'system', 0, "Session timeout set to {$minutes} minutes (enabled: {$enabled})");

            $_SESSION['admin_success'] = 'Session settings saved successfully';
            header('Location: index.php?tab=session');
            exit;

        case 'save_maintenance_settings':
            require_once __DIR__ . '/../includes/settings.php';

            $enabled = isset($_POST['maintenance_mode']) ? '1' : '0';
            $title = trim($_POST['maintenance_title'] ?? 'System Maintenance');
            $message = trim($_POST['maintenance_message'] ?? '');
            $eta = trim($_POST['maintenance_eta'] ?? '');

            if (empty($title)) $title = 'System Maintenance';
            if (mb_strlen($message) > 1000) $message = mb_substr($message, 0, 1000);

            saveSettings($db, [
                'maintenance_mode'    => $enabled,
                'maintenance_title'   => $title,
                'maintenance_message' => $message,
                'maintenance_eta'     => $eta,
            ]);

            require_once __DIR__ . '/../includes/activity.php';
            logActivity($db, $userId, 'admin.maintenance', 'system', 0, 'Maintenance mode ' . ($enabled === '1' ? 'ENABLED' : 'DISABLED'));

            $_SESSION['admin_success'] = $enabled === '1'
                ? '🚧 Maintenance mode is now ENABLED — users cannot access the system'
                : '✅ Maintenance mode DISABLED — system is live';

            header('Location: index.php?tab=maintenance');
            exit;

        case 'save_branding':
            require_once __DIR__ . '/../includes/settings.php';
            require_once __DIR__ . '/../includes/branding.php';

            $accentColor = sanitizeColor(trim($_POST['brand_accent_color'] ?? '#D4A84B'));

            saveSettings($db, [
                'brand_name'         => mb_substr(trim($_POST['brand_name'] ?? 'AuctionKai'), 0, 100),
                'brand_tagline'      => mb_substr(trim($_POST['brand_tagline'] ?? 'Settlement Management System'), 0, 200),
                'brand_owner'        => mb_substr(trim($_POST['brand_owner'] ?? 'Mirai Global Solutions'), 0, 200),
                'brand_email'        => mb_substr(trim($_POST['brand_email'] ?? ''), 0, 200),
                'brand_phone'        => mb_substr(trim($_POST['brand_phone'] ?? ''), 0, 50),
                'brand_address'      => mb_substr(trim($_POST['brand_address'] ?? ''), 0, 500),
                'brand_accent_color' => $accentColor,
                'brand_footer_text'  => mb_substr(trim($_POST['brand_footer_text'] ?? 'Designed & Developed by Mirai Global Solutions'), 0, 300),
            ]);

            require_once __DIR__ . '/../includes/activity.php';
            logActivity($db, $userId, 'admin.branding', 'system', 0, 'Branding updated: ' . trim($_POST['brand_name'] ?? ''));

            $_SESSION['admin_success'] = '🎨 Branding settings saved successfully';
            header('Location: index.php?tab=branding');
            exit;

        case 'save_backup_settings':
            require_once __DIR__ . '/../includes/settings.php';

            $enabled = isset($_POST['backup_enabled']) ? '1' : '0';
            $frequency = in_array($_POST['backup_frequency'] ?? '', ['daily', 'weekly', 'monthly']) ? $_POST['backup_frequency'] : 'daily';
            $retention = max(1, min(365, (int)($_POST['backup_retention_days'] ?? 30)));
            $compress = isset($_POST['backup_compress']) ? '1' : '0';

            saveSettings($db, [
                'backup_enabled' => $enabled,
                'backup_frequency' => $frequency,
                'backup_retention_days' => (string)$retention,
                'backup_compress' => $compress,
            ]);

            require_once __DIR__ . '/../includes/activity.php';
            logActivity($db, $userId, 'admin.backup_settings', 'system', 0, "Backup settings: {$frequency}, retain {$retention} days, enabled: {$enabled}");

            $_SESSION['admin_success'] = 'Backup settings saved successfully';
            header('Location: index.php?tab=backups');
            exit;

        case 'delete_backup':
            $filename = basename($_POST['filename'] ?? '');
            $backupDir = __DIR__ . '/../../backups/';
            $filepath = $backupDir . $filename;

            if (!empty($filename) && preg_match('/^auctionkai_backup_[\w\-\.]+\.sql(\.gz)?$/', $filename) && file_exists($filepath)) {
                unlink($filepath);
                require_once __DIR__ . '/../includes/activity.php';
                logActivity($db, $userId, 'backup.delete', 'system', 0, "Deleted backup: {$filename}");
                $_SESSION['admin_success'] = "Backup {$filename} deleted";
            }

            header('Location: index.php?tab=backups');
            exit;
    }
} catch (Exception $e) {
    error_log('Admin action error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
