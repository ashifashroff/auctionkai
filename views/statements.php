<div class="flex justify-between items-start mb-5 flex-wrap gap-3">
  <h2 class="text-base md:text-lg font-bold">Settlement Statements — <?= h($auction['name']) ?></h2>
  <div class="flex flex-wrap gap-2">
    <a class="btn btn-dark btn-sm text-[11px]" href="pdf.php?all=1&v=3.0&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ PDFs</a>
    <a class="btn btn-dark btn-sm text-[11px]" href="api/download_pdf_zip.php?auction_id=<?= (int)$activeAuctionId ?>&_tok=<?= h($_SESSION["tok"] ?? "") ?>" onclick="showToast('📦 Preparing ZIP...','info',3000)">📦 ZIP</a>
    <a href="auction_summary.php?auction_id=<?= (int)$activeAuctionId ?>" target="_blank" class="btn btn-dark btn-sm text-[11px]">📊 Summary</a>
  </div>
</div>

<!-- Search & Filter -->
<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 mb-4">
  <div class="vehicles-search-wrap flex-1">
    <div class="search-icon-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" id="statement-search" aria-label="Search statements" class="vehicles-search-input" placeholder="Search members..." autocomplete="off">
    </div>
  </div>
  <div class="flex items-center gap-2">
    <select id="payment-filter" aria-label="Filter by payment status" class="inp text-xs py-1.5 px-2 flex-1 sm:flex-none sm:w-auto" onchange="filterStatements()">
      <option value="all">All</option>
      <option value="paid">✓ Paid</option>
      <option value="unpaid">✗ Unpaid</option>
      <option value="partial">◑ Partial</option>
    </select>
    <button onclick="markAllUnpaidAsPaid()" class="btn btn-gold btn-sm text-[11px] whitespace-nowrap" title="Mark all Unpaid as Paid">✓ Pay All</button>
  </div>
</div>

<?php
$totalPaid = 0; $totalUnpaid = 0; $totalPartial = 0; $totalNetPayout = 0;
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
    if ($s['count'] === 0 && $s['unsoldCount'] === 0) continue;
    $ps = $paymentStatuses[$m['id']] ?? null;
    $payStatus = $ps['status'] ?? 'unpaid';
    $totalNetPayout += $s['netPayout'];
    if ($payStatus === 'paid') $totalPaid++;
    elseif ($payStatus === 'partial') $totalPartial++;
    else $totalUnpaid++;
}
?>

<!-- Total payout — full width on mobile -->
<div class="bg-ak-card rounded-xl p-3 md:p-4 border border-ak-border mb-3 md:hidden">
  <div class="flex justify-between items-center">
    <span class="text-ak-muted text-xs">Total Net Payout</span>
    <span class="font-bold font-mono text-ak-gold text-lg"><?= fmt($totalNetPayout) ?></span>
  </div>
</div>

<!-- Stats row — 3 cols on mobile, 4 cols on desktop -->
<div class="grid grid-cols-3 md:grid-cols-4 gap-2 md:gap-3 mb-5">
  <div class="hidden md:block bg-ak-card rounded-xl p-4 border border-ak-border text-center">
    <div class="text-2xl font-bold font-mono text-ak-gold"><?= fmt($totalNetPayout) ?></div>
    <div class="text-ak-muted text-xs mt-1">Total Net Payout</div>
  </div>
  <div class="bg-ak-card rounded-xl p-3 md:p-4 border border-ak-green/30 text-center">
    <div class="text-xl md:text-2xl font-bold font-mono text-ak-green"><?= $totalPaid ?></div>
    <div class="text-ak-muted text-xs mt-1">✓ Paid</div>
  </div>
  <div class="bg-ak-card rounded-xl p-3 md:p-4 border border-amber-500/30 text-center">
    <div class="text-xl md:text-2xl font-bold font-mono text-yellow-400"><?= $totalPartial ?></div>
    <div class="text-ak-muted text-xs mt-1">◑ Partial</div>
  </div>
  <div class="bg-ak-card rounded-xl p-3 md:p-4 border border-ak-red/30 text-center">
    <div class="text-xl md:text-2xl font-bold font-mono text-ak-red"><?= $totalUnpaid ?></div>
    <div class="text-ak-muted text-xs mt-1">✗ Unpaid</div>
  </div>
</div>

<!-- Statements Grid (2 columns) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-5" id="statements-container" style="min-height:400px">
<?php if (empty($members)): ?>
  <div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border md:col-span-2">No sales history available for this auction.</div>
<?php else: ?>
  <?php $hasSales = false; $stmtRank = 1; ?>
  <?php foreach ($members as $m):
    $isNagareOnlyMember = count(array_filter($vehicles, fn($v) => (int)$v['member_id'] === (int)$m['id'] && $v['sold'])) === 0;
    $chargeCommission = $isNagareOnlyMember ? ($commissionFlags[(int)$m['id']] ?? false) : true;
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? [], $chargeCommission);
    if ($s['count'] === 0 && $s['unsoldCount'] === 0) continue;
    $hasSales = true;
    $ps = $paymentStatuses[$m['id']] ?? null;
    $payStatus = $ps['status'] ?? 'unpaid';
    $payClass = match($payStatus) {
        'paid' => 'bg-ak-green/15 text-ak-green border-ak-green/30',
        'partial' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
        default => 'bg-ak-red/10 text-ak-red border-ak-red/20',
    };
    $payIcon = match($payStatus) {
        'paid' => '✓ Paid',
        'partial' => '◑ Partial',
        default => '✗ Unpaid',
    };
  ?>
  <?php if ($s['isNagareOnly'] ?? false): ?>
  <!-- Nagare-only member card -->
  <?php
  $hasSales = true;
  $emailSubject = urlencode("Fee Notice – {$auction['name']} {$auction['date']}");
  $emailBody = urlencode("Dear {$m['name']},\n\nRegarding {$auction['name']} on {$auction['date']}.\n\nAll vehicles were unsold.\n\nNagare Fee: " . fmt($s['nagareFeeTotal']) . "\nOther Fees: " . fmt($s['otherFeeTotal']) . "\nCommission: " . fmt($s['commissionTotal']) . "\n\nTOTAL OWED: " . fmt(abs($s['netPayout'])) . "\n\nThank you.");
  ?>
  <div class="bg-ak-card rounded-xl border border-ak-red/30 mb-5 overflow-hidden animate-fade-in-up">

    <!-- Header -->
    <div class="sh nagare-header">
      <div>
        <div class="flex items-center gap-2">
          <div class="sn2"><?= h($m['name']) ?></div>
          <span class="nagare-badge">✗ No Sales</span>
        </div>
        <div class="flex items-center gap-2 mt-1">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              class="accent-ak-gold nagare-commission-cb"
              id="cb_commission_<?= (int)$m['id'] ?>"
              data-member-id="<?= (int)$m['id'] ?>"
              data-auction-id="<?= $activeAuctionId ?>"
              data-commission-raw="<?= (float)($auction['commission_fee'] ?? 3300) ?>"
              <?= ($commissionFlags[(int)$m['id']] ?? false) ? 'checked' : '' ?>
              onchange="toggleNagareCommission(this)"
            >
            <span class="text-ak-muted text-xs">
              Charge commission
              <span class="text-ak-muted font-mono">(¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>)</span>
            </span>
          </label>
        </div>
        <div class="sm"><?= h($m['email']) ?> · <?= h($m['phone']) ?></div>
      </div>
      <div class="sa flex flex-wrap gap-1.5 sm:gap-2 shrink-0">
        <!-- Row 1: PDF -->
        <div class="flex gap-1.5 w-full sm:w-auto">
          <a class="btn btn-gold btn-sm flex-1 sm:flex-none text-center text-xs" href="pdf.php?member=<?= (int)$m['id'] ?>&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ PDF</a>
        </div>

        <!-- Row 2: Email + WhatsApp + Share -->
        <div class="flex gap-1.5 w-full sm:w-auto">
          <?php if (!empty($m['email'])): ?>
          <button onclick="sendStatementEmail(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, this)" class="btn-email flex-1 sm:flex-none text-center text-xs py-1.5" id="email-btn-<?= (int)$m['id'] ?>">✉ Email</button>
          <?php endif; ?>
          <?php
          $nagareSpecialFees = $memberFeesAll[$m['id']] ?? [];
          $nagareShareUrl = '';
          try {
            $nslStmt = $db->prepare("SELECT token FROM statement_links WHERE member_id=? AND auction_id=? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $nslStmt->execute([(int)$m['id'], (int)$activeAuctionId]);
            $nagareToken = $nslStmt->fetchColumn();
            if ($nagareToken) $nagareShareUrl = appUrl() . '/statement.php?token=' . $nagareToken;
          } catch (Exception $e) {}
          $nagareWAMsg = buildWhatsAppMessage($m, $auction, $s, $nagareSpecialFees, $brand['brand_name'] ?? 'AuctionKai', $nagareShareUrl);
          $nagareWAUrl = buildWhatsAppUrl($m['phone'] ?? '', $nagareWAMsg);
          ?>
          <?php if (!empty($m['phone'])): ?>
          <button onclick="openWhatsApp('<?= h($nagareWAUrl) ?>', <?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, <?= round($s['netPayout']) ?>)" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold border transition-all bg-[#075E54]/20 border-[#25D366]/40 text-[#25D366] hover:bg-[#075E54]/40 cursor-pointer">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            WA
          </button>
          <?php endif; ?>
          <button onclick="generateStatementLink(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, this)" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold border transition-all bg-ak-gold/10 border-ak-gold/30 text-ak-gold hover:bg-ak-gold/20 cursor-pointer">🔗 Link</button>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="sb2">
      <div class="sl">
        <div class="ssl">Unsold Vehicles (<?= $s['unsoldCount'] ?>)</div>
        <?php foreach ($s['uv'] as $v): ?>
        <div class="vr">
          <span class="vr-car">
            <span class="vr-lot"><?= h($v['lot'] ?: '—') ?></span>
            <?= h($v['make'] . ' ' . $v['model']) ?>
          </span>
          <span class="vr-p text-ak-red">
            <?= (float)($v['nagare_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['nagare_fee']) : '—' ?>
          </span>
        </div>
        <?php endforeach; ?>
        <div class="sg">
          <span class="sg-l">Gross Sales</span>
          <span class="sg-n text-ak-muted">¥0</span>
        </div>
      </div>

      <div class="sr">
        <div class="ssl">Fees Owed</div>
        <?php if ($s['nagareFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Nagare Fee ×<?= $s['unsoldCount'] ?></span><span class="dr-a">−<?= fmt($s['nagareFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['otherFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Other Fee</span><span class="dr-a">−<?= fmt($s['otherFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['commissionTotal'] > 0): ?>
        <div class="dr" id="nagare_commission_row_<?= (int)$m['id'] ?>"><span class="dr-l">Commission ¥<?= number_format($s['commissionFee']) ?>/member</span><span class="dr-a">−<?= fmt($s['commissionTotal']) ?></span></div>
        <?php elseif (!($commissionFlags[(int)$m['id']] ?? false)): ?>
        <div class="dr" id="nagare_commission_row_<?= (int)$m['id'] ?>" style="display:none"><span class="dr-l">Commission ¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>/member</span><span class="dr-a">−<?= fmt((float)($auction['commission_fee'] ?? 3300)) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($s['specialFees'])): foreach ($s['specialFees'] as $sf): $isAdd = ($sf['fee_type'] ?? 'deduction') === 'addition'; ?>
        <div class="dr"><span class="dr-l"><?= $isAdd ? '+' : '−' ?> <?= h($sf['fee_name']) ?></span><span class="dr-a"><?= $isAdd ? '+' : '−' ?><?= fmt((float)$sf['amount']) ?></span></div>
        <?php endforeach; endif; ?>
        <div class="dt"><span class="dt-l">Total Fees</span><span class="dt-n" id="nagare_total_<?= (int)$m['id'] ?>">−<?= fmt(abs($s['netPayout'])) ?></span></div>
        <div class="np nagare-np"><span class="np-l">AMOUNT OWED</span><span class="np-n text-ak-red" id="nagare_owed_<?= (int)$m['id'] ?>"><?= fmt(abs($s['netPayout'])) ?></span></div>
      </div>
    </div>
    <div id="link-result-<?= (int)$m['id'] ?>" class="hidden" style="margin:1.25rem"></div>

  </div>
  <?php continue; ?>
  <?php endif; ?>
  <?php
  $stmtPayStatus = $paymentStatuses[$m['id']]['status'] ?? 'unpaid';
  $stmtBorderClass = match($stmtPayStatus) {
    'paid' => 'border-l-4 border-l-ak-green',
    'partial' => 'border-l-4 border-l-amber-400',
    default => 'border-l-4 border-l-ak-border'
  };
  ?>
  <div class="bg-ak-card rounded-xl border border-ak-border <?= $stmtBorderClass ?> overflow-hidden animate-fade-in-up statement-card" data-member-name="<?= h(mb_strtolower($m['name'])) ?>" data-payment="<?= $payStatus ?>">
    <div class="sh flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 p-4 md:p-5">
      <!-- Member info row -->
      <div class="flex items-start gap-3 min-w-0">
        <div class="w-9 h-9 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm shrink-0"><?= mb_strtoupper(mb_substr($m['name'],0,1)) ?></div>
        <div class="min-w-0 flex-1">
          <div class="sn2 text-base truncate"><?= h($m['name']) ?></div>
          <div class="sm text-xs truncate"><?= h($m['email']) ?> · <?= h($m['phone']) ?></div>
          <?php
          $globalNote = $m['notes'] ?? '';
          $auctionNote = $auctionMemberNotes[$m['id']] ?? '';
          ?>
          <?php if ($globalNote || $auctionNote): ?>
          <div class="mt-2 space-y-1">
            <?php if ($globalNote): ?>
            <div class="flex items-start gap-1.5">
              <span class="text-[10px] font-bold text-ak-gold uppercase tracking-wider shrink-0 mt-0.5">📋</span>
              <span class="text-ak-muted text-xs leading-relaxed line-clamp-2"><?= h($globalNote) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($auctionNote): ?>
            <div class="flex items-start gap-1.5">
              <span class="text-[10px] font-bold text-amber-400 uppercase tracking-wider shrink-0 mt-0.5">🏷️</span>
              <span class="text-amber-400/70 text-xs leading-relaxed line-clamp-2"><?= h($auctionNote) ?></span>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if ($payStatus === 'paid' && $ps['paid_at']): ?>
          <div class="text-ak-green text-[11px] mt-0.5">✓ Paid on <?= date('Y-m-d H:i', strtotime($ps['paid_at'])) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Action buttons — stacked on mobile -->
      <div class="sa flex flex-wrap gap-1.5 sm:gap-2 shrink-0">
        <!-- Row 1: Payment + PDF -->
        <div class="flex gap-1.5 w-full sm:w-auto">
          <div class="relative flex-1 sm:flex-none" id="pay-wrap-<?= (int)$m['id'] ?>">
            <button onclick="togglePaymentMenu(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, <?= $s['netPayout'] ?>)" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border cursor-pointer transition-all <?= $payClass ?>" id="pay-btn-<?= (int)$m['id'] ?>" aria-expanded="false" aria-haspopup="true">
              <?= $payIcon ?>
              <span class="text-[10px] opacity-60">▾</span>
            </button>
            <div id="pay-menu-<?= (int)$m['id'] ?>" class="hidden absolute right-0 top-full mt-1 bg-ak-card border border-ak-border rounded-xl shadow-2xl z-50 min-w-[200px] overflow-hidden">
              <div class="px-3 py-2 border-b border-ak-border"><div class="text-ak-muted text-[10px] uppercase tracking-wider">Update Payment Status</div></div>
              <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'paid', <?= $s['netPayout'] ?>)" class="w-full px-4 py-2.5 text-left text-sm text-ak-green hover:bg-ak-green/10 transition-colors flex items-center gap-2">✓ Mark as Paid</button>
              <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'partial', <?= $s['netPayout'] ?>)" class="w-full px-4 py-2.5 text-left text-sm text-yellow-400 hover:bg-yellow-500/10 transition-colors flex items-center gap-2">◑ Mark as Partial</button>
              <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'unpaid', 0)" class="w-full px-4 py-2.5 text-left text-sm text-ak-red hover:bg-ak-red/10 transition-colors flex items-center gap-2">✗ Mark as Unpaid</button>
              <?php if ($ps && $ps['notes']): ?>
              <div class="px-4 py-2 border-t border-ak-border text-ak-muted text-xs italic">Note: <?= h($ps['notes']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <a class="btn btn-gold btn-sm flex-1 sm:flex-none text-center text-xs" href="pdf.php?member=<?= (int)$m['id'] ?>&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ PDF</a>
        </div>

        <!-- Row 2: Email + WhatsApp + Share -->
        <div class="flex gap-1.5 w-full sm:w-auto">
          <?php if (!empty($m['email'])): ?>
          <button onclick="sendStatementEmail(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, this)" class="btn-email flex-1 sm:flex-none text-center text-xs py-1.5" id="email-btn-<?= (int)$m['id'] ?>">✉ Email</button>
          <?php endif; ?>

          <?php
            $memberSpecialFees = $memberFeesAll[$m['id']] ?? [];
            $existingLink = null;
            try {
              $slStmt = $db->prepare("SELECT token FROM statement_links WHERE member_id=? AND auction_id=? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
              $slStmt->execute([(int)$m['id'], (int)$activeAuctionId]);
              $existingLink = $slStmt->fetchColumn();
            } catch (Exception $e) {}
            $shareUrl = $existingLink ? appUrl() . '/statement.php?token=' . $existingLink : '';
            $waMessage = buildWhatsAppMessage($m, $auction, $s, $memberSpecialFees, $brand['brand_name'] ?? 'AuctionKai', $shareUrl);
            $waUrl = buildWhatsAppUrl($m['phone'] ?? '', $waMessage);
          ?>
          <?php if (!empty($m['phone'])): ?>
          <button onclick="openWhatsApp('<?= h($waUrl) ?>', <?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, <?= round($s['netPayout']) ?>)" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold border transition-all bg-[#075E54]/20 border-[#25D366]/40 text-[#25D366] hover:bg-[#075E54]/40 cursor-pointer">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            WA
          </button>
          <?php else: ?>
          <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold border border-ak-border/30 text-ak-muted/50 cursor-not-allowed">WA</span>
          <?php endif; ?>

          <button onclick="generateStatementLink(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, this)" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-bold border transition-all bg-ak-infield border-ak-border text-ak-muted hover:border-ak-gold hover:text-ak-gold cursor-pointer" id="link-btn-<?= (int)$m['id'] ?>">🔗 Link</button>
        </div>
      </div>
    </div>

    <div id="link-result-<?= (int)$m['id'] ?>" class="hidden" style="margin:1.25rem"></div>

    <button class="w-full px-4 py-2.5 flex items-center justify-between text-xs text-ak-muted hover:text-ak-gold hover:bg-ak-infield/50 transition-colors sm:hidden" onclick="this.nextElementSibling.classList.toggle('hidden');this.querySelector('.stmt-chevron').classList.toggle('rotate-180')"><span>📊 View Breakdown</span><span class="stmt-chevron transition-transform">▾</span></button><div class="sb2 hidden sm:grid">
      <div class="sl">
        <div class="ssl">Sold Vehicles (<?= $s['count'] ?>)</div>
        <?php foreach ($s['mv'] as $v): $vTax = round((float)$v['sold_price'] * 0.10); $vRecycle = (float)($v['recycle_fee'] ?? 0); ?>
        <div class="vr">
          <span class="vr-car"><span class="vr-lot"><?= h($v['lot'] ?: '—') ?></span><?= h($v['make'] . ' ' . $v['model']) ?></span>
          <span class="vr-p"><?= fmt((float)$v['sold_price']) ?></span>
        </div>
        <?php if ($vTax > 0 || $vRecycle > 0): ?>
        <div class="pl-4 py-0.5 pb-1.5 text-[11px] text-ak-muted flex justify-between">
          <span>+ Tax 10%: <?= fmt($vTax) ?><?php if ($vRecycle > 0): ?> + Recycle: <?= fmt($vRecycle) ?><?php endif; ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="sg"><span class="sg-l">Gross Sales</span><span class="sg-n"><?= fmt($s['grossSales']) ?></span></div>
        <?php if ($s['taxTotal'] > 0): ?>
        <div class="flex justify-between py-1 text-[13px]"><span class="text-ak-text2">+ Consumption Tax 10%</span><span class="font-mono text-ak-green"><?= fmt($s['taxTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['recycleTotal'] > 0): ?>
        <div class="flex justify-between py-1 text-[13px]"><span class="text-ak-text2">+ Recycle Fees</span><span class="font-mono text-ak-green"><?= fmt($s['recycleTotal']) ?></span></div>
        <?php endif; ?>
        <div class="flex justify-between py-2 border-t-2 border-ak-border mt-1.5 font-bold"><span class="text-ak-gold">Total Received</span><span class="font-mono text-ak-gold text-[15px]"><?= fmt($s['totalReceived']) ?></span></div>
      </div>
      <div class="sr">
        <div class="ssl">Deductions</div>
        <?php if ($s['listingFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Listing Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['listingFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['soldFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Sold Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['soldFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['nagareFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Nagare Fee ×<?= $s['unsoldCount'] ?></span><span class="dr-a">−<?= fmt($s['nagareFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['commissionTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Commission ¥<?= number_format($s['commissionFee']) ?>/member</span><span class="dr-a">−<?= fmt($s['commissionTotal']) ?></span></div>
        <?php if (!empty($s['specialFees'])): ?>
        <?php foreach ($s['specialFees'] as $sf): $isAdd = $sf['fee_type'] === 'addition'; ?>
        <div class="dr"><span class="dr-l flex items-center gap-1"><?= $isAdd ? '➕' : '➖' ?> <?= h($sf['fee_name']) ?><?php if (!empty($sf['notes'])): ?> <span class="text-ak-muted text-[10px]">(<?= h($sf['notes']) ?>)</span><?php endif; ?></span><span class="dr-a <?= $isAdd ? 'text-ak-green' : '' ?>"><?= $isAdd ? '+' : '−' ?>¥<?= number_format((float)$sf['amount']) ?></span></div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
        <div class="dt"><span class="dt-l">Total Deductions</span><span class="dt-n">−<?= fmt($s['totalDed']) ?></span></div>
        <div class="np"><span class="np-l">NET PAYOUT</span><span class="np-n" data-countup="true" data-target="<?= round($s['netPayout']) ?>" data-prefix="¥"><?= fmt($s['netPayout']) ?></span></div>
      </div>

      <?php
      $memberHistory = $stmtHistory[$m['id']] ?? [];
      if (!empty($memberHistory)):
      ?>
      <div class="border-t border-ak-border mt-0">
        <button onclick="toggleStmtHistory(<?= (int)$m['id'] ?>)" class="w-full px-4 md:px-6 py-2.5 flex items-center justify-between text-xs text-ak-muted hover:text-ak-text2 hover:bg-ak-infield/50 transition-colors">
          <span>📋 History (<?= count($memberHistory) ?>)</span>
          <span id="stmt-history-arrow-<?= (int)$m['id'] ?>">▾</span>
        </button>
        <div id="stmt-history-<?= (int)$m['id'] ?>" class="hidden border-t border-ak-border/50">
        <?php foreach ($memberHistory as $h):
          $actionIcon = match($h['action']) {
            'email' => '✉',
            'whatsapp' => '💬',
            default => '📄'
          };
          $actionLabel = match($h['action']) {
            'email' => 'Email sent',
            'whatsapp' => 'WhatsApp opened',
            default => 'PDF generated'
          };
          $actionColor = match($h['action']) {
            'email' => 'text-ak-gold',
            'whatsapp' => 'text-[#25D366]',
            default => 'text-ak-text2'
          };
        ?>
          <div class="flex items-center gap-2 px-4 py-2 text-xs border-b border-ak-border/30 last:border-0 hover:bg-ak-infield/30 transition-colors">
            <span class="<?= $actionColor ?> shrink-0"><?= $actionIcon ?></span>
            <span class="text-ak-text2 font-medium flex-1 min-w-0 truncate"><?= $actionLabel ?></span>
            <span class="text-ak-green font-mono shrink-0 text-[11px]"><?= fmt($h['net_payout']) ?></span>
            <span class="text-ak-muted font-mono shrink-0 text-[10px] hidden sm:inline"><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></span>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$hasSales): ?>
  <div class="bg-ak-card rounded-xl p-12 text-center text-ak-muted border border-ak-border md:col-span-2 animate-fade-in-up">
    <div class="text-5xl mb-4">📄</div>
    <div class="font-semibold text-ak-text text-lg mb-2">No Statements Yet</div>
    <div class="text-sm mb-5">Add vehicles and mark them as sold to generate settlement statements.</div>
    <a href="?tab=vehicles" class="btn btn-gold">+ Add Vehicles</a>
  </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('statement-search')?.addEventListener('input', function() {
  filterStatements();
});

function filterStatements() {
  const q = (document.getElementById('statement-search')?.value || '').toLowerCase().trim();
  const payFilter = document.getElementById('payment-filter')?.value || 'all';
  document.querySelectorAll('.statement-card').forEach(card => {
    const name = card.getAttribute('data-member-name') || '';
    const payment = card.getAttribute('data-payment') || 'unpaid';
    const matchSearch = !q || name.includes(q);
    const matchPayment = payFilter === 'all' || payment === payFilter;
    card.style.display = (matchSearch && matchPayment) ? '' : 'none';
  });
}
</script>
<?php endif; ?>
