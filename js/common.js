/* ── AuctionKai — Common JS (shared utilities) ─── */

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
