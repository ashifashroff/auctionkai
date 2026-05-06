<!-- ─── TABS ────────────────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-7 flex items-center gap-1 hidden md:flex">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="px-5 py-3 text-sm font-semibold transition-all duration-200 border-b-2 rounded-t-lg <?= $tab === $key ? 'tab-btn-active' : 'text-ak-muted border-transparent hover:text-ak-text2 hover:bg-ak-infield/50' ?>" href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="ml-auto text-xs text-ak-muted flex gap-4">
    <span><b class="text-ak-text"><?= $membersInAuction ?></b> sellers</span>
    <span><b class="text-ak-green"><?= $totalSold ?></b> sold / <b class="text-ak-text"><?= count($vehicles) ?></b> total</span>
  </div>
</div>
