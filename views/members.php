<h2 class="text-lg font-bold mb-5">Members / Sellers — <?= h($auction['name']) ?></h2>
<div class="bg-ak-card rounded-xl p-4 md:p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add New Member</div>
  <form onsubmit="return submitAddMember(event)" data-parsley-validate>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
      <div><label class="lbl">Full Name *</label><input class="inp" name="name" placeholder="e.g. Ahmad Hassan" data-parsley-required="true"></div>
      <div><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="email@example.com" data-parsley-type="email"></div>
      <div class="flex items-end pt-[22px]"><button class="btn btn-gold w-full" type="submit" id="addMemberBtn">+ Add</button></div>
    </div>
  </form>
  <div id="addMemberMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
</div>

<!-- CSV Import Card -->
<div class="bg-ak-card rounded-xl p-4 md:p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Bulk Import via CSV</div>
  <div class="flex flex-col sm:flex-row items-start gap-4">
    <div class="flex-1 min-w-0">
      <div class="text-ak-text2 text-sm mb-2">Upload a CSV file to import multiple members at once.</div>
      <div class="text-ak-muted text-xs leading-relaxed">Supported columns: <span class="font-mono text-ak-gold">name, phone, email</span><br>First row can be a header row — auto-detected.<br>Duplicates are automatically skipped.</div>
    </div>
    <div class="flex flex-col gap-2 w-full sm:w-auto">
      <a href="api/csv_template.php" class="btn btn-dark btn-sm text-center">↓ Download Template</a>
      <label class="btn btn-dark btn-sm cursor-pointer text-center" for="csvImportInput">📁 Choose File</label>
      <input type="file" id="csvImportInput" accept=".csv,.txt" class="sr-only" onchange="showCsvFileName(this)">
      <button id="csvImportBtn" class="btn btn-gold btn-sm opacity-50 cursor-not-allowed" disabled onclick="handleCsvImport(document.getElementById('csvImportInput'))">↑ Import CSV</button>
    </div>
    <div id="csvFileName" class="text-ak-muted text-xs mt-2 hidden">📄 <span id="csvFileNameText" class="text-ak-gold font-mono"></span></div>
  </div>
  <div id="csvImportResult" class="hidden mt-3 p-3 rounded-lg text-sm border"></div>
</div>

<!-- Search + Controls -->
<div class="vehicles-search-wrap">
  <div class="search-icon-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="member-search" class="vehicles-search-input" placeholder="Search members by name, phone, or email..." autocomplete="off">
  </div>
  <div class="per-page-wrap">
    <span>Show</span>
    <select class="per-page-select" id="member-per-page-select">
      <option value="10">10</option>
      <option value="25" selected>25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
    <span>per page</span>
  </div>
  <div class="vehicles-count-badge" id="members-count-badge">— members</div>
</div>

<!-- Members List -->
<div class="flex flex-col gap-2.5" id="members-list-container">
  <!-- populated by JS -->
</div>

<!-- Pagination -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden mt-3" id="members-pagination-wrap" style="display:none">
  <div class="pagination-wrap">
    <div class="pagination-info" id="members-pagination-info"></div>
    <div class="pagination-controls" id="members-pagination-controls"></div>
  </div>
</div>
