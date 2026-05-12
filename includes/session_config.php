<?php
/**
 * Centralized session configuration.
 * Include this BEFORE session_start() in all files that need sessions.
 */

function configureSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Already started
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
}
