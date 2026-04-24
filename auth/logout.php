<?php
// ─── Logout handler ───────────────────────────────────────────────────────────
session_start();
$_SESSION = [];
session_destroy();
header('Location: /auctionkai/auth/login.php');
exit;
