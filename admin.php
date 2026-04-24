<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
session_start();

// ─── ADMIN PROTECTION ────────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    $_SESSION['admin_error'] = 'Access denied. Admins only.';
    header('Location: /auctionkai/index.php');
    exit;
}

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];
$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Admin';
$userRole = $_SESSION['user_role'] ?? 'admin';

$db = db();
$msg = '';
$msgType = '';

// ─── HANDLE POST ACTIONS ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['_tok'] ?? '') !== $tok) {
        http_response_code(403);
        exit('Forbidden');
    }

    $action = $_POST['action'] ?? '';

    // ── LOGIN AS ──────────────────────────────────────────────────────────────
    if ($action === 'login_as') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if ($target && (int)$target['id'] !== $userId) {
            $_SESSION['original_admin_id']   = $userId;
            $_SESSION['original_admin_name'] = $userName;
            $_SESSION['user_id']             = (int)$target['id'];
            $_SESSION['user_name']           = $target['name'];
            $_SESSION['user_role']           = $target['role'];
            $_SESSION['user_username']       = $target['username'];
            header('Location: /auctionkai/index.php');
            exit;
        }
        $msg = 'Invalid user.'; $msgType = 'error';
    }

    // ── RETURN TO ADMIN ───────────────────────────────────────────────────────
    if ($action === 'return_to_admin') {
        $origId   = (int)($_SESSION['original_admin_id'] ?? 0);
        $origName = $_SESSION['original_admin_name'] ?? 'Admin';
        if ($origId) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$origId]);
            $orig = $stmt->fetch();
            if ($orig) {
                $_SESSION['user_id']       = (int)$orig['id'];
                $_SESSION['user_name']     = $orig['name'];
                $_SESSION['user_role']     = $orig['role'];
                $_SESSION['user_username'] = $orig['username'];
            }
        }
        unset($_SESSION['original_admin_id'], $_SESSION['original_admin_name']);
        header('Location: /auctionkai/admin.php');
        exit;
    }

    // ── CREATE USER ───────────────────────────────────────────────────────────
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($username === '' || $name === '' || $password === '') {
            $msg = 'Username, name, and password are required.'; $msgType = 'error';
        } elseif (strlen($password) < 6) {
            $msg = 'Password must be at least 6 characters.'; $msgType = 'error';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $msg = 'Username already exists.'; $msgType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?,?,?,?,?)");
                $stmt->execute([$username, $hash, $name, $email, $role]);
                $msg = 'User created successfully.'; $msgType = 'success';
            }
        }
    }

    // ── EDIT USER ─────────────────────────────────────────────────────────────
    if ($action === 'edit_user') {
        $id       = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($id && $username !== '' && $name !== '') {
            // Check username uniqueness (exclude self)
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$username, $id]);
            if ($chk->fetch()) {
                $msg = 'Username already taken by another user.'; $msgType = 'error';
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, name=?, email=?, role=? WHERE id=?");
                $stmt->execute([$username, $name, $email, $role, $id]);
                $msg = 'User updated.'; $msgType = 'success';
            }
        } else {
            $msg = 'Username and name are required.'; $msgType = 'error';
        }
    }

    // ── DELETE USER ───────────────────────────────────────────────────────────
    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== $userId) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $msg = 'User deleted.'; $msgType = 'success';
        } else {
            $msg = 'Cannot delete yourself.'; $msgType = 'error';
        }
    }

    // ── SUSPEND USER ──────────────────────────────────────────────────────────
    if ($action === 'suspend_user') {
        $id     = (int)($_POST['user_id'] ?? 0);
        $days   = max(1, (int)($_POST['days'] ?? 1));
        $reason = trim($_POST['reason'] ?? 'No reason provided');
        if ($id && $id !== $userId) {
            $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $stmt = $db->prepare("UPDATE users SET status='suspended', suspended_until=?, suspend_reason=? WHERE id=?");
            $stmt->execute([$until, $reason, $id]);
            $msg = "User suspended until {$until}."; $msgType = 'success';
        } else {
            $msg = 'Cannot suspend yourself.'; $msgType = 'error';
        }
    }

    // ── UNSUSPEND USER ────────────────────────────────────────────────────────
    if ($action === 'unsuspend_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE users SET status='active', suspended_until=NULL, suspend_reason=NULL WHERE id=?");
            $stmt->execute([$id]);
            $msg = 'User reactivated.'; $msgType = 'success';
        }
    }

    // ── ADMIN SETTINGS ────────────────────────────────────────────────────────
    if ($action === 'admin_settings') {
        $newUsername = trim($_POST['username'] ?? '');
        $newName     = trim($_POST['name'] ?? '');
        $newEmail    = trim($_POST['email'] ?? '');
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';

        if ($newUsername === '' || $newName === '') {
            $msg = 'Username and name are required.'; $msgType = 'error';
        } else {
            // Check username uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$newUsername, $userId]);
            if ($chk->fetch()) {
                $msg = 'Username already taken.'; $msgType = 'error';
            } else {
                // If changing password, verify current
                if ($newPass !== '') {
                    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch();
                    if (!$row || !password_verify($currentPass, $row['password'])) {
                        $msg = 'Current password is incorrect.'; $msgType = 'error';
                    } elseif (strlen($newPass) < 6) {
                        $msg = 'New password must be at least 6 characters.'; $msgType = 'error';
                    } else {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET username=?, name=?, email=?, password=? WHERE id=?");
                        $stmt->execute([$newUsername, $newName, $newEmail, $hash, $userId]);
                        $_SESSION['user_name']     = $newName;
                        $_SESSION['user_username'] = $newUsername;
                        $userName = $newName;
                        $msg = 'Profile and password updated.'; $msgType = 'success';
                    }
                } else {
                    $stmt = $db->prepare("UPDATE users SET username=?, name=?, email=? WHERE id=?");
                    $stmt->execute([$newUsername, $newName, $newEmail, $userId]);
                    $_SESSION['user_name']     = $newName;
                    $_SESSION['user_username'] = $newUsername;
                    $userName = $newName;
                    $msg = 'Profile updated.'; $msgType = 'success';
                }
            }
        }
    }
}

// ─── FETCH ALL USERS ─────────────────────────────────────────────────────────
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// ─── FETCH CURRENT ADMIN DATA ────────────────────────────────────────────────
$adminStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$adminStmt->execute([$userId]);
$admin = $adminStmt->fetch();

// ─── ACTIVE TAB ──────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'users';
$tabs = [
    'users'    => ['icon' => '👥', 'label' => 'Users'],
    'create'   => ['icon' => '➕', 'label' => 'Create User'],
    'settings' => ['icon' => '⚙️', 'label' => 'Admin Settings'],
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
<link rel="stylesheet" href="css/style.css">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50 animate-slide-down">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai <span class="text-[10px] bg-ak-gold/20 text-ak-gold px-2 py-0.5 rounded ml-1 font-mono">ADMIN</span></div>
    <div class="text-ak-muted text-[11px]">Administration Panel</div>
  </div>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
    <div><div class="text-ak-text text-sm font-semibold"><?= h($userName) ?></div><div class="text-ak-muted text-[10px] capitalize"><?= h($userRole) ?></div></div>
    <a href="/auctionkai/index.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">← Back to App</a>
    <a href="/auctionkai/auth/logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
  </div>
</div>

<!-- ─── MESSAGE ──────────────────────────────────────── -->
<?php if ($msg): ?>
<div class="px-7 pt-4">
  <div class="<?= $msgType === 'success' ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/15 text-ak-red' ?> px-4 py-3 rounded-lg text-sm animate-fade-in"><?= h($msg) ?></div>
</div>
<?php endif; ?>

<!-- ─── TABS ────────────────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-7 flex items-center gap-1">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="px-5 py-3 text-sm font-semibold transition-all duration-200 border-b-2 <?= $tab === $key ? 'text-ak-gold border-ak-gold' : 'text-ak-muted border-transparent hover:text-ak-text2' ?>" href="?tab=<?= $key ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="ml-auto text-xs text-ak-muted flex gap-4">
    <span><b class="text-ak-text"><?= count($users) ?></b> total users</span>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
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
        <th class="px-4 py-3 text-left">Status</th>
        <th class="px-4 py-3 text-left">Joined</th>
        <th class="px-4 py-3 text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="border-b border-ak-border/50 hover:bg-ak-bg/50 transition-colors">
        <td class="px-4 py-3">
          <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?></div>
        </td>
        <td class="px-4 py-3 font-mono text-ak-text2"><?= h($u['username']) ?></td>
        <td class="px-4 py-3"><?= h($u['name']) ?></td>
        <td class="px-4 py-3 text-ak-muted"><?= h($u['email']) ?></td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $u['role'] === 'admin' ? 'bg-ak-gold/20 text-ak-gold' : 'bg-blue-500/20 text-blue-400' ?>"><?= h($u['role']) ?></span>
        </td>
        <td class="px-4 py-3">
          <?php
            $statusColors = ['active' => 'bg-ak-green/20 text-ak-green', 'suspended' => 'bg-yellow-500/20 text-yellow-400', 'restricted' => 'bg-ak-red/20 text-ak-red'];
            $st = $u['status'] ?? 'active';
          ?>
          <span class="px-2 py-0.5 rounded text-[11px] font-bold <?= $statusColors[$st] ?? $statusColors['active'] ?>"><?= h($st) ?></span>
          <?php if ($st === 'suspended' && !empty($u['suspended_until'])): ?>
            <span class="text-[10px] text-ak-muted ml-1">until <?= h(date('M j, Y', strtotime($u['suspended_until']))) ?></span>
          <?php endif; ?>
        </td>
        <td class="px-4 py-3 text-ak-muted text-xs"><?= h(date('M j, Y', strtotime($u['created_at']))) ?></td>
        <td class="px-4 py-3 text-center">
          <div class="flex gap-1.5 justify-center flex-wrap">
            <?php if ((int)$u['id'] !== $userId): ?>
              <form method="POST" action="/auctionkai/admin.php?tab=users" style="display:inline" data-parsley-validate>
                <input type="hidden" name="action" value="login_as">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                <button class="btn btn-dark btn-sm text-[11px]" type="submit">Login As</button>
              </form>
            <?php endif; ?>
            <button class="btn btn-dark btn-sm text-[11px]" onclick="openEditUserModal(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['username'])) ?>', '<?= h(addslashes($u['name'])) ?>', '<?= h(addslashes($u['email'])) ?>', '<?= h($u['role']) ?>')">Edit</button>
            <?php if ((int)$u['id'] !== $userId): ?>
              <?php if ($st === 'suspended'): ?>
                <form method="POST" action="/auctionkai/admin.php?tab=users" style="display:inline" data-parsley-validate>
                  <input type="hidden" name="action" value="unsuspend_user">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="_tok" value="<?= h($tok) ?>">
                  <button class="btn btn-sm text-[11px] bg-ak-green/20 text-ak-green border border-ak-green/30 hover:bg-ak-green/30" type="submit">Reactivate</button>
                </form>
              <?php else: ?>
                <button class="btn btn-sm text-[11px] bg-yellow-500/15 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/25" onclick="openSuspendModal(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')">Suspend</button>
              <?php endif; ?>
              <form method="POST" action="/auctionkai/admin.php?tab=users" style="display:inline" onsubmit="return confirm('Delete user <?= h(addslashes($u['name'])) ?>? This will also delete all their auctions, members, and vehicles.')" data-parsley-validate>
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

<?php elseif ($tab === 'create'): ?>
<h2 class="text-lg font-bold mb-5">Create New User</h2>
<div class="bg-ak-card border border-ak-border rounded-xl p-7 max-w-lg animate-fade-in-up">
  <form method="POST" action="/auctionkai/admin.php?tab=create" data-parsley-validate>
    <input type="hidden" name="action" value="create_user">
    <input type="hidden" name="_tok" value="<?= h($tok) ?>">

    <div class="mb-4">
      <label class="lbl">Username *</label>
      <input class="inp" name="username" placeholder="Choose a username" data-parsley-required="true">
    </div>
    <div class="mb-4">
      <label class="lbl">Full Name *</label>
      <input class="inp" name="name" placeholder="e.g. Ahmad Hassan" data-parsley-required="true">
    </div>
    <div class="mb-4">
      <label class="lbl">Email</label>
      <input class="inp" type="email" name="email" placeholder="email@example.com" data-parsley-type="email">
    </div>
    <div class="mb-4">
      <label class="lbl">Password * <span class="font-normal text-ak-muted">(min 6 chars)</span></label>
      <input class="inp" type="password" name="password" placeholder="••••••" data-parsley-required="true">
    </div>
    <div class="mb-5">
      <label class="lbl">Role</label>
      <select class="inp" name="role">
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <button class="btn btn-gold w-full" type="submit">+ Create User</button>
  </form>
</div>

<?php elseif ($tab === 'settings'): ?>
<h2 class="text-lg font-bold mb-5">Admin Settings</h2>
<div class="bg-ak-card border border-ak-border rounded-xl p-7 max-w-lg animate-fade-in-up">
  <form method="POST" action="/auctionkai/admin.php?tab=settings" data-parsley-validate>
    <input type="hidden" name="action" value="admin_settings">
    <input type="hidden" name="_tok" value="<?= h($tok) ?>">

    <div class="mb-4">
      <label class="lbl">Username *</label>
      <input class="inp" name="username" value="<?= h($admin['username'] ?? '') ?>" data-parsley-required="true">
    </div>
    <div class="mb-4">
      <label class="lbl">Full Name *</label>
      <input class="inp" name="name" value="<?= h($admin['name'] ?? '') ?>" data-parsley-required="true">
    </div>
    <div class="mb-4">
      <label class="lbl">Email</label>
      <input class="inp" type="email" name="email" value="<?= h($admin['email'] ?? '') ?>" data-parsley-type="email">
    </div>

    <div class="border-t border-ak-border my-5 pt-5">
      <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase mb-3">Change Password</div>
      <div class="mb-4">
        <label class="lbl">Current Password</label>
        <input class="inp" type="password" name="current_password" placeholder="Enter current password to change">
      </div>
      <div class="mb-4">
        <label class="lbl">New Password <span class="font-normal text-ak-muted">(min 6 chars, leave blank to keep current)</span></label>
        <input class="inp" type="password" name="new_password" placeholder="••••••">
      </div>
    </div>

    <button class="btn btn-gold w-full" type="submit">Save Settings</button>
  </form>
</div>
<?php endif; ?>

</div>

<!-- ─── EDIT USER MODAL ─────────────────────────────── -->
<div id="editUserModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[500px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit User</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditUserModal()">×</button>
    </div>
    <form method="POST" action="/auctionkai/admin.php?tab=users" data-parsley-validate>
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="eu_id">
      <input type="hidden" name="_tok" value="<?= h($tok) ?>">
      <div class="mb-4">
        <label class="lbl">Username *</label>
        <input class="inp" name="username" id="eu_username" data-parsley-required="true">
      </div>
      <div class="mb-4">
        <label class="lbl">Full Name *</label>
        <input class="inp" name="name" id="eu_name" data-parsley-required="true">
      </div>
      <div class="mb-4">
        <label class="lbl">Email</label>
        <input class="inp" type="email" name="email" id="eu_email" data-parsley-type="email">
      </div>
      <div class="mb-5">
        <label class="lbl">Role</label>
        <select class="inp" name="role" id="eu_role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditUserModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── SUSPEND USER MODAL ──────────────────────────── -->
<div id="suspendModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[420px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-yellow-400 text-lg font-bold">⏸ Suspend User</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeSuspendModal()">×</button>
    </div>
    <form method="POST" action="/auctionkai/admin.php?tab=users" data-parsley-validate>
      <input type="hidden" name="action" value="suspend_user">
      <input type="hidden" name="user_id" id="sus_id">
      <input type="hidden" name="_tok" value="<?= h($tok) ?>">
      <div class="mb-2 text-ak-muted text-sm">Suspending: <b class="text-ak-text" id="sus_name"></b></div>
      <div class="mb-4">
        <label class="lbl">Reason</label>
        <input class="inp" name="reason" placeholder="e.g. Policy violation" data-parsley-required="true">
      </div>
      <div class="mb-5">
        <label class="lbl">Duration (days)</label>
        <input class="inp font-mono" type="number" name="days" value="7" data-parsley-type="number" data-parsley-min="1">
      </div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
        <button type="button" class="btn btn-dark btn-sm" onclick="closeSuspendModal()">Cancel</button>
        <button type="submit" class="btn btn-sm bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 hover:bg-yellow-500/30">Suspend</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditUserModal(id, username, name, email, role) {
  document.getElementById('eu_id').value = id;
  document.getElementById('eu_username').value = username;
  document.getElementById('eu_name').value = name;
  document.getElementById('eu_email').value = email;
  document.getElementById('eu_role').value = role;
  const m = document.getElementById('editUserModal');
  m.style.display = 'flex';
}
function closeEditUserModal() {
  document.getElementById('editUserModal').style.display = 'none';
}
function openSuspendModal(id, name) {
  document.getElementById('sus_id').value = id;
  document.getElementById('sus_name').textContent = name;
  const m = document.getElementById('suspendModal');
  m.style.display = 'flex';
}
function closeSuspendModal() {
  document.getElementById('suspendModal').style.display = 'none';
}
// Close modals on backdrop click
document.querySelectorAll('#editUserModal, #suspendModal').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>