<?php
/**
 * Shared API bootstrap: session, auth, CSRF, headers
 * Include this at the top of every api/ endpoint.
 */

require_once __DIR__ . '/db.php';

// Secure session
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Security headers
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Auth check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// CSRF check on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['_tok'] ?? '';
    if (empty($tok)) {
        $rawInput = file_get_contents('php://input');
        $GLOBALS['_json_input'] = json_decode($rawInput, true);
        $tok = $GLOBALS['_json_input']['_tok'] ?? '';
    }
    if (empty($tok) || $tok !== ($_SESSION['tok'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token mismatch']);
        exit;
    }
}

// Expose for use
$userId = (int)$_SESSION['user_id'];
$db = db();

// ── API Rate Limiting (120 req/min per IP) ──────────
$_apiRlKey = 'api_' . trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown')[0]);
$_apiRlFile = sys_get_temp_dir() . '/ak_rl_' . md5($_apiRlKey) . '.lock';
$_apiRlData = ['count' => 0, 'window_start' => time()];
if (file_exists($_apiRlFile)) {
    $_apiRlExisting = json_decode(file_get_contents($_apiRlFile), true);
    if ($_apiRlExisting && (time() - ($_apiRlExisting['window_start'] ?? 0)) < 60) {
        $_apiRlData = $_apiRlExisting;
    }
}
$_apiRlData['count']++;
file_put_contents($_apiRlFile, json_encode($_apiRlData));
if ($_apiRlData['count'] > 120) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.', 'retry_after' => 60]);
    exit;
}
