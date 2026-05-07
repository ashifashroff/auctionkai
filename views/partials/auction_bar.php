<!-- ─── AUCTION SELECTOR ────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-4 md:px-7 py-2 md:py-3">
  <div class="auction-chips-wrap flex gap-2 items-center overflow-x-auto pb-1 scrollbar-thin scrollbar-ak">
    <?php foreach ($allAuctions as $a): ?>
      <a class="inline-flex items-center gap-2 px-3 py-1.5 md:px-4 md:py-2 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap <?= (int)$a['id'] === $activeAuctionId ? 'bg-ak-gold text-ak-bg animate-pulse-gold' : 'bg-ak-card text-ak-text2 hover:bg-ak-border' ?>" href="?auction_id=<?= (int)$a['id'] ?>&tab=<?= h($tab) ?>">
        <?= h($a['name']) ?>
        <span class="text-[10px] opacity-70 hidden md:inline">📅 <?= h($a['date']) ?></span>
        <?php
        $daysLeft = (int)((strtotime($a['expires_at']) - time()) / 86400);
        $badgeClass = $daysLeft <= 0 ? 'bg-ak-red/20 text-ak-red' : ($daysLeft <= 3 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-ak-green/20 text-ak-green');
        $badgeText = $daysLeft <= 0 ? 'Expired' : ($daysLeft . 'd left');
        ?>
        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold <?= (int)$a['id'] === $activeAuctionId ? 'bg-ak-bg/20 text-ak-bg' : $badgeClass ?>"><?= $badgeText ?></span>
      </a>
    <?php endforeach; ?>
    <button class="px-3 py-1.5 md:px-3 md:py-2 rounded-lg border border-dashed border-ak-border text-ak-muted text-xs hover:border-ak-gold hover:text-ak-gold transition-all duration-200" onclick="document.getElementById('addAuctionForm').classList.toggle('hidden')">+ New Auction</button>
  </div>
  <div id="chipsScrollHint" class="hidden text-[10px] text-ak-muted mt-1 text-right">
    ← scroll to see more auctions →
  </div>
  <?php if ($auction): ?>
  <div id="auctionEditPanel" class="hidden bg-ak-bg2 border border-ak-border rounded-xl p-5 mt-3 animate-slide-down">
    <form onsubmit="return submitSaveAuction(event)" data-parsley-validate>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
        <div><label class="lbl">Auction Name</label><input class="inp" name="name" value="<?= h($auction['name']) ?>" placeholder="Auction name"></div>
        <div><label class="lbl">Date</label><input class="inp opacity-50 cursor-not-allowed" type="date" name="date" value="<?= h($auction['date']) ?>" disabled></div>
        <div><label class="lbl">Commission ¥/member</label><input class="inp font-mono" type="number" step="1" name="commissionFee" value="<?= (float)($auction['commission_fee'] ?? 3300) ?>" data-parsley-type="number" data-parsley-min="0"></div>
        <div class="flex items-end gap-2">
          <button class="btn btn-gold btn-sm flex-1 sm:flex-initial" type="submit">💾 Save</button>
          <a class="btn btn-sm flex-1 sm:flex-initial" href="api/delete_auction.php?auction_id=<?= (int)$auction['id'] ?>" style="background:rgba(204,119,119,.15);color:var(--red);border:1px solid rgba(204,119,119,.3)">🗑 Delete</a>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM ─────────────────────────────── -->
<div id="addAuctionForm" class="hidden bg-ak-bg2 border-b border-ak-border px-4 md:px-7 py-4 animate-slide-down">
  <form onsubmit="return submitAddAuction(event)" data-parsley-validate class="w-full md:max-w-md">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-0">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" data-parsley-required="true"></div>
      <div><label class="lbl">Auction Date *</label><input class="inp" type="date" name="date" data-parsley-required="true" max="<?= date('Y-m-d') ?>"></div>
      <div class="flex items-end sm:col-span-2 sm:justify-end"><button class="btn btn-gold w-full sm:w-auto" type="submit">+ Create</button></div>
    </div>
  </form>
</div>
