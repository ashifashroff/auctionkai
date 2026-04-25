<!-- ─── FOOTER ──────────────────────────────────────── -->
<?php
// Auto-detect base path: if included from auth/ subfolder, use ../
$base = '';
if (isset($footerBase)) { $base = $footerBase; }
elseif (!isset($footerBase)) {
  // Detect if we're inside a subfolder by checking if index.php exists at current level
  $base = file_exists(__DIR__ . '/../index.php') ? '../' : '';
}
?>
<div class="bg-ak-bg2 border-t border-ak-border px-7 py-8 mt-8">
  <div class="max-w-[1400px] mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- Column 1 — Brand -->
    <div>
      <div class="text-ak-gold font-bold text-lg">⚡ AuctionKai</div>
      <div class="text-ak-muted text-xs mt-1">Settlement Management System</div>
      <div class="mt-2"><span class="text-[10px] bg-ak-border text-ak-muted px-2 py-0.5 rounded font-mono">v2.4</span></div>
    </div>
    <!-- Column 2 — System -->
    <div>
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">System</div>
      <div class="flex flex-col gap-1.5">
        <a href="<?= $base ?>index.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Home</a>
        <a href="<?= $base ?>profile.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Profile</a>
        <a href="<?= $base ?>help.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Help</a>
        <a href="<?= $base ?>about.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">About</a>
      </div>
    </div>
    <!-- Column 3 — Legal -->
    <div>
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Legal</div>
      <div class="flex flex-col gap-1.5">
        <a href="<?= $base ?>privacy.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Privacy Policy</a>
        <a href="<?= $base ?>terms.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Terms of Use</a>
        <a href="<?= $base ?>contact.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Contact</a>
      </div>
    </div>
  </div>
  <!-- Bottom Bar -->
  <div class="max-w-[1400px] mx-auto flex flex-col md:flex-row justify-between items-center mt-8 pt-4 border-t border-ak-border">
    <div class="text-ak-muted text-xs">© 2025–<?= date('Y') ?> AuctionKai. All rights reserved.</div>
    <div class="text-ak-muted text-xs mt-2 md:mt-0">Designed & Developed by Mirai Global Solutions</div>
  </div>
</div>
