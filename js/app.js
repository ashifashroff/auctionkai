/* ── AuctionKai — Main JavaScript ─────────────────── */

// ─── ADD VEHICLE FORM: Toggle sold/unsold fields ────
function toggleSoldFields(isSold) {
  document.querySelectorAll('.sold-fields').forEach(el => {
    el.disabled = !isSold;
    if (!isSold) el.value = '';
  });
  document.querySelectorAll('.nagare-field').forEach(el => {
    if (isSold) { const inp = el.querySelector('input'); inp.value = ''; inp.disabled = true; }
    else { el.querySelector('input').disabled = false; }
  });
}

// ─── MODAL: Toggle sold/unsold fields ───────────────
function toggleModalSoldFields(isSold) {
  document.querySelectorAll('.modal-sold-field').forEach(el => {
    el.disabled = !isSold;
    if (!isSold) el.value = '';
  });
  document.querySelectorAll('.modal-nagare-field').forEach(el => {
    const inp = el.querySelector('input');
    if (isSold) { inp.value = ''; inp.disabled = true; }
    else { inp.disabled = false; }
  });
}

// ─── ADD VEHICLE: Member autocomplete ───────────────
function filterMembers() {
  const q = document.getElementById('memberSearch').value.toLowerCase();
  const dd = document.getElementById('memberDropdown');
  const hidden = document.getElementById('memberId');
  hidden.value = '';
  if (!q) { dd.style.display = 'none'; return; }
  const filtered = membersData.filter(m => m.name.toLowerCase().includes(q) || m.phone.includes(q));
  if (!filtered.length) { dd.style.display = 'none'; return; }
  dd.innerHTML = filtered.map(m => `<div class="member-dropdown-item" data-id="${m.id}" onclick="selectMember(${m.id},'${m.name.replace(/'/g,"\\'")}')">${m.name}<span class="mdi-phone">${m.phone}</span></div>`).join('');
  dd.style.display = 'block';
}

function selectMember(id, name) {
  document.getElementById('memberSearch').value = name;
  document.getElementById('memberId').value = id;
  document.getElementById('memberDropdown').style.display = 'none';
}

function showMemberResults() {
  const q = document.getElementById('memberSearch').value;
  if (q) filterMembers();
}

// ─── MODAL: Member autocomplete ─────────────────────
function filterModalMembers() {
  const q = document.getElementById('edit_memberSearch').value.toLowerCase();
  const dd = document.getElementById('edit_memberDropdown');
  const hidden = document.getElementById('edit_memberId');
  hidden.value = '';
  if (!q) { dd.style.display = 'none'; return; }
  const filtered = membersData.filter(m => m.name.toLowerCase().includes(q) || m.phone.includes(q));
  if (!filtered.length) { dd.style.display = 'none'; return; }
  dd.innerHTML = filtered.map(m => `<div class="member-dropdown-item" onclick="selectModalMember(${m.id},'${m.name.replace(/'/g,"\\'")}')">${m.name}<span class="mdi-phone">${m.phone}</span></div>`).join('');
  dd.style.display = 'block';
}

function selectModalMember(id, name) {
  document.getElementById('edit_memberSearch').value = name;
  document.getElementById('edit_memberId').value = id;
  document.getElementById('edit_memberDropdown').style.display = 'none';
}

// ─── EDIT MODAL: Open ───────────────────────────────
function openEditModal(vehicleId) {
  const modal = document.getElementById('editModal');
  const msg = document.getElementById('modalMsg');
  msg.style.display = 'none';

  // Reset form
  document.getElementById('editForm').reset();
  document.getElementById('edit_id').value = '';
  document.getElementById('edit_memberId').value = '';
  document.getElementById('edit_memberSearch').value = '';

  // Show loading state
  document.getElementById('editSubmitBtn').disabled = true;
  document.getElementById('editSubmitBtn').textContent = 'Loading…';
  modal.style.display = 'flex';
  toggleModalSoldFields(true);

  // Fetch vehicle data
  fetch(`get_vehicle.php?id=${vehicleId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        showModalMsg(data.error, 'error');
        return;
      }
      document.getElementById('edit_id').value = data.id;
      document.getElementById('edit_memberId').value = data.member_id;
      document.getElementById('edit_memberSearch').value = data.member_name;
      document.getElementById('edit_make').value = data.make;
      document.getElementById('edit_model').value = data.model;
      document.getElementById('edit_lot').value = data.lot;
      document.getElementById('edit_soldPrice').value = data.sold_price || '';
      document.getElementById('edit_recycleFee').value = data.recycle_fee || '';
      document.getElementById('edit_listingFee').value = data.listing_fee || '';
      document.getElementById('edit_soldFee').value = data.sold_fee || '';
      document.getElementById('edit_nagareFee').value = data.nagare_fee || '';
      document.getElementById('edit_otherFee').value = data.other_fee || '';
      document.getElementById('edit_sold').checked = data.sold;
      toggleModalSoldFields(data.sold);
    })
    .catch(() => showModalMsg('Failed to load vehicle data.', 'error'))
    .finally(() => {
      document.getElementById('editSubmitBtn').disabled = false;
      document.getElementById('editSubmitBtn').textContent = 'Save Changes';
    });
}

// ─── EDIT MODAL: Close ──────────────────────────────
function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// ─── EDIT MODAL: Messages ───────────────────────────
function showModalMsg(text, type) {
  const msg = document.getElementById('modalMsg');
  msg.textContent = text;
  msg.style.display = 'block';
  msg.style.background = type === 'error' ? 'rgba(231,76,60,.15)' : 'rgba(46,204,113,.15)';
  msg.style.color = type === 'error' ? '#e74c3c' : '#2ecc71';
}

// ─── EDIT MODAL: Submit ─────────────────────────────
function submitEditForm(e) {
  e.preventDefault();
  const btn = document.getElementById('editSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  const msg = document.getElementById('modalMsg');
  msg.style.display = 'none';

  const payload = {
    id:         parseInt(document.getElementById('edit_id').value),
    memberId:   parseInt(document.getElementById('edit_memberId').value),
    make:       document.getElementById('edit_make').value.trim(),
    model:      document.getElementById('edit_model').value.trim(),
    lot:        document.getElementById('edit_lot').value.trim(),
    soldPrice:  parseFloat(document.getElementById('edit_soldPrice').value) || 0,
    recycleFee: parseFloat(document.getElementById('edit_recycleFee').value) || 0,
    listingFee: parseFloat(document.getElementById('edit_listingFee').value) || 0,
    soldFee:    parseFloat(document.getElementById('edit_soldFee').value) || 0,
    nagareFee:  parseFloat(document.getElementById('edit_nagareFee').value) || 0,
    otherFee:   parseFloat(document.getElementById('edit_otherFee').value) || 0,
    sold:       document.getElementById('edit_sold').checked,
  };

  // Frontend validation
  if (!payload.memberId) { showModalMsg('Please select a member.', 'error'); btn.disabled = false; btn.textContent = 'Save Changes'; return false; }
  if (!payload.make) { showModalMsg('Make is required.', 'error'); btn.disabled = false; btn.textContent = 'Save Changes'; return false; }

  fetch('update_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showModalMsg(data.error, 'error');
      return;
    }
    showModalMsg('Vehicle updated successfully!', 'success');
    setTimeout(() => { closeEditModal(); location.reload(); }, 800);
  })
  .catch(() => showModalMsg('Network error. Please try again.', 'error'))
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  });

  return false;
}

// ─── ADD VEHICLE (AJAX) ────────────────────────────
function submitAddVehicle(e) {
  e.preventDefault();
  const fields = document.getElementById('addVehicleFields');
  const btn = document.getElementById('addVehicleBtn');
  const msg = document.getElementById('addVehicleMsg');
  msg.style.display = 'none';

  const payload = {
    memberId:   parseInt(document.getElementById('memberId').value),
    make:       document.getElementById('add_make').value.trim(),
    model:      document.getElementById('add_model').value.trim(),
    lot:        document.getElementById('add_lot').value.trim(),
    soldPrice:  parseFloat(document.getElementById('add_soldPrice').value) || 0,
    recycleFee: parseFloat(document.getElementById('add_recycleFee').value) || 0,
    listingFee: parseFloat(document.getElementById('add_listingFee').value) || 0,
    soldFee:    parseFloat(document.getElementById('add_soldFee').value) || 0,
    nagareFee:  parseFloat(document.getElementById('add_nagareFee').value) || 0,
    otherFee:   parseFloat(document.getElementById('add_otherFee').value) || 0,
    sold:       document.getElementById('add_sold').checked,
    auctionId:  activeAuctionId
  };

  if (!payload.memberId) { showAddMsg('Please select a member.', 'error'); return false; }
  if (!payload.make) { showAddMsg('Make is required.', 'error'); return false; }
  if (!payload.auctionId) { showAddMsg('No active auction selected.', 'error'); return false; }

  // Fade + disable + preloader
  fields.style.opacity = '0.4';
  fields.style.pointerEvents = 'none';
  btn.disabled = true;
  btn.innerHTML = '<span class="add-preloader"></span> Adding…';

  fetch('add_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showAddMsg(data.error, 'error');
      return;
    }
    showAddMsg('Vehicle added successfully!', 'success');
    document.getElementById('addVehicleForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('memberSearch').value = '';
    toggleSoldFields(true);
    setTimeout(() => location.reload(), 600);
  })
  .catch(() => showAddMsg('Network error. Please try again.', 'error'))
  .finally(() => {
    fields.style.opacity = '1';
    fields.style.pointerEvents = 'auto';
    btn.disabled = false;
    btn.textContent = 'Add';
  });

  return false;
}

function showAddMsg(text, type) {
  const msg = document.getElementById('addVehicleMsg');
  msg.textContent = text;
  msg.classList.remove('hidden');
  msg.style.background = type === 'error' ? 'rgba(231,76,60,.15)' : 'rgba(46,204,113,.15)';
  msg.style.color = type === 'error' ? '#e74c3c' : '#2ecc71';
}

// ─── DELETE VEHICLE (AJAX) ─────────────────────────
function deleteVehicle(vehicleId, btn) {
  if (!confirm('Remove this vehicle?')) return;
  btn.disabled = true;
  btn.textContent = '…';
  fetch('delete_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: vehicleId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { alert(data.error); btn.disabled = false; btn.textContent = '×'; return; }
    const row = document.querySelector(`tr[data-vid="${vehicleId}"]`);
    if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .3s'; setTimeout(() => location.reload(), 300); }
    else location.reload();
  })
  .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = '×'; });
}

// ─── GLOBAL EVENT LISTENERS ─────────────────────────

// Close modal on overlay click
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeEditModal();
});

// Close member dropdowns on outside click
document.addEventListener('click', function(e) {
  document.querySelectorAll('.member-dropdown').forEach(dd => {
    if (!dd.contains(e.target) && e.target.id !== 'memberSearch' && e.target.id !== 'edit_memberSearch') dd.style.display = 'none';
  });
});

// Body scroll lock when modal is open
const editModal = document.getElementById('editModal');
if (editModal) {
  const observer = new MutationObserver(() => {
    document.body.style.overflow = editModal.style.display === 'flex' ? 'hidden' : '';
  });
  observer.observe(editModal, { attributes: true, attributeFilter: ['style'] });
}
