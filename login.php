<?php
require_once 'config.php';
session_start();

// ─── If already logged in, redirect ───────────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db();
$error = '';
$success = '';
$showRegister = isset($_GET['register']);

// ─── LOGIN ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_username'] = $user['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// ─── REGISTER ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    $username = trim($_POST['username'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($username === '' || $name === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?,?,?,?,?)");
            $stmt->execute([$username, $hash, $name, $email, 'user']);
            $success = 'Account created! You can now log in.';
            $showRegister = false;
        }
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — <?= $showRegister ? 'Register' : 'Login' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">⚡ AuctionKai</div>
    <div class="login-sub"><?= $showRegister ? 'Create Your Account' : 'Settlement Management System' ?></div>

    <?php if ($error): ?>
      <div class="login-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="login-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if ($showRegister): ?>
    <!-- ─── REGISTER FORM ─────────────────────────────────────────── -->
    <form method="POST" action="login.php?register=1">
      <input type="hidden" name="form" value="register">
      <div class="login-field">
        <label class="lbl">Full Name *</label>
        <input class="inp" name="name" placeholder="e.g. Ahmad Hassan" value="<?= h($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="login-field">
        <label class="lbl">Username *</label>
        <input class="inp" name="username" placeholder="Choose a username" value="<?= h($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="login-field">
        <label class="lbl">Email</label>
        <input class="inp" type="email" name="email" placeholder="email@example.com" value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="login-field">
        <label class="lbl">Password * <span style="font-weight:400;color:var(--muted)">(min 6 chars)</span></label>
        <input class="inp" type="password" name="password" placeholder="••••••" required>
      </div>
      <div class="login-field">
        <label class="lbl">Confirm Password *</label>
        <input class="inp" type="password" name="confirm" placeholder="••••••" required>
      </div>
      <button class="btn btn-gold login-btn" type="submit">Create Account</button>
    </form>
    <div class="login-switch">Already have an account? <a href="login.php">Log in</a></div>

    <?php else: ?>
    <!-- ─── LOGIN FORM ─────────────────────────────────────────────── -->
    <form method="POST" action="login.php">
      <input type="hidden" name="form" value="login">
      <div class="login-field">
        <label class="lbl">Username</label>
        <input class="inp" name="username" placeholder="Enter username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="login-field">
        <label class="lbl">Password</label>
        <input class="inp" type="password" name="password" placeholder="••••••" required>
      </div>
      <button class="btn btn-gold login-btn" type="submit">Log In</button>
    </form>
    <div class="login-switch">Don't have an account? <a href="login.php?register=1">Register</a></div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
