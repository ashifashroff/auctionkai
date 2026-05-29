<?php
$totalGross = 0; $totalNet = 0; $memberRanking = [];
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
    $totalGross += $s['grossSales'];
    $totalNet  += $s['netPayout'];
    $memberRanking[] = ['name'=>$m['name'], 'count'=>$s['count'], 'unsoldCount'=>$s['unsoldCount'], 'gross'=>$s['grossSales'], 'net'=>$s['netPayout']];
}
usort($memberRanking, fn($a, $b) => $b['net'] <=> $a['net']);
?>
<h2 class="text-lg font-bold mb-5">Dashboard — <?= h($auction['name']) ?></h2>

<!-- Stats Cards -->
<div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-6">
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Members</div>
    <div class="text-3xl font-bold text-ak-text mt-2 font-mono" data-countup="true" data-target="<?= $membersInAuction ?>"><?= $membersInAuction ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.05s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Vehicles</div>
    <div class="text-3xl font-bold text-ak-text mt-2 font-mono" data-countup="true" data-target="<?= count($vehicles) ?>"><?= count($vehicles) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.1s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Sold</div>
    <div class="text-3xl font-bold text-ak-green mt-2 font-mono" data-countup="true" data-target="<?= $totalSold ?>"><?= $totalSold ?></div>
    <?php $soldPct = count($vehicles) > 0 ? round(($totalSold / count($vehicles)) * 100) : 0; ?>
    <div class="mt-2 h-1.5 bg-ak-border rounded-full overflow-hidden"><div class="h-full bg-ak-green rounded-full transition-all" style="width:<?= $soldPct ?>%"></div></div>
    <div class="text-[10px] text-ak-muted mt-1"><?= $soldPct ?>% sell rate</div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.15s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Gross</div>
    <div class="text-2xl font-bold text-ak-text2 mt-2 font-mono" data-countup="true" data-target="<?= round($totalGross) ?>" data-prefix="¥" data-compact="true"><?= fmt($totalGross) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.2s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Net Payout</div>
    <div class="text-2xl font-bold text-ak-gold mt-2 font-mono" data-countup="true" data-target="<?= round($totalNet) ?>" data-prefix="¥" data-compact="true"><?= fmt($totalNet) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.25s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Unpaid</div>
    <div class="text-3xl font-bold text-ak-red mt-2 font-mono"><?= $totalUnpaid ?></div>
    <a href="?tab=statements" class="text-[10px] text-ak-muted hover:text-ak-gold transition-colors">View →</a>
  </div>
</div>

<!-- Member Ranking -->
<div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.25s">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-4">Member Ranking by Net Payout</div>
  <?php if (empty($memberRanking) || $totalNet == 0): ?>
    <div class="text-ak-muted text-center py-8">No sales data available yet.</div>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
    <?php foreach (array_slice($memberRanking, 0, 10) as $i => $mr): ?>
    <?php if ($mr['net'] <= 0 && $mr['gross'] <= 0) continue; ?>
    <div class="flex items-center gap-3 bg-ak-bg rounded-lg px-4 py-3 flex-wrap">
      <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shrink-0 <?= $i < 3 ? 'bg-ak-gold text-ak-bg' : 'bg-ak-border text-ak-muted' ?>"><?= $i + 1 ?></div>
      <div class="flex-1 min-w-0">
        <div class="text-ak-text font-semibold"><?= h($mr['name']) ?></div>
        <div class="text-ak-muted text-xs"><?= $mr['count'] ?> sold · <?= $mr['unsoldCount'] ?> unsold</div>
      </div>
      <div class="text-ak-gold font-mono font-bold"><?= fmt($mr['net']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
