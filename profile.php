<?php
require_once 'config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $stmt->execute([$name, $email, $userId]);
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully.';
            // Refresh user data
            $user = $db->prepare("SELECT * FROM users WHERE id = ?");
            $user->execute([$userId]);
            $user = $user->fetch();
        }
    }

    elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $userId]);
            $success = 'Password changed successfully.';
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
<title>AuctionKai — Profile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="login-wrap" style="align-items:flex-start;padding-top:60px">
  <div class="login-card" style="max-width:500px">
    <div class="login-logo">⚡ AuctionKai</div>
    <div class="login-sub">Account Settings</div>

    <?php if ($error): ?>
      <div class="login-error"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="login-success"><?= h($success) ?></div>
    <?php endif; ?>

    <!-- Profile Info -->
    <div style="margin-bottom:28px">
      <div class="sec-lbl" style="margin-bottom:16px">Profile Information</div>
      <form method="POST" action="profile.php">
        <input type="hidden" name="form" value="update_profile">

        <div class="login-field">
          <label class="lbl">Username</label>
          <input class="inp" value="<?= h($user['username']) ?>" disabled style="opacity:0.5;cursor:not-allowed">
          <div style="font-size:10px;color:var(--muted);margin-top:3px">Username cannot be changed</div>
        </div>

        <div class="login-field">
          <label class="lbl">Full Name *</label>
          <input class="inp" name="name" value="<?= h($user['name']) ?>" required>
        </div>

        <div class="login-field">
          <label class="lbl">Email</label>
          <input class="inp" type="email" name="email" value="<?= h($user['email']) ?>" placeholder="email@example.com">
        </div>

        <div class="login-field">
          <label class="lbl">Role</label>
          <input class="inp" value="<?= h($user['role']) ?>" disabled style="opacity:0.5;cursor:not-allowed;text-transform:capitalize">
        </div>

        <button class="btn btn-gold login-btn" type="submit">Save Changes</button>
      </form>
    </div>

    <!-- Password Change -->
    <div>
      <div class="sec-lbl" style="margin-bottom:16px">Change Password</div>
      <form method="POST" action="profile.php">
        <input type="hidden" name="form" value="change_password">

        <div class="login-field">
          <label class="lbl">Current Password *</label>
          <input class="inp" type="password" name="current_password" placeholder="Enter current password" required>
        </div>

        <div class="login-field">
          <label class="lbl">New Password * <span style="font-weight:400;color:var(--muted)">(min 6 chars)</span></label>
          <input class="inp" type="password" name="new_password" placeholder="Enter new password" required>
        </div>

        <div class="login-field">
          <label class="lbl">Confirm New Password *</label>
          <input class="inp" type="password" name="confirm_password" placeholder="Confirm new password" required>
        </div>

        <button class="btn btn-gold login-btn" type="submit">Change Password</button>
      </form>
    </div>

    <div class="login-switch" style="margin-top:24px">
      <a href="index.php">← Back to Dashboard</a>
    </div>
  </div>
</div>

</body>
</html>
