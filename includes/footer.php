<!-- ─── FOOTER ──────────────────────────────────────── -->
</div><!-- end main content wrapper (flex-1) -->
<?php
$footerBase = '';
if (basename(dirname(__DIR__)) === 'admin' || basename(dirname(__FILE__)) === 'admin') {
  $footerBase = '../';
}
?>
<footer class="bg-ak-bg2 border-t border-ak-border px-7 py-8 w-full mt-auto">
  <div class="max-w-[1400px] mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- Column 1 — Brand -->
    <div>
      <div class="text-ak-gold font-bold text-lg">⚡ AuctionKai</div>
      <div class="text-ak-muted text-xs mt-1">Settlement Management System</div>
      <div class="mt-2"><span class="text-[10px] bg-ak-border text-ak-muted px-2 py-0.5 rounded font-mono">v3.2</span></div>
    </div>
    <!-- Column 2 — System -->
    <div>
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">System</div>
      <div class="flex flex-col gap-1.5">
        <a href="<?= $footerBase ?>index.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Home</a>
        <a href="<?= $footerBase ?>profile.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Profile</a>
        <a href="<?= $footerBase ?>help.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Help</a>
        <a href="<?= $footerBase ?>about.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">About</a>
      </div>
    </div>
    <!-- Column 3 — Legal -->
    <div>
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Legal</div>
      <div class="flex flex-col gap-1.5">
        <a href="<?= $footerBase ?>privacy.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Privacy Policy</a>
        <a href="<?= $footerBase ?>terms.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Terms of Use</a>
        <a href="<?= $footerBase ?>contact.php" class="text-ak-muted hover:text-ak-gold transition-colors text-sm">Contact</a>
      </div>
    </div>
  </div>
  <!-- Bottom Bar -->
  <div class="max-w-[1400px] mx-auto flex flex-col md:flex-row justify-between items-center mt-8 pt-4 border-t border-ak-border">
    <div class="text-ak-muted text-xs">© 2025–<?= date('Y') ?> AuctionKai. All rights reserved.</div>
    <div class="text-ak-muted text-xs mt-2 md:mt-0">Designed & Developed by Mirai Global Solutions</div>
  </div>
</footer>
