/* ── AuctionKai — Vehicles JS ────────────────── */

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
        <td><span class="lot" data-field="lot">${this.esc(v.lot || '—')}</span></td>
        <td style="color:var(--ak-text2)">${this.esc(v.member_name || '?')}</td>
        <td style="color:var(--ak-text2)" data-field="make">${this.esc(v.make + ' ' + v.model)}</td>
        <td style="text-align:right;font-family:var(--mono);color:${soldClass}" data-field="sold_price">${price}</td>
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

// ── Inline Edit (double-click on vehicle table cells) ──
document.addEventListener('dblclick', function(e) {
  const td = e.target.closest('td[data-field]');
  if (!td) return;
  const row = td.closest('tr');
  if (!row || !row.id) return;
  const vehicleId = row.id.replace('vehicle-row-', '');
  if (!vehicleId) return;
  const field = td.dataset.field;
  const currentValue = td.textContent.trim();

  // Don't re-trigger if already editing
  if (td.querySelector('input')) return;

  const input = document.createElement('input');
  input.type = 'text';
  input.value = currentValue;
  input.className = 'inp text-xs w-full';
  input.style.minWidth = '60px';

  td.textContent = '';
  td.appendChild(input);
  input.focus();
  input.select();

  const save = async () => {
    const newValue = input.value.trim();
    td.textContent = newValue || currentValue;

    if (newValue && newValue !== currentValue) {
      try {
        const payload = { id: parseInt(vehicleId), _tok: CSRF_TOKEN };
        payload[field] = field === 'sold_price' || field === 'lot' ? (field === 'lot' ? newValue : parseFloat(newValue) || 0) : newValue;
        // Map data-field to API field names
        const fieldMap = { make: 'make', model: 'model', lot: 'lot', sold_price: 'soldPrice' };
        const apiPayload = { id: parseInt(vehicleId), _tok: CSRF_TOKEN };
        apiPayload[fieldMap[field] || field] = payload[field];

        const res = await fetch('api/update_vehicle.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(apiPayload)
        });
        const data = await res.json();
        if (data.error) {
          showToast(data.error, 'error');
          td.textContent = currentValue;
        } else {
          showToast('Updated', 'success', 1500);
          if (typeof VehiclesPager !== 'undefined') VehiclesPager.reload();
        }
      } catch {
        showToast('Update failed', 'error');
        td.textContent = currentValue;
      }
    }
  };

  input.addEventListener('blur', save);
  input.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') { ev.preventDefault(); input.blur(); }
    if (ev.key === 'Escape') { td.textContent = currentValue; }
  });
});
JSEEOF