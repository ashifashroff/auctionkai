<!-- Edit Vehicle Modal -->
<div id="editModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center hidden" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[720px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit Vehicle</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditModal()">×</button>
    </div>
    <div id="modalMsg" class="hidden mb-3 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
    <form id="editForm" onsubmit="return submitEditForm(event)" data-parsley-validate>
      <input type="hidden" id="edit_id" name="id">
      <div class="grid grid-cols-2 gap-3 max-[600px]:grid-cols-1">
        <div class="relative">
          <label class="lbl">Member *</label>
          <input class="inp" id="edit_memberSearch" placeholder="Type to search member…" autocomplete="off" oninput="filterModalMembers()" data-parsley-required="true">
          <input type="hidden" id="edit_memberId" name="memberId">
          <div id="edit_memberDropdown" class="member-dropdown" style="display:none"></div>
        </div>
        <div><label class="lbl">Make *</label><input class="inp" id="edit_make" name="make" data-parsley-required="true"></div>
        <div><label class="lbl">Model</label><input class="inp" id="edit_model" name="model"></div>
        <div><label class="lbl">Lot #</label><input class="inp" id="edit_lot" name="lot"></div>
        <div><label class="lbl">Sold Price (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldPrice" name="soldPrice" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_recycleFee" name="recycleFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_listingFee" name="listingFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldFee" name="soldFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div class="modal-nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="edit_nagareFee" name="nagareFee" data-parsley-type="number" data-parsley-min="0" disabled></div>
        </div>
      <div class="flex items-center gap-3 mt-4 pt-4 border-t border-ak-border">
        <label class="flex items-center gap-1.5 text-ak-muted text-xs cursor-pointer">
          <input type="checkbox" id="edit_sold" name="sold" class="accent-ak-gold" onchange="toggleModalSoldFields(this.checked)"> Sold
        </label>
        <div class="flex-1"></div>
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm" id="editSubmitBtn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Member Detail Modal -->
<div id="memberDetailModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[800px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-ak-gold text-lg font-bold" id="mdName">Member</h3>
        <div class="text-ak-muted text-xs mt-1" id="mdContact"></div>
      </div>
      <div class="flex items-center gap-3">
        <a id="mdPdfLink" class="btn btn-gold btn-sm" href="#" target="_blank">↓ PDF</a>
        <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeMemberDetail()">×</button>
      </div>
    </div>
    <div id="mdContent"><div class="text-center text-ak-muted py-12">Loading…</div></div>
  </div>
</div>

<!-- Remove Member Modal -->
<div id="removeMemberModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[420px] p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-red text-lg font-bold">🗑 Remove Member</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeRemoveMemberModal()">×</button>
    </div>
    <div class="mb-5">
      <div class="text-ak-text text-sm mb-2">Are you sure you want to remove <b id="removeMemberName" class="text-ak-gold"></b>?</div>
      <div class="text-ak-muted text-xs">This will also remove all their vehicles from this auction. This cannot be undone.</div>
    </div>
    <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
      <button class="btn btn-dark btn-sm" onclick="closeRemoveMemberModal()">Cancel</button>
      <button class="btn btn-sm bg-ak-red/15 text-ak-red border border-ak-red/30 hover:bg-ak-red/25" id="confirmRemoveMemberBtn" onclick="confirmRemoveMember()">Remove</button>
    </div>
  </div>
</div>

<!-- Edit Member Modal -->
<div id="editMemberModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[500px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit Member</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditMemberModal()">×</button>
    </div>
    <div id="editMemberMsg" class="hidden mb-3 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
    <form id="editMemberForm" onsubmit="return submitEditMember(event)" data-parsley-validate>
      <input type="hidden" id="em_id" name="id">
      <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" id="em_name" name="name" data-parsley-required="true"></div>
      <div class="mb-4"><label class="lbl">Phone</label><input class="inp" id="em_phone" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div class="mb-5"><label class="lbl">Email</label><input class="inp" type="email" id="em_email" name="email" placeholder="email@example.com" data-parsley-type="email"></div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditMemberModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm" id="emSubmitBtn">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<?php require_once 'includes/footer.php'; ?>

<!-- ── Mobile Navigation Menu ── -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mobile-menu-panel">
    <div class="flex justify-between items-center mb-6">
      <div class="text-ak-gold font-bold">⚡ <?= h($brand['brand_name'] ?? 'AuctionKai') ?></div>
      <button onclick="document.getElementById('hamburgerBtn').classList.remove('open'); document.getElementById('mobileMenu').classList.remove('open');" class="text-ak-muted hover:text-ak-text text-xl">✕</button>
    </div>
    <?php foreach ($tabs as $key => $t): ?>
    <a href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>" class="<?= $tab === $key ? 'active' : '' ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
    <?php endforeach; ?>
    <div class="border-t border-ak-border my-4"></div>
    <a href="profile.php" class="">👤 Profile</a>
    <a href="help.php" class="">❓ Help</a>
    <a href="about.php" class="">ℹ️ About</a>
    <div class="border-t border-ak-border my-4"></div>
    <a href="auth/logout.php" class="text-ak-red">🚪 Logout</a>
  </div>
</div>

<!-- ─── Mobile Bottom Navigation ─── -->
<div class="fixed bottom-0 left-0 right-0 bg-ak-bg2 border-t border-ak-border md:hidden z-50 safe-bottom">
  <div class="flex justify-around items-center h-14">
    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
    <a href="?tab=<?= $tabKey ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>" class="flex flex-col items-center justify-center gap-0.5 text-[10px] font-bold py-1 px-2 <?= $tab === $tabKey ? 'text-ak-gold' : 'text-ak-muted' ?>">
      <span class="text-lg"><?= $tabInfo['icon'] ?></span>
      <span><?= $tabInfo['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<!-- Toast Container -->
<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none"></div>

<!-- Keyboard Shortcuts Modal -->
<div class="shortcuts-modal-overlay" id="shortcuts-modal-overlay">
  <div class="shortcuts-modal">
    <h3><span>⌨ Keyboard Shortcuts</span><button onclick="closeShortcutsModal()" style="background:none;border:none;color:#6A88A0;font-size:20px;cursor:pointer;line-height:1">×</button></h3>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">Navigation</div>
      <div class="shortcut-row"><span>Go to Members tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">M</span></div></div>
      <div class="shortcut-row"><span>Go to Vehicles tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">V</span></div></div>
      <div class="shortcut-row"><span>Go to Statements tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">S</span></div></div>
      <div class="shortcut-row"><span>Go to Dashboard tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">D</span></div></div>
    </div>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">Actions</div>
      <div class="shortcut-row"><span>Add new vehicle</span><div class="shortcut-keys"><span class="kbd">N</span></div></div>
      <div class="shortcut-row"><span>Add new member</span><div class="shortcut-keys"><span class="kbd">Shift</span><span class="shortcut-plus">+</span><span class="kbd">N</span></div></div>
      <div class="shortcut-row"><span>Focus lot number field</span><div class="shortcut-keys"><span class="kbd">L</span></div></div>
      <div class="shortcut-row"><span>Print all PDFs</span><div class="shortcut-keys"><span class="kbd">Ctrl</span><span class="shortcut-plus">+</span><span class="kbd">P</span></div></div>
    </div>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">General</div>
      <div class="shortcut-row"><span>Show this help</span><div class="shortcut-keys"><span class="kbd">?</span></div></div>
      <div class="shortcut-row"><span>Close modal / dialog</span><div class="shortcut-keys"><span class="kbd">Esc</span></div></div>
      <div class="shortcut-row"><span>Search vehicles</span><div class="shortcut-keys"><span class="kbd">/</span></div></div>
    </div>
    <div style="text-align:center;margin-top:8px;font-size:11px;color:#3A5570">Shortcuts are disabled when typing in input fields</div>
  </div>
</div>
<div class="shortcut-hint" id="shortcut-hint"></div>

<!-- Edit Fee Modal -->
<div id="editFeeModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl p-8 max-w-[520px] w-[92%] shadow-2xl max-h-[90vh] overflow-y-auto">
    <h3 class="text-ak-gold font-bold text-lg mb-5">✎ Edit Fee</h3>
    <form id="editFeeForm" data-parsley-validate>
      <input type="hidden" id="ef_feeId">
      <input type="hidden" id="ef_memberId">
      <div class="mb-4"><label class="lbl">Fee Name *</label><input class="inp" id="ef_feeName" data-parsley-required="true" data-parsley-required-message="Fee name is required"></div>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div><label class="lbl">Amount (¥) *</label><input class="inp font-mono" type="number" id="ef_amount" min="1" data-parsley-required="true" data-parsley-type="number" data-parsley-min="1" data-parsley-required-message="Amount is required"></div>
        <div><label class="lbl">Type</label><select class="inp" id="ef_feeType"><option value="deduction">− Deduction</option><option value="addition">+ Addition</option></select></div>
      </div>
      <div class="mb-5"><label class="lbl">Notes</label><input class="inp" id="ef_notes" placeholder="Optional"></div>
      <div class="flex gap-3">
        <button type="button" onclick="closeEditFeeModal()" class="btn btn-dark flex-1">Cancel</button>
        <button type="submit" id="editFeeBtn" class="btn btn-gold flex-1">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>