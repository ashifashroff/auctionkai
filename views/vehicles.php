<h2 class="text-lg font-bold mb-5">Vehicle Listings — <?= h($auction['name']) ?></h2>

<!-- Add Vehicle Form -->
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Vehicle</div>
  <form id="addVehicleForm" onsubmit="return submitAddVehicle(event)" data-parsley-validate>
    <div class="grid grid-cols-6 gap-2 add-vehicle-grid ar-vehicles-6" id="addVehicleFields">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="memberSearch" name="memberSearch" placeholder="Type to search member…" autocomplete="off" data-parsley-required="true" onfocus="showMemberResults()" oninput="filterMembers()">
        <input type="hidden" id="memberId" name="memberId">
        <div id="memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" id="add_make" name="make" placeholder="Toyota" data-parsley-required="true"></div>
      <div><label class="lbl">Model</label><input class="inp" id="add_model" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Lot #</label><input class="inp" id="add_lot" name="lot" placeholder="A-001"></div>
      <div><label class="lbl">Sold Price (¥) *</label><input class="inp font-mono sold-fields" type="number" id="add_soldPrice" name="soldPrice" placeholder="850000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_recycleFee" name="recycleFee" placeholder="15000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_listingFee" name="listingFee" placeholder="3000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_soldFee" name="soldFee" placeholder="25500" data-parsley-type="number" data-parsley-min="0"></div>
      <div class="nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="add_nagareFee" name="nagareFee" placeholder="8000" data-parsley-type="number" data-parsley-min="0" disabled></div>
      <div class="flex items-end pt-[22px] gap-2">
        <label class="flex items-center gap-1.5 text-ak-muted text-xs cursor-pointer"><input type="checkbox" id="add_sold" name="sold" checked class="accent-ak-gold" onchange="toggleSoldFields(this.checked)"> Sold</label>
        <button class="btn btn-gold" type="submit" id="addVehicleBtn">Add</button>
      </div>
    </div>
    <div id="addVehicleMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
  </form>
</div>

<!-- Search + Controls Row -->
<div class="vehicles-search-wrap">
  <div class="search-icon-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="vehicle-search" aria-label="Search vehicles" class="vehicles-search-input" placeholder="Search lot, make, model, member..." autocomplete="off">
  </div>
  <div class="per-page-wrap">
    <span>Show</span>
    <select class="per-page-select" id="per-page-select">
      <option value="10">10</option>
      <option value="25" selected>25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
    <span>per page</span>
  </div>
  <div class="vehicles-count-badge" id="vehicles-count-badge">— vehicles</div>
</div>

<!-- Vehicles Table -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden" id="vehicles-table-wrap">
  <div class="flex justify-between items-center mb-3 px-4 pt-3">
    <div class="text-[10px] uppercase tracking-wider text-ak-muted">
      <span id="vehicles-count-badge">Loading…</span>
    </div>
    <div class="flex items-center gap-3">
      <button onclick="toggleAllColumns(this)" class="text-[11px] text-ak-muted hover:text-ak-gold transition-colors flex items-center gap-1">
        <span>⊞</span>
        <span class="toggle-col-label">Show all columns</span>
      </button>
    </div>
  </div>
  <table class="vt vehicles-table-desktop" id="vehicles-table">
    <thead>
      <tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Total</th><th>Status</th><th class="w-[90px]">Actions</th></tr>
    </thead>
    <tbody id="vehicles-tbody">
      <!-- populated by JS -->
    </tbody>
  </table>

  <!-- Pagination Controls -->
  <div class="pagination-wrap" id="pagination-wrap">
    <div class="pagination-info" id="pagination-info"></div>
    <div class="pagination-controls" id="pagination-controls"></div>
  </div>
</div>

<!-- Mobile cards container -->
<div class="vehicle-card-mobile" id="vehicle-cards-mobile">
  <!-- populated by JS -->
</div>
