<?php
// ─── Logout handler ───────────────────────────────────────────────────────────
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId) {
    $db = db();
    logActivity($db, $userId, 'logout', 'user', $userId, "User logged out");
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
