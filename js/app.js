/* ── AuctionKai — Main JavaScript ─────────────────── */

// ─── ADD VEHICLE FORM: Toggle sold/unsold fields ────
function toggleSoldFields(isSold) {
  document.querySelectorAll('.sold-fields').forEach(el => {
    el.disabled = !isSold;
    if (!isSold) el.value = '';
  });
  document.querySelectorAll('.nagare-field').forEach(el => {
    el.querySelector('input').disabled = isSold;
  });
}

// ─── MODAL: Toggle sold/unsold fields ───────────────
function toggleModalSoldFields(isSold) {
  document.querySelectorAll('.modal-sold-field').forEach(el => {
    el.disabled = !isSold;
    if (!isSold) el.value = '';
  });
  document.querySelectorAll('.modal-nagare-field').forEach(el => {
    el.querySelector('input').disabled = isSold;
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
  fetch(`api/get_vehicle.php?id=${vehicleId}`)
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

  fetch('api/update_vehicle.php', {
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

  fetch('api/add_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showAddMsg(data.error, 'error');
      // Show inline lot error if duplicate lot
      if (data.error.includes('Lot number already exists')) {
        const lotInput = document.getElementById('add_lot');
        let lotErr = document.getElementById('add_lot_error');
        if (!lotErr) {
          lotErr = document.createElement('div');
          lotErr.id = 'add_lot_error';
          lotErr.style.cssText = 'color:#ef4444;font-size:11px;margin-top:2px;';
          lotInput.parentNode.appendChild(lotErr);
        }
        lotErr.textContent = data.error;
      }
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

// ─── ADD AUCTION (AJAX) ───────────────────────────
function submitAddAuction(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Creating…';
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'add_auction', name:form.name.value, date:form.date.value})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;btn.textContent='+ Create';return;}
    window.location.href = 'index.php?auction_id=' + d.auction_id + '&tab=dashboard';
  }).catch(()=>{alert('Error');btn.disabled=false;btn.textContent='+ Create';});
  return false;
}

// ─── ADD MEMBER (AJAX) ──────────────────────────────
function submitAddMember(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Adding…';
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'add_member', name:form.name.value, phone:form.phone.value, email:form.email.value})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;btn.textContent='+ Add';return;}
    location.reload();
  }).catch(()=>{alert('Error');btn.disabled=false;btn.textContent='+ Add';});
  return false;
}

// ─── SAVE AUCTION (AJAX) ────────────────────────────
function submitSaveAuction(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Saving…';
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'save_auction', auction_id:activeAuctionId, name:form.name.value, date:form.date.value, commissionFee:form.commissionFee.value})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;btn.textContent='Save';return;}
    location.reload();
  }).catch(()=>{alert('Error');btn.disabled=false;btn.textContent='Save';});
  return false;
}

// ─── REMOVE MEMBER (AJAX) ──────────────────────────
function removeMember(id, name) {
  if (!confirm('Remove ' + name + ' and all their vehicles?')) return;
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'remove_member', id:id})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);return;}
    location.reload();
  }).catch(()=>alert('Error'));
}

// ─── TOGGLE SOLD (AJAX) ────────────────────────────
function toggleSold(id, btn) {
  btn.disabled = true;
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_sold', id:id})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;return;}
    location.reload();
  }).catch(()=>{alert('Error');btn.disabled=false;});
}

// ─── VEHICLE TABLE SEARCH ──────────────────────────
function filterVehicles() {
  const q = document.getElementById('vehicleSearch').value.toLowerCase();
  document.querySelectorAll('tr[data-vid]').forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = !q || text.includes(q) ? '' : 'none';
  });
}

// ─── EDIT MEMBER MODAL ────────────────────────────
function openEditMemberModal(memberId) {
  const modal = document.getElementById('editMemberModal');
  const msg = document.getElementById('editMemberMsg');
  msg.classList.add('hidden');
  document.getElementById('editMemberForm').reset();
  document.getElementById('em_id').value = '';
  document.getElementById('em_name').value = '';
  document.getElementById('em_phone').value = '';
  document.getElementById('em_email').value = '';
  document.getElementById('emSubmitBtn').disabled = true;
  document.getElementById('emSubmitBtn').textContent = 'Loading…';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  fetch(`api/update_member.php?id=${memberId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { showEditMemberMsg(data.error, 'error'); return; }
      document.getElementById('em_id').value = data.id;
      document.getElementById('em_name').value = data.name;
      document.getElementById('em_phone').value = data.phone;
      document.getElementById('em_email').value = data.email;
    })
    .catch(() => showEditMemberMsg('Failed to load member data.', 'error'))
    .finally(() => {
      document.getElementById('emSubmitBtn').disabled = false;
      document.getElementById('emSubmitBtn').textContent = 'Save';
    });
}

function closeEditMemberModal() {
  document.getElementById('editMemberModal').style.display = 'none';
  document.body.style.overflow = '';
}

function showEditMemberMsg(text, type) {
  const msg = document.getElementById('editMemberMsg');
  msg.textContent = text;
  msg.classList.remove('hidden');
  msg.style.background = type === 'error' ? 'rgba(231,76,60,.15)' : 'rgba(46,204,113,.15)';
  msg.style.color = type === 'error' ? '#e74c3c' : '#2ecc71';
}

function submitEditMember(e) {
  e.preventDefault();
  const btn = document.getElementById('emSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';
  const msg = document.getElementById('editMemberMsg');
  msg.classList.add('hidden');

  const payload = {
    id: parseInt(document.getElementById('em_id').value),
    name: document.getElementById('em_name').value.trim(),
    phone: document.getElementById('em_phone').value.trim(),
    email: document.getElementById('em_email').value.trim(),
  };

  if (!payload.name) { showEditMemberMsg('Name is required.', 'error'); btn.disabled = false; btn.textContent = 'Save'; return false; }

  fetch('api/update_member.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { showEditMemberMsg(data.error, 'error'); return; }
    showEditMemberMsg('Member updated!', 'success');
    setTimeout(() => { closeEditMemberModal(); location.reload(); }, 600);
  })
  .catch(() => showEditMemberMsg('Network error.', 'error'))
  .finally(() => { btn.disabled = false; btn.textContent = 'Save'; });
  return false;
}

// Close edit member modal on overlay click
document.getElementById('editMemberModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditMemberModal();
});

// ─── MEMBER DETAIL MODAL ──────────────────────────
function openMemberDetail(memberId) {
  const modal = document.getElementById('memberDetailModal');
  document.getElementById('mdContent').innerHTML = '<div class="text-center text-ak-muted py-12">Loading…</div>';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  fetch(`api/get_member_detail.php?member_id=${memberId}&auction_id=${activeAuctionId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { document.getElementById('mdContent').innerHTML = `<div class="text-ak-red text-center py-8">${data.error}</div>`; return; }

      document.getElementById('mdName').textContent = data.member.name;
      document.getElementById('mdContact').textContent = `${data.member.email || '—'} · ${data.member.phone || '—'}`;
      document.getElementById('mdPdfLink').href = `pdf.php?member=${data.member.id}&auction_id=${data.auction.id}`;

      let html = '';

      // Sold vehicles
      if (data.soldCount > 0) {
        html += `<div class="ssl">Sold Vehicles (${data.soldCount})</div>`;
        html += `<table class="vt"><thead><tr><th>Lot</th><th>Vehicle</th><th class="r">Price</th><th class="r">Tax</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Other</th><th class="r">Net</th></tr></thead><tbody>`;
        data.sold.forEach(v => {
          html += `<tr><td><span class="lot">${v.lot || '—'}</span></td><td class="text-ak-text2">${v.make} ${v.model}</td><td class="text-right font-mono text-ak-green">¥${Math.round(v.sold_price).toLocaleString()}</td><td class="text-right font-mono text-ak-text2 text-xs">¥${Math.round(v.tax).toLocaleString()}</td><td class="text-right font-mono text-ak-text2 text-xs">${v.recycle_fee > 0 ? '¥'+Math.round(v.recycle_fee).toLocaleString() : '—'}</td><td class="text-right font-mono text-ak-red text-xs">${v.listing_fee > 0 ? '−¥'+Math.round(v.listing_fee).toLocaleString() : '—'}</td><td class="text-right font-mono text-ak-red text-xs">${v.sold_fee > 0 ? '−¥'+Math.round(v.sold_fee).toLocaleString() : '—'}</td><td class="text-right font-mono text-ak-red text-xs">${v.other_fee > 0 ? '−¥'+Math.round(v.other_fee).toLocaleString() : '—'}</td><td class="text-right font-mono text-ak-gold font-bold">¥${Math.round(v.net).toLocaleString()}</td></tr>`;
        });
        html += '</tbody></table>';
      }

      // Unsold vehicles
      if (data.unsoldCount > 0) {
        html += `<div class="ssl mt-5">Unsold Vehicles (${data.unsoldCount})</div>`;
        html += `<table class="vt"><thead><tr><th>Lot</th><th>Vehicle</th><th class="r">Nagare</th><th class="r">Other</th></tr></thead><tbody>`;
        data.unsold.forEach(v => {
          html += `<tr><td><span class="lot">${v.lot || '—'}</span></td><td class="text-ak-text2">${v.make} ${v.model}</td><td class="text-right font-mono text-ak-red text-xs">${v.nagare_fee > 0 ? '−¥'+Math.round(v.nagare_fee).toLocaleString() : '—'}</td><td class="text-right font-mono text-ak-red text-xs">${v.other_fee > 0 ? '−¥'+Math.round(v.other_fee).toLocaleString() : '—'}</td></tr>`;
        });
        html += '</tbody></table>';
      }

      if (data.soldCount === 0 && data.unsoldCount === 0) {
        html = '<div class="text-ak-muted text-center py-8">No vehicles assigned to this member.</div>';
      }

      document.getElementById('mdContent').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('mdContent').innerHTML = '<div class="text-ak-red text-center py-8">Failed to load member details.</div>';
    });
}

function closeMemberDetail() {
  document.getElementById('memberDetailModal').style.display = 'none';
  document.body.style.overflow = '';
}

// Close member modal on overlay click
document.getElementById('memberDetailModal').addEventListener('click', function(e) {
  if (e.target === this) closeMemberDetail();
});

// ─── DELETE VEHICLE (AJAX) ─────────────────────────
function deleteVehicle(vehicleId, btn) {
  if (!confirm('Remove this vehicle?')) return;
  btn.disabled = true;
  btn.textContent = '…';
  fetch('api/delete_vehicle.php', {
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

// Clear lot error when user types
const addLotInput = document.getElementById('add_lot');
if (addLotInput) {
  addLotInput.addEventListener('input', function() {
    const lotErr = document.getElementById('add_lot_error');
    if (lotErr) lotErr.textContent = '';
  });
}

// Body scroll lock when modal is open
const editModal = document.getElementById('editModal');
if (editModal) {
  const observer = new MutationObserver(() => {
    document.body.style.overflow = editModal.style.display === 'flex' ? 'hidden' : '';
  });
  observer.observe(editModal, { attributes: true, attributeFilter: ['style'] });
}
