<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

if (!$user) {
    session_destroy();
    header('Location: auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?, email=? WHERE id=?");
            $stmt->execute([$name, $email, $userId]);
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully.';
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

require_once __DIR__ . '/includes/helpers.php';
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
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<div class="min-h-screen flex items-start justify-center pt-16 px-4">
  <div class="w-full max-w-lg">

    <!-- Header -->
    <div class="text-center mb-8 animate-fade-in">
      <div class="w-16 h-16 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-2xl mx-auto mb-4"><?= mb_strtoupper(mb_substr($user['name'], 0, 1)) ?></div>
      <h1 class="text-2xl font-bold text-ak-gold">⚡ AuctionKai</h1>
      <p class="text-ak-muted text-sm mt-1">Account Settings</p>
    </div>

    <!-- Messages -->
    <?php if ($error): ?>
      <div class="bg-ak-red/15 text-ak-red px-4 py-3 rounded-lg text-sm mb-4 animate-fade-in"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm mb-4 animate-fade-in"><?= h($success) ?></div>
    <?php endif; ?>

    <!-- Profile Info Card -->
    <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5 animate-fade-in-up">
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-5">Profile Information</div>
      <form method="POST" action="profile.php" data-parsley-validate>

        <div class="mb-4">
          <label class="lbl">Username</label>
          <input class="inp opacity-50 cursor-not-allowed" value="<?= h($user['username']) ?>" disabled>
          <div class="text-[10px] text-ak-muted mt-1">Username cannot be changed</div>
        </div>

        <div class="mb-4">
          <label class="lbl">Full Name *</label>
          <input class="inp" name="name" value="<?= h($user['name']) ?>" data-parsley-required="true">
        </div>

        <div class="mb-4">
          <label class="lbl">Email</label>
          <input class="inp" type="email" name="email" value="<?= h($user['email']) ?>" placeholder="email@example.com" data-parsley-type="email">
        </div>

        <div class="mb-5">
          <label class="lbl">Role</label>
          <input class="inp opacity-50 cursor-not-allowed capitalize" value="<?= h($user['role']) ?>" disabled>
        </div>

        <button class="btn btn-gold w-full" type="submit">Save Changes</button>
      </form>
    </div>

    <!-- Password Card -->
    <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5 animate-fade-in-up">
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-5">Change Password</div>
      <form method="POST" action="profile.php" data-parsley-validate>
        <input type="hidden" name="form" value="change_password">

        <div class="mb-4">
          <label class="lbl">Current Password *</label>
          <input class="inp" type="password" name="current_password" placeholder="Enter current password" data-parsley-required="true">
        </div>

        <div class="mb-4">
          <label class="lbl">New Password * <span class="font-normal text-ak-muted">(min 6 chars)</span></label>
          <input class="inp" type="password" name="new_password" placeholder="Enter new password" data-parsley-required="true">
        </div>

        <div class="mb-5">
          <label class="lbl">Confirm New Password *</label>
          <input class="inp" type="password" name="confirm_password" placeholder="Confirm new password" data-parsley-required="true">
        </div>

        <button class="btn btn-gold w-full" type="submit">Change Password</button>
      </form>
    </div>

    <!-- Back Link -->
    <div class="text-center mt-5 animate-fade-in">
      <a href="index.php" class="text-ak-muted text-sm hover:text-ak-gold transition-colors">← Back to Dashboard</a>
    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<?php require_once 'includes/footer.php'; ?>
</body>
</html>
