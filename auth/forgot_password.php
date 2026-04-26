<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
session_start();

$db = db();
$error = '';
$resetLink = '';

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit($_SERVER['REMOTE_ADDR'], 5, 300)) {
        $error = 'Too many attempts. Please try again in 5 minutes.';
    } else {
    if (($_POST['_tok'] ?? '') !== $tok) {
        $error = 'Invalid request.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Please enter your email address.';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Delete any existing tokens for this email
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // Insert new token
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiresAt]);

                $resetLink = "http://localhostreset_password.php?token=" . $token;
            } else {
                // Don't reveal whether email exists — but for now show a generic message
                $error = 'If that email exists in our system, a reset link has been generated.';
            }
        }
    }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Forgot Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
<?php include __DIR__ . '/../css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md">

    <div class="text-center mb-8 animate-fade-in">
      <div class="text-4xl font-bold text-ak-gold tracking-tight">⚡ AuctionKai</div>
      <div class="text-ak-muted text-sm mt-2">Reset Your Password</div>
    </div>

    <div class="bg-ak-card border border-ak-border rounded-xl p-8 animate-fade-in-up">

      <?php if ($error): ?>
        <div class="bg-ak-red/15 text-ak-red px-4 py-3 rounded-lg text-sm mb-5"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($resetLink): ?>
        <div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm mb-5">
          <p class="mb-2 font-semibold">Password reset link generated:</p>
          <p class="break-all font-mono text-xs bg-ak-bg p-3 rounded"><?= h($resetLink) ?></p>
        </div>
        <div class="text-center mt-5">
          <a href="login.php" class="btn btn-gold w-full inline-block text-center">← Back to Login</a>
        </div>
      <?php else: ?>

      <form method="POST" action="forgot_password.php" data-parsley-validate>
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">

        <div class="mb-4">
          <label class="lbl">Email Address</label>
          <input class="inp" type="email" name="email" placeholder="Enter your registered email" data-parsley-required data-parsley-type="email">
        </div>

        <button class="btn btn-gold w-full" type="submit">Send Reset Link</button>
      </form>

      <div class="text-center mt-5 text-sm text-ak-muted">
        Remember your password? <a href="login.php" class="text-ak-gold hover:underline">Log in</a>
      </div>

      <?php endif; ?>

    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>