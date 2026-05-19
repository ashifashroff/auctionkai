/**
 * AuctionKai Animation System
 * Uses Web Animations API — no dependencies
 * Respects prefers-reduced-motion
 */

const AK = {

  get reducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  },

  ease: {
    out: [0.0, 0.0, 0.2, 1.0],
    outBack: [0.34, 1.56, 0.64, 1.0],
    outExpo: [0.16, 1, 0.3, 1],
    inOut: [0.4, 0, 0.2, 1],
    spring: [0.175, 0.885, 0.32, 1.275],
    snappy: [0.25, 0.46, 0.45, 0.94],
  },

  dur: { instant: 100, fast: 150, normal: 220, slow: 350, xslow: 500 },

  animate(el, keyframes, options) {
    if (!el) return Promise.resolve();
    if (this.reducedMotion) {
      const last = keyframes[keyframes.length - 1];
      Object.assign(el.style, last);
      return Promise.resolve();
    }
    return el.animate(keyframes, {
      duration: this.dur.normal,
      easing: `cubic-bezier(${this.ease.out.join(',')})`,
      fill: 'forwards',
      ...options,
    }).finished;
  },

  fadeIn(el, options = {}) {
    if (!el) return;
    el.style.opacity = '0';
    el.style.display = options.display || '';
    return this.animate(el, [{ opacity: 0 }, { opacity: 1 }], { duration: this.dur.fast, ...options });
  },

  fadeOut(el, options = {}) {
    if (!el) return Promise.resolve();
    return this.animate(el, [{ opacity: 1 }, { opacity: 0 }], { duration: this.dur.fast, ...options });
  },

  slideInDown(el, options = {}) {
    if (!el) return;
    return this.animate(el, [
      { opacity: 0, transform: 'translateY(-16px) scale(0.98)' },
      { opacity: 1, transform: 'translateY(0) scale(1)' },
    ], { duration: this.dur.normal, easing: `cubic-bezier(${this.ease.outExpo.join(',')})`, ...options });
  },

  slideInUp(el, options = {}) {
    if (!el) return;
    return this.animate(el, [
      { opacity: 0, transform: 'translateY(20px) scale(0.98)' },
      { opacity: 1, transform: 'translateY(0) scale(1)' },
    ], { duration: this.dur.normal, easing: `cubic-bezier(${this.ease.outExpo.join(',')})`, ...options });
  },

  slideOutLeft(el, options = {}) {
    if (!el) return Promise.resolve();
    const height = el.offsetHeight;
    return el.animate([
      { opacity: 1, transform: 'translateX(0)', maxHeight: height + 'px', marginBottom: getComputedStyle(el).marginBottom, overflow: 'hidden' },
      { opacity: 0, transform: 'translateX(-30px)', maxHeight: height + 'px', overflow: 'hidden' },
      { opacity: 0, transform: 'translateX(-30px)', maxHeight: '0px', marginBottom: '0px', overflow: 'hidden' },
    ], { duration: this.dur.slow, easing: `cubic-bezier(${this.ease.inOut.join(',')})`, fill: 'forwards', ...options }).finished;
  },

  scaleIn(el, options = {}) {
    if (!el) return;
    return this.animate(el, [
      { opacity: 0, transform: 'scale(0.92) translateY(8px)' },
      { opacity: 1, transform: 'scale(1) translateY(0)' },
    ], { duration: this.dur.normal, easing: `cubic-bezier(${this.ease.outBack.join(',')})`, ...options });
  },

  scaleOut(el, options = {}) {
    if (!el) return Promise.resolve();
    return el.animate([
      { opacity: 1, transform: 'scale(1) translateY(0)' },
      { opacity: 0, transform: 'scale(0.94) translateY(4px)' },
    ], { duration: this.dur.fast, easing: `cubic-bezier(${this.ease.inOut.join(',')})`, fill: 'forwards', ...options }).finished;
  },

  ripple(el, event) {
    if (this.reducedMotion) return;
    const rect = el.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 2;
    const x = (event?.clientX ?? rect.left + rect.width / 2) - rect.left - size / 2;
    const y = (event?.clientY ?? rect.top + rect.height / 2) - rect.top - size / 2;
    const ripple = document.createElement('span');
    ripple.style.cssText = `position:absolute;width:${size}px;height:${size}px;left:${x}px;top:${y}px;border-radius:50%;background:rgba(255,255,255,0.15);pointer-events:none;transform:scale(0);`;
    const prevPos = el.style.position;
    el.style.position = 'relative';
    el.style.overflow = 'hidden';
    el.appendChild(ripple);
    ripple.animate([
      { transform: 'scale(0)', opacity: 1 },
      { transform: 'scale(1)', opacity: 0 },
    ], { duration: 500, easing: 'ease-out', fill: 'forwards' }).finished.then(() => {
      ripple.remove();
      if (!prevPos) el.style.position = '';
    });
  },

  press(el) {
    if (!el || this.reducedMotion) return;
    el.animate([
      { transform: 'scale(1)' },
      { transform: 'scale(0.95)' },
      { transform: 'scale(1)' },
    ], { duration: 150, easing: 'ease-in-out' });
  },

  successPulse(el) {
    if (!el || this.reducedMotion) return;
    el.animate([
      { boxShadow: '0 0 0 0 rgba(76,175,130,0)' },
      { boxShadow: '0 0 0 8px rgba(76,175,130,0.3)' },
      { boxShadow: '0 0 0 0 rgba(76,175,130,0)' },
    ], { duration: 600, easing: 'ease-out' });
  },

  shake(el) {
    if (!el || this.reducedMotion) return;
    el.animate([
      { transform: 'translateX(0)' },
      { transform: 'translateX(-8px)' },
      { transform: 'translateX(8px)' },
      { transform: 'translateX(-6px)' },
      { transform: 'translateX(6px)' },
      { transform: 'translateX(-3px)' },
      { transform: 'translateX(0)' },
    ], { duration: 400, easing: 'ease-in-out' });
  },

  initCardHover(selector = '.bg-ak-card') {
    if (this.reducedMotion) return;
    document.querySelectorAll(selector).forEach(card => {
      if (card.dataset.hoverInit) return;
      card.dataset.hoverInit = '1';
      card.addEventListener('mouseenter', () => {
        card.animate([
          { transform: 'translateY(0)', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' },
          { transform: 'translateY(-2px)', boxShadow: '0 8px 25px rgba(0,0,0,0.4)' },
        ], { duration: 150, fill: 'forwards', easing: 'ease-out' });
      });
      card.addEventListener('mouseleave', () => {
        card.animate([
          { transform: 'translateY(-2px)', boxShadow: '0 8px 25px rgba(0,0,0,0.4)' },
          { transform: 'translateY(0)', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' },
        ], { duration: 200, fill: 'forwards', easing: 'ease-out' });
      });
    });
  },

  countUp(el, target, options = {}) {
    if (!el) return;
    const { duration = 800, prefix = '', suffix = '', decimals = 0 } = options;
    const compact = el.dataset.compact === 'true';
    const isMobile = window.innerWidth < 640;
    
    function formatYen(val) {
      if (compact) {
        // Always use 万 for amounts >= 100,000
        if (val >= 100000) {
          return prefix + (val / 10000).toLocaleString('ja-JP', {maximumFractionDigits: 0}) + '万';
        }
        if (val >= 10000) {
          return prefix + (val / 10000).toLocaleString('ja-JP', {maximumFractionDigits: 1}) + '万';
        }
      }
      return prefix + val.toLocaleString('ja-JP') + suffix;
    }
    
    if (this.reducedMotion) { el.textContent = formatYen(target); return; }
    const startTime = performance.now();
    function easeOutExpo(t) { return t === 1 ? 1 : 1 - Math.pow(2, -10 * t); }
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const current = Math.floor(target * easeOutExpo(progress));
      el.textContent = formatYen(current);
      if (progress < 1) requestAnimationFrame(update);
      else el.textContent = formatYen(target);
    }
    requestAnimationFrame(update);
  },

  staggerIn(parent, childSelector = ':scope > *', options = {}) {
    if (!parent || this.reducedMotion) return;
    const children = parent.querySelectorAll(childSelector);
    const delay = options.stagger ?? 50;
    children.forEach((child, i) => {
      child.style.opacity = '0';
      child.style.transform = 'translateY(12px)';
      setTimeout(() => {
        child.animate([
          { opacity: 0, transform: 'translateY(12px)' },
          { opacity: 1, transform: 'translateY(0)' },
        ], { duration: this.dur.normal, easing: `cubic-bezier(${this.ease.outExpo.join(',')})`, fill: 'forwards' });
      }, i * delay);
    });
  },

  highlightChange(el, type = 'success') {
    if (!el || this.reducedMotion) return;
    const color = type === 'success' ? 'rgba(76,175,130,0.3)' : 'rgba(204,119,119,0.3)';
    el.animate([{ backgroundColor: color }, { backgroundColor: 'transparent' }], { duration: 800, easing: 'ease-out' });
  },

  tabSwitch(el) {
    if (!el || this.reducedMotion) return;
    el.animate([
      { opacity: 0, transform: 'translateX(-8px)' },
      { opacity: 1, transform: 'translateX(0)' },
    ], { duration: 180, easing: `cubic-bezier(${this.ease.out.join(',')})` });
  },

  soldToggle(btn, isSold) {
    if (!btn || this.reducedMotion) return;
    const color = isSold ? 'rgba(76,175,130,0.5)' : 'rgba(204,119,119,0.3)';
    btn.animate([
      { transform: 'scale(0.92)', boxShadow: `0 0 0 0 ${color}` },
      { transform: 'scale(1.05)', boxShadow: `0 0 0 8px ${color}` },
      { transform: 'scale(1)', boxShadow: `0 0 0 0 ${color}` },
    ], { duration: 350, easing: `cubic-bezier(${this.ease.spring.join(',')})` });
  },

  paymentBadgeChange(el) {
    if (!el || this.reducedMotion) return;
    el.animate([
      { transform: 'scale(0.8) rotate(-3deg)', opacity: 0.5 },
      { transform: 'scale(1.1) rotate(1deg)', opacity: 1 },
      { transform: 'scale(1) rotate(0deg)', opacity: 1 },
    ], { duration: 300, easing: `cubic-bezier(${this.ease.outBack.join(',')})` });
  },

  shimmer(el) {
    if (!el || this.reducedMotion) return;
    el.style.position = 'relative';
    el.style.overflow = 'hidden';
    const shine = document.createElement('div');
    shine.style.cssText = `position:absolute;top:0;left:-100%;width:60%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.15),transparent);pointer-events:none;border-radius:inherit;`;
    el.appendChild(shine);
    shine.animate([{ left: '-60%' }, { left: '110%' }], { duration: 700, easing: 'ease-in-out' }).finished.then(() => shine.remove());
  },

  floatCheck(x, y) {
    if (this.reducedMotion) return;
    const el = document.createElement('div');
    el.textContent = '✓';
    el.style.cssText = `position:fixed;left:${x}px;top:${y}px;color:#4CAF82;font-size:20px;font-weight:700;pointer-events:none;z-index:99999;`;
    document.body.appendChild(el);
    el.animate([
      { opacity: 1, transform: 'translateY(0) scale(1)' },
      { opacity: 0, transform: 'translateY(-30px) scale(1.3)' },
    ], { duration: 600, easing: 'ease-out', fill: 'forwards' }).finished.then(() => el.remove());
  },
};

// Global button ripple
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn, .btn-gold, .btn-dark, .btn-email, .tab-btn, button[class*="btn"]');
  if (btn && !btn.disabled) AK.ripple(btn, e);
}, { passive: true });

// Global button press
document.addEventListener('mousedown', (e) => {
  const btn = e.target.closest('.btn, .btn-gold, .btn-dark, button');
  if (btn && !btn.disabled) AK.press(btn);
}, { passive: true });

// Auto-init on load
document.addEventListener('DOMContentLoaded', () => {
  AK.initCardHover('.bg-ak-card');

  // Count up numbers
  document.querySelectorAll('[data-countup="true"]').forEach(el => {
    const target = parseFloat(el.dataset.target || '0');
    const prefix = el.dataset.prefix || '';
    const suffix = el.dataset.suffix || '';
    AK.countUp(el, target, { duration: 800, prefix, suffix });
  });

  // Animate main content in
  const main = document.querySelector('.main-content, [class*="px-4"][class*="py-4"]');
  if (main) AK.tabSwitch(main);

  // Stagger statement cards
  const stmtContainer = document.getElementById('statements-container');
  if (stmtContainer) AK.staggerIn(stmtContainer, '.statement-card', { stagger: 60 });

  // Stagger member cards via MutationObserver
  const memberContainer = document.getElementById('members-list-container');
  if (memberContainer) {
    const observer = new MutationObserver(mutations => {
      mutations.forEach(m => {
        m.addedNodes.forEach(node => {
          if (node.nodeType === 1) AK.slideInDown(node, { duration: 180 });
        });
      });
    });
    observer.observe(memberContainer, { childList: true });
  }
});
