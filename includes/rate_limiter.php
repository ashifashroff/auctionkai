<?php
function checkRateLimit(string $identifier, int $maxAttempts = MAX_LOGIN_ATTEMPTS, int $timeWindow = LOGIN_LOCKOUT_SECONDS): bool {
    $key = 'login_attempts_' . md5($identifier);
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
    }
    $data = $_SESSION[$key];
    if (time() > $data['reset_time']) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
        return true;
    }
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    $_SESSION[$key]['count']++;
    return true;
}
