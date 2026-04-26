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
                $stmt = $db->prepare("SELECT COUNT(*) FROM auction WHERE user_id=?");
                $stmt->execute([$targetId]);
                $count = (int)$stmt->fetchColumn();
                if ($count === 0) {
                    $db->prepare("DELETE FROM members WHERE user_id=?")->execute([$targetId]);
                    $db->prepare("DELETE FROM users WHERE id=?")->execute([$targetId]);
                }
            }
            break;

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
