/* ── AuctionKai — Members JS ─────────────────── */

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

// ─── REMOVE MEMBER (AJAX) ──────────────────────────
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
