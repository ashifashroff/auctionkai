<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = db();
$error = '';
$success = '';
$showRegister = isset($_GET['register']);

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    if (($_POST['_tok'] ?? '') !== $tok) { $error = 'Invalid request.'; }
    else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Brute force protection
    $attemptKey = 'login_attempts_' . $username;
    if (!isset($_SESSION[$attemptKey])) $_SESSION[$attemptKey] = ['count' => 0, 'last' => 0];
    $att = &$_SESSION[$attemptKey];
    if ($att['count'] >= 5 && (time() - $att['last']) < 30) {
        $remaining = 30 - (time() - $att['last']);
        $error = "Too many failed attempts. Try again in {$remaining}s.";
    }
    elseif ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check suspended status
            $userStatus = $user['status'] ?? 'active';
            if ($userStatus === 'suspended') {
                $suspendedUntil = $user['suspended_until'] ?? null;
                if ($suspendedUntil && strtotime($suspendedUntil) > time()) {
                    $untilFormatted = date('M j, Y g:i A', strtotime($suspendedUntil));
                    $reason = $user['suspend_reason'] ?? 'No reason provided';
                    $error = "Your account is suspended until {$untilFormatted}. Reason: {$reason}";
                } else {
                    // Suspension expired — reactivate
                    $db->prepare("UPDATE users SET status='active', suspended_until=NULL, suspend_reason=NULL WHERE id=?")->execute([(int)$user['id']]);
                    $userStatus = 'active';
                }
            }
            if ($userStatus === 'restricted') {
                $error = 'Your account has been restricted. Contact an administrator.';
            }
            if ($userStatus === 'active') {
                unset($_SESSION[$attemptKey]);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_username'] = $user['username'];
                session_regenerate_id(true);
                header('Location: ../index.php');
                exit;
            }
        } else {
            $att['count']++;
            $att['last'] = time();
            $error = 'Invalid username or password.';
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    if (($_POST['_tok'] ?? '') !== $tok) { $error = 'Invalid request.'; }
    else {
    $username = trim($_POST['username'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($username === '' || $name === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } elseif ($email !== '') {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email address already registered.';
            }
        }
        if (empty($error)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?,?,?,?,?)");
            $stmt->execute([$username, $hash, $name, $email, 'user']);
            $success = 'Account created! You can now log in.';
            $showRegister = false;
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
<title>AuctionKai — <?= $showRegister ? 'Register' : 'Login' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
<?php include __DIR__ . '/../css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8 animate-fade-in">
      <div class="text-4xl font-bold text-ak-gold tracking-tight">⚡ AuctionKai</div>
      <div class="text-ak-muted text-sm mt-2"><?= $showRegister ? 'Create Your Account' : 'Settlement Management System' ?></div>
    </div>

    <!-- Card -->
    <div class="bg-ak-card border border-ak-border rounded-xl p-8 animate-fade-in-up">

      <?php if (isset($_GET['reset'])): ?>
        <div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm mb-5">Password reset successfully! You can now log in.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="bg-ak-red/15 text-ak-red px-4 py-3 rounded-lg text-sm mb-5"><?= h($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm mb-5"><?= h($success) ?></div>
      <?php endif; ?>

      <?php if ($showRegister): ?>
      <!-- Register -->
      <form method="POST" action="login.php?register=1" data-parsley-validate>
        <input type="hidden" name="form" value="register">
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">

        <div class="mb-4">
          <label class="lbl">Full Name *</label>
          <input class="inp" name="name" placeholder="e.g. Ahmad Hassan" value="<?= h($_POST['name'] ?? '') ?>" data-parsley-required="true">
        </div>

        <div class="mb-4">
          <label class="lbl">Username *</label>
          <input class="inp" name="username" placeholder="Choose a username" value="<?= h($_POST['username'] ?? '') ?>" data-parsley-required data-parsley-minlength="3">
        </div>

        <div class="mb-4">
          <label class="lbl">Email</label>
          <input class="inp" type="email" name="email" placeholder="email@example.com" value="<?= h($_POST['email'] ?? '') ?>" data-parsley-type="email">
        </div>

        <div class="mb-4">
          <label class="lbl">Password * <span class="font-normal text-ak-muted">(min 8 chars)</span></label>
          <input class="inp" type="password" name="password" id="register-password" placeholder="••••••" data-parsley-required data-parsley-minlength="8">
          <div class="strength-bar-wrap" id="reg-strength-bars"><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div></div>
          <div class="strength-label" id="reg-strength-label"></div>
        </div>

        <div class="mb-5">
          <label class="lbl">Confirm Password *</label>
          <input class="inp" type="password" name="confirm" placeholder="••••••" data-parsley-required="true">
        </div>

        <button class="btn btn-gold w-full" type="submit">Create Account</button>
      </form>

      <div class="text-center mt-5 text-sm text-ak-muted">
        Already have an account? <a href="login.php" class="text-ak-gold hover:underline">Log in</a>
      </div>

      <?php else: ?>
      <!-- Login -->
      <form method="POST" action="login.php" id="loginForm" data-parsley-validate>
        <input type="hidden" name="form" value="login">
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">

        <div class="mb-4">
          <label class="lbl">Username</label>
          <input class="inp" name="username" placeholder="Enter username" value="<?= h($_POST['username'] ?? '') ?>" data-parsley-required data-parsley-minlength="3" autofocus>
        </div>

        <div class="mb-5">
          <label class="lbl">Password</label>
          <input class="inp" type="password" name="password" placeholder="••••••" data-parsley-required data-parsley-minlength="8">
        </div>

        <button class="btn btn-gold w-full" type="submit">Log In</button>
      </form>

      <!-- Forgot Password -->
      <div id="forgotToggle" class="text-center mt-4">
        <a href="#" onclick="document.getElementById('forgotForm').style.display='block';document.getElementById('forgotToggle').style.display='none';return false;" class="text-ak-gold hover:underline text-sm">Forgot password?</a>
      </div>
      <div id="forgotForm" style="display:none">
        <form method="POST" action="forgot_password.php" data-parsley-validate class="mt-4 pt-4 border-t border-ak-border">
          <input type="hidden" name="_tok" value="<?= h($tok) ?>">
          <div class="mb-4">
            <label class="lbl">Email Address</label>
            <input class="inp" type="email" name="email" placeholder="Enter your registered email" data-parsley-required data-parsley-type="email">
          </div>
          <button class="btn btn-gold w-full" type="submit">Send Reset Link</button>
          <div class="text-center mt-3">
            <a href="#" onclick="document.getElementById('forgotForm').style.display='none';document.getElementById('forgotToggle').style.display='block';return false;" class="text-ak-muted text-sm hover:underline">← Back to login</a>
          </div>
        </form>
      </div>

      <div class="text-center mt-5 text-sm text-ak-muted">
        Don't have an account? <a href="login.php?register=1" class="text-ak-gold hover:underline">Register</a>
      </div>
      <?php endif; ?>

    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<!-- Toast Container -->
<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none"></div>
<script src="../js/app.js"></script>
<script>
<?php if (!empty($error)): ?>
showToast('<?= addslashes($error) ?>', 'error');
<?php endif; ?>
<?php if (!empty($success)): ?>
showToast('<?= addslashes($success) ?>', 'success');
<?php endif; ?>
</script>
</body>
</html>
