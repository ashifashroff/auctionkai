/* ── AuctionKai — Fees JS ────────────────────── */

// ── Special Member Fees ───────────────────────
document.getElementById('addFeeForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const memberId = document.getElementById('af_memberId').value;
  const feeName = document.getElementById('af_feeName').value.trim();
  const amount = parseFloat(document.getElementById('af_amount').value);
  const feeType = document.getElementById('af_feeType').value;
  const notes = document.getElementById('af_notes').value.trim();
  const btn = document.getElementById('addFeeBtn');

  btn.textContent = 'Adding...'; btn.disabled = true;
  try {
    const res = await fetch('api/member_fees.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _tok: CSRF_TOKEN, action: 'add', auction_id: activeAuctionId, member_id: parseInt(memberId), fee_name: feeName, amount, fee_type: feeType, notes })
    });
    const data = await res.json();
    if (data.success) {
      showToast(`✓ ${feeName} added`, 'success');
      closeAddFeeModal();
      if (typeof FeesPager !== 'undefined') FeesPager.load();
    } else { showToast(data.message || 'Failed', 'error'); }
  } catch { showToast('Connection error', 'error'); }
  btn.textContent = '+ Add Fee'; btn.disabled = false;
});

function openEditFeeModal(feeId, memberId, feeName, amount, feeType, notes) {
  document.getElementById('ef_feeId').value = feeId;
  document.getElementById('ef_memberId').value = memberId;
  document.getElementById('ef_feeName').value = feeName;
  document.getElementById('ef_amount').value = amount;
  document.getElementById('ef_feeType').value = feeType;
  document.getElementById('ef_notes').value = notes;
  const m = document.getElementById('editFeeModal');
  m.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  document.getElementById('ef_feeName').focus();
}

function closeEditFeeModal() {
  document.getElementById('editFeeModal').style.display = 'none';
  document.body.style.overflow = '';
}

document.getElementById('editFeeForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const feeId = document.getElementById('ef_feeId').value;
  const memberId = document.getElementById('ef_memberId').value;
  const feeName = document.getElementById('ef_feeName').value.trim();
  const amount = parseFloat(document.getElementById('ef_amount').value);
  const feeType = document.getElementById('ef_feeType').value;
  const notes = document.getElementById('ef_notes').value.trim();
  const btn = document.getElementById('editFeeBtn');

  btn.textContent = 'Saving...'; btn.disabled = true;
  try {
    const res = await fetch('api/member_fees.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _tok: CSRF_TOKEN, action: 'edit', auction_id: activeAuctionId, member_id: parseInt(memberId), fee_id: parseInt(feeId), fee_name: feeName, amount, fee_type: feeType, notes })
    });
    const data = await res.json();
    if (data.success) {
      showToast(`✓ ${feeName} updated`, 'success');
      closeEditFeeModal();
    } else { showToast(data.message || 'Failed', 'error'); }
  } catch { showToast('Connection error', 'error'); }
  btn.textContent = '💾 Save Changes'; btn.disabled = false;
});

// ── Special Fees (matches vehicle tab style) ──

function showSfMemberResults() {
  filterSfMembers();
}

function filterSfMembers() {
  const input = document.getElementById('sf_memberSearch');
  const dropdown = document.getElementById('sf_memberDropdown');
  if (!input || !dropdown) return;
  const query = input.value.toLowerCase().trim();
  const filtered = membersData.filter(m => m.name.toLowerCase().includes(query));
  if (filtered.length === 0 || query === '') { dropdown.style.display = 'none'; return; }
  dropdown.innerHTML = filtered.map(m => `
    <div class="member-dropdown-item" onclick="sfSelectMember(${m.id}, '${m.name.replace(/'/g,"\\'")}')">
      ${m.name}<span class="mdi-phone">${m.phone || ''}</span>
    </div>
  `).join('');
  dropdown.style.display = 'block';
}

function sfSelectMember(id, name) {
  document.getElementById('sf_memberSearch').value = name;
  document.getElementById('sf_memberId').value = id;
  document.getElementById('sf_memberDropdown').style.display = 'none';
}

// Close dropdown on outside click
document.addEventListener('click', (e) => {
  const dropdown = document.getElementById('sf_memberDropdown');
  const input = document.getElementById('sf_memberSearch');
  if (dropdown && input && !dropdown.contains(e.target) && e.target !== input) {
    dropdown.style.display = 'none';
  }
});

// Quick preset
function sfSetPreset(name, amount, type) {
  const nameEl = document.getElementById('sf_feeName');
  const amountEl = document.getElementById('sf_amount');
  const typeEl = document.getElementById('sf_feeType');
  if (nameEl) nameEl.value = name;
  if (amountEl) amountEl.value = amount;
  if (typeEl) typeEl.value = type;
  document.getElementById('sf_memberSearch')?.focus();
}

// Submit add special fee (matches submitAddVehicle pattern)
async function submitAddSpecialFee(event) {
  event.preventDefault();
  const memberId = document.getElementById('sf_memberId')?.value;
  const feeName = document.getElementById('sf_feeName')?.value.trim();
  const amount = parseFloat(document.getElementById('sf_amount')?.value);
  const feeType = document.getElementById('sf_feeType')?.value;
  const notes = document.getElementById('sf_notes')?.value.trim();
  const msgDiv = document.getElementById('addSpecialFeeMsg');
  const btn = document.getElementById('addSpecialFeeBtn');

  if (!memberId) { showToast('Please select a member', 'warning'); return false; }
  if (!feeName) { showToast('Please enter a fee name', 'warning'); return false; }
  if (!amount || amount <= 0) { showToast('Please enter a valid amount', 'warning'); return false; }

  const origText = btn.textContent;
  btn.textContent = 'Adding…';
  btn.disabled = true;

  try {
    const res = await fetch('api/member_fees.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        _tok: CSRF_TOKEN,
        action: 'add',
        auction_id: activeAuctionId,
        member_id: parseInt(memberId),
        fee_name: feeName,
        amount: amount,
        fee_type: feeType,
        notes: notes
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast(`✓ ${feeName} added`, 'success');
      document.getElementById('sf_memberSearch').value = '';
      document.getElementById('sf_memberId').value = '';
      document.getElementById('sf_feeName').value = '';
      document.getElementById('sf_amount').value = '';
      document.getElementById('sf_notes').value = '';

      const tbody = document.getElementById('specialFeesTableBody');
      if (tbody && data.fee) {
        const f = data.fee;
        const isAdd = f.fee_type === 'addition';
        const today = new Date().toISOString().slice(0, 10);
        const member = membersData.find(m => m.id == memberId);
        const memberName = member ? member.name : '?';
        const emptyRow = tbody.querySelector('td[colspan="7"]')?.closest('tr');
        if (emptyRow) emptyRow.remove();
        const tr = document.createElement('tr');
        tr.id = `sf-row-${f.id}`;
        tr.className = 'animate-fade-in';
        tr.innerHTML = `
          <td class="font-medium text-ak-text">${memberName}</td>
          <td class="text-ak-text2">${f.fee_name}</td>
          <td class="text-ak-muted text-xs">${f.notes || '—'}</td>
          <td><span class="text-[11px] px-2 py-0.5 rounded-full font-bold ${isAdd ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/10 text-ak-red'}">${isAdd ? '+ Addition' : '− Deduction'}</span></td>
          <td class="text-right font-mono font-bold ${isAdd ? 'text-ak-green' : 'text-ak-red'}">${isAdd ? '+' : '−'}¥${parseInt(f.amount).toLocaleString('ja-JP')}</td>
          <td class="text-ak-muted text-xs font-mono">${today}</td>
          <td><button class="btn-icon" onclick="sfDeleteFee(${f.id}, ${memberId}, ${activeAuctionId})">×</button></td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);
      }
    } else {
      showToast(data.message || 'Failed to add fee', 'error');
    }
  } catch {
    showToast('Connection error. Please try again.', 'error');
  }
  btn.textContent = origText;
  btn.disabled = false;
  return false;
}

// Delete special fee (same pattern as deleteVehicle in vehicle tab)
async function sfDeleteFee(feeId, memberId, auctionId) {
  if (!confirm('Delete this fee?')) return;
  try {
    const res = await fetch('api/member_fees.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _tok: CSRF_TOKEN, action: 'delete', auction_id: auctionId, member_id: memberId, fee_id: feeId })
    });
    const data = await res.json();
    if (data.success) {
      const row = document.getElementById(`sf-row-${feeId}`);
      if (row) {
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        row.style.transition = 'all 0.2s ease';
        setTimeout(() => row.remove(), 200);
      }
      showToast('Fee deleted', 'warning');
    } else {
      showToast(data.message || 'Delete failed', 'error');
    }
  } catch {
    showToast('Connection error. Please try again.', 'error');
  }
}

// Modal backdrop close
document.getElementById('editFeeModal')?.addEventListener('click', function(e) { if (e.target === this) closeEditFeeModal(); });
