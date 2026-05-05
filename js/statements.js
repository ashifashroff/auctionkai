/* ── AuctionKai — Statements JS ──────────────── */

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

// ── WhatsApp Statement Sender ─────────────────
async function openWhatsApp(url, memberId, auctionId, netPayout) {
  window.open(url, '_blank');
  try {
    await fetch('api/log_statement.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        _tok: CSRF_TOKEN,
        auction_id: auctionId,
        member_id: memberId,
        action: 'whatsapp',
        net_payout: netPayout
      })
    });
  } catch {}
  showToast('💬 Opening WhatsApp...', 'info', 2000);
}

// ── Statement Link Generator ──────────────────
async function generateStatementLink(memberId, auctionId, btnEl) {
  const originalText = btnEl.innerHTML;
  btnEl.innerHTML = '⏳ Generating...';
  btnEl.disabled = true;
  const resultDiv = document.getElementById(`link-result-${memberId}`);
  try {
    const res = await fetch('api/generate_link.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({member_id: memberId, auction_id: auctionId})
    });
    const data = await res.json();
    if (data.success) {
      btnEl.innerHTML = '🔗 Share Link';
      btnEl.disabled = false;
      const expiry = new Date(data.expires_at).toLocaleDateString('ja-JP', {year:'numeric',month:'2-digit',day:'2-digit'});
      if (resultDiv) {
        resultDiv.className = 'mt-3 p-4 rounded-xl border border-ak-border bg-ak-infield';
        resultDiv.innerHTML = `
          <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
            <div>
              <div class="text-[10px] uppercase tracking-wider text-ak-muted mb-1">${data.is_new ? '✓ New link generated' : '🔗 Existing valid link'}</div>
              <div class="text-xs text-ak-muted">🔐 PIN: <span class="font-mono font-bold text-ak-gold text-sm tracking-[4px]">${data.pin}</span> <span class="text-ak-muted/60 ml-1">(last 4 digits of phone)</span></div>
              <div class="text-xs text-ak-muted mt-1">⏱ Expires: ${expiry} · 👁 ${data.views} view(s)</div>
            </div>
          </div>
          <div class="flex gap-2 items-center">
            <input type="text" readonly value="${data.url}" class="inp text-xs font-mono flex-1 text-ak-text2 cursor-pointer" onclick="this.select()" id="link-url-${memberId}">
            <button onclick="copyStatementLink('${data.url}', ${memberId})" class="btn btn-gold btn-sm shrink-0" id="copy-btn-${memberId}">Copy</button>
            <a href="${data.url}" target="_blank" class="btn btn-dark btn-sm shrink-0 text-center">Preview</a>
          </div>
          <div class="mt-2 text-[11px] text-ak-muted/60">Share this link with the member. They need their PIN to view it.</div>
        `;
        resultDiv.classList.remove('hidden');
      }
      showToast(data.is_new ? '🔗 Statement link generated!' : '🔗 Link retrieved', 'success', 3000);
    } else {
      showToast(data.message || 'Failed to generate link', 'error');
      btnEl.innerHTML = originalText;
      btnEl.disabled = false;
    }
  } catch {
    showToast('Connection error. Please try again.', 'error');
    btnEl.innerHTML = originalText;
    btnEl.disabled = false;
  }
}

async function copyStatementLink(url, memberId) {
  try {
    await navigator.clipboard.writeText(url);
    const btn = document.getElementById(`copy-btn-${memberId}`);
    if (btn) {
      const orig = btn.innerHTML;
      btn.innerHTML = '✓ Copied!';
      btn.style.background = '#4CAF82';
      btn.style.color = '#0A1420';
      setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; btn.style.color = ''; }, 2000);
    }
    showToast('Link copied to clipboard!', 'success', 2000);
  } catch {
    const input = document.getElementById(`link-url-${memberId}`);
    if (input) { input.select(); document.execCommand('copy'); showToast('Link copied!', 'success', 2000); }
  }
}

// ── Bulk: Mark All Unpaid as Paid ─────────────
async function markAllUnpaidAsPaid() {
  const unpaidCards = document.querySelectorAll('.statement-card[data-payment="unpaid"]');
  if (!unpaidCards.length) { showToast('No unpaid members found', 'info'); return; }
  if (!confirm(`Mark ${unpaidCards.length} unpaid member(s) as paid?`)) return;

  let success = 0, failed = 0;
  for (const card of unpaidCards) {
    const memberId = card.querySelector('[id^="pay-btn-"]')?.id?.replace('pay-btn-', '');
    if (!memberId) { failed++; continue; }
    try {
      const res = await fetch('api/update_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _tok: CSRF_TOKEN, auction_id: activeAuctionId, member_id: parseInt(memberId), status: 'paid', paid_amount: 0 })
      });
      const data = await res.json();
      if (data.success) success++; else failed++;
    } catch { failed++; }
  }
  showToast(`✓ ${success} marked as paid${failed ? `, ${failed} failed` : ''}`, success ? 'success' : 'error');
  setTimeout(() => location.reload(), 1500);
}
JSEEOF