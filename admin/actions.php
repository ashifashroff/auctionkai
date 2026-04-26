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
    }
} catch (Exception $e) {
    error_log('Admin action error: ' . $e->getMessage());
}

header('Location: index.php');
exit;
