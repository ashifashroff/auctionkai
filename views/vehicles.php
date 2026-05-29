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

    <!-- Live payout preview — shown/hidden by JS -->
    <div id="vehiclePreview" style="display:none" class="mt-4 pt-4 border-t border-ak-border">
      <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Estimated Payout Preview</div>
      <div class="flex gap-8 flex-wrap items-start">

        <!-- Received column (sold mode only) -->
        <div id="pvSoldBlock">
          <div class="pv-row"><span class="pv-l">Sold Price</span><span class="pv-v text-ak-green" id="pvSoldPrice">—</span></div>
          <div class="pv-row"><span class="pv-l">+ Tax 10%</span><span class="pv-v text-ak-green" id="pvTax">—</span></div>
          <div class="pv-row"><span class="pv-l">+ Recycle</span><span class="pv-v text-ak-green" id="pvRecycle">—</span></div>
          <div class="pv-row pv-subtotal"><span class="pv-l">Total In</span><span class="pv-v text-ak-gold" id="pvTotalRec">—</span></div>
        </div>

        <!-- Deductions column -->
        <div>
          <div class="pv-row" id="pvListingRow"><span class="pv-l">− Listing Fee</span><span class="pv-v text-ak-red" id="pvListing">—</span></div>
          <div class="pv-row" id="pvSoldFeeRow"><span class="pv-l">− Sold Fee</span><span class="pv-v text-ak-red" id="pvSoldFee">—</span></div>
          <div class="pv-row" id="pvNagareRow" style="display:none"><span class="pv-l">− Nagare Fee</span><span class="pv-v text-ak-red" id="pvNagare">—</span></div>
        </div>

        <!-- Net payout — the big number -->
        <div class="pv-net-block">
          <div>
            <div class="text-[10px] text-ak-muted uppercase tracking-widest mb-1">Net Payout</div>
            <div class="text-[10px] text-ak-muted">(estimate)</div>
          </div>
          <div class="font-mono font-bold text-2xl leading-none" id="pvNet">—</div>
        </div>

      </div>

      <!-- Disclaimer -->
      <div class="pv-disclaimer">
        <span class="pv-disclaimer-icon">ⓘ</span>
        <span>
          This is a <strong>per-vehicle estimate only</strong>.
          Commission (¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>/member)
          and any other shared fees are <strong>not included here</strong>.
          The full settlement with all deductions is shown in the
          <strong>Statements tab</strong>.
        </span>
      </div>
    </div>
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
  <button class="vf-btn" onclick="expandAllGroups()" title="Expand all members">↕ Expand</button>
  <button class="vf-btn" onclick="collapseAllGroups()" title="Collapse all members">↕ Collapse</button>
</div>

<!-- Vehicles Table -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden" id="vehicles-table-wrap">
  <div class="flex justify-between items-center mb-3 px-4 pt-3">
    <div class="text-[10px] uppercase tracking-wider text-ak-muted">
      <span class="text-ak-muted text-[11px]" id="vehicles-count-header"></span>
    </div>
    <div class="flex items-center gap-3">
      <button onclick="toggleAllColumns(this)" class="text-[11px] text-ak-muted hover:text-ak-gold transition-colors flex items-center gap-1">
        <span>⊞</span>
        <span class="toggle-col-label">Show all columns</span>
      </button>
    </div>
  </div>
  <div class="vt-scroll-wrap">
  <table class="vt vehicles-table-desktop" id="vehicles-table">
    <thead class="sticky-thead">
      <tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Total</th><th>Status</th><th class="w-[90px]">Actions</th></tr>
    </thead>
    <tbody id="vehicles-tbody">
      <!-- populated by JS -->
    </tbody>
  </table>
  </div><!-- /vt-scroll-wrap -->

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
