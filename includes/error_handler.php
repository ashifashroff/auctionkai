<?php
/**
 * Custom error handler — logs PHP errors to database + server error_log
 * 
 * Usage: require_once this file early in your bootstrap
 * After including db.php, call initErrorHandler($db)
 */

/**
 * Log an error to the error_logs table
 */
function logError(
    PDO $db,
    string $severity,
    string $message,
    ?string $file = null,
    ?int $line = null,
    ?string $stackTrace = null,
    ?array $context = null
): void {
    try {
        $url = ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO error_logs
            (severity, message, file, line, url, request_method, user_id, stack_trace, context)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $severity,
            mb_substr($message, 0, 65535),
            $file ? mb_substr($file, 0, 500) : null,
            $line,
            mb_substr($url, 0, 500),
            $method,
            $userId,
            $stackTrace ? mb_substr($stackTrace, 0, 65535) : null,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
        ]);

        // Also write to PHP error_log as backup
        error_log("[AuctionKai][{$severity}] {$message}" . ($file ? " in {$file}" : '') . ($line ? ":{$line}" : ''));

    } catch (Exception $e) {
        // Fallback: if DB logging fails, at least PHP error_log gets it
        error_log('[AuctionKai] Error log DB write failed: ' . $e->getMessage());
    }
}

/**
 * Convenience wrappers
 */
function logCritical(PDO $db, string $msg, ?string $file = null, ?int $line = null, ?string $trace = null, ?array $ctx = null): void {
    logError($db, 'critical', $msg, $file, $line, $trace, $ctx);
}
function logErrorError(PDO $db, string $msg, ?string $file = null, ?int $line = null, ?string $trace = null, ?array $ctx = null): void {
    logError($db, 'error', $msg, $file, $line, $trace, $ctx);
}
function logWarning(PDO $db, string $msg, ?string $file = null, ?int $line = null, ?string $trace = null, ?array $ctx = null): void {
    logError($db, 'warning', $msg, $file, $line, $trace, $ctx);
}
function logNotice(PDO $db, string $msg, ?string $file = null, ?int $line = null, ?string $trace = null, ?array $ctx = null): void {
    logError($db, 'notice', $msg, $file, $line, $trace, $ctx);
}

/**
 * PHP error handler — catches E_ERROR, E_WARNING, E_NOTICE, etc.
 * Stores in DB. Called automatically by PHP.
 */
function akErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool {
    global $akDb;

    $severityMap = [
        E_ERROR             => 'critical',
        E_PARSE             => 'critical',
        E_CORE_ERROR        => 'critical',
        E_COMPILE_ERROR     => 'critical',
        E_USER_ERROR        => 'error',
        E_RECOVERABLE_ERROR => 'error',
        E_WARNING           => 'warning',
        E_CORE_WARNING      => 'warning',
        E_COMPILE_WARNING   => 'warning',
        E_USER_WARNING      => 'warning',
        E_NOTICE            => 'notice',
        E_USER_NOTICE       => 'notice',
        E_STRICT            => 'notice',
        E_DEPRECATED        => 'notice',
        E_USER_DEPRECATED   => 'notice',
    ];

    $severity = $severityMap[$errno] ?? 'error';

    // Suppress @ operator errors
    if (!(error_reporting() & $errno)) {
        return false;
    }

    if (isset($akDb) && $akDb instanceof PDO) {
        logError($akDb, $severity, $errstr, $errfile, $errline);
    } else {
        error_log("[AuctionKai][{$severity}] {$errstr} in {$errfile}:{$errline}");
    }

    // Don't execute PHP's internal handler
    return true;
}

/**
 * Exception handler — catches uncaught exceptions
 */
function akExceptionHandler(Throwable $e): void {
    global $akDb;

    $severity = ($e instanceof Error) ? 'critical' : 'error';
    $trace = $e->getTraceAsString();

    if (isset($akDb) && $akDb instanceof PDO) {
        logError($akDb, $severity, $e->getMessage(), $e->getFile(), $e->getLine(), $trace);
    } else {
        error_log("[AuctionKai][{$severity}] Uncaught " . get_class($e) . ': ' . $e->getMessage() . " in {$e->getFile()}:{$e->getLine()}\n{$trace}");
    }

    // Show a clean error page if headers not sent
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (http_response_code() === 500) {
        // Try to show a friendly error page
        $errorPage = __DIR__ . '/../500.php';
        if (file_exists($errorPage)) {
            include $errorPage;
        } else {
            echo '<h1>Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
        }
    }
}

/**
 * Shutdown handler — catches fatal errors that bypass the error handler
 */
function akShutdownHandler(): void {
    $error = error_get_last();
    if ($error === null) return;

    // Only handle fatal types
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) return;

    global $akDb;
    $severity = 'critical';

    if (isset($akDb) && $akDb instanceof PDO) {
        logError($akDb, $severity, $error['message'], $error['file'], $error['line']);
    } else {
        error_log("[AuctionKai][critical] FATAL: {$error['message']} in {$error['file']}:{$error['line']}");
    }
}

/**
 * Initialize the error handling system
 * Call this AFTER db.php is loaded and $db is available
 */
function initErrorHandler(PDO $db): void {
    global $akDb;
    $akDb = $db;

    set_error_handler('akErrorHandler');
    set_exception_handler('akExceptionHandler');
    register_shutdown_function('akShutdownHandler');
}
