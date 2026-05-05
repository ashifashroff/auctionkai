<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/error_handler.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[AuctionKai][critical] DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        echo '<h1>Database Error</h1>';
        echo '<p>Could not connect to the database. Please check config.php settings.</p>';
        echo '<!-- DB connection error -->';
        exit;
    }

    // Initialize error handler with DB connection
    initErrorHandler($pdo);

    return $pdo;
}
