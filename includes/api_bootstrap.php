<?php
/**
 * Shared API bootstrap: session, auth, CSRF, headers
 * Include this at the top of every api/ endpoint.
 */

require_once __DIR__ . '/db.php';

// Secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

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
        $input = json_decode($rawInput, true);
        $tok = $input['_tok'] ?? '';
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
