<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/settings.php';

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
$tok = $_SESSION['tok'];

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Admin';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'admin';
$db = db();
$settings = loadSettings($db);

$totalUsers     = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAuctions  = (int)$db->query("SELECT COUNT(*) FROM auction")->fetchColumn();
$totalMembers   = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
$totalVehicles  = (int)$db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();

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

$adminStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$adminStmt->execute([$userId]);
$admin = $adminStmt->fetch();

$tab = $_GET['tab'] ?? 'users';
$tabs = [
    'users'    => ['icon' => '👥', 'label' => 'Users'],
    'create'   => ['icon' => '➕', 'label' => 'Create User'],
    'email'    => ['icon' => '📧', 'label' => 'Email Settings'],
    'settings' => ['icon' => '⚙', 'label' => 'Admin Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css?v=2.7">
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

<!-- Stats -->
<div class="p-7 pb-0 max-w-[1400px] mx-auto">
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
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
</div>

<!-- Tabs -->
<div class="bg-ak-bg border-b border-ak-border px-7 flex items-center gap-1">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="px-5 py-3 text-sm font-semibold transition-all duration-200 border-b-2 <?= $tab === $key ? 'text-ak-gold border-ak-gold' : 'text-ak-muted border-transparent hover:text-ak-text2' ?>" href="?tab=<?= $key ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="ml-auto text-xs text-ak-muted flex gap-4">
    <span><b class="text-ak-text"><?= count($users) ?></b> total users</span>
  </div>
</div>

<!-- Messages -->
<?php if (!empty($_SESSION['admin_success'])): ?>
<div class="px-7 pt-4"><div class="bg-ak-green/15 text-ak-green px-4 py-3 rounded-lg text-sm animate-fade-in"><?= h($_SESSION['admin_success']); unset($_SESSION['admin_success']); ?></div></div>
<?php endif; ?>
<?php if (!empty($_SESSION['admin_error'])): ?>
<div class="px-7 pt-4"><div class="bg-ak-red/15 text-ak-red px-4 py-3 rounded-lg text-sm animate-fade-in"><?= h($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div></div>
<?php endif; ?>

<!-- Content -->
<div class="p-7 max-w-[1400px] mx-auto animate-fade-in">

<?php if ($tab === 'users'): ?>
<h2 class="text-lg font-bold mb-5">All Registered Users</h2>
<div class="bg-ak-card rounded-xl border border-ak-border overflow-x-auto">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-ak-border text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">
        <th class="px-4 py-3 text-left"></th>
        <th class="px-4 py-3 text-left">Username</th>
        <th class="px-4 py-3 text-left">Full Name</th>
        <th class="px-4 py-3 text-left">Email</th>
        <th class="px-4 py-3 text-left">Role</th>
        <th class="px-4 py-3 text-center">Auctions</th>
        <th class="px-4 py-3 text-center">Members</th>
        <th class="px-4 py-3 text-left">Status</th>
        <th class="px-4 py-3 text-left">Joined</th>
        <th class="px-4 py-3 text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
      $isSelf = (int)$u['id'] === $userId;
      $st = $u['status'] ?? 'active';
      $isDisabled = !empty($u['disabled']);
      if ($isDisabled) $st = 'disabled';
      $statusColors = ['active'=>'bg-ak-green/20 text-ak-green','suspended'=>'bg-yellow-500/20 text-yellow-400','restricted'=>'bg-ak-red/20 text-ak-red','disabled'=>'bg-ak-red/20 text-ak-red'];
    ?>
      <tr class="border-b border-ak-border/50 hover:bg-ak-bg/50 transition-colors">
        <td class="px-4 py-3"><div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?></div></td>
        <td class="px-4 py-3 font-mono text-ak-text2"><?= h($u['username']) ?></td>
        <td class="px-4 py-3"><?= h($u['name']) ?></td>
        <td class="px-4 py-3 text-ak-muted"><?= h($u['email']) ?></td>
        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $u['role']==='admin'?'bg-ak-gold/20 text-ak-gold':'bg-blue-500/20 text-blue-400' ?>"><?= h($u['role']) ?></span></td>
        <td class="px-4 py-3 text-center font-mono"><?= (int)$u['auction_count'] ?></td>
        <td class="px-4 py-3 text-center font-mono"><?= (int)$u['member_count'] ?></td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $statusColors[$st]??$statusColors['active'] ?>"><?= h($st) ?></span>
          <?php if ($st==='suspended'&&!empty($u['suspended_until'])): ?><span class="text-[10px] text-ak-muted ml-1">until <?= h(date('M j, Y',strtotime($u['suspended_until']))) ?></span><?php endif; ?>
        </td>
        <td class="px-4 py-3 text-ak-muted text-xs"><?= h(date('M j, Y',strtotime($u['created_at']))) ?></td>
        <td class="px-4 py-3 text-center">
          <div class="flex gap-1.5 justify-center flex-wrap">
            <?php if (!$isSelf): ?>
              <form method="POST" action="actions.php" style="display:inline"><input type="hidden" name="action" value="login_as"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><button class="btn btn-dark btn-sm text-[11px]" type="submit">Login As</button></form>
            <?php endif; ?>
            <button class="btn btn-dark btn-sm text-[11px]" onclick="openEditUserModal(<?= (int)$u['id'] ?>,'<?= h(addslashes($u['username'])) ?>','<?= h(addslashes($u['name'])) ?>','<?= h(addslashes($u['email'])) ?>','<?= h($u['role']) ?>')">Edit</button>
            <?php if (!$isSelf): ?>
              <?php if ($st==='suspended'): ?>
                <form method="POST" action="actions.php" style="display:inline"><input type="hidden" name="action" value="unsuspend_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><button class="btn btn-sm text-[11px] bg-ak-green/20 text-ak-green border border-ak-green/30 hover:bg-ak-green/30" type="submit">Reactivate</button></form>
              <?php else: ?>
                <button class="btn btn-sm text-[11px] bg-yellow-500/15 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/25" onclick="openSuspendModal(<?= (int)$u['id'] ?>,'<?= h(addslashes($u['name'])) ?>')">Suspend</button>
              <?php endif; ?>
              <?php if ($isDisabled): ?>
                <form method="POST" action="actions.php" style="display:inline"><input type="hidden" name="action" value="enable_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><button class="btn btn-sm text-[11px] bg-ak-green/15 text-ak-green border border-ak-green/30 hover:bg-ak-green/25" type="submit">Enable</button></form>
              <?php else: ?>
                <form method="POST" action="actions.php" style="display:inline"><input type="hidden" name="action" value="disable_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><button class="btn btn-sm text-[11px] bg-yellow-500/15 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/25" type="submit">Disable</button></form>
              <?php endif; ?>
              <form method="POST" action="actions.php" style="display:inline" onsubmit="return confirm('Delete user <?= h(addslashes($u['name'])) ?>? This will also delete all their data.')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><button class="btn btn-sm text-[11px] bg-ak-red/15 text-ak-red border border-ak-red/30 hover:bg-ak-red/25" type="submit">Delete</button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'create'): ?>
<h2 class="text-lg font-bold mb-5">Create New User</h2>
<div class="bg-ak-card border border-ak-border rounded-xl p-7 max-w-lg mx-auto animate-fade-in-up">
  <form method="POST" action="actions.php" data-parsley-validate>
    <input type="hidden" name="action" value="create_user"><input type="hidden" name="_tok" value="<?= h($tok) ?>">
    <div class="mb-4"><label class="lbl">Username *</label><input class="inp" name="username" placeholder="Choose a username" data-parsley-required="true"></div>
    <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" name="name" placeholder="e.g. Ahmad Hassan" data-parsley-required="true"></div>
    <div class="mb-4"><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="email@example.com" data-parsley-type="email"></div>
    <div class="mb-4"><label class="lbl">Password * <span class="font-normal text-ak-muted">(min 6 chars)</span></label><input class="inp" type="password" name="password" placeholder="••••••" data-parsley-required="true"></div>
    <div class="mb-5"><label class="lbl">Role</label><select class="inp" name="role"><option value="user">User</option><option value="admin">Admin</option></select></div>
    <button class="btn btn-gold w-full" type="submit">+ Create User</button>
  </form>
</div>

<?php elseif ($tab === 'email'): ?>
<div id="email-settings">
<h2 class="text-lg font-bold text-ak-gold mb-4">📧 Email Settings</h2>
<div class="mb-5">
<?php if (($settings['mail_enabled'] ?? '0') === '1'): ?>
  <span class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-ak-green/15 text-ak-green border border-ak-green/30">✓ Email Active</span>
<?php else: ?>
  <span class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-ak-red/15 text-ak-red border border-ak-red/30">✗ Email Disabled</span>
<?php endif; ?>
</div>
<form method="POST" action="actions.php" id="emailSettingsForm">
<input type="hidden" name="action" value="save_email_settings"><input type="hidden" name="_tok" value="<?= h($tok) ?>"><input type="hidden" name="mail_provider" id="mail_provider_input" value="<?= h($settings['mail_provider'] ?? 'smtp') ?>">
<div class="mb-6">
  <label class="lbl mb-3">Mail Provider</label>
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="provider-card cursor-pointer bg-ak-card border border-ak-border rounded-xl p-4 text-center transition-all duration-200 hover:border-ak-gold/50" data-provider="servermail" onclick="selectProvider('servermail')"><div class="text-2xl mb-1">🖥</div><div class="text-sm font-semibold text-ak-text">Server Mail</div><div class="text-[10px] text-ak-muted">PHP mail()</div></div>
    <div class="provider-card cursor-pointer bg-ak-card border border-ak-border rounded-xl p-4 text-center transition-all duration-200 hover:border-ak-gold/50" data-provider="smtp" onclick="selectProvider('smtp')"><div class="text-2xl mb-1">📧</div><div class="text-sm font-semibold text-ak-text">Custom SMTP</div><div class="text-[10px] text-ak-muted">Any host</div></div>
    <div class="provider-card cursor-pointer bg-ak-card border border-ak-border rounded-xl p-4 text-center transition-all duration-200 hover:border-ak-gold/50" data-provider="gmail" onclick="selectProvider('gmail')"><div class="text-2xl mb-1">G</div><div class="text-sm font-semibold text-ak-text">Gmail SMTP</div><div class="text-[10px] text-ak-muted">App Password</div></div>
    <div class="provider-card cursor-pointer bg-ak-card border border-ak-border rounded-xl p-4 text-center transition-all duration-200 hover:border-ak-gold/50" data-provider="xserver" onclick="selectProvider('xserver')"><div class="text-2xl mb-1">X</div><div class="text-sm font-semibold text-ak-text">Xserver</div><div class="text-[10px] text-ak-muted">Japan hosting</div></div>
    <div class="provider-card cursor-pointer bg-ak-card border border-ak-border rounded-xl p-4 text-center transition-all duration-200 hover:border-ak-gold/50" data-provider="sakura" onclick="selectProvider('sakura')"><div class="text-2xl mb-1">🌸</div><div class="text-sm font-semibold text-ak-text">Sakura</div><div class="text-[10px] text-ak-muted">Internet</div></div>
  </div>
</div>
<div class="provider-fields" data-for="servermail" style="display:none"><div class="bg-ak-bg rounded-lg p-4 mb-4 text-ak-muted text-sm">💡 Uses your hosting server's built-in mail. No SMTP credentials needed. Works on Xserver, Sakura, ConoHa automatically.</div></div>
<div class="provider-fields" data-for="gmail" style="display:none">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div><label class="lbl">Gmail Address</label><input class="inp" name="mail_username" value="<?= h($settings['mail_username'] ?? '') ?>" placeholder="you@gmail.com"></div>
    <div><label class="lbl">App Password</label><div class="relative"><input class="inp pr-10" type="password" name="mail_password" id="gmail_password" placeholder="xxxx xxxx xxxx xxxx"><button type="button" onclick="togglePasswordVisibility('gmail_password')" class="absolute right-2 top-1/2 -translate-y-1/2 text-ak-muted hover:text-ak-text text-xs">👁</button></div></div>
  </div>
  <div class="bg-ak-bg rounded-lg p-4 mb-4 text-ak-muted text-sm">💡 Use Gmail App Password — not your login password. Generate at: Google Account → Security → 2-Step Verification → App Passwords</div>
</div>
<div class="provider-fields" data-for="xserver" style="display:none">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div><label class="lbl">SMTP Host</label><input class="inp" name="mail_host" value="<?= h($settings['mail_host'] ?? '') ?>" placeholder="sv12345.xserver.jp"></div>
    <div><label class="lbl">Username (email)</label><input class="inp" name="mail_username" value="<?= h($settings['mail_username'] ?? '') ?>" placeholder="info@yourdomain.com"></div>
    <div><label class="lbl">Password</label><div class="relative"><input class="inp pr-10" type="password" name="mail_password" id="xserver_password" placeholder="Email password"><button type="button" onclick="togglePasswordVisibility('xserver_password')" class="absolute right-2 top-1/2 -translate-y-1/2 text-ak-muted hover:text-ak-text text-xs">👁</button></div></div>
  </div>
  <div class="bg-ak-bg rounded-lg p-4 mb-4 text-ak-muted text-sm">💡 Find your SMTP host in Xserver panel → Mail Settings</div>
</div>
<div class="provider-fields" data-for="sakura" style="display:none">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div><label class="lbl">SMTP Host</label><input class="inp" name="mail_host" value="<?= h($settings['mail_host'] ?? '') ?>" placeholder="mail.yourdomain.sakura.ne.jp"></div>
    <div><label class="lbl">Username</label><input class="inp" name="mail_username" value="<?= h($settings['mail_username'] ?? '') ?>" placeholder="info@yourdomain.sakura.ne.jp"></div>
    <div><label class="lbl">Password</label><div class="relative"><input class="inp pr-10" type="password" name="mail_password" id="sakura_password" placeholder="Email password"><button type="button" onclick="togglePasswordVisibility('sakura_password')" class="absolute right-2 top-1/2 -translate-y-1/2 text-ak-muted hover:text-ak-text text-xs">👁</button></div></div>
  </div>
</div>
<div class="provider-fields" data-for="smtp" style="display:none">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div><label class="lbl">SMTP Host</label><input class="inp" name="mail_host" value="<?= h($settings['mail_host'] ?? '') ?>" placeholder="smtp.example.com"></div>
    <div><label class="lbl">SMTP Port</label><input class="inp" name="mail_port" value="<?= h($settings['mail_port'] ?? '587') ?>" placeholder="587"></div>
    <div><label class="lbl">Encryption</label><div class="flex gap-4 items-center mt-1"><label class="flex items-center gap-2 text-sm text-ak-text2 cursor-pointer"><input type="radio" name="mail_encryption" value="tls" <?= ($settings['mail_encryption'] ?? 'tls')==='tls'?'checked':'' ?> class="accent-ak-gold"> TLS</label><label class="flex items-center gap-2 text-sm text-ak-text2 cursor-pointer"><input type="radio" name="mail_encryption" value="ssl" <?= ($settings['mail_encryption'] ?? '')==='ssl'?'checked':'' ?> class="accent-ak-gold"> SSL</label></div></div>
    <div><label class="lbl">Username</label><input class="inp" name="mail_username" value="<?= h($settings['mail_username'] ?? '') ?>" placeholder="user@example.com"></div>
    <div><label class="lbl">Password</label><div class="relative"><input class="inp pr-10" type="password" name="mail_password" id="smtp_password" placeholder="SMTP password"><button type="button" onclick="togglePasswordVisibility('smtp_password')" class="absolute right-2 top-1/2 -translate-y-1/2 text-ak-muted hover:text-ak-text text-xs">👁</button></div></div>
  </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 mt-4">
  <div><label class="lbl">From Name</label><input class="inp" name="mail_from_name" value="<?= h($settings['mail_from_name'] ?? 'AuctionKai Settlement System') ?>" placeholder="AuctionKai Settlement System"></div>
  <div><label class="lbl">From Email</label><input class="inp" name="mail_from_email" value="<?= h($settings['mail_from_email'] ?? '') ?>" placeholder="noreply@yourdomain.com"></div>
</div>
<div class="flex items-center gap-3 mb-6 bg-ak-bg rounded-lg p-4">
  <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="mail_enabled" value="1" <?= ($settings['mail_enabled'] ?? '0')==='1'?'checked':'' ?> class="w-5 h-5 accent-ak-gold rounded"><span class="text-sm font-semibold text-ak-text">Enable Email Sending</span></label>
</div>
<div class="flex gap-3 flex-wrap">
  <button class="btn btn-gold" type="submit">💾 Save Settings</button>
  <button type="button" class="btn btn-dark" onclick="openTestEmailModal()">🧪 Test Email</button>
</div>
</form>
</div>
<div id="testEmailModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[420px] p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5"><h3 class="text-ak-gold text-lg font-bold">🧪 Test Email</h3><button class="text-ak-muted text-2xl hover:text-ak-text" onclick="closeTestEmailModal()">×</button></div>
    <form method="POST" action="actions.php"><input type="hidden" name="action" value="test_email"><input type="hidden" name="_tok" value="<?= h($tok) ?>">
      <div class="mb-4"><label class="lbl">Send test email to</label><input class="inp" type="email" name="test_email" value="<?= h($userEmail) ?>" placeholder="admin@example.com" required></div>
      <button class="btn btn-gold w-full" type="submit">Send Test Email</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'settings'): ?>
<h2 class="text-lg font-bold mb-5">Admin Settings</h2>
<div class="bg-ak-card border border-ak-border rounded-xl p-7 max-w-lg mx-auto animate-fade-in-up">
  <form method="POST" action="actions.php" data-parsley-validate>
    <input type="hidden" name="action" value="admin_settings"><input type="hidden" name="_tok" value="<?= h($tok) ?>">
    <div class="mb-4"><label class="lbl">Username *</label><input class="inp" name="username" value="<?= h($admin['username'] ?? '') ?>" data-parsley-required="true"></div>
    <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" name="name" value="<?= h($admin['name'] ?? '') ?>" data-parsley-required="true"></div>
    <div class="mb-4"><label class="lbl">Email</label><input class="inp" type="email" name="email" value="<?= h($admin['email'] ?? '') ?>" data-parsley-type="email"></div>
    <div class="border-t border-ak-border my-5 pt-5">
      <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase mb-3">Change Password</div>
      <div class="mb-4"><label class="lbl">Current Password</label><input class="inp" type="password" name="current_password" placeholder="Enter current password to change"></div>
      <div class="mb-4"><label class="lbl">New Password <span class="font-normal text-ak-muted">(min 6 chars, leave blank to keep current)</span></label><input class="inp" type="password" name="new_password" placeholder="••••••"></div>
    </div>
    <button class="btn btn-gold w-full" type="submit">Save Settings</button>
  </form>
</div>
<?php endif; ?>

</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[500px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5"><h3 class="text-ak-gold text-lg font-bold">Edit User</h3><button class="text-ak-muted text-2xl hover:text-ak-text" onclick="closeEditUserModal()">×</button></div>
    <form method="POST" action="actions.php" data-parsley-validate>
      <input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" id="eu_id"><input type="hidden" name="_tok" value="<?= h($tok) ?>">
      <div class="mb-4"><label class="lbl">Username *</label><input class="inp" name="username" id="eu_username" data-parsley-required="true"></div>
      <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" name="name" id="eu_name" data-parsley-required="true"></div>
      <div class="mb-4"><label class="lbl">Email</label><input class="inp" type="email" name="email" id="eu_email" data-parsley-type="email"></div>
      <div class="mb-5"><label class="lbl">Role</label><select class="inp" name="role" id="eu_role"><option value="user">User</option><option value="admin">Admin</option></select></div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border"><button type="button" class="btn btn-dark btn-sm" onclick="closeEditUserModal()">Cancel</button><button type="submit" class="btn btn-gold btn-sm">Save Changes</button></div>
    </form>
  </div>
</div>

<!-- Suspend User Modal -->
<div id="suspendModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[420px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5"><h3 class="text-yellow-400 text-lg font-bold">⏸ Suspend User</h3><button class="text-ak-muted text-2xl hover:text-ak-text" onclick="closeSuspendModal()">×</button></div>
    <form method="POST" action="actions.php" data-parsley-validate>
      <input type="hidden" name="action" value="suspend_user"><input type="hidden" name="user_id" id="sus_id"><input type="hidden" name="_tok" value="<?= h($tok) ?>">
      <div class="mb-2 text-ak-muted text-sm">Suspending: <b class="text-ak-text" id="sus_name"></b></div>
      <div class="mb-4"><label class="lbl">Reason</label><input class="inp" name="reason" placeholder="e.g. Policy violation" data-parsley-required="true"></div>
      <div class="mb-5"><label class="lbl">Duration (days)</label><input class="inp font-mono" type="number" name="days" value="7" data-parsley-type="number" data-parsley-min="1"></div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border"><button type="button" class="btn btn-dark btn-sm" onclick="closeSuspendModal()">Cancel</button><button type="submit" class="btn btn-sm bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/30">Suspend</button></div>
    </form>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<script src="../js/app.js?v=2.7"></script>
<script>
// Provider selector
const currentProvider = '<?= h($settings["mail_provider"] ?? "smtp") ?>';
function selectProvider(provider) {
  document.getElementById('mail_provider_input').value = provider;
  document.querySelectorAll('.provider-card').forEach(c => { c.classList.remove('border-ak-gold','bg-ak-gold/10'); c.classList.add('border-ak-border'); });
  const selected = document.querySelector('.provider-card[data-provider="'+provider+'"]');
  if (selected) { selected.classList.remove('border-ak-border'); selected.classList.add('border-ak-gold','bg-ak-gold/10'); }
  document.querySelectorAll('.provider-fields').forEach(f => f.style.display = 'none');
  const fields = document.querySelector('.provider-fields[data-for="'+provider+'"]');
  if (fields) fields.style.display = 'block';
}
function togglePasswordVisibility(id) { const el = document.getElementById(id); if (el) el.type = el.type === 'password' ? 'text' : 'password'; }
function openTestEmailModal() { document.getElementById('testEmailModal').style.display = 'flex'; }
function closeTestEmailModal() { document.getElementById('testEmailModal').style.display = 'none'; }
function openEditUserModal(id, username, name, email, role) {
  document.getElementById('eu_id').value = id; document.getElementById('eu_username').value = username; document.getElementById('eu_name').value = name; document.getElementById('eu_email').value = email; document.getElementById('eu_role').value = role;
  document.getElementById('editUserModal').style.display = 'flex';
}
function closeEditUserModal() { document.getElementById('editUserModal').style.display = 'none'; }
function openSuspendModal(id, name) { document.getElementById('sus_id').value = id; document.getElementById('sus_name').textContent = name; document.getElementById('suspendModal').style.display = 'flex'; }
function closeSuspendModal() { document.getElementById('suspendModal').style.display = 'none'; }
// Close modals on backdrop click
document.querySelectorAll('#editUserModal,#suspendModal,#testEmailModal').forEach(m => { m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; }); });
// Init provider
selectProvider(currentProvider);
</script>
</body>
</html>
