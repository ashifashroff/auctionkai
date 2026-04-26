<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Must be admin role
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
