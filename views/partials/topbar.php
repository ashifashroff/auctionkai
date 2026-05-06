<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50 animate-slide-down topbar-inner">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ <?= h($brand['brand_name']) ?></div>
    <div class="text-ak-muted text-[11px]"><?= h($brand['brand_tagline']) ?></div>
  </div>
  <?php if ($auction): ?>
  <button onclick="document.getElementById('auctionEditPanel').classList.toggle('hidden')" class="btn btn-dark btn-sm text-[11px] shrink-0">✎ Edit Auction</button>
  <?php endif; ?>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="document.getElementById('hamburgerBtn').classList.toggle('open'); document.getElementById('mobileMenu').classList.toggle('open');">
      <span></span><span></span><span></span>
    </button>
    <a href="profile.php" class="flex items-center gap-2 no-underline hover:opacity-80 transition-opacity">
      <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
      <div><div class="text-ak-text text-sm font-semibold"><?= h($userName) ?></div><div class="text-ak-muted text-[10px] capitalize"><?= h($userRole) ?></div></div>
    </a>
    <?php if (!empty($_SESSION['original_admin_id'])): ?>
      <?php
        $impStart = (int)($_SESSION['impersonate_started'] ?? 0);
        $impRemaining = max(0, 3600 - (time() - $impStart));
        $impMins = floor($impRemaining / 60);
      ?>
      <form method="POST" action="admin/index.php" style="display:inline" data-parsley-validate>
        <input type="hidden" name="action" value="return_to_admin">
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">
        <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-ak-gold/20 text-ak-gold border border-ak-gold/30 hover:bg-ak-gold/30 transition-colors">← Return to Admin (<?= $impMins ?>m left)</button>
      </form>
    <?php endif; ?>
    <?php if ($userRole === 'admin'): ?>
      <a href="admin/index.php" style="background:#1A3A2A;color:#4CAF82;border:1px solid #2A5A3A;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none">⚙ Admin</a>
    <?php endif; ?>
    <button onclick="KeyboardShortcuts.openShortcutsModal()" class="theme-toggle" title="Keyboard shortcuts (?)"><span>⌨</span><span class="hide-mobile">Shortcuts</span></button>
    <a href="auth/logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
  </div>
</div>

<?php
$maintenanceOn = false;
try {
    $maintenanceOn = $db->query("SELECT value FROM settings WHERE `key`='maintenance_mode'")->fetchColumn() === '1';
} catch (Exception $e) {}
if ($maintenanceOn && $userRole === 'admin'):
?>
<div class="bg-yellow-500/20 border-b border-yellow-500/40 px-7 py-2 flex items-center justify-between gap-4">
  <div class="flex items-center gap-2">
    <span class="text-amber-400 font-bold animate-pulse">🚧</span>
    <span class="text-yellow-400 text-sm font-semibold">Maintenance Mode is ACTIVE — Non-admin users cannot access the system</span>
  </div>
  <a href="admin/index.php?tab=maintenance" class="text-yellow-400 text-xs hover:underline font-medium">Manage →</a>
</div>
<?php endif; ?>
