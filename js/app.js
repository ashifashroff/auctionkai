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
      body: JSON.stringify({ lot, auction_id: auctionId, exclude_id: excludeId })
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
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      showToast(data.error, 'error');
      return;
    }
    showToast('Vehicle updated successfully', 'success');
    setTimeout(() => { closeEditModal(); location.reload(); }, 800);
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
    body: JSON.stringify(payload)
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
    setTimeout(() => location.reload(), 600);
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
    if(d.error){showToast(d.error, 'error');btn.disabled=false;btn.textContent='+ Add';return;}
    showToast('Member added successfully', 'success');
    // Prepend new member card to top of list
    const list = document.getElementById('memberList');
    const name = form.name.value.trim();
    const phone = form.phone.value.trim();
    const email = form.email.value.trim();
    const initial = name.charAt(0).toUpperCase();
    const card = document.createElement('div');
    card.className = 'bg-ak-card rounded-xl p-4 border border-ak-gold/30 flex items-center gap-4 animate-fade-in-up member-card';
    card.setAttribute('data-member-name', name.toLowerCase());
    card.setAttribute('data-member-phone', phone.toLowerCase());
    card.setAttribute('data-member-email', email.toLowerCase());
    card.innerHTML = `
      <div class="w-10 h-10 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-lg shrink-0">${initial}</div>
      <div class="flex-1 min-w-0">
        <div class="text-ak-text font-semibold">${name}</div>
        <div class="text-ak-muted text-xs">${phone} · ${email}</div>
      </div>
      <div class="text-center px-3"><div class="text-ak-text font-bold text-lg">0</div><div class="text-ak-muted text-[10px]">0 sold</div></div>
      <div class="text-right px-3"><div class="text-ak-gold font-mono font-bold">¥0</div><div class="text-ak-muted text-[10px]">net payout</div></div>
    `;
    list.prepend(card);
    form.reset();
    btn.disabled = false; btn.textContent = '+ Add';
    // Clear search to show new member
    const search = document.getElementById('memberListSearch');
    if (search) search.value = '';
    filterMemberList();
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
    body: JSON.stringify({action:'save_auction', auction_id:activeAuctionId, name:form.name.value, date:form.date.value, commissionFee:form.commissionFee.value})
  }).then(r=>r.json()).then(d=>{
    if(d.error){alert(d.error);btn.disabled=false;btn.textContent='Save';return;}
    location.reload();
  }).catch(()=>{alert('Error');btn.disabled=false;btn.textContent='Save';});
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
    setTimeout(() => { closeEditMemberModal(); location.reload(); }, 600);
  })
  .catch(() => showToast('Connection error. Please try again.', 'error'))
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
