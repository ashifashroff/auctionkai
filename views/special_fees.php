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
        <div id="sf_memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div class="col-span-2">
        <label class="lbl">Fee Name *</label>
        <input class="inp" id="sf_feeName" placeholder="e.g. Car Wash Fee" required>
      </div>
      <div>
        <label class="lbl">Amount (¥) *</label>
        <input class="inp font-mono" type="number" id="sf_amount" placeholder="3000" min="1" required>
      </div>
      <div class="flex items-end gap-2 pt-[22px]">
        <select class="inp" id="sf_feeType">
          <option value="deduction">− Deduction</option>
          <option value="addition">+ Addition</option>
        </select>
        <button class="btn btn-gold" type="submit" id="addSpecialFeeBtn">Add</button>
      </div>
    </div>
    <!-- Notes row -->
    <div class="mt-2">
      <label class="lbl">Notes</label>
      <input class="inp" id="sf_notes" placeholder="Notes (optional) — e.g. Invoice #123, Car plate number">
    </div>

    <!-- Quick preset chips below the form -->
    <div class="mt-3 flex flex-wrap gap-1.5">
      <span class="text-[10px] uppercase tracking-wider text-ak-muted self-center mr-1">Quick:</span>
      <?php
      $presets = [
        ['name' => 'Car Wash', 'amount' => 3000, 'type' => 'deduction'],
        ['name' => 'Bank Charges', 'amount' => 500, 'type' => 'deduction'],
        ['name' => 'Storage Fee', 'amount' => 5000, 'type' => 'deduction'],
        ['name' => 'Transport Extra', 'amount' => 10000, 'type' => 'deduction'],
        ['name' => 'Repair Cost', 'amount' => 20000, 'type' => 'deduction'],
        ['name' => 'Inspection', 'amount' => 8000, 'type' => 'deduction'],
        ['name' => 'Key Duplicate', 'amount' => 3500, 'type' => 'deduction'],
        ['name' => 'Bonus', 'amount' => 5000, 'type' => 'addition'],
      ];
      foreach ($presets as $p):
      ?>
      <button type="button"
        class="px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-all
          <?= $p['type'] === 'addition'
            ? 'border-ak-green/40 text-ak-green hover:bg-ak-green/10'
            : 'border-ak-border text-ak-muted hover:border-ak-gold hover:text-ak-gold' ?>"
        onclick="sfSetPreset('<?= h($p['name']) ?>', <?= $p['amount'] ?>, '<?= $p['type'] ?>')">
        <?= $p['type'] === 'addition' ? '+' : '−' ?>
        <?= h($p['name']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="addSpecialFeeMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
  </form>
</div>

<!-- Records Table (same style as vehicles table) -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-x-auto">
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
      <tr>
        <td colspan="7" class="text-center text-ak-muted py-12">
          No special fees yet for this auction. Use the form above to add fees.
        </td>
      </tr>
      <?php else: ?>
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
          <button class="btn-icon min-w-[36px] min-h-[36px] sticky right-0" onclick="sfDeleteFee(<?= (int)$fee['id'] ?>, <?= (int)$fee['member_id'] ?>, <?= (int)$activeAuctionId ?>)">×</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Summary row at bottom -->
  <?php if (!empty($allSpecialFees)):
    $sumDed = array_sum(array_map(fn($f) => $f['fee_type'] === 'deduction' ? (float)$f['amount'] : 0, $allSpecialFees));
    $sumAdd = array_sum(array_map(fn($f) => $f['fee_type'] === 'addition' ? (float)$f['amount'] : 0, $allSpecialFees));
  ?>
  <div class="px-5 py-3 border-t border-ak-border flex gap-6 text-sm">
    <span class="text-ak-muted"><?= count($allSpecialFees) ?> fee(s) total</span>
    <?php if ($sumDed > 0): ?>
    <span class="font-mono text-ak-red font-bold">−¥<?= number_format($sumDed) ?> deductions</span>
    <?php endif; ?>
    <?php if ($sumAdd > 0): ?>
    <span class="font-mono text-ak-green font-bold">+¥<?= number_format($sumAdd) ?> additions</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>


