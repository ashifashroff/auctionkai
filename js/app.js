/* ── AuctionKai — Main JavaScript ─────────────────── */

// ── Keyboard Shortcuts Manager ────────────────
const KeyboardShortcuts = {
  gPressed: false, gTimer: null,
  init() {
    document.addEventListener('keydown', (e) => {
      const tag = document.activeElement.tagName;
      if (['INPUT','TEXTAREA','SELECT'].includes(tag)) return;
      if (e.ctrlKey && e.key.toLowerCase() !== 'p') return;
      if (e.altKey || e.metaKey) return;
      this.handle(e);
    });
  },
  handle(e) {
    const key = e.key.toLowerCase();
    if (this.gPressed) {
      clearTimeout(this.gTimer); this.gPressed = false;
      switch(key) {
        case 'm': this.goToTab('members'); this.showHint('→ Members'); break;
        case 'v': this.goToTab('vehicles'); this.showHint('→ Vehicles'); break;
        case 's': this.goToTab('statements'); this.showHint('→ Statements'); break;
        case 'd': this.goToTab('dashboard'); this.showHint('→ Dashboard'); break;
      }
      return;
    }
    switch(key) {
      case 'g':
        this.gPressed = true;
        this.showHint('G → M Members  V Vehicles  S Statements  D Dashboard');
        this.gTimer = setTimeout(() => { this.gPressed = false; this.hideHint(); }, 1500);
        break;
      case 'n':
        if (e.shiftKey) {
          this.goToTab('members');
          setTimeout(() => { const el = document.querySelector('input[name="name"]'); if(el) el.focus(); this.showHint('Add new member'); }, 100);
        } else {
          this.goToTab('vehicles');
          setTimeout(() => { const el = document.getElementById('add_make'); if(el) el.focus(); this.showHint('Add new vehicle'); }, 100);
        }
        break;
      case 'l':
        this.goToTab('vehicles');
        setTimeout(() => { const el = document.getElementById('add_lot'); if(el) el.focus(); this.showHint('Lot # field'); }, 100);
        break;
      case '/':
        e.preventDefault();
        const search = document.getElementById('vehicleSearch');
        if (search) { search.focus(); this.showHint('Search vehicles'); }
        break;
      case '?':
        this.openShortcutsModal();
        break;
      case 'escape':
        this.closeAllModals();
        break;
    }
  },
  goToTab(tabName) {
    const link = document.querySelector('a[href*="tab=' + tabName + '"]');
    if (link) { link.click(); }
    else { const url = new URL(window.location.href); url.searchParams.set('tab', tabName); window.location.href = url.toString(); }
  },
  openShortcutsModal() {
    const overlay = document.getElementById('shortcuts-modal-overlay');
    if (overlay) { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  closeAllModals() {
    const so = document.getElementById('shortcuts-modal-overlay');
    if (so) { so.classList.remove('open'); document.body.style.overflow = ''; }
  },
  showHint(message) {
    const hint = document.getElementById('shortcut-hint');
    if (!hint) return;
    hint.textContent = message; hint.classList.add('visible');
    clearTimeout(this._hintTimer);
    this._hintTimer = setTimeout(() => this.hideHint(), 1800);
  },
  hideHint() {
    const hint = document.getElementById('shortcut-hint');
    if (hint) hint.classList.remove('visible');
  }
};

function closeShortcutsModal() { KeyboardShortcuts.closeAllModals(); }

document.addEventListener('DOMContentLoaded', function() {
  const overlay = document.getElementById('shortcuts-modal-overlay');
  if (overlay) { overlay.addEventListener('click', function(e) { if (e.target === overlay) KeyboardShortcuts.closeAllModals(); }); }
});
KeyboardShortcuts.init();

// ─── TOAST NOTIFICATIONS ────────────────────────────
function showToast(message, type = 'success', duration = 3500) {
  const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
  const container = document.getElementById('toast-container');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.style.position = 'relative';
  toast.style.overflow = 'hidden';
  toast.innerHTML = `
    <span class="toast-icon">${icons[type]}</span>
    <span class="toast-msg">${message}</span>
    <span class="toast-close">×</span>
    <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
  `;
  toast.addEventListener('click', () => dismissToast(toast));
  container.appendChild(toast);
  requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
  const timer = setTimeout(() => dismissToast(toast), duration);
  toast._timer = timer;
  return toast;
}

function dismissToast(toast) {
  clearTimeout(toast._timer);
  toast.classList.remove('show');
  toast.classList.add('hide');
  setTimeout(() => toast.remove(), 350);
}

// ── Duplicate Lot Number Check ────────────────
async function checkDuplicateLot(lot, auctionId, excludeId = 0) {
  if (!lot || !lot.trim()) return false;
  try {
    const res = await fetch('api/check_lot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _tok: CSRF_TOKEN, lot, auction_id: auctionId, exclude_id: excludeId })
    });
    const data = await res.json();
    if (data.duplicate) {
      showToast(data.message, 'warning');
      return true;
    }
    return false;
  } catch {
    return false;
  }
}

// ── Password Strength Checker ─────────────────
function getPasswordStrength(password) {
  let score = 0;
  if (!password) return { score: 0, label: '', level: '' };
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[a-z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;
  if (score <= 1) return { score: 1, label: 'Weak', level: 'strength-weak' };
  if (score <= 2) return { score: 2, label: 'Fair', level: 'strength-fair' };
  if (score <= 4) return { score: 3, label: 'Good', level: 'strength-good' };
  return { score: 4, label: 'Strong', level: 'strength-strong' };
}

function initStrengthIndicator(inputId, barsId, labelId) {
  const input = document.getElementById(inputId);
  const bars = document.getElementById(barsId);
  const label = document.getElementById(labelId);
  if (!input || !bars || !label) return;
  input.addEventListener('input', function() {
    const val = input.value;
    if (!val) { bars.className = 'strength-bar-wrap'; label.textContent = ''; return; }
    const result = getPasswordStrength(val);
    bars.className = 'strength-bar-wrap ' + result.level;
    label.textContent = result.label;
    const form = input.closest('form');
    if (form) {
      if (result.score < 2) {
        input.setCustomValidity('Password is too weak. Add uppercase, numbers, or symbols.');
      } else {
        input.setCustomValidity('');
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  initStrengthIndicator('register-password', 'reg-strength-bars', 'reg-strength-label');
  initStrengthIndicator('profile-new-password', 'prof-strength-bars', 'prof-strength-label');
});

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
        showToast(data.error, 'error');
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
      document.getElementById('edit_sold').checked = data.sold;
      toggleModalSoldFields(data.sold);
    })
    .catch(() => showToast('Failed to load vehicle data.', 'error'))
    .finally(() => {
      document.getElementById('editSubmitBtn').disabled = false;
      document.getElementById('editSubmitBtn').textContent = 'Save Changes';
    });
}

// ─── EDIT MODAL: Close ──────────────────────────────
function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// ─── EDIT MODAL: Submit ─────────────────────────────
async function submitEditForm(e) {
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
    sold:       document.getElementById('edit_sold').checked,
  };

  // Frontend validation
  if (!payload.memberId) { showToast('Please select a member.', 'warning'); btn.disabled = false; btn.textContent = 'Save Changes'; return false; }
  if (!payload.make) { showToast('Make is required.', 'warning'); btn.disabled = false; btn.textContent = 'Save Changes'; return false; }

  // Duplicate lot check (exclude current vehicle)
  if (payload.lot) {
    const isDuplicate = await checkDuplicateLot(payload.lot, activeAuctionId, payload.id);
    if (isDuplicate) {
      const lotInput = document.getElementById('edit_lot');
      if (lotInput) { lotInput.style.borderColor = '#CC7777'; lotInput.focus(); setTimeout(() => lotInput.style.borderColor = '', 2500); }
      btn.disabled = false; btn.textContent = 'Save Changes';
      return false;
    }
  }

  fetch('api/update_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({...payload, _tok: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showToast(data.error, 'error');
      return;
    }
    showToast('Vehicle updated successfully', 'success');
    setTimeout(() => { closeEditModal(); if(typeof VehiclesPager!=="undefined"){VehiclesPager.reload();}else{location.reload();} }, 800);
  })
  .catch(() => showToast('Connection error. Please try again.', 'error'))
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  });

  return false;
}

// ─── ADD VEHICLE (AJAX) ────────────────────────────
async function submitAddVehicle(e) {
  e.preventDefault();

  // Let Parsley validate first
  const form = document.getElementById('addVehicleForm');
  const parsleyForm = $(form).parsley();
  parsleyForm.validate();
  if (!parsleyForm.isValid()) return false;

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
    sold:       document.getElementById('add_sold').checked,
    auctionId:  activeAuctionId
  };

  if (!payload.auctionId) { showToast('No active auction selected.', 'warning'); return false; }
  if (payload.sold && !payload.soldPrice) { showToast('Sold price is required for sold vehicles.', 'warning'); return false; }

  // Duplicate lot check
  if (payload.lot) {
    const isDuplicate = await checkDuplicateLot(payload.lot, payload.auctionId, 0);
    if (isDuplicate) {
      const lotInput = document.getElementById('add_lot');
      if (lotInput) { lotInput.style.borderColor = '#CC7777'; lotInput.focus(); setTimeout(() => lotInput.style.borderColor = '', 2500); }
      return false;
    }
  }

  // Fade + disable + preloader
  fields.style.opacity = '0.4';
  fields.style.pointerEvents = 'none';
  btn.disabled = true;
  btn.innerHTML = '<span class="add-preloader"></span> Adding…';

  fetch('api/add_vehicle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({...payload, _tok: CSRF_TOKEN})
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showToast(data.error, 'error');
      return;
    }
    showToast('Vehicle added successfully', 'success');
    document.getElementById('addVehicleForm').reset();
    document.getElementById('memberId').value = '';
    document.getElementById('memberSearch').value = '';
    toggleSoldFields(true);
    setTimeout(() => { if(typeof VehiclesPager!=="undefined"){VehiclesPager.reload();}else{location.reload();} }, 600);
  })
  .catch(() => showToast('Connection error. Please try again.', 'error'))
  .finally(() => {
    fields.style.opacity = '1';
    fields.style.pointerEvents = 'auto';
    btn.disabled = false;
    btn.textContent = 'Add';
  });

  return false;
}

// ─── ADD AUCTION (AJAX) ───────────────────────────
function submitAddAuction(e) {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('button[type=submit]');
  btn.disabled = true; btn.textContent = 'Creating…';
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'add_auction', name:form.name.value, date:form.date.value, _tok:CSRF_TOKEN})
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
    body: JSON.stringify({action:'add_member', name:form.name.value, phone:form.phone.value, email:form.email.value, _tok:CSRF_TOKEN})
  }).then(r=>r.json()).then(d=>{
    if(d.error){showToast(d.error, 'error');btn.disabled=false;btn.textContent='+ Add';return;}
    showToast('Member added successfully', 'success');
    form.reset();
    btn.disabled = false; btn.textContent = '+ Add';
    if (typeof MembersPager !== 'undefined') { MembersPager.reload(); } else { location.reload(); }
  }).catch(()=>{showToast('Connection error. Please try again.', 'error');btn.disabled=false;btn.textContent='+ Add';});
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
    body: JSON.stringify({action:'save_auction', auction_id:activeAuctionId, name:form.name.value, date:form.date.value, commissionFee:form.commissionFee.value, _tok:CSRF_TOKEN})
  }).then(r=>r.json()).then(d=>{
    if(d.error){showToast(d.error, 'error');btn.disabled=false;btn.textContent='Save';return;}
    showToast('Auction saved', 'success');
    btn.disabled=false;btn.textContent='Save';
    if(typeof VehiclesPager!=='undefined'){VehiclesPager.reload();}
    if(typeof MembersPager!=='undefined'){MembersPager.reload();}
  }).catch(()=>{showToast('Error saving auction', 'error');btn.disabled=false;btn.textContent='Save';});
  return false;
}

// ─── REMOVE MEMBER (AJAX) ──────────────────────────
// ─── MEMBER LIST SEARCH ───────────────────────────
function filterMemberList() {
  const q = (document.getElementById('memberListSearch')?.value || '').toLowerCase().trim();
  document.querySelectorAll('.member-card').forEach(card => {
    const name = card.getAttribute('data-member-name') || '';
    const phone = card.getAttribute('data-member-phone') || '';
    const email = card.getAttribute('data-member-email') || '';
    const match = !q || name.includes(q) || phone.includes(q) || email.includes(q);
    card.style.display = match ? '' : 'none';
  });
}

function removeMember(id, name) {
  document.getElementById('removeMemberName').textContent = name;
  document.getElementById('removeMemberModal').dataset.memberId = id;
  document.getElementById('removeMemberModal').style.display = 'flex';
}

function closeRemoveMemberModal() {
  document.getElementById('removeMemberModal').style.display = 'none';
}

function confirmRemoveMember() {
  const modal = document.getElementById('removeMemberModal');
  const id = modal.dataset.memberId;
  const btn = document.getElementById('confirmRemoveMemberBtn');
  btn.disabled = true;
  btn.textContent = 'Removing...';

  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'remove_member', id:id, _tok:CSRF_TOKEN})
  }).then(r=>r.json()).then(d=>{
    if(d.error){showToast(d.error, 'error');btn.disabled=false;btn.textContent='Remove';return;}
    showToast('Member removed successfully', 'success', 3000);
    closeRemoveMemberModal();
    if(typeof MembersPager!=="undefined"){MembersPager.reload();}else{location.reload();}
    btn.disabled=false;btn.textContent='Remove';
  }).catch(()=>{
    showToast('Error removing member', 'error');
    btn.disabled=false;btn.textContent='Remove';
  });
}

// ─── TOGGLE SOLD (AJAX) ────────────────────────────
function toggleSold(id, btn) {
  btn.disabled = true;
  fetch('api.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_sold', id:id, _tok:CSRF_TOKEN})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;return;}
    if(typeof VehiclesPager!=="undefined"){VehiclesPager.reload();}else{location.reload();}
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
      if (data.error) { showToast(data.error, 'error'); return; }
      document.getElementById('em_id').value = data.id;
      document.getElementById('em_name').value = data.name;
      document.getElementById('em_phone').value = data.phone;
      document.getElementById('em_email').value = data.email;
    })
    .catch(() => showToast('Failed to load member data.', 'error'))
    .finally(() => {
      document.getElementById('emSubmitBtn').disabled = false;
      document.getElementById('emSubmitBtn').textContent = 'Save';
    });
}

function closeEditMemberModal() {
  document.getElementById('editMemberModal').style.display = 'none';
  document.body.style.overflow = '';
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
    _tok: CSRF_TOKEN
  };

  if (!payload.name) { showToast('Name is required.', 'warning'); btn.disabled = false; btn.textContent = 'Save'; return false; }

  fetch('api/update_member.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { showToast(data.error, 'error'); return; }
    showToast('Member updated successfully', 'success');
    setTimeout(() => { closeEditMemberModal(); if(typeof MembersPager!=="undefined"){MembersPager.reload();}else{location.reload();} }, 600);
  })
  .catch(() => showToast('Connection error. Please try again.', 'error'))
  .finally(() => { btn.disabled = false; btn.textContent = 'Save'; });
  return false;
}

// Close edit member modal on overlay click
document.getElementById('editMemberModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeEditMemberModal();
});

// ─── MEMBER DETAIL MODAL ──────────────────────────
function openMemberDetail(memberId) {
  const modal = document.getElementById('memberDetailModal');
  document.getElementById('mdContent').innerHTML = '<div class="text-center text-ak-muted py-12">Loading…</div>';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  if (!activeAuctionId) {
    document.getElementById('mdContent').innerHTML = '<div class="text-ak-muted text-center py-8">No active auction selected.</div>';
    return;
  }

  fetch(`api/get_member_detail.php?member_id=${memberId}&auction_id=${activeAuctionId}`)
    .then(r => {
      if (!r.ok) throw new Error('Server returned ' + r.status);
      return r.json();
    })
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
    .catch((err) => {
      console.error('Member detail error:', err);
      document.getElementById('mdContent').innerHTML = '<div class="text-ak-red text-center py-8">Failed to load member details.</div>';
    });
}

function closeMemberDetail() {
  document.getElementById('memberDetailModal').style.display = 'none';
  document.body.style.overflow = '';
}

// Close member modal on overlay click
document.getElementById('memberDetailModal')?.addEventListener('click', function(e) {
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
    body: JSON.stringify({ _tok: CSRF_TOKEN, id: vehicleId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { alert(data.error); btn.disabled = false; btn.textContent = '×'; return; }
    const row = document.querySelector(`tr[data-vid="${vehicleId}"]`);
    if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .3s'; setTimeout(() => {if(typeof VehiclesPager!=="undefined"){VehiclesPager.reload();}else{location.reload();}}, 300); }
    else if(typeof VehiclesPager!=="undefined"){VehiclesPager.reload();}else{location.reload();}
  })
  .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = '×'; });
}

// ── Send Statement Email ──────────────────────
async function sendStatementEmail(
  memberId, 
  auctionId, 
  btnEl
) {
  // Show loading state
  const originalText = btnEl.innerHTML;
  btnEl.innerHTML = '⏳ Sending...';
  btnEl.disabled = true;

  try {
    const res = await fetch('api/send_email.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json' 
      },
      body: JSON.stringify({ _tok: CSRF_TOKEN, 
        member_id: memberId, 
        auction_id: auctionId 
      })
    });

    const data = await res.json();

    if (data.success) {
      showToast(
        '✉ ' + data.message, 
        'success', 
        4000
      );
      btnEl.innerHTML = '✓ Sent';
      btnEl.style.background = '#1A3A2A';
      btnEl.style.color = '#4CAF82';
      btnEl.style.border = '1px solid #2A5A3A';

      // Reset button after 4 seconds
      setTimeout(() => {
        btnEl.innerHTML = originalText;
        btnEl.style.background = '';
        btnEl.style.color = '';
        btnEl.style.border = '';
        btnEl.disabled = false;
      }, 4000);

    } else {
      showToast(
        data.message || 'Failed to send email', 
        'error'
      );
      btnEl.innerHTML = originalText;
      btnEl.disabled = false;
    }

  } catch (err) {
    showToast(
      'Connection error. Please try again.', 
      'error'
    );
    btnEl.innerHTML = originalText;
    btnEl.disabled = false;
  }
}

// ─── GLOBAL EVENT LISTENERS ─────────────────────────

// Close modal on overlay click
document.getElementById('editModal')?.addEventListener('click', function(e) {
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


// ── Vehicles Pagination ───────────────────────
const VehiclesPager = {
  page: 1,
  lastPage: 1,
  total: 0,
  perPage: 25,
  search: '',
  loading: false,
  auctionId: 0,

  init(auctionId) {
    this.auctionId = auctionId;
    this.page = 1;
    this.search = '';

    const searchInput = document.getElementById('vehicle-search');
    if (searchInput) {
      let timer;
      searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          this.search = searchInput.value.trim();
          this.page = 1;
          this.load();
        }, 400);
      });
    }

    const perPageSel = document.getElementById('per-page-select');
    if (perPageSel) {
      perPageSel.addEventListener('change', () => {
        this.perPage = parseInt(perPageSel.value);
        this.page = 1;
        this.load();
      });
    }

    this.load();
  },

  async load() {
    if (this.loading) return;
    this.loading = true;
    this.showSkeleton();

    try {
      const params = new URLSearchParams({
        auction_id: this.auctionId,
        page: this.page,
        per_page: this.perPage,
        search: this.search,
      });

      const res = await fetch('api/get_vehicles_page.php?' + params);
      const data = await res.json();

      if (!data.success) {
        this.showEmpty('Failed to load vehicles');
        return;
      }

      this.total = data.total;
      this.lastPage = data.lastPage;
      this.page = data.page;

      this.renderTable(data.vehicles);
      this.renderMobileCards(data.vehicles);
      this.renderPagination();
      this.updateBadge();

    } catch (err) {
      this.showEmpty('Connection error');
      console.error(err);
    } finally {
      this.loading = false;
    }
  },

  showSkeleton() {
    const tbody = document.getElementById('vehicles-tbody');
    if (!tbody) return;
    let rows = '';
    for (let i = 0; i < 5; i++) {
      rows += '<tr class="skeleton-row"><td colspan="11">&nbsp;</td></tr>';
    }
    tbody.innerHTML = rows;
  },

  showEmpty(msg = 'No vehicles found') {
    const tbody = document.getElementById('vehicles-tbody');
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="11" style="padding:48px;text-align:center;color:var(--ak-muted)">${msg}</td></tr>`;
    }
    const badge = document.getElementById('vehicles-count-badge');
    if (badge) badge.textContent = '0 vehicles';
  },

  renderTable(vehicles) {
    const tbody = document.getElementById('vehicles-tbody');
    if (!tbody) return;

    if (vehicles.length === 0) {
      this.showEmpty(this.search ? 'No vehicles match your search' : 'No vehicles in this auction yet');
      return;
    }

    tbody.innerHTML = vehicles.map(v => {
      const soldClass = v.sold == 1 ? 'var(--ak-green)' : 'var(--ak-muted)';
      const price = v.sold == 1 ? '¥' + parseInt(v.sold_price).toLocaleString('ja-JP') : '—';
      const statusClass = v.sold == 1 ? 'sy' : 'sn';
      const statusText = v.sold == 1 ? '✓ SOLD' : '✗ UNSOLD';
      const vTax = v.sold == 1 ? Math.round(parseFloat(v.sold_price) * 0.10) : 0;
      const vTotal = v.sold == 1 ? parseFloat(v.sold_price) + vTax + parseFloat(v.recycle_fee||0) - parseFloat(v.listing_fee||0) - parseFloat(v.sold_fee||0) - parseFloat(v.nagare_fee||0) : 0;

      return `<tr id="vehicle-row-${v.id}" style="border-bottom:1px solid #131F2E">
        <td><span class="lot">${this.esc(v.lot || '—')}</span></td>
        <td style="color:var(--ak-text2)">${this.esc(v.member_name || '?')}</td>
        <td style="color:var(--ak-text2)">${this.esc(v.make + ' ' + v.model)}</td>
        <td style="text-align:right;font-family:var(--mono);color:${soldClass}">${price}</td>
        <td style="text-align:right;font-family:monospace;color:var(--ak-text2);font-size:12px">${v.sold && parseFloat(v.recycle_fee||0)>0 ? '¥'+Math.round(parseFloat(v.recycle_fee)).toLocaleString() : '—'}</td>
        <td style="text-align:right;font-family:monospace;color:var(--ak-red);font-size:12px">${v.sold && parseFloat(v.listing_fee||0)>0 ? '−¥'+Math.round(parseFloat(v.listing_fee)).toLocaleString() : '—'}</td>
        <td style="text-align:right;font-family:monospace;color:var(--ak-red);font-size:12px">${v.sold && parseFloat(v.sold_fee||0)>0 ? '−¥'+Math.round(parseFloat(v.sold_fee)).toLocaleString() : '—'}</td>
        <td style="text-align:right;font-family:monospace;color:var(--ak-red);font-size:12px">${!v.sold && parseFloat(v.nagare_fee||0)>0 ? '−¥'+Math.round(parseFloat(v.nagare_fee)).toLocaleString() : '—'}</td>
        <td style="text-align:right;font-family:monospace;font-weight:700;color:${v.sold?'var(--ak-gold)':'var(--ak-muted)'}">${v.sold ? '¥'+Math.round(vTotal).toLocaleString() : '—'}</td>
        <td><button class="sb ${statusClass}" onclick="toggleSold(${v.id}, this)">${statusText}</button></td>
        <td style="white-space:nowrap"><div style="display:flex;gap:6px;align-items:center"><button onclick="openEditModal(${v.id})" class="btn btn-dark btn-sm">Edit</button><button onclick="deleteVehicle(${v.id})" class="btn-icon">×</button></div></td>
      </tr>`;
    }).join('');
  },

  renderMobileCards(vehicles) {
    const container = document.getElementById('vehicle-cards-mobile');
    if (!container) return;

    if (vehicles.length === 0) {
      container.innerHTML = `<div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border">${this.search ? 'No vehicles match your search' : 'No vehicles yet'}</div>`;
      return;
    }

    container.innerHTML = vehicles.map(v => {
      const soldPrice = v.sold == 1 ? '¥' + parseInt(v.sold_price).toLocaleString('ja-JP') : '—';
      return `<div class="v-card" id="v-card-${v.id}">
        <div class="v-card-top">
          <span class="v-card-lot">${this.esc(v.lot || '—')}</span>
          <button class="sb ${v.sold==1?'sy':'sn'}" onclick="toggleSold(${v.id}, this)">${v.sold==1?'✓ SOLD':'✗ UNSOLD'}</button>
        </div>
        <div class="v-card-name">${this.esc(v.make + ' ' + v.model)}</div>
        <div class="v-card-meta">${this.esc(v.member_name || '?')}</div>
        <div class="v-card-fees">
          <div class="v-card-fee"><div class="v-card-fee-label">Sold Price</div><div class="v-card-fee-value ${v.sold==1?'':'muted'}">${soldPrice}</div></div>
          <div class="v-card-fee"><div class="v-card-fee-label">Listing Fee</div><div class="v-card-fee-value">¥${parseInt(v.listing_fee||0).toLocaleString('ja-JP')}</div></div>
        </div>
        <div class="v-card-actions">
          <button onclick="openEditModal(${v.id})" style="background:#1E3A5F;color:var(--ak-gold)">✎ Edit</button>
          <button onclick="deleteVehicle(${v.id})" style="background:#3A1A1A;color:var(--ak-red)">× Delete</button>
        </div>
      </div>`;
    }).join('');
  },

  renderPagination() {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    if (!info || !controls) return;

    const from = ((this.page - 1) * this.perPage) + 1;
    const to = Math.min(this.page * this.perPage, this.total);
    info.innerHTML = this.total === 0 ? 'No results' : `Showing <b>${from}–${to}</b> of <b>${this.total}</b> vehicles`;

    if (this.lastPage <= 1) { controls.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn prev" ${this.page===1?'disabled':''} onclick="VehiclesPager.goTo(${this.page-1})">‹</button>`;

    const pages = this.getPageRange(this.page, this.lastPage);
    let lastRendered = 0;
    for (const p of pages) {
      if (p - lastRendered > 1) html += '<span class="page-ellipsis">…</span>';
      html += `<button class="page-btn ${p===this.page?'active':''}" onclick="VehiclesPager.goTo(${p})">${p}</button>`;
      lastRendered = p;
    }

    html += `<button class="page-btn next" ${this.page===this.lastPage?'disabled':''} onclick="VehiclesPager.goTo(${this.page+1})">›</button>`;
    controls.innerHTML = html;
  },

  getPageRange(current, last) {
    const pages = new Set([1, last, current, Math.max(1, current - 1), Math.min(last, current + 1)]);
    return [...pages].sort((a, b) => a - b);
  },

  updateBadge() {
    const badge = document.getElementById('vehicles-count-badge');
    if (badge) {
      badge.textContent = this.search
        ? `${this.total} result${this.total !== 1 ? 's' : ''}`
        : `${this.total} vehicle${this.total !== 1 ? 's' : ''}`;
    }
  },

  goTo(page) {
    if (page < 1 || page > this.lastPage) return;
    this.page = page;
    this.load();
    const wrap = document.getElementById('vehicles-table-wrap');
    if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  },

  reload() {
    this.load();
  },

  esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
};

// ── Members Pagination ────────────────────────
const MembersPager = {
  page: 1,
  lastPage: 1,
  total: 0,
  perPage: 25,
  search: '',
  loading: false,
  auctionId: 0,

  init(auctionId) {
    this.auctionId = auctionId;
    this.page = 1;
    this.search = '';

    const searchInput = document.getElementById('member-search');
    if (searchInput) {
      let timer;
      searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          this.search = searchInput.value.trim();
          this.page = 1;
          this.load();
        }, 400);
      });
    }

    const perPageSel = document.getElementById('member-per-page-select');
    if (perPageSel) {
      perPageSel.addEventListener('change', () => {
        this.perPage = parseInt(perPageSel.value);
        this.page = 1;
        this.load();
      });
    }

    this.load();
  },

  async load() {
    if (this.loading) return;
    this.loading = true;

    const container = document.getElementById('members-list-container');
    if (container) {
      container.innerHTML = '<div class="bg-ak-card rounded-xl p-12 text-center text-ak-muted border border-ak-border">Loading…</div>';
    }

    try {
      const params = new URLSearchParams({
        auction_id: this.auctionId,
        page: this.page,
        per_page: this.perPage,
        search: this.search,
      });

      const res = await fetch('api/get_members_page.php?' + params);
      const data = await res.json();

      if (!data.success) {
        this.showEmpty('Failed to load members');
        return;
      }

      this.total = data.total;
      this.lastPage = data.lastPage;
      this.page = data.page;

      this.renderMembers(data.members);
      this.renderPagination();
      this.updateBadge();

    } catch (err) {
      this.showEmpty('Connection error');
      console.error(err);
    } finally {
      this.loading = false;
    }
  },

  showEmpty(msg = 'No members found') {
    const container = document.getElementById('members-list-container');
    if (container) {
      container.innerHTML = `<div class="bg-ak-card rounded-xl p-12 text-center text-ak-muted border border-ak-border">${msg}</div>`;
    }
    const badge = document.getElementById('members-count-badge');
    if (badge) badge.textContent = '0 members';
    const pagWrap = document.getElementById('members-pagination-wrap');
    if (pagWrap) pagWrap.style.display = 'none';
  },

  renderMembers(members) {
    const container = document.getElementById('members-list-container');
    if (!container) return;

    if (members.length === 0) {
      this.showEmpty(this.search ? 'No members match your search' : 'No members yet for this auction.');
      return;
    }

    container.innerHTML = members.map(m => {
      const initial = (m.name || '?').charAt(0).toUpperCase();
      const phone = m.phone || '';
      const email = m.email || '';
      const vehicleCount = parseInt(m.vehicle_count || 0);
      const soldCount = parseInt(m.sold_count || 0);
      const netPayout = parseInt(m.net_payout || 0);
      const id = parseInt(m.id);

      return `<div class="bg-ak-card rounded-xl p-4 border border-ak-border flex items-center gap-4 hover:border-ak-border/80 transition-all duration-200 animate-fade-in-up">
        <div class="w-10 h-10 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-lg shrink-0">${initial}</div>
        <div class="flex-1 min-w-0">
          <div class="text-ak-text font-semibold cursor-pointer hover:text-ak-gold transition-colors" onclick="openMemberDetail(${id})">${this.esc(m.name)}</div>
          <div class="text-ak-muted text-xs">${this.esc(phone)}${phone && email ? ' · ' : ''}${this.esc(email)}</div>
        </div>
        <div class="text-center px-3">
          <div class="text-ak-text font-bold text-lg">${vehicleCount}</div>
          <div class="text-ak-muted text-[10px]">${soldCount} sold</div>
        </div>
        <div class="text-right px-3">
          <div class="text-ak-gold font-mono font-bold">¥${netPayout.toLocaleString()}</div>
          <div class="text-ak-muted text-[10px]">net payout</div>
        </div>
        <div class="flex gap-1.5 items-center">
          <button class="btn btn-dark btn-sm" onclick="openEditMemberModal(${id})">Edit</button>
          <button class="btn btn-ghost btn-sm" onclick="removeMember(${id}, '${this.esc(m.name).replace(/'/g, "\\'")}')">Remove</button>
        </div>
      </div>`;
    }).join('');
  },

  renderPagination() {
    const info = document.getElementById('members-pagination-info');
    const controls = document.getElementById('members-pagination-controls');
    const pagWrap = document.getElementById('members-pagination-wrap');
    if (!info || !controls) return;

    if (this.lastPage <= 1) {
      if (pagWrap) pagWrap.style.display = 'none';
      return;
    }

    if (pagWrap) pagWrap.style.display = 'block';

    const from = ((this.page - 1) * this.perPage) + 1;
    const to = Math.min(this.page * this.perPage, this.total);
    info.innerHTML = `Showing <b>${from}–${to}</b> of <b>${this.total}</b> members`;

    let html = '';
    html += `<button class="page-btn prev" ${this.page===1?'disabled':''} onclick="MembersPager.goTo(${this.page-1})">‹</button>`;

    const pages = this.getPageRange(this.page, this.lastPage);
    let lastRendered = 0;
    for (const p of pages) {
      if (p - lastRendered > 1) html += '<span class="page-ellipsis">…</span>';
      html += `<button class="page-btn ${p===this.page?'active':''}" onclick="MembersPager.goTo(${p})">${p}</button>`;
      lastRendered = p;
    }

    html += `<button class="page-btn next" ${this.page===this.lastPage?'disabled':''} onclick="MembersPager.goTo(${this.page+1})">›</button>`;
    controls.innerHTML = html;
  },

  getPageRange(current, last) {
    const pages = new Set([1, last, current, Math.max(1, current - 1), Math.min(last, current + 1)]);
    return [...pages].sort((a, b) => a - b);
  },

  updateBadge() {
    const badge = document.getElementById('members-count-badge');
    if (badge) {
      badge.textContent = this.search
        ? `${this.total} result${this.total !== 1 ? 's' : ''}`
        : `${this.total} member${this.total !== 1 ? 's' : ''}`;
    }
  },

  goTo(page) {
    if (page < 1 || page > this.lastPage) return;
    this.page = page;
    this.load();
    const wrap = document.getElementById('members-list-container');
    if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  },

  reload() {
    this.load();
  },

  esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
};

// ── CSV Import ────────────────────────────────
function showCsvFileName(input) {
  const file = input.files[0];
  const nameDiv = document.getElementById('csvFileName');
  const nameText = document.getElementById('csvFileNameText');
  const importBtn = document.getElementById('csvImportBtn');
  if (file) {
    nameText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    nameDiv.classList.remove('hidden');
    importBtn.disabled = false;
    importBtn.classList.remove('opacity-50', 'cursor-not-allowed');
  } else {
    nameDiv.classList.add('hidden');
    importBtn.disabled = true;
    importBtn.classList.add('opacity-50', 'cursor-not-allowed');
  }
}

function handleCsvImport(input) {
  const file = input.files[0];
  if (!file) return;

  const resultDiv = document.getElementById('csvImportResult');
  const importBtn = document.getElementById('csvImportBtn');
  resultDiv.classList.remove('hidden');
  resultDiv.className = 'mt-3 p-3 rounded-lg text-sm border bg-ak-bg text-ak-muted';
  resultDiv.textContent = '⏳ Importing members...';
  importBtn.disabled = true;
  importBtn.textContent = 'Importing...';

  const fd = new FormData();
  fd.append('csv_file', file);
  fd.append('_tok', CSRF_TOKEN);

  fetch('api/import_members_csv.php', {
    method: 'POST',
    body: fd
  })
  .then(r => {
    if (!r.ok) return r.text().then(t => { throw new Error('Server ' + r.status + ': ' + t.substring(0, 300)); });
    return r.text().then(t => { try { return JSON.parse(t); } catch(e) { throw new Error('Invalid JSON: ' + t.substring(0, 300)); } });
  })
  .then(data => {
    if (data.success) {
      resultDiv.className = 'mt-3 p-3 rounded-lg text-sm border bg-ak-green/15 text-ak-green border-ak-green/30';
      let html = '✓ ' + data.message;
      if (data.errors && data.errors.length > 0) {
        html += '<div class="mt-2 text-xs text-ak-muted">' + data.errors.map(e => '• ' + e).join('<br>') + '</div>';
      }
      resultDiv.innerHTML = html;
      if (typeof MembersPager !== 'undefined') MembersPager.reload();
    } else {
      resultDiv.className = 'mt-3 p-3 rounded-lg text-sm border bg-ak-red/15 text-ak-red border-ak-red/30';
      resultDiv.textContent = '✗ ' + (data.message || 'Import failed');
    }
    input.value = '';
    importBtn.disabled = true;
    importBtn.textContent = '↑ Import CSV';
    document.getElementById('csvFileName').classList.add('hidden');
  })
  .catch(() => {
    resultDiv.className = 'mt-3 p-3 rounded-lg text-sm border bg-ak-red/15 text-ak-red border-ak-red/30';
    resultDiv.textContent = '✗ ' + (err.message || 'Connection error');
    input.value = '';
    importBtn.disabled = true;
    importBtn.textContent = '↑ Import CSV';
  });
}

// ── Session Timeout Manager ───────────────────
const SessionTimeout = {
  timeoutMinutes: 30,
  warnMinutes: 2,
  lastActivity: Date.now(),
  warningShown: false,
  warnTimer: null,
  logoutTimer: null,
  enabled: true,

  init(timeoutMinutes, warnMinutes) {
    if (!this.enabled) return;
    this.timeoutMinutes = timeoutMinutes || 30;
    this.warnMinutes = warnMinutes || 2;

    const events = ['mousedown','mousemove','keydown','scroll','touchstart','click'];
    events.forEach(evt => {
      document.addEventListener(evt, () => this.resetTimer(), { passive: true });
    });

    this.startTimers();
  },

  resetTimer() {
    this.lastActivity = Date.now();
    this.warningShown = false;
    const existingWarn = document.getElementById('session-warn-toast');
    if (existingWarn) existingWarn.remove();
    this.clearTimers();
    this.startTimers();
  },

  startTimers() {
    const timeoutMs = this.timeoutMinutes * 60 * 1000;
    const warnMs = timeoutMs - (this.warnMinutes * 60 * 1000);

    if (warnMs > 0) {
      this.warnTimer = setTimeout(() => { this.showWarning(); }, warnMs);
    }
    this.logoutTimer = setTimeout(() => { this.forceLogout(); }, timeoutMs);
  },

  clearTimers() {
    clearTimeout(this.warnTimer);
    clearTimeout(this.logoutTimer);
  },

  showWarning() {
    if (this.warningShown) return;
    this.warningShown = true;

    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.id = 'session-warn-toast';
    toast.style.cssText = 'background:#2B200D;border:1px solid #D4A84B;border-left:4px solid #D4A84B;border-radius:10px;padding:14px 18px;min-width:300px;box-shadow:0 8px 24px rgba(0,0,0,.4);pointer-events:all;opacity:0;transform:translateX(20px);transition:all .3s ease;';
    toast.innerHTML = '<div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">⏱</span><div style="flex:1"><div style="font-weight:700;color:#D4A84B;font-size:13px;margin-bottom:2px">Session expiring soon</div><div style="color:#A07828;font-size:12px">You will be logged out in ' + this.warnMinutes + ' minute(s) due to inactivity.</div></div><button onclick="SessionTimeout.resetTimer()" style="background:#D4A84B;color:#0A1420;border:none;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap">Stay Logged In</button></div>';

    container.appendChild(toast);
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
      });
    });
  },

  forceLogout() {
    const container = document.getElementById('toast-container');
    if (container) {
      const toast = document.createElement('div');
      toast.style.cssText = 'background:#2B0D0D;border:1px solid #CC7777;border-left:4px solid #CC7777;border-radius:10px;padding:14px 18px;min-width:260px;color:#CC7777;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.4);pointer-events:all;';
      toast.innerHTML = '🔒 Session expired — redirecting to login...';
      container.appendChild(toast);
    }
    setTimeout(() => { window.location.href = 'auth/login.php?timeout=1'; }, 1500);
  }
};

// ── Payment Status Manager ────────────────────
function togglePaymentMenu(memberId, auctionId, netPayout) {
  document.querySelectorAll('[id^="pay-menu-"]').forEach(m => {
    if (m.id !== `pay-menu-${memberId}`) m.classList.add('hidden');
  });
  const menu = document.getElementById(`pay-menu-${memberId}`);
  if (menu) menu.classList.toggle('hidden');
  const closeHandler = (e) => {
    const wrap = document.getElementById(`pay-wrap-${memberId}`);
    if (wrap && !wrap.contains(e.target)) {
      menu?.classList.add('hidden');
      document.removeEventListener('click', closeHandler);
    }
  };
  setTimeout(() => { document.addEventListener('click', closeHandler); }, 0);
}

async function setPaymentStatus(memberId, auctionId, status, netPayout) {
  const menu = document.getElementById(`pay-menu-${memberId}`);
  if (menu) menu.classList.add('hidden');
  const btn = document.getElementById(`pay-btn-${memberId}`);
  if (btn) { btn.innerHTML = '⏳ Updating...'; btn.disabled = true; }

  try {
    const res = await fetch('api/update_payment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _tok: CSRF_TOKEN, auction_id: auctionId, member_id: memberId, status: status, paid_amount: status === 'paid' ? netPayout : 0 })
    });
    const data = await res.json();

    if (data.success) {
      const classes = {
        paid: 'bg-ak-green/15 text-ak-green border-ak-green/30',
        partial: 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30',
        unpaid: 'bg-ak-red/10 text-ak-red border-ak-red/20'
      };
      const icons = { paid: '✓ Paid', partial: '◑ Partial', unpaid: '✗ Unpaid' };

      if (btn) {
        btn.className = `inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border cursor-pointer transition-all ${classes[status]}`;
        btn.innerHTML = `${icons[status]} <span class="text-[10px] opacity-60">▾</span>`;
        btn.disabled = false;
      }

      showToast(`Payment marked as ${status}`, status === 'paid' ? 'success' : status === 'partial' ? 'warning' : 'info');
    } else {
      showToast(data.message || 'Update failed', 'error');
      if (btn) btn.disabled = false;
    }
  } catch {
    showToast('Connection error. Please try again.', 'error');
    if (btn) btn.disabled = false;
  }
}

// ── Statement History Toggle ──────────────────
function toggleStmtHistory(memberId) {
  const panel = document.getElementById(`stmt-history-${memberId}`);
  const arrow = document.getElementById(`stmt-history-arrow-${memberId}`);
  if (!panel) return;
  const isHidden = panel.classList.contains('hidden');
  panel.classList.toggle('hidden');
  if (arrow) arrow.textContent = isHidden ? '▴' : '▾';
}


// ── Special Member Fees ───────────────────────
// Add form is inline on the Fees tab (not modal)
// Member search dropdown, fee description, amount, type, add button

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
