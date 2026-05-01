<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/db.php';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
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
        } elseif (strlen($new) < MIN_PASSWORD_LENGTH) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $userId]);
            logActivity($db, $userId, 'password.change', 'user', $userId, "Password changed");
            $success = 'Password changed successfully.';
        }
    }
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/activity.php';

// Fetch last 10 login attempts
$stmt = $db->prepare("SELECT * FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$loginHistory = $stmt->fetchAll();

// Helper: parse browser from user agent
function parseBrowser(string $ua): string {
  if (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg')) return 'Chrome';
  if (str_contains($ua, 'Firefox')) return 'Firefox';
  if (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) return 'Safari';
  if (str_contains($ua, 'Edg')) return 'Edge';
  if (str_contains($ua, 'Opera') || str_contains($ua, 'OPR')) return 'Opera';
  return 'Unknown Browser';
}

// Helper: parse OS from user agent
function parseOS(string $ua): string {
  if (str_contains($ua, 'Windows NT')) return 'Windows';
  if (str_contains($ua, 'Mac OS X')) return 'macOS';
  if (str_contains($ua, 'Linux')) return 'Linux';
  if (str_contains($ua, 'Android')) return 'Android';
  if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
  return 'Unknown OS';
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
<link rel="stylesheet" href="css/style.css?v=3.2">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><div class="flex-1 flex flex-col">

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
          <label class="lbl">New Password * <span class="font-normal text-ak-muted">(min 8 chars)</span></label>
          <input class="inp" type="password" name="new_password" id="profile-new-password" placeholder="Enter new password" data-parsley-required="true" data-parsley-minlength="8">
          <div class="strength-bar-wrap" id="prof-strength-bars"><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div></div>
          <div class="strength-label" id="prof-strength-label"></div>
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

    <!-- Recent Activity -->
    <?php
    function timeAgo(string $datetime): string {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('Y-m-d', strtotime($datetime));
    }

    $myActivity = $db->prepare("SELECT * FROM activity_log WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $myActivity->execute([$userId]);
    $myActivity = $myActivity->fetchAll();
    ?>
    <div class="bg-ak-card border border-ak-border rounded-xl p-6 mb-5 animate-fade-in-up">
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-4">📋 Recent Activity</div>
      <div class="text-ak-muted text-xs mb-4">Your last 20 actions</div>
      <?php if (empty($myActivity)): ?>
        <div class="text-center text-ak-muted py-6">No activity recorded yet.</div>
      <?php else: ?>
      <div class="flex flex-col gap-2">
        <?php foreach ($myActivity as $a): ?>
        <div class="flex items-center gap-3 bg-ak-bg rounded-lg px-4 py-3 <?= getActivityBorder($a['action']) ?>">
          <div class="text-lg"><?= getActivityIcon($a['action']) ?></div>
          <div class="flex-1 min-w-0">
            <div class="text-ak-text text-sm"><?= h($a['description'] ?: $a['action']) ?></div>
            <div class="text-ak-muted text-[11px]"><?= timeAgo($a['created_at']) ?> · <?= h($a['action']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Login History -->
    <div class="bg-ak-card border border-ak-border rounded-xl mb-5 animate-fade-in-up overflow-hidden">
      <div class="px-6 py-4 border-b border-ak-border flex justify-between items-center">
        <div>
          <h3 class="text-ak-text font-bold">🔑 Login History</h3>
          <p class="text-ak-muted text-xs mt-0.5">Your last 10 login attempts</p>
        </div>
      </div>
      <?php if (empty($loginHistory)): ?>
        <div class="p-8 text-center text-ak-muted text-sm">No login history yet.</div>
      <?php else: ?>
      <div class="divide-y divide-ak-border">
        <?php foreach ($loginHistory as $log):
          $browser = parseBrowser($log['user_agent'] ?? '');
          $os = parseOS($log['user_agent'] ?? '');
          $isSuccess = $log['status'] === 'success';
        ?>
        <div class="flex items-center gap-4 px-6 py-3 <?= !$isSuccess ? 'bg-ak-red/5' : 'hover:bg-ak-infield/50' ?> transition-colors">
          <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 <?= $isSuccess ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/15 text-ak-red' ?>">
            <?= $isSuccess ? '✓' : '✗' ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-medium <?= $isSuccess ? 'text-ak-text' : 'text-ak-red' ?>">
                <?= $isSuccess ? 'Successful login' : 'Failed attempt' ?>
              </span>
              <span class="text-[10px] px-2 py-0.5 rounded-full font-mono <?= $isSuccess ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/15 text-ak-red' ?>">
                <?= h($log['status']) ?>
              </span>
            </div>
            <div class="text-ak-muted text-xs mt-0.5 flex gap-3 flex-wrap">
              <span>🌐 <?= h($log['ip_address'] ?? 'Unknown IP') ?></span>
              <span>💻 <?= h($browser) ?> on <?= h($os) ?></span>
            </div>
          </div>
          <div class="text-ak-muted text-xs shrink-0 font-mono text-right">
            <div><?= timeAgo($log['created_at']) ?></div>
            <div class="text-[10px] opacity-60"><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<?php require_once 'includes/footer.php'; ?>
<!-- Toast Container -->
<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none"></div>
<script src="js/app.js?v=3.2"></script>
<script>
<?php if (isset($_GET['success'])): ?>
showToast('Profile updated successfully', 'success');
<?php elseif (isset($_GET['error'])): ?>
showToast('Failed to update profile. Please try again.', 'error');
<?php elseif (isset($_GET['password_changed'])): ?>
showToast('Password changed successfully', 'success');
<?php elseif (isset($_GET['wrong_password'])): ?>
showToast('Current password is incorrect', 'error');
<?php endif; ?>
</script>
</body>
</html>
