<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /auctionkai/auth/login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
