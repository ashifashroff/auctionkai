<h2 class="text-lg font-bold mb-5">💴 Special Fees — <?= h($auction['name']) ?></h2>

<!-- Add Special Fee Card -->
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Special Fee</div>
  <form id="addSpecialFeeForm" onsubmit="return submitAddSpecialFee(event)">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="sf_memberSearch" placeholder="Type to search member…" autocomplete="off" required onfocus="showSfMemberResults()" oninput="filterSfMembers()">
        <input type="hidden" id="sf_memberId" required>
        <div id="sf_memberDropdown" class="hidden absolute z-50 w-full mt-1 bg-ak-card border border-ak-border rounded-lg shadow-xl max-h-48 overflow-y-auto"></div>
      </div>
      <div>
        <label class="lbl">Fee Name *</label>
        <input class="inp" id="sf_feeName" placeholder="e.g. Car Wash" required>
      </div>
      <div>
        <label class="lbl">Amount (¥) *</label>
        <input class="inp" id="sf_amount" type="number" min="0" placeholder="0" required>
      </div>
      <div>
        <label class="lbl">Type</label>
        <select class="inp" id="sf_feeType">
          <option value="deduction">− Deduction</option>
          <option value="addition">+ Addition</option>
        </select>
      </div>
    </div>
    <div class="mt-3">
      <label class="lbl">Notes (optional)</label>
      <input class="inp" id="sf_notes" placeholder="e.g. Repair cost">
    </div>
    <div class="mt-3 flex flex-wrap gap-2">
      <span class="text-[10px] text-ak-muted uppercase tracking-wider mr-1 mt-1">Quick:</span>
      <button type="button" class="chip" onclick="sfPreset('Car Wash',1500,'deduction')">🚗 Car Wash</button>
      <button type="button" class="chip" onclick="sfPreset('Bank Charges',2000,'deduction')">🏦 Bank Charges</button>
      <button type="button" class="chip" onclick="sfPreset('Storage',3000,'deduction')">📦 Storage</button>
      <button type="button" class="chip" onclick="sfPreset('Repairs',5000,'deduction')">🔧 Repairs</button>
      <button type="button" class="chip" onclick="sfPreset('Inspection',4000,'deduction')">🔍 Inspection</button>
      <button type="button" class="chip" onclick="sfPreset('Key Duplicate',2500,'deduction')">🔑 Key Dup</button>
      <button type="button" class="chip" onclick="sfPreset('Bonus',5000,'addition')">🎁 Bonus</button>
    </div>
    <div class="mt-4">
      <button type="submit" class="btn btn-gold">+ Add Fee</button>
    </div>
  </form>
</div>

<!-- Records Table -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden">
  <?php
  $allSpecialFees = [];
  foreach ($memberFeesAll as $mId => $fees) {
    $memberName = '';
    foreach ($members as $m) {
      if ((int)$m['id'] === $mId) {
        $memberName = $m['name'];
        break;
      }
    }
    foreach ($fees as $fee) {
      $fee['member_name'] = $memberName;
      $allSpecialFees[] = $fee;
    }
  }
  usort($allSpecialFees, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
  ?>

  <?php if (empty($allSpecialFees)): ?>
  <div class="text-center text-ak-muted py-12">No special fees yet for this auction.</div>
  <?php else: ?>

  <!-- Summary -->
  <div class="px-4 py-2.5 border-b border-ak-border flex items-center justify-between bg-ak-infield/30">
    <span class="text-xs text-ak-muted"><?= count($allSpecialFees) ?> fee(s) total</span>
    <div class="flex gap-4 text-xs font-mono">
      <?php
      $totalDed = array_sum(array_map(fn($f) => $f['fee_type']==='deduction' ? (float)$f['amount'] : 0, $allSpecialFees));
      $totalAdd = array_sum(array_map(fn($f) => $f['fee_type']==='addition' ? (float)$f['amount'] : 0, $allSpecialFees));
      ?>
      <span class="text-ak-red">−¥<?= number_format($totalDed) ?></span>
      <span class="text-ak-green">+¥<?= number_format($totalAdd) ?></span>
    </div>
  </div>

  <!-- Mobile card view -->
  <div class="sm:hidden">
    <?php foreach ($allSpecialFees as $fee):
      $isAdd = $fee['fee_type'] === 'addition';
    ?>
    <div id="sf-row-<?= (int)$fee['id'] ?>" class="p-4 border-b border-ak-border animate-fade-in">
      <div class="flex justify-between items-start">
        <div class="flex-1 min-w-0">
          <div class="font-medium text-ak-text truncate"><?= h($fee['member_name']) ?></div>
          <div class="text-ak-text2 text-sm"><?= h($fee['fee_name']) ?></div>
        </div>
        <div class="flex items-center gap-2 ml-2">
          <span class="font-mono font-bold text-sm <?= $isAdd ? 'text-ak-green' : 'text-ak-red' ?>"><?= $isAdd ? '+' : '−' ?>¥<?= number_format((float)$fee['amount']) ?></span>
          <button class="btn-icon" onclick="sfDeleteFee(<?= (int)$fee['id'] ?>, <?= (int)$fee['member_id'] ?>, <?= (int)$activeAuctionId ?>)">×</button>
        </div>
      </div>
      <div class="flex items-center gap-2 mt-1.5 text-xs text-ak-muted">
        <span class="text-[11px] px-2 py-0.5 rounded-full font-bold <?= $isAdd ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/10 text-ak-red' ?>"><?= $isAdd ? '+ Addition' : '− Deduction' ?></span>
        <?php if (!empty($fee['notes'])): ?><span class="truncate"><?= h($fee['notes']) ?></span><?php endif; ?>
        <span class="ml-auto font-mono"><?= date('Y-m-d', strtotime($fee['created_at'])) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Desktop table view -->
  <div class="hidden sm:block overflow-x-auto">
    <table class="vt">
      <thead>
        <tr>
          <th>Member</th>
          <th>Fee Name</th>
          <th>Notes</th>
          <th>Type</th>
          <th class="r">Amount</th>
          <th>Added</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="specialFeesTableBody">
        <?php foreach ($allSpecialFees as $fee):
          $isAdd = $fee['fee_type'] === 'addition';
        ?>
        <tr id="sf-row-<?= (int)$fee['id'] ?>" class="animate-fade-in">
          <td class="font-medium text-ak-text"><?= h($fee['member_name']) ?></td>
          <td class="text-ak-text2"><?= h($fee['fee_name']) ?></td>
          <td class="text-ak-muted text-xs"><?= h($fee['notes'] ?? '—') ?></td>
          <td>
            <span class="text-[11px] px-2 py-0.5 rounded-full font-bold <?= $isAdd ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/10 text-ak-red' ?>">
              <?= $isAdd ? '+ Addition' : '− Deduction' ?>
            </span>
          </td>
          <td class="text-right font-mono font-bold <?= $isAdd ? 'text-ak-green' : 'text-ak-red' ?>">
            <?= $isAdd ? '+' : '−' ?>¥<?= number_format((float)$fee['amount']) ?>
          </td>
          <td class="text-ak-muted text-xs font-mono"><?= date('Y-m-d', strtotime($fee['created_at'])) ?></td>
          <td>
            <button class="btn-icon" onclick="sfDeleteFee(<?= (int)$fee['id'] ?>, <?= (int)$fee['member_id'] ?>, <?= (int)$activeAuctionId ?>)">×</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
