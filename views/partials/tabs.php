<!-- ─── TABS ────────────────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-4 md:px-7 flex items-center gap-1 hidden md:flex">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="px-5 py-3 text-sm font-semibold transition-all duration-200 border-b-2 rounded-t-lg <?= $tab === $key ? 'tab-btn-active' : 'text-ak-muted border-transparent hover:text-ak-text2 hover:bg-ak-infield/50' ?>" href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="ml-auto text-xs text-ak-muted flex gap-4">
    <span><b class="text-ak-text"><?= $membersInAuction ?></b> sellers</span>
    <span><b class="text-ak-green"><?= $totalSold ?></b> sold / <b class="text-ak-text"><?= count($vehicles) ?></b> total</span>
  </div>
</div>

<!-- ─── MOBILE BOTTOM TAB BAR ─────────────────────────── -->
<div class="fixed bottom-0 left-0 right-0 bg-ak-bg2 border-t border-ak-border flex md:hidden z-40 safe-area-pb">
  <?php foreach ($tabs as $key => $t): ?>
  <a href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>"
     class="flex-1 flex flex-col items-center justify-center py-2.5 gap-0.5 text-[10px] font-medium transition-colors <?= $tab === $key ? 'text-ak-gold' : 'text-ak-muted' ?>">
    <span class="text-xl leading-none"><?= $t['icon'] ?></span>
    <span><?= $t['label'] ?></span>
  </a>
  <?php endforeach; ?>
</div>
