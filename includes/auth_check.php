<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Session Timeout Check ─────────────────────
try {
    require_once __DIR__ . '/db.php';
    $db = db();

    $rows = $db->query(
        "SELECT `key`, value FROM settings WHERE `key` IN ('session_timeout_enabled','session_timeout_minutes')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $timeoutEnabled  = ($rows['session_timeout_enabled'] ?? '1') === '1';
    $timeoutMinutes  = (int)($rows['session_timeout_minutes'] ?? 30);
    $timeoutSeconds  = $timeoutMinutes * 60;

    if ($timeoutEnabled) {
        $lastActivity = $_SESSION['last_activity'] ?? null;

        if ($lastActivity !== null) {
            $inactiveSeconds = time() - $lastActivity;

            if ($inactiveSeconds > $timeoutSeconds) {
                // Session expired — log out
                $sessionUserId = (int)$_SESSION['user_id'];

                try {
                    require_once __DIR__ . '/activity.php';
                    $db->prepare("
                        INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, ip_address)
                        VALUES (?, 'session.timeout', 'user', ?, 'Session timed out after inactivity', ?)
                    ")->execute([$sessionUserId, $sessionUserId, $_SERVER['REMOTE_ADDR'] ?? '']);
                } catch (Exception $e) {
                    // Never crash on logging
                }

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

                // Redirect with timeout message
                header('Location: auth/login.php?timeout=1');
                exit;
            }
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
    }
} catch (Exception $e) {
    error_log('Session timeout check error: ' . $e->getMessage());
}
