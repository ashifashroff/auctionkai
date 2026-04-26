<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/constants.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
$tok = $_SESSION['tok'];

$userName = $_SESSION['user_name'] ?? 'Admin';
$db = db();

// Stats
$totalUsers     = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAuctions  = (int)$db->query("SELECT COUNT(*) FROM auction")->fetchColumn();
$totalMembers   = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$totalVehicles  = (int)$db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();

// Users with counts
$users = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT a.id) as auction_count,
           COUNT(DISTINCT m.id) as member_count
    FROM users u
    LEFT JOIN auction a ON a.user_id = u.id
    LEFT JOIN members m ON m.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css?v=2.6">
<?php include '../css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<!-- Topbar -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai <span class="text-[10px] bg-ak-gold/20 text-ak-gold px-2 py-0.5 rounded ml-1 font-mono">ADMIN</span></div>
    <div class="text-ak-muted text-[11px]">Administration Panel</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
    <div><div class="text-ak-text text-sm font-semibold"><?= h($userName) ?></div><div class="text-ak-muted text-[10px]">Admin</div></div>
    <a href="../index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
    <a href="../auth/logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
  </div>
</div>

<!-- Content -->
<div class="p-7 max-w-[1400px] mx-auto">

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 text-center">
    <div class="text-ak-gold font-bold text-3xl font-mono"><?= $totalUsers ?></div>
    <div class="text-ak-muted text-xs mt-1 uppercase tracking-wider">Total Users</div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 text-center">
    <div class="text-ak-gold font-bold text-3xl font-mono"><?= $totalAuctions ?></div>
    <div class="text-ak-muted text-xs mt-1 uppercase tracking-wider">Total Auctions</div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 text-center">
    <div class="text-ak-gold font-bold text-3xl font-mono"><?= $totalMembers ?></div>
    <div class="text-ak-muted text-xs mt-1 uppercase tracking-wider">Total Members</div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 text-center">
    <div class="text-ak-gold font-bold text-3xl font-mono"><?= $totalVehicles ?></div>
    <div class="text-ak-muted text-xs mt-1 uppercase tracking-wider">Total Vehicles</div>
  </div>
</div>

<!-- Users Table -->
<h2 class="text-lg font-bold text-ak-gold mb-4">👥 User Management</h2>
<div class="bg-ak-card border border-ak-border rounded-xl overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-ak-border text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">
        <th class="px-4 py-3 text-left">ID</th>
        <th class="px-4 py-3 text-left">Username</th>
        <th class="px-4 py-3 text-left">Full Name</th>
        <th class="px-4 py-3 text-left">Email</th>
        <th class="px-4 py-3 text-left">Role</th>
        <th class="px-4 py-3 text-center">Auctions</th>
        <th class="px-4 py-3 text-center">Members</th>
        <th class="px-4 py-3 text-left">Registered</th>
        <th class="px-4 py-3 text-left">Status</th>
        <th class="px-4 py-3 text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): 
      $isSelf = (int)$u['id'] === $userId;
      $isDisabled = !empty($u['disabled']);
    ?>
      <tr class="border-b border-ak-border/50 hover:bg-ak-bg/50 transition-colors">
        <td class="px-4 py-3 font-mono text-ak-muted"><?= (int)$u['id'] ?></td>
        <td class="px-4 py-3 font-mono text-ak-text2"><?= h($u['username']) ?></td>
        <td class="px-4 py-3"><?= h($u['name']) ?></td>
        <td class="px-4 py-3 text-ak-muted"><?= h($u['email']) ?></td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $u['role'] === 'admin' ? 'bg-ak-gold/20 text-ak-gold' : 'bg-blue-500/20 text-blue-400' ?>"><?= h($u['role']) ?></span>
        </td>
        <td class="px-4 py-3 text-center font-mono"><?= (int)$u['auction_count'] ?></td>
        <td class="px-4 py-3 text-center font-mono"><?= (int)$u['member_count'] ?></td>
        <td class="px-4 py-3 text-ak-muted text-xs"><?= h(date('Y-m-d', strtotime($u['created_at']))) ?></td>
        <td class="px-4 py-3">
          <?php if ($isDisabled): ?>
            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-ak-red/20 text-ak-red">Disabled</span>
          <?php else: ?>
            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-ak-green/20 text-ak-green">Active</span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 text-center">
          <div class="flex gap-1.5 justify-center flex-wrap">
            <?php if ($u['role'] !== 'admin'): ?>
              <form method="POST" action="actions.php" style="display:inline">
                <input type="hidden" name="action" value="make_admin">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-sm text-[11px] bg-ak-gold/15 text-ak-gold border border-ak-gold/30 hover:bg-ak-gold/25" type="submit">Make Admin</button>
              </form>
            <?php elseif (!$isSelf): ?>
              <form method="POST" action="actions.php" style="display:inline">
                <input type="hidden" name="action" value="make_user">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-sm text-[11px] bg-blue-500/15 text-blue-400 border border-blue-500/30 hover:bg-blue-500/25" type="submit">Make User</button>
              </form>
            <?php endif; ?>

            <?php if (!$isDisabled && !$isSelf): ?>
              <form method="POST" action="actions.php" style="display:inline">
                <input type="hidden" name="action" value="disable_user">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-sm text-[11px] bg-yellow-500/15 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/25" type="submit">Disable</button>
              </form>
            <?php elseif ($isDisabled): ?>
              <form method="POST" action="actions.php" style="display:inline">
                <input type="hidden" name="action" value="enable_user">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-sm text-[11px] bg-ak-green/15 text-ak-green border border-ak-green/30 hover:bg-ak-green/25" type="submit">Enable</button>
              </form>
            <?php endif; ?>

            <?php if (!$isSelf && (int)$u['auction_count'] === 0): ?>
              <form method="POST" action="actions.php" style="display:inline" onsubmit="return confirm('Delete user <?= h(addslashes($u['name'])) ?>? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-sm text-[11px] bg-ak-red/15 text-ak-red border border-ak-red/30 hover:bg-ak-red/25" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Email / SMTP Configuration -->
<h2 class="text-lg font-bold text-ak-gold mb-4 mt-8">📧 Email / SMTP Configuration</h2>
<div class="bg-ak-card border border-ak-border rounded-xl p-6">
<?php if (defined('MAIL_ENABLED') && MAIL_ENABLED): ?>
  <div class="flex items-center gap-3 mb-4">
    <span class="text-[11px] font-bold px-3 py-1 rounded-full bg-ak-green/15 text-ak-green border border-ak-green/30">✓ Email Enabled</span>
  </div>
  <div class="text-ak-text2 text-sm">
    <div class="mb-1"><strong>Host:</strong> <?= h(MAIL_HOST) ?>:<?= (int)MAIL_PORT ?></div>
    <div class="mb-1"><strong>Username:</strong> <?= h(MAIL_USERNAME) ?></div>
    <div><strong>Password:</strong> ••••••••</div>
  </div>
<?php else: ?>
  <div class="flex items-center gap-3 mb-4">
    <span class="text-[11px] font-bold px-3 py-1 rounded-full bg-ak-red/15 text-ak-red border border-ak-red/30">✗ Email Disabled</span>
  </div>
  <div class="text-ak-text2 text-sm leading-relaxed">
    <p class="mb-3">To enable email sending:</p>
    <ol class="list-decimal list-inside space-y-1 text-ak-muted">
      <li>Get a <strong>Gmail App Password</strong> from Google Account → Security → 2-Step Verification → App Passwords</li>
      <li>Edit <code class="text-ak-gold bg-ak-bg px-1.5 py-0.5 rounded text-xs">config.php</code> and set:
        <ul class="list-disc list-inside ml-4 mt-1 space-y-0.5 text-xs">
          <li><code>MAIL_USERNAME</code> to your Gmail address</li>
          <li><code>MAIL_PASSWORD</code> to the App Password</li>
          <li><code>MAIL_FROM_EMAIL</code> to your Gmail address</li>
          <li><code>MAIL_ENABLED</code> to <code>true</code></li>
        </ul>
      </li>
      <li>Test by sending a statement from the Statements tab</li>
    </ol>
  </div>
<?php endif; ?>
</div>

</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<script src="../js/app.js?v=2.6"></script>
</body>
</html>
