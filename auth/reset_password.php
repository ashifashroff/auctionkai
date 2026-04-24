<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

$db = db();
$error = '';
$success = '';
$validToken = false;
$tokenEmail = '';

$token = trim($_GET['token'] ?? '');

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];

// Validate token
if ($token !== '') {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    if ($reset) {
        $validToken = true;
        $tokenEmail = $reset['email'];
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'No reset token provided.';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (($_POST['_tok'] ?? '') !== $tok) {
        $error = 'Invalid request.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if ($password === '' || $confirm === '') {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $tokenEmail]);

            // Delete used token
            $db->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);

            header('Location: /auctionkai/auth/login.php?reset=1');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Reset Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/auctionkai/css/style.css">
<?php include __DIR__ . '/../css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md">

    <div class="text-center mb-8 animate-fade-in">
      <div class="text-4xl font-bold text-ak-gold tracking-tight">⚡ AuctionKai</div>
      <div class="text-ak-muted text-sm mt-2">Set New Password</div>
    </div>

    <div class="bg-ak-card border border-ak-border rounded-xl p-8 animate-fade-in-up">

      <?php if ($error): ?>
        <div class="bg-ak-red/15 text-ak-red px-4 py-3 rounded-lg text-sm mb-5"><?= h($error) ?></div>
        <?php if (!$validToken): ?>
          <div class="text-center mt-3">
            <a href="/auctionkai/auth/forgot_password.php" class="text-ak-gold hover:underline text-sm">Request a new reset link</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($validToken): ?>
      <form method="POST" action="/auctionkai/auth/reset_password.php?token=<?= h($token) ?>" data-parsley-validate>
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">

        <div class="mb-4">
          <label class="lbl">New Password <span class="font-normal text-ak-muted">(min 8 chars)</span></label>
          <input class="inp" type="password" name="password" placeholder="••••••••" data-parsley-required data-parsley-minlength="8">
        </div>

        <div class="mb-5">
          <label class="lbl">Confirm New Password</label>
          <input class="inp" type="password" name="confirm" placeholder="••••••••" data-parsley-required data-parsley-equalto="[name='password']">
        </div>

        <button class="btn btn-gold w-full" type="submit">Reset Password</button>
      </form>
      <?php endif; ?>

      <div class="text-center mt-5 text-sm text-ak-muted">
        <a href="/auctionkai/auth/login.php" class="text-ak-gold hover:underline">← Back to Login</a>
      </div>

    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>