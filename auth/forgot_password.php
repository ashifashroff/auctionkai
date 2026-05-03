<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/branding.php';
session_start();

$db = db();
$error = '';
$sent = false;

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
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Delete any existing tokens for this email
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // Insert hashed token (not plaintext)
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $tokenHash, $expiresAt]);

                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST']
                    . '/auth/reset_password.php?token=' . $token;

                // Send reset email using admin panel email settings
                $brand = loadBranding($db);
                $siteName = $brand['brand_name'] ?? 'AuctionKai';
                $subject = "Password Reset — {$siteName}";
                $htmlBody = "
                <div style=\"font-family:'Noto Sans JP',sans-serif;max-width:480px;margin:0 auto;padding:20px\">
                    <div style=\"text-align:center;margin-bottom:24px\">
                        <span style=\"font-size:28px;font-weight:700;color:#D4A84B\">⚡ {$siteName}</span>
                    </div>
                    <div style=\"background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:24px\">
                        <h2 style=\"margin:0 0 12px;font-size:18px;color:#111\">Password Reset Request</h2>
                        <p style=\"margin:0 0 16px;color:#444;font-size:14px\">We received a request to reset your password. Click the button below to set a new password. This link expires in 1 hour.</p>
                        <div style=\"text-align:center;margin:20px 0\">
                            <a href=\"{$resetLink}\" style=\"display:inline-block;background:#D4A84B;color:#fff;padding:12px 28px;border-radius:6px;font-weight:700;text-decoration:none;font-size:14px\">Reset Password</a>
                        </div>
                        <p style=\"margin:12px 0 0;color:#888;font-size:12px\">If the button doesn't work, copy this link:<br><span style=\"word-break:break-all;color:#555\">{$resetLink}</span></p>
                        <p style=\"margin:16px 0 0;color:#999;font-size:12px\">If you didn't request this, you can safely ignore this email.</p>
                    </div>
                    <div style=\"text-align:center;margin-top:16px;font-size:11px;color:#bbb\">© " . date('Y') . " {$siteName}</div>
                </div>";

                $result = sendEmail($db, $email, $user['name'] ?? '', $subject, $htmlBody);

                if (!$result['success']) {
                    // Email failed — fall back to showing link on screen
                    $error = 'Could not send email: ' . $result['message'];
                    // Still show the link as fallback
                    $sent = true;
                    $fallbackLink = $resetLink;
                } else {
                    $sent = true;
                }
            } else {
                // Don't reveal whether email exists — show same success message
                $sent = true;
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

      <?php if ($sent): ?>
        <div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm mb-5">
          <?php if (!empty($fallbackLink)): ?>
            <p class="mb-2 font-semibold">Email could not be sent, but here is your reset link:</p>
            <p class="break-all font-mono text-xs bg-ak-bg p-3 rounded text-ak-text"><?= h($fallbackLink) ?></p>
          <?php else: ?>
            <p class="font-semibold">✓ Reset link sent!</p>
            <p class="mt-1 text-ak-green/80">If that email exists in our system, you'll receive a password reset link shortly. Check your inbox and spam folder.</p>
          <?php endif; ?>
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
