(() => {
  const root = document.documentElement;

  const applyTheme = (mode) => {
    let resolved = mode;
    if (mode === 'system') {
      resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    root.setAttribute('data-theme', resolved);
    localStorage.setItem('edgeplay-theme', mode);
    document.dispatchEvent(new CustomEvent('edgeplay:theme', { detail: { mode: resolved } }));
  };

  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(current);
    });
  });

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) return;
    const field = toggle.closest('.password-field');
    const input = field ? field.querySelector('input') : null;
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggle.textContent = show ? 'Hide' : 'Show';
  });

  document.querySelectorAll('form[data-password-match]').forEach((form) => {
    const password = form.querySelector('[name="password"]');
    const confirm = form.querySelector('[name="password_confirmation"]');
    const matchError = form.querySelector('[data-password-match-error]');
    if (!password || !confirm) return;

    const sync = () => {
      const mismatch = confirm.value !== '' && password.value !== confirm.value;
      confirm.setCustomValidity(mismatch ? 'Password and confirm password must match.' : '');
      if (matchError) matchError.hidden = !mismatch;
    };

    password.addEventListener('input', sync);
    confirm.addEventListener('input', sync);
    form.addEventListener('submit', (event) => {
      sync();
      if (password.value !== confirm.value) {
        event.preventDefault();
        confirm.reportValidity();
      }
    });
  });

  const dialogOpen = () => Boolean(
    document.querySelector('.ob-checkout:not([hidden]), .ob-cookies:not([hidden]), .ob-confirm:not([hidden]), .an-sync-modal:not([hidden])')
  );

  const setMenuOpen = (open) => {
    const nav = document.getElementById('mobile-nav');
    const btn = document.querySelector('.header-actions [data-nav-toggle], [data-nav-toggle]');
    const scrim = document.querySelector('[data-nav-scrim]');
    if (!nav) return;
    nav.toggleAttribute('hidden', !open);
    scrim?.toggleAttribute('hidden', !open);
    btn?.setAttribute('aria-expanded', String(open));
    btn?.classList.toggle('is-open', open);
    document.body.classList.toggle('menu-open', open);
  };

  const setSidebarOpen = (open) => {
    const sidebar = document.querySelector('.sidebar');
    const scrim = document.querySelector('.sidebar-scrim');
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', open);
    scrim?.toggleAttribute('hidden', !open);
    document.querySelectorAll('.topbar [data-sidebar-toggle]').forEach((btn) => {
      btn.setAttribute('aria-expanded', String(open));
    });
    if (open) document.body.classList.add('nav-lock');
    else if (!dialogOpen()) document.body.classList.remove('nav-lock');
  };

  document.querySelectorAll('[data-nav-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const nav = document.getElementById('mobile-nav');
      if (!nav) return;
      setMenuOpen(nav.hasAttribute('hidden'));
    });
  });

  document.getElementById('mobile-nav')?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenuOpen(false));
  });

  document.querySelectorAll('[data-sidebar-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const sidebar = document.querySelector('.sidebar');
      if (!sidebar) return;
      setSidebarOpen(!sidebar.classList.contains('is-open'));
    });
  });

  document.querySelectorAll('.sidebar nav a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 899px)').matches) setSidebarOpen(false);
    });
  });

  window.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    setMenuOpen(false);
    setSidebarOpen(false);
  });

  const desktopMq = window.matchMedia('(min-width: 900px)');
  const syncDesktopNav = () => {
    if (!desktopMq.matches) return;
    setMenuOpen(false);
    setSidebarOpen(false);
  };
  if (desktopMq.addEventListener) desktopMq.addEventListener('change', syncDesktopNav);
  else desktopMq.addListener(syncDesktopNav);

  const countdown = document.querySelector('[data-countdown]');
  if (countdown) {
    const target = new Date(countdown.getAttribute('data-countdown')).getTime();
    const tick = () => {
      const diff = Math.max(0, target - Date.now());
      const days = Math.floor(diff / 86400000);
      const hours = Math.floor((diff % 86400000) / 3600000);
      const minutes = Math.floor((diff % 3600000) / 60000);
      const seconds = Math.floor((diff % 60000) / 1000);
      const set = (sel, val) => {
        const el = countdown.querySelector(sel);
        if (el) el.textContent = String(val).padStart(2, '0');
      };
      set('[data-days]', days);
      set('[data-hours]', hours);
      set('[data-minutes]', minutes);
      set('[data-seconds]', seconds);
    };
    tick();
    setInterval(tick, 1000);
  }

  const faqSearch = document.querySelector('[data-faq-search]');
  if (faqSearch) {
    faqSearch.addEventListener('input', () => {
      const q = faqSearch.value.toLowerCase();
      document.querySelectorAll('[data-faq-item]').forEach((item) => {
        item.hidden = q !== '' && !item.textContent.toLowerCase().includes(q);
      });
    });
  }

  if (document.body.classList.contains('is-marketing') || document.body.classList.contains('is-auth')) {
    root.setAttribute('data-theme', 'dark');
  }

  const header = document.querySelector('body.is-marketing .site-header');
  if (header) {
    const onScroll = () => header.classList.toggle('is-solid', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  document.querySelectorAll('[data-count]').forEach((el) => {
    const to = parseInt(el.dataset.count, 10);
    if (Number.isNaN(to)) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) { el.textContent = String(to); return; }
    let t0 = null;
    const from = Math.max(0, to - 14);
    const step = (t) => {
      if (!t0) t0 = t;
      const k = Math.min(1, (t - t0) / 900);
      const e = 1 - Math.pow(1 - k, 3);
      el.textContent = String(Math.round(from + (to - from) * e));
      if (k < 1) requestAnimationFrame(step);
      else el.textContent = String(to);
    };
    requestAnimationFrame(step);
  });

  const board = document.querySelector('.ob-board[data-kickoff]');
  if (board) {
    const target = new Date(board.getAttribute('data-kickoff')).getTime();
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const units = { d: 3, h: 2, m: 2, s: 2 };
    const flaps = {};
    Object.keys(units).forEach((u) => {
      const host = board.querySelector('[data-unit="' + u + '"]');
      flaps[u] = [];
      if (!host) return;
      for (let i = 0; i < units[u]; i += 1) {
        const f = document.createElement('div');
        f.className = 'ob-flap';
        f.innerHTML = '<span>0</span>';
        f.dataset.v = '0';
        host.appendChild(f);
        flaps[u].push(f);
      }
    });
    const setFlap = (f, val) => {
      if (!f || f.dataset.v === val) return;
      f.dataset.v = val;
      const span = f.querySelector('span');
      if (span) span.textContent = val;
    };
    const render = (u, num, width) => {
      let s = String(num);
      while (s.length < width) s = '0' + s;
      if (s.length > width) s = s.slice(-width);
      (flaps[u] || []).forEach((f, i) => setFlap(f, s[i]));
    };
    let live = false;
    const swapToLive = () => {
      if (live) return;
      live = true;
      board.classList.add('is-live');
      ['.ob-board__title', '.ob-board__sub', '.ob-board__cta'].forEach((sel) => {
        const el = board.querySelector(sel);
        if (el && el.dataset.live) el.textContent = el.dataset.live;
      });
    };
    const tickBoard = () => {
      const diff = target - Date.now();
      if (diff <= 0 || Number.isNaN(target)) { swapToLive(); return; }
      const t = Math.floor(diff / 1000);
      render('d', Math.floor(t / 86400), 3);
      render('h', Math.floor((t % 86400) / 3600), 2);
      render('m', Math.floor((t % 3600) / 60), 2);
      render('s', t % 60, 2);
    };
    tickBoard();
    setInterval(tickBoard, 1000);
    if (reduce) { /* flaps already set */ }
  }

  const slate = document.querySelector('[data-slate]');
  if (slate) {
    const slates = [
      { sport: 'NFL', cadence: 'Sent before kickoff', rows: [
        ['HOME −2.5', '8:20 PM ET', '−110', '1 UNIT'],
        ['UNDER 44.5', '1:00 PM ET', '−105', '1 UNIT'],
        ['AWAY MONEYLINE', '4:25 PM ET', '+120', '1 UNIT'],
      ]},
      { sport: 'NBA', cadence: 'Sent before tip-off', rows: [
        ['HOME −4.5', '7:30 PM ET', '−110', '1 UNIT'],
        ['OVER 224.5', '10:00 PM ET', '−108', '1 UNIT'],
        ['AWAY MONEYLINE', '8:00 PM ET', '+135', '1 UNIT'],
      ]},
      { sport: 'MLB', cadence: 'Sent before first pitch', rows: [
        ['UNDER 8.5', '7:05 PM ET', '−115', '1 UNIT'],
        ['HOME F5 MONEYLINE', '7:40 PM ET', '−120', '1 UNIT'],
        ['AWAY MONEYLINE', '9:40 PM ET', '+105', '1 UNIT'],
      ]},
      { sport: 'COLLEGE FOOTBALL', cadence: 'Sent before kickoff', rows: [
        ['HOME −7.5', '12:00 PM ET', '−110', '1 UNIT'],
        ['OVER 51.5', '3:30 PM ET', '−112', '1 UNIT'],
        ['AWAY MONEYLINE', '7:00 PM ET', '+145', '1 UNIT'],
      ]},
    ];
    let idx = 0;
    const paint = (n) => {
      const item = slates[n];
      const sport = slate.querySelector('[data-sport]');
      const cadence = slate.querySelector('[data-cadence]');
      const rows = slate.querySelector('[data-rows]');
      const dots = slate.querySelectorAll('[data-dots] i');
      if (sport) sport.textContent = item.sport;
      if (cadence) cadence.textContent = item.cadence;
      if (rows) {
        rows.innerHTML = item.rows.map((r) => (
          '<div class="ob-pb__row is-in"><div><span class="ob-pb__play">' + r[0] + '</span><span class="ob-pb__time">' + r[1] + '</span></div>' +
          '<div class="ob-pb__cell"><b>' + r[2] + '</b><span>Price</span></div>' +
          '<div class="ob-pb__cell"><b>' + r[3] + '</b><span>Stake</span></div></div>'
        )).join('');
      }
      dots.forEach((d, i) => d.classList.toggle('is-on', i === n));
    };
    setInterval(() => {
      idx = (idx + 1) % slates.length;
      paint(idx);
    }, 6000);
  }

  (() => {
    if (window.__orionDialog) return;
    window.__orionDialog = true;

    let root = null;
    let lastFocus = null;
    let resolver = null;

    const html = `
      <div class="ob-confirm__scrim" data-confirm-dismiss></div>
      <div class="ob-confirm__panel" role="dialog" aria-modal="true" aria-labelledby="ob-confirm-title" aria-describedby="ob-confirm-copy">
        <p class="ob-confirm__kicker" id="ob-confirm-kicker">Confirm</p>
        <h2 class="ob-confirm__title" id="ob-confirm-title"></h2>
        <p class="ob-confirm__copy" id="ob-confirm-copy"></p>
        <div class="ob-confirm__actions">
          <button type="button" class="btn btn-ghost ob-confirm__cancel" data-confirm-cancel>Cancel</button>
          <button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>
        </div>
      </div>`;

    const mount = () => {
      if (root) return root;
      root = document.createElement('div');
      root.className = 'ob-confirm';
      root.hidden = true;
      root.innerHTML = html;
      document.body.appendChild(root);
      root.querySelector('[data-confirm-dismiss]').addEventListener('click', () => close(false));
      root.querySelector('[data-confirm-cancel]').addEventListener('click', () => close(false));
      root.querySelector('[data-confirm-ok]').addEventListener('click', () => close(true));
      root.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); close(false); return; }
        if (e.key !== 'Tab') return;
        const nodes = [...root.querySelectorAll('button:not([hidden])')].filter((el) => el.offsetParent !== null);
        if (!nodes.length) return;
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      });
      return root;
    };

    const close = (ok) => {
      if (!root || root.hidden) return;
      root.hidden = true;
      document.body.classList.remove('nav-lock');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
      const done = resolver;
      resolver = null;
      if (done) done(ok);
    };

    const open = (opts) => {
      const el = mount();
      const tone = opts.tone || 'default';
      const isAlert = !!opts.alert;
      lastFocus = document.activeElement;
      el.classList.toggle('is-danger', tone === 'danger');
      el.classList.toggle('is-restore', tone === 'restore');
      el.classList.toggle('is-alert', isAlert);
      el.querySelector('#ob-confirm-kicker').textContent = opts.kicker || (isAlert ? 'Notice' : (tone === 'danger' ? 'Please confirm' : 'Confirm'));
      el.querySelector('#ob-confirm-title').textContent = opts.title || 'Are you sure?';
      const copy = el.querySelector('#ob-confirm-copy');
      copy.textContent = opts.copy || '';
      copy.hidden = !opts.copy;
      el.querySelector('[data-confirm-cancel]').textContent = opts.cancel || 'Cancel';
      const okBtn = el.querySelector('[data-confirm-ok]');
      okBtn.textContent = opts.ok || (isAlert ? 'Got it' : 'Confirm');
      okBtn.className = 'btn btn-small ' + (tone === 'danger' ? 'btn-danger' : 'btn-primary');
      el.hidden = false;
      document.body.classList.add('nav-lock');
      (isAlert ? okBtn : el.querySelector('[data-confirm-cancel]')).focus();
      return new Promise((resolve) => { resolver = resolve; });
    };

    const fromNode = (node) => open({
      title: node.getAttribute('data-confirm'),
      copy: node.getAttribute('data-confirm-copy') || '',
      ok: node.getAttribute('data-confirm-ok') || 'Confirm',
      cancel: node.getAttribute('data-confirm-cancel') || 'Cancel',
      tone: node.getAttribute('data-confirm-tone') || 'default',
      kicker: node.getAttribute('data-confirm-kicker') || '',
    });

    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') return;
      const source = (e.submitter && e.submitter.hasAttribute('data-confirm')) ? e.submitter : form;
      const native = /confirm\s*\(/.test(form.getAttribute('onsubmit') || '');
      if (!source.hasAttribute('data-confirm') && !native) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      form.removeAttribute('onsubmit');
      form.onsubmit = null;
      if (native && !source.hasAttribute('data-confirm')) {
        const match = (form.getAttribute('onsubmit') || '').match(/confirm\s*\(\s*(['"])([\s\S]*?)\1/);
        if (match) form.setAttribute('data-confirm', match[2]);
      }
      fromNode(source.hasAttribute('data-confirm') ? source : form).then((ok) => {
        if (!ok) return;
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit(e.submitter || undefined);
        else form.submit();
      });
    }, true);

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-confirm]');
      if (!btn || btn.closest('form') || btn.tagName === 'FORM') return;
      e.preventDefault();
      fromNode(btn).then((ok) => {
        if (!ok) return;
        if (btn.tagName === 'A' && btn.href) window.location.href = btn.href;
      });
    });

    window.orionConfirm = (title, copy, opts = {}) => open({ title, copy, ...opts });
    window.orionAlert = (title, copy, opts = {}) => open({ title, copy, alert: true, ...opts });
  })();

  (() => {
    if (window.__orionCookies) return;
    window.__orionCookies = true;

    const KEY = 'orion-cookie-consent';
    const COOKIE = 'orion_cookie_consent=accepted; Path=/; Max-Age=31536000; SameSite=Lax';
    const root = document.querySelector('[data-cookie-consent]');
    const html = document.documentElement;

    const hasConsent = () => {
      try {
        if (localStorage.getItem(KEY) === 'accepted') return true;
      } catch (err) {}
      return /(?:^|;\s*)orion_cookie_consent=accepted(?:;|$)/.test(document.cookie);
    };

    const unlock = () => {
      try { localStorage.setItem(KEY, 'accepted'); } catch (err) {}
      document.cookie = COOKIE + (window.location.protocol === 'https:' ? '; Secure' : '');
      html.classList.remove('cookie-pending');
      html.classList.add('cookie-ok');
      document.body.classList.remove('nav-lock');
      if (root) root.hidden = true;
    };

    if (hasConsent()) {
      unlock();
      return;
    }

    if (!root) return;

    html.classList.add('cookie-pending');
    html.classList.remove('cookie-ok');
    if (root.classList.contains('is-legal')) {
      document.body.classList.add('is-legal-read');
    } else {
      document.body.classList.add('nav-lock');
    }

    const allowBtn = root.querySelector('[data-cookie-allow]');
    const declineBtn = root.querySelector('[data-cookie-decline]');
    const denyMsg = root.querySelector('[data-cookie-deny-msg]');

    const refuse = () => {
      root.classList.add('is-denied');
      if (denyMsg) denyMsg.hidden = false;
      if (allowBtn) allowBtn.focus();
    };

    const trapFocus = (e) => {
      if (html.classList.contains('cookie-ok')) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        refuse();
        return;
      }
      if (e.key !== 'Tab') return;
      const nodes = [...root.querySelectorAll('a[href], button:not([disabled])')];
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };

    document.addEventListener('keydown', trapFocus, true);
    root.querySelector('.ob-cookies__scrim')?.addEventListener('click', refuse);
    if (allowBtn) allowBtn.addEventListener('click', unlock);
    if (declineBtn) declineBtn.addEventListener('click', refuse);

    window.setTimeout(() => {
      if (allowBtn) allowBtn.focus();
    }, 0);
  })();

  (() => {
    if (window.__orionCheckout) return;
    window.__orionCheckout = true;

    const root = document.querySelector('.ob-checkout');
    if (!root) return;

    const titleEl = root.querySelector('#ob-checkout-title');
    const metaEl = root.querySelector('[data-checkout-meta]');
    const chooser = root.querySelector('[data-checkout-chooser]');
    const errorEl = root.querySelector('[data-checkout-error]');
    const discordPanel = root.querySelector('[data-checkout-panel="discord"]');
    const paypalPanel = root.querySelector('[data-checkout-panel="paypal"]');
    const discordGate = root.querySelector('[data-checkout-discord-gate]');
    const frameEl = root.querySelector('[data-checkout-frame]');
    const loadEl = root.querySelector('[data-checkout-load]');
    const loadCopy = root.querySelector('[data-checkout-load-copy]');
    const doneEl = root.querySelector('[data-checkout-done]');
    const doneTitle = root.querySelector('[data-checkout-done-title]');
    const doneCopy = root.querySelector('[data-checkout-done-copy]');
    const doneMeta = root.querySelector('[data-checkout-done-meta]');
    const doneAccount = root.querySelector('[data-checkout-done-account]');
    const doneClaim = root.querySelector('[data-checkout-done-claim]');
    const doneDiscord = root.querySelector('[data-checkout-done-discord]');
    const firstNameEl = root.querySelector('[data-checkout-first-name]');
    const lastNameEl = root.querySelector('[data-checkout-last-name]');
    const paypalEmailEl = root.querySelector('[data-checkout-paypal-email]');
    const tabs = root.querySelectorAll('[data-checkout-tab]');
    let lastFocus = null;
    let currentUrl = '';
    let currentPlanId = '';
    let currentTitle = '';
    let currentPrice = '';
    let currentInterval = '';
    let currentTab = 'discord';
    let pollTimer = 0;
    let payToken = '';
    let thankYouUrl = '';
    let paypalButtons = null;
    let paypalReady = false;
    let discordMounting = false;

    const attr = (name) => (root.getAttribute(name) || '').trim();
    const signedIn = () => attr('data-checkout-signed-in') === '1';
    const hasDiscord = () => attr('data-checkout-discord') === '1';
    const paypalClientId = () => attr('data-paypal-client-id');
    const csrf = () => attr('data-checkout-csrf');
    const startUrl = () => attr('data-checkout-start') || '/checkout/start';
    const statusUrl = () => attr('data-checkout-status') || '/checkout/status';
    const paypalCreateUrl = () => attr('data-checkout-paypal-create') || '/checkout/paypal/create-order';
    const paypalCaptureUrl = () => attr('data-checkout-paypal-capture') || '/checkout/paypal/capture-order';

    const allowed = (url) => {
      try {
        const parsed = new URL(url);
        return parsed.protocol === 'https:' && (parsed.hostname === 'upgrade.chat' || parsed.hostname === 'www.upgrade.chat');
      } catch (err) {
        return false;
      }
    };

    const cadence = (interval, price) => {
      const raw = String(interval || '').trim() || String(price || '').split('/')[1] || '';
      const v = raw.replace(/^\s+|\s+$/g, '').toLowerCase();
      if (!v) return '';
      if (v === 'month' || v === 'monthly') return 'MONTH';
      if (v === 'year' || v === 'yearly') return 'YEAR';
      if (v === 'season') return 'SEASON';
      if (v.includes('one')) return 'ONE TIME';
      return v.toUpperCase();
    };

    const formatSum = (price, interval) => {
      const amount = String(price || '').replace(/\s*\/\s*.*$/, '').trim();
      const cycle = cadence(interval, price);
      if (!amount) return cycle;
      return cycle ? amount + ' / ' + cycle.toLowerCase() : amount;
    };

    const showError = (message) => {
      if (!errorEl) return;
      errorEl.textContent = message || '';
      errorEl.hidden = !message;
    };

    const identity = () => ({
      name: attr('data-checkout-user-name'),
      first: attr('data-checkout-user-first'),
      last: attr('data-checkout-user-last'),
      email: attr('data-checkout-user-email'),
    });

    const applyUser = (user, extra = {}) => {
      if (!user) return;
      const first = String(user.first_name || extra.first || '').trim();
      const last = String(user.last_name || extra.last || '').trim();
      const name = String(user.name || [first, last].filter(Boolean).join(' ')).trim();
      const email = String(user.email || extra.email || '').trim();
      if (name) root.setAttribute('data-checkout-user-name', name);
      if (first) root.setAttribute('data-checkout-user-first', first);
      if (last) root.setAttribute('data-checkout-user-last', last);
      if (email) root.setAttribute('data-checkout-user-email', email);
      if (user.discord_id) root.setAttribute('data-checkout-discord', '1');
      root.setAttribute('data-checkout-signed-in', '1');
      if (extra.csrf) root.setAttribute('data-checkout-csrf', extra.csrf);
      if (firstNameEl && !firstNameEl.value) firstNameEl.value = first;
      if (lastNameEl && !lastNameEl.value) lastNameEl.value = last;
      if (paypalEmailEl && !paypalEmailEl.value) paypalEmailEl.value = email;
    };

    const stopPoll = () => {
      if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = 0;
      }
    };

    const thanksUrl = (token) => {
      if (thankYouUrl) return thankYouUrl;
      const sid = token || payToken;
      const base = attr('data-checkout-thanks') || '/thank-you';
      return sid ? base + (base.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(sid) : base;
    };

    const goThanks = (token) => {
      const dest = thanksUrl(token);
      if (dest) window.location.href = dest;
    };

    const jsonPost = async (url, body) => {
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || 'Request failed.');
      return data;
    };

    const everflowFields = () => {
      const tracking = (window.orionEverflowTracking && window.orionEverflowTracking()) || {};
      return {
        ef_transaction_id: (window.orionEverflowId && window.orionEverflowId()) || window.orionEverflowTid || tracking.transaction_id || '',
        impression_id: tracking.impression_id || '',
        sub1: tracking.sub1 || '',
        sub2: tracking.sub2 || '',
        sub3: tracking.sub3 || '',
        sub4: tracking.sub4 || '',
        sub5: tracking.sub5 || '',
        affid: tracking.affid || '',
        oid: tracking.oid || '',
      };
    };

    const showDone = (session) => {
      stopPoll();
      root.classList.add('is-live');
      root.classList.add('is-done');
      root.classList.remove('is-paypal');
      if (loadEl) loadEl.hidden = true;
      if (chooser) chooser.hidden = true;
      if (discordPanel) discordPanel.hidden = true;
      if (paypalPanel) paypalPanel.hidden = true;
      if (frameEl) {
        frameEl.hidden = true;
        frameEl.removeAttribute('src');
      }
      const guest = session && session.guest !== false && !signedIn();
      if (doneTitle) doneTitle.textContent = guest ? 'Guest access is ready' : 'Payment confirmed';
      if (doneCopy) {
        doneCopy.textContent = guest
          ? 'Your order is confirmed. Create an account with this email or sign up with Discord so billing history stays on this account.'
          : 'Your order is confirmed. Open billing history any time from your desk.';
      }
      if (doneMeta) {
        const bits = [];
        if (session && session.email) bits.push(session.email);
        if (session && session.transaction_id) bits.push('Txn ' + session.transaction_id);
        if (session && session.order_id) bits.push('Order ' + session.order_id);
        doneMeta.textContent = bits.join(' · ');
        doneMeta.hidden = bits.length === 0;
      }
      if (doneAccount) {
        doneAccount.hidden = guest;
        if (session && session.account_url) doneAccount.setAttribute('href', session.account_url);
      }
      if (doneClaim) {
        doneClaim.hidden = !guest;
        if (session && session.register_url) doneClaim.setAttribute('href', session.register_url);
      }
      if (doneDiscord) {
        doneDiscord.hidden = !guest;
        if (session && session.discord_url) doneDiscord.setAttribute('href', session.discord_url);
      }
      if (doneEl) doneEl.hidden = false;
      if (session && session.thank_you_url) {
        thankYouUrl = session.thank_you_url;
      }
      window.setTimeout(() => goThanks(session && session.token ? session.token : payToken), 500);
    };

    const applyStatus = (session) => {
      if (!session || !session.status) return;
      if (session.status === 'completed') showDone(session);
    };

    const pollStatus = (token) => {
      stopPoll();
      if (!token) return;
      let ticks = 0;
      const tick = async () => {
        ticks += 1;
        try {
          const res = await fetch(statusUrl() + '?token=' + encodeURIComponent(token) + '&probe=1', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });
          const data = await res.json().catch(() => ({}));
          applyStatus(data);
          if (data.status === 'completed' || data.status === 'cancelled' || ticks > 200) stopPoll();
        } catch (err) {}
      };
      tick();
      pollTimer = window.setInterval(tick, 3000);
    };

    const showLoad = (copy) => {
      if (loadCopy) loadCopy.textContent = copy || 'Opening checkout';
      if (loadEl) loadEl.hidden = false;
    };

    const hideLoad = () => {
      if (loadEl) loadEl.hidden = true;
    };

    const mountDiscordFrame = async () => {
      if (discordMounting) return;
      if (frameEl && frameEl.getAttribute('src')) return;
      if (!allowed(currentUrl)) {
        showError('This plan needs a valid Upgrade.Chat payment link.');
        return;
      }
      const who = identity();
      if (!who.email) {
        showError('Connect Discord so checkout can match your account.');
        return;
      }
      showError('');
      if (discordGate) discordGate.hidden = true;
      showLoad('Opening Discord checkout');
      root.classList.add('is-live');
      root.classList.remove('is-paypal');
      discordMounting = true;
      try {
        const data = await jsonPost(startUrl(), Object.assign({
          email: who.email,
          name: who.name || [who.first, who.last].filter(Boolean).join(' '),
          url: currentUrl,
          plan_id: currentPlanId || 0,
          channel: 'discord',
        }, everflowFields()));
        payToken = data.token || '';
        thankYouUrl = (data.session && data.session.thank_you_url) || thanksUrl(payToken);
        if (frameEl) {
          frameEl.hidden = false;
          frameEl.src = data.frame_url;
        }
        pollStatus(payToken);
      } catch (err) {
        root.classList.remove('is-live');
        if (discordGate) discordGate.hidden = false;
        hideLoad();
        showError(err.message || 'Discord checkout could not start.');
      } finally {
        discordMounting = false;
      }
    };

    const paypalFields = () => {
      const first = ((firstNameEl && firstNameEl.value) || '').trim();
      const last = ((lastNameEl && lastNameEl.value) || '').trim();
      const email = ((paypalEmailEl && paypalEmailEl.value) || '').trim().toLowerCase();
      if (!first) return { ok: false, error: 'Enter your first name.' };
      if (!last) return { ok: false, error: 'Enter your last name.' };
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return { ok: false, error: 'Enter a valid email address.' };
      }
      return { ok: true, first, last, email };
    };

    const lockPaypalIdentity = (lock) => {
      [firstNameEl, lastNameEl, paypalEmailEl].forEach((el) => {
        if (!el) return;
        el.readOnly = !!lock;
        el.classList.toggle('is-locked', !!lock);
      });
    };

    const paypalSdkSrc = () => 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(paypalClientId()) + '&intent=capture&currency=USD&components=buttons';

    const paypalSdkMessage = async () => {
      try {
        const res = await fetch(paypalSdkSrc(), { mode: 'cors', credentials: 'omit' });
        const text = await res.text();
        if (/client-id not recognized/i.test(text) || /SDK Validation error/i.test(text)) {
          return 'PayPal rejected this client ID. Create a Sandbox REST app at developer.paypal.com, copy Client ID and Secret into production .env (PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET), then refresh.';
        }
        if (!res.ok || /throw new Error/i.test(text)) {
          return 'PayPal could not start with the current Client ID. Check PAYPAL_CLIENT_ID and PAYPAL_ENV in production .env.';
        }
      } catch (err) {}
      return 'PayPal buttons did not start. Allow www.paypal.com and www.sandbox.paypal.com, then refresh.';
    };

    const loadPaypalSdk = () => new Promise((resolve, reject) => {
      if (window.paypal && typeof window.paypal.Buttons === 'function') {
        resolve(window.paypal);
        return;
      }
      if (!paypalClientId()) {
        reject(new Error('PayPal is not configured on the server.'));
        return;
      }
      let settled = false;
      const pass = () => {
        if (settled || !window.paypal || typeof window.paypal.Buttons !== 'function') return false;
        settled = true;
        resolve(window.paypal);
        return true;
      };
      const fail = () => {
        if (settled) return;
        settled = true;
        paypalSdkMessage().then((message) => reject(new Error(message)), () => {
          reject(new Error('PayPal could not start with the current Client ID.'));
        });
      };
      const src = paypalSdkSrc();
      let script = document.querySelector('script[data-orion-paypal-sdk]');
      if (!script) {
        script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.setAttribute('data-orion-paypal-sdk', '1');
        document.head.appendChild(script);
      }
      if (pass()) return;
      script.addEventListener('load', () => {
        if (!pass()) fail();
      });
      script.addEventListener('error', fail);
      window.setTimeout(() => {
        if (!pass()) fail();
      }, 8000);
    });

    const teardownPaypal = () => {
      paypalReady = false;
      if (paypalButtons && typeof paypalButtons.close === 'function') {
        try { paypalButtons.close(); } catch (err) {}
      }
      paypalButtons = null;
      const box = document.getElementById('paypal-button-container');
      if (box) box.innerHTML = '';
    };

    const renderPaypalButtons = async () => {
      if (paypalReady || !paypalClientId()) return;
      try {
        const paypal = await loadPaypalSdk();
        const box = document.getElementById('paypal-button-container');
        if (!paypal || typeof paypal.Buttons !== 'function' || !box) {
          throw new Error('PayPal buttons are not available. Confirm PAYPAL_CLIENT_ID matches PAYPAL_ENV, then refresh.');
        }
        box.innerHTML = '';
        paypalButtons = paypal.Buttons({
          style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'paypal' },
          onInit: (_data, actions) => {
            const sync = () => {
              try {
                if (paypalFields().ok) actions.enable();
                else actions.disable();
              } catch (err) {}
            };
            sync();
            [firstNameEl, lastNameEl, paypalEmailEl].forEach((el) => {
              if (el) el.addEventListener('input', sync);
            });
          },
          onClick: (_data, actions) => {
            const fields = paypalFields();
            if (!fields.ok) {
              showError(fields.error);
              lockPaypalIdentity(false);
              return actions.reject();
            }
            showError('');
            lockPaypalIdentity(true);
            return actions.resolve();
          },
          createOrder: async () => {
            const fields = paypalFields();
            if (!fields.ok) throw new Error(fields.error);
            try {
              const data = await jsonPost(paypalCreateUrl(), Object.assign({
                plan_id: currentPlanId || 0,
                first_name: fields.first,
                last_name: fields.last,
                email: fields.email,
              }, everflowFields()));
              payToken = data.token || '';
              thankYouUrl = (data.session && data.session.thank_you_url) || thanksUrl(payToken);
              if (!data.id) throw new Error('PayPal did not return an order id.');
              lockPaypalIdentity(true);
              return data.id;
            } catch (err) {
              lockPaypalIdentity(false);
              throw err;
            }
          },
          onApprove: async (data) => {
            showLoad('Confirming PayPal payment');
            try {
              const fields = paypalFields();
              const result = await jsonPost(paypalCaptureUrl(), {
                orderID: data.orderID,
                planId: currentPlanId || 0,
                plan_id: currentPlanId || 0,
                email: fields.email,
                firstName: fields.first,
                lastName: fields.last,
                first_name: fields.first,
                last_name: fields.last,
                token: payToken,
              });
              if (result.redirectUrl) thankYouUrl = result.redirectUrl;
              showDone(result.session || { status: 'completed', token: payToken, thank_you_url: thankYouUrl });
            } catch (err) {
              hideLoad();
              showError((err && err.message) || 'PayPal could not complete this payment.');
            }
          },
          onError: (err) => {
            lockPaypalIdentity(false);
            hideLoad();
            showError((err && err.message) || 'PayPal could not complete this payment.');
          },
          onCancel: () => {
            lockPaypalIdentity(false);
            hideLoad();
            showError('PayPal checkout was cancelled. You can try again without leaving this page.');
          },
        });
        await paypalButtons.render('#paypal-button-container');
        paypalReady = true;
      } catch (err) {
        if (currentTab === 'paypal') {
          showError(err.message || 'PayPal buttons could not load.');
        }
      }
    };

    const selectTab = (name) => {
      currentTab = name === 'paypal' ? 'paypal' : 'discord';
      tabs.forEach((tab) => {
        const on = tab.getAttribute('data-checkout-tab') === currentTab;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      if (discordPanel) discordPanel.hidden = currentTab !== 'discord';
      if (paypalPanel) paypalPanel.hidden = currentTab !== 'paypal';
      root.classList.toggle('is-paypal', currentTab === 'paypal');
      if (currentTab === 'paypal') {
        root.classList.remove('is-live');
        hideLoad();
        renderPaypalButtons();
      } else {
        showError('');
        if (hasDiscord() && allowed(currentUrl) && (!frameEl || !frameEl.getAttribute('src'))) {
          mountDiscordFrame();
        }
      }
    };

    const resetLive = () => {
      stopPoll();
      payToken = '';
      thankYouUrl = '';
      discordMounting = false;
      teardownPaypal();
      root.classList.remove('is-live');
      root.classList.remove('is-paypal');
      root.classList.remove('is-done');
      hideLoad();
      showError('');
      if (chooser) chooser.hidden = false;
      if (doneEl) doneEl.hidden = true;
      if (discordPanel) discordPanel.hidden = false;
      if (paypalPanel) paypalPanel.hidden = true;
      if (discordGate) discordGate.hidden = hasDiscord();
      if (frameEl) {
        frameEl.hidden = true;
        frameEl.removeAttribute('src');
      }
    };

    const close = () => {
      if (root.hidden) return;
      resetLive();
      currentUrl = '';
      currentPlanId = '';
      root.hidden = true;
      document.body.classList.remove('nav-lock');
      if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    };

    const open = (opts) => {
      const hasUc = allowed(opts.url);
      if (!hasUc && !paypalClientId()) {
        if (window.orionAlert) {
          window.orionAlert('Checkout unavailable', 'This plan needs a valid Upgrade.Chat payment link or PayPal credentials.');
        }
        return;
      }
      lastFocus = document.activeElement;
      currentUrl = opts.url || '';
      currentPlanId = String(opts.planId || '');
      currentTitle = opts.title || 'Complete payment';
      currentPrice = opts.price || '';
      currentInterval = opts.interval || '';
      titleEl.textContent = 'Choose payment method';
      if (metaEl) {
        const sum = [currentTitle, formatSum(currentPrice, currentInterval)].filter(Boolean).join(' — ');
        metaEl.textContent = sum;
        metaEl.hidden = !sum;
      }
      resetLive();
      if (hasDiscord() && discordGate) discordGate.hidden = true;
      root.hidden = false;
      document.body.classList.add('nav-lock');
      if (!hasUc && paypalClientId()) selectTab('paypal');
      else {
        selectTab('discord');
        if (hasDiscord()) mountDiscordFrame();
      }
    };

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        if (tab.disabled) return;
        selectTab(tab.getAttribute('data-checkout-tab') || 'discord');
      });
    });

    root.querySelectorAll('[data-checkout-dismiss]').forEach((el) => {
      el.addEventListener('click', close);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !root.hidden) {
        e.preventDefault();
        close();
      }
    });

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-checkout]');
      if (!btn) return;
      e.preventDefault();
      open({
        url: btn.getAttribute('data-checkout-url') || '',
        title: btn.getAttribute('data-checkout-title') || '',
        price: btn.getAttribute('data-checkout-price') || '',
        interval: btn.getAttribute('data-checkout-interval') || '',
        planId: btn.getAttribute('data-checkout-plan-id') || '',
      });
    });

    const onDiscordAuth = (data) => {
      if (root.hidden) return false;
      const ok = data && (data.status === 'ok' || data.type === 'DISCORD_AUTH_SUCCESS' || data.event === 'DISCORD_AUTH_SUCCESS');
      if (!ok) {
        showError((data && data.message) || 'Discord sign-in could not be completed.');
        return true;
      }
      applyUser(data.user || {}, { csrf: data.csrf });
      if (discordGate) discordGate.hidden = true;
      selectTab('discord');
      return true;
    };

    window.addEventListener('orion:discord-auth', (e) => {
      onDiscordAuth((e && e.detail) || {});
    });

    window.addEventListener('message', (e) => {
      const data = e.data || {};
      if (!data) return;
      if (data.type === 'DISCORD_AUTH_SUCCESS' || data.event === 'DISCORD_AUTH_SUCCESS' || data.type === 'DISCORD_AUTH_ERROR') {
        onDiscordAuth(data);
        return;
      }
      if (data.source !== 'orion-checkout') return;
      if (data.token) payToken = data.token;
      if (data.event === 'ready' || data.event === 'processor' || data.event === 'upgrade-click') hideLoad();
      if (data.event === 'complete') {
        if (loadCopy) loadCopy.textContent = 'Payment received — taking you through';
        showLoad('Payment received — taking you through');
        pollStatus(payToken || data.token);
        window.setTimeout(() => goThanks(payToken || data.token), 600);
      }
    });
  })();

  (function geoAdmin() {
    const root = document.querySelector('[data-geo-admin]');
    if (!root) return;

    const csrf = root.getAttribute('data-csrf') || '';
    const tree = root.querySelector('[data-geo-tree]');
    const rulesBox = root.querySelector('[data-geo-rules]');
    const search = root.querySelector('[data-geo-search]');
    const enabledInput = root.querySelector('[data-geo-enabled]');
    const enabledLabel = root.querySelector('[data-geo-enabled-label]');
    const previewInput = root.querySelector('[data-geo-preview-ip]');
    let countries = [];

    const api = async (path, opts = {}) => {
      const res = await fetch(path, {
        credentials: 'same-origin',
        ...opts,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
          ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
          ...(opts.headers || {}),
        },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || 'Request failed');
      return data;
    };

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const badge = (status) => {
      if (!status) return '';
      if (status.explicit === true) return '<span class="badge badge-lost">Restricted</span>';
      if (status.explicit === false) return '<span class="badge badge-won">Allowed</span>';
      if (status.inherited && status.restricted) return '<span class="badge">Inherited lock</span>';
      return '<span class="badge">Open</span>';
    };

    const switchHtml = (status, attrs) => {
      const on = !!(status && status.restricted);
      return `<label class="geo-switch is-row">
        <input type="checkbox" ${on ? 'checked' : ''} ${attrs}>
        <span class="geo-switch__ui"></span>
      </label>`;
    };

    const renderRules = (rules) => {
      if (!rulesBox) return;
      if (!rules || !rules.length) {
        rulesBox.innerHTML = '<p class="muted">No rules yet. Expand a country below and flip a switch.</p>';
        return;
      }
      rulesBox.innerHTML = `<div class="geo-rules">${rules.map((rule) => {
        const label = [rule.city_name, rule.state_name, rule.country_name].filter(Boolean).join(', ');
        const kind = Number(rule.restricted) === 1 ? 'Restricted' : 'Allowed';
        const tone = Number(rule.restricted) === 1 ? 'badge-lost' : 'badge-won';
        return `<div class="geo-rule">
          <span class="badge ${tone}">${kind}</span>
          <div><strong>${escapeHtml(label)}</strong><div class="muted">${escapeHtml(rule.scope)}</div></div>
          <button class="btn btn-ghost btn-small" type="button" data-geo-del="${rule.id}">Remove</button>
        </div>`;
      }).join('')}</div>`;
    };

    const renderCountries = () => {
      const q = (search?.value || '').trim().toLowerCase();
      const list = countries.filter((c) => {
        if (!q) return true;
        return (c.name + ' ' + c.iso2).toLowerCase().includes(q);
      });
      tree.innerHTML = list.map((c) => countryRow(c)).join('') || '<p class="muted">No countries match.</p>';
    };

    const countryRow = (c) => `
      <div class="geo-country" data-cc="${escapeHtml(c.iso2)}">
        <div class="geo-row">
          <button class="geo-row__toggle" type="button" data-geo-expand="country" aria-label="Open states">+</button>
          <div class="geo-row__name"><strong>${c.flag ? escapeHtml(c.flag) + ' ' : ''}${escapeHtml(c.name)}</strong><span>${escapeHtml(c.iso2)} · entire country</span></div>
          ${badge(c.status)}
          ${switchHtml(c.status, `data-geo-toggle data-scope="country" data-cc="${escapeHtml(c.iso2)}"`)}
        </div>
        <div class="geo-children" hidden></div>
      </div>`;

    const stateRow = (cc, countryName, s) => `
      <div class="geo-state" data-cc="${escapeHtml(cc)}" data-sc="${escapeHtml(s.iso2)}" data-name="${escapeHtml(s.name)}">
        <div class="geo-row is-child">
          <button class="geo-row__toggle" type="button" data-geo-expand="state" aria-label="Open cities">+</button>
          <div class="geo-row__name"><strong>${escapeHtml(s.name)}</strong><span>${escapeHtml(s.iso2 || 'state')} · entire state · ${escapeHtml(countryName)}</span></div>
          ${badge(s.status)}
          ${switchHtml(s.status, `data-geo-toggle data-scope="state" data-cc="${escapeHtml(cc)}" data-sc="${escapeHtml(s.iso2)}"`)}
        </div>
        <div class="geo-children" hidden></div>
      </div>`;

    const cityRow = (cc, sc, city) => `
      <div class="geo-row is-city">
        <span></span>
        <div class="geo-row__name"><strong>${escapeHtml(city.name)}</strong><span>city</span></div>
        ${badge(city.status)}
        ${switchHtml(city.status, `data-geo-toggle data-scope="city" data-cc="${escapeHtml(cc)}" data-sc="${escapeHtml(sc)}" data-city="${encodeURIComponent(city.name)}"`)}
      </div>`;

    const filterStates = (kids) => {
      if (!kids) return;
      const q = (kids.querySelector('[data-geo-state-search]')?.value || '').trim().toLowerCase();
      let shown = 0;
      kids.querySelectorAll('.geo-state').forEach((el) => {
        const hay = ((el.getAttribute('data-name') || '') + ' ' + (el.getAttribute('data-sc') || '')).toLowerCase();
        const match = !q || hay.includes(q);
        el.hidden = !match;
        if (match) shown += 1;
      });
      const empty = kids.querySelector('[data-geo-state-empty]');
      if (empty) empty.hidden = shown > 0;
    };

    let cityTimer = 0;
    const renderCityList = (box, data, cc, sc, stateName, q) => {
      if (!box) return;
      const total = Number(data.total || 0);
      const cities = data.cities || [];
      if (data.need_query) {
        box.innerHTML = `<p class="muted">Type to search ${total.toLocaleString()} cities in ${escapeHtml(stateName)}.</p>`;
        return;
      }
      if (!cities.length) {
        box.innerHTML = q
          ? '<p class="muted">No cities match that search.</p>'
          : '<p class="muted">No cities in catalog for this state.</p>';
        return;
      }
      const extra = total > cities.length
        ? `<p class="muted">Showing ${cities.length} of ${total.toLocaleString()} cities. Type more of the name to narrow it.</p>`
        : '';
      box.innerHTML = extra + cities.map((city) => cityRow(cc, sc, city)).join('');
    };

    const loadCities = async (stateWrap, q) => {
      const cc = stateWrap.getAttribute('data-cc') || '';
      const sc = stateWrap.getAttribute('data-sc') || '';
      const stateName = stateWrap.getAttribute('data-name') || sc;
      const kids = stateWrap.querySelector(':scope > .geo-children');
      const box = kids?.querySelector('[data-geo-city-list]');
      if (!box) return;
      box.innerHTML = '<p class="muted">Searching cities…</p>';
      const data = await api('/admin/geo/cities?country=' + encodeURIComponent(cc) + '&state=' + encodeURIComponent(sc) + '&q=' + encodeURIComponent(q || ''));
      renderCityList(box, data, cc, sc, stateName, q);
    };

    const paintLocation = (location, decision) => {
      const set = (sel, val) => {
        const el = root.querySelector(sel);
        if (el) el.textContent = val || '—';
      };
      set('[data-geo-ip]', location.lan_ip ? `${location.ip}  (Docker ${location.lan_ip})` : location.ip);
      set('[data-geo-country]', location.country ? `${location.country}${location.country_code ? ' (' + location.country_code + ')' : ''}` : '—');
      set('[data-geo-state]', location.state);
      set('[data-geo-city]', location.city);
      const verdict = root.querySelector('[data-geo-verdict]');
      if (verdict) {
        const blocked = !!(decision && decision.blocked);
        verdict.textContent = blocked ? 'Blocked' : 'Allowed';
        verdict.classList.toggle('badge-lost', blocked);
        verdict.classList.toggle('badge-won', !blocked);
      }
    };

    const loadCountries = async () => {
      const data = await api('/admin/geo/countries');
      countries = data.countries || [];
      renderCountries();
    };

    const loadRules = async () => {
      const data = await api('/admin/geo/rules');
      renderRules(data.rules || []);
    };

    enabledInput?.addEventListener('change', async () => {
      const on = enabledInput.checked;
      try {
        await api('/admin/geo/settings', { method: 'POST', body: JSON.stringify({ enabled: on }) });
        window.location.reload();
      } catch (err) {
        enabledInput.checked = !on;
        window.orionAlert
          ? window.orionAlert('Could not save', err.message || 'Refresh and try again.')
          : window.alert(err.message || 'Could not save blocking switch.');
      }
    });

    search?.addEventListener('input', renderCountries);

    tree.addEventListener('input', (e) => {
      const stateSearch = e.target.closest('[data-geo-state-search]');
      if (stateSearch) {
        filterStates(stateSearch.closest('.geo-children'));
        return;
      }
      const citySearch = e.target.closest('[data-geo-city-search]');
      if (!citySearch) return;
      const stateWrap = citySearch.closest('.geo-state');
      window.clearTimeout(cityTimer);
      cityTimer = window.setTimeout(() => {
        loadCities(stateWrap, citySearch.value.trim()).catch((err) => {
          const box = stateWrap?.querySelector('[data-geo-city-list]');
          if (box) box.innerHTML = '<p class="muted">' + escapeHtml(err.message || 'Could not search cities') + '</p>';
        });
      }, 220);
    });

    tree.addEventListener('click', async (e) => {
      const expand = e.target.closest('[data-geo-expand]');
      if (!expand) return;
      const wrap = expand.closest('.geo-country, .geo-state');
      const kids = wrap?.querySelector(':scope > .geo-children');
      if (!kids) return;
      if (!kids.hidden && kids.dataset.loaded) {
        kids.hidden = true;
        expand.textContent = '+';
        return;
      }
      expand.textContent = '–';
      kids.hidden = false;
      if (kids.dataset.loaded) return;

      const kind = expand.getAttribute('data-geo-expand');
      if (kind === 'country') {
        const cc = wrap.getAttribute('data-cc');
        kids.innerHTML = '<p class="muted">Loading states…</p>';
        const data = await api('/admin/geo/states?country=' + encodeURIComponent(cc));
        const countryName = data.country?.name || cc;
        const states = data.states || [];
        kids.innerHTML = `
          <div class="geo-search-wrap">
            <input type="search" data-geo-state-search placeholder="Search states in ${escapeHtml(countryName)}" autocomplete="off">
          </div>
          ${states.map((s) => stateRow(cc, countryName, s)).join('') || '<p class="muted">No states in catalog for this country.</p>'}
          <p class="muted" data-geo-state-empty hidden>No states match that search.</p>`;
        kids.dataset.loaded = '1';
        return;
      }

      const cc = wrap.getAttribute('data-cc');
      const sc = wrap.getAttribute('data-sc');
      const stateName = wrap.getAttribute('data-name') || sc;
      kids.innerHTML = `
        <div class="geo-search-wrap">
          <input type="search" data-geo-city-search placeholder="Search cities in ${escapeHtml(stateName)}" autocomplete="off">
        </div>
        <div data-geo-city-list><p class="muted">Loading cities…</p></div>`;
      kids.dataset.loaded = '1';
      try {
        await loadCities(wrap, '');
      } catch (err) {
        const box = kids.querySelector('[data-geo-city-list]');
        if (box) box.innerHTML = '<p class="muted">' + escapeHtml(err.message || 'Could not load cities') + '</p>';
      }
    });

    root.addEventListener('change', async (e) => {
      const input = e.target.closest('[data-geo-toggle]');
      if (!input) return;
      const payload = {
        scope: input.getAttribute('data-scope'),
        country_code: input.getAttribute('data-cc'),
        state_code: input.getAttribute('data-sc') || '',
        city_name: decodeURIComponent(input.getAttribute('data-city') || ''),
        restricted: input.checked,
      };
      try {
        const data = await api('/admin/geo/rules', { method: 'POST', body: JSON.stringify(payload) });
        renderRules(data.rules || []);
        const row = input.closest('.geo-row');
        const badgeEl = row?.querySelector('.badge');
        if (badgeEl && data.status) {
          badgeEl.outerHTML = badge(data.status);
        }
        const countryWrap = input.closest('.geo-country');
        countryWrap?.querySelectorAll('.geo-children').forEach((el) => {
          if (el.closest('.geo-row') === row) return;
          delete el.dataset.loaded;
        });
      } catch (err) {
        input.checked = !input.checked;
        window.orionAlert ? window.orionAlert('Could not save', err.message || 'Could not save rule') : window.alert(err.message || 'Could not save rule');
      }
    });

    rulesBox?.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-geo-del]');
      if (!btn) return;
      const data = await api('/admin/geo/rules/' + btn.getAttribute('data-geo-del') + '/delete', { method: 'POST', body: '{}' });
      renderRules(data.rules || []);
      await loadCountries();
    });

    const runLookup = async (ip) => {
      const data = await api('/admin/geo/lookup?ip=' + encodeURIComponent(ip || ''));
      paintLocation(data.location || {}, data.decision || {});
      return data;
    };

    root.querySelector('[data-geo-lookup]')?.addEventListener('click', () => runLookup(previewInput?.value || ''));
    root.querySelector('[data-geo-open-as]')?.addEventListener('click', () => {
      const ip = (previewInput?.value || '').trim();
      if (!ip) {
        window.orionAlert ? window.orionAlert('Need an IP', 'Lookup or paste a public IP first.') : window.alert('Paste a public IP first.');
        return;
      }
      window.open('/?geo_test_ip=' + encodeURIComponent(ip), '_blank', 'noopener');
    });
    root.querySelector('[data-geo-chips]')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-ip]');
      if (!btn || !previewInput) return;
      previewInput.value = btn.getAttribute('data-ip') || '';
      runLookup(previewInput.value);
    });

    loadCountries().catch((err) => {
      tree.innerHTML = '<p class="muted">' + (err.message || 'Could not load countries') + '</p>';
    });
    loadRules().catch(() => {});
  })();

  (() => {
    if (window.__orionDiscordAuth) return;
    window.__orionDiscordAuth = true;

    const MSG_TYPE = 'orionbets:discord-auth';
    const STORAGE_KEY = 'orionbets:discord-auth';
    const WIDTH = 500;
    const HEIGHT = 750;

    let pending = false;
    let popup = null;
    let watch = 0;
    let activeBtn = null;

    const safePath = (value, fallback) => {
      if (typeof value !== 'string') return fallback;
      const path = value.trim();
      if (!path.startsWith('/') || path.startsWith('//') || path.includes('\\')) return fallback;
      if (/^[a-z][a-z0-9+.-]*:/i.test(path)) return fallback;
      return path;
    };

    const setBusy = (on) => {
      if (!activeBtn) return;
      activeBtn.classList.toggle('is-busy', on);
      activeBtn.setAttribute('aria-busy', on ? 'true' : 'false');
      if (!on) activeBtn = null;
    };

    const stopWatch = () => {
      if (watch) {
        window.clearInterval(watch);
        watch = 0;
      }
    };

    const isAuthMessage = (data) => {
      if (!data) return false;
      return data.type === MSG_TYPE
        || data.source === MSG_TYPE
        || data.type === 'DISCORD_AUTH_SUCCESS'
        || data.type === 'DISCORD_AUTH_ERROR'
        || data.event === 'DISCORD_AUTH_SUCCESS'
        || data.event === 'DISCORD_AUTH_ERROR';
    };

    const checkoutOpen = () => {
      const modal = document.querySelector('.ob-checkout');
      return !!(modal && !modal.hidden);
    };

    const finish = (data) => {
      if (!pending || !isAuthMessage(data)) return;
      pending = false;
      stopWatch();
      setBusy(false);
      try { window.localStorage.removeItem(STORAGE_KEY); } catch (err) {}
      if (popup && !popup.closed) {
        try { popup.close(); } catch (err) {}
      }
      popup = null;
      const ok = data.status === 'ok' || data.type === 'DISCORD_AUTH_SUCCESS' || data.event === 'DISCORD_AUTH_SUCCESS';
      window.dispatchEvent(new CustomEvent('orion:discord-auth', { detail: data }));
      if (checkoutOpen() && ok) return;
      window.location.assign(safePath(data.redirect, ok ? '/dashboard' : '/login'));
    };

    const authUrl = (href) => {
      const url = new URL('/auth/discord', window.location.origin);
      try {
        const parsed = new URL(href || '/auth/discord', window.location.origin);
        url.pathname = parsed.pathname || '/auth/discord';
        parsed.searchParams.forEach((value, key) => url.searchParams.set(key, value));
      } catch (err) {}
      url.searchParams.set('popup', '1');
      const next = new URLSearchParams(window.location.search).get('next');
      if (next && !url.searchParams.get('next')) url.searchParams.set('next', next);
      return url.toString();
    };

    const openPopup = (href) => {
      const left = Math.round(window.screenX + Math.max(0, (window.outerWidth - WIDTH) / 2));
      const top = Math.round(window.screenY + Math.max(0, (window.outerHeight - HEIGHT) / 2));
      const features = [
        'popup=yes',
        'width=' + WIDTH,
        'height=' + HEIGHT,
        'left=' + left,
        'top=' + top,
        'status=0',
        'menubar=0',
        'toolbar=0',
        'scrollbars=1',
        'resizable=1',
      ].join(',');
      return window.open(href, 'DiscordAuth', features);
    };

    document.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-auth="discord"], .btn-discord');
      if (!btn || btn.tagName !== 'A') return;
      event.preventDefault();
      event.stopPropagation();

      if (popup && !popup.closed) {
        try { popup.focus(); } catch (err) {}
        return;
      }

      activeBtn = btn;
      pending = true;
      setBusy(true);
      const popupHref = authUrl(btn.getAttribute('href') || '/auth/discord');
      let win = null;
      try {
        win = openPopup(popupHref);
      } catch (err) {}

      if (!win) {
        pending = false;
        setBusy(false);
        const fallback = new URL(authUrl(btn.getAttribute('href') || '/auth/discord'));
        fallback.searchParams.delete('popup');
        window.location.href = fallback.toString();
        return;
      }

      popup = win;
      try { win.focus(); } catch (err) {}
      stopWatch();
      watch = window.setInterval(() => {
        if (popup && !popup.closed) return;
        stopWatch();
        popup = null;
        window.setTimeout(() => {
          if (!pending) return;
          pending = false;
          setBusy(false);
        }, 500);
      }, 400);
    });

    window.addEventListener('message', (event) => {
      if (event.origin !== window.location.origin) return;
      finish(event.data || {});
    });

    window.addEventListener('storage', (event) => {
      if (event.key !== STORAGE_KEY || !event.newValue) return;
      try {
        finish(JSON.parse(event.newValue));
      } catch (err) {}
    });
  })();

  (function everflowAdmin() {
    const root = document.querySelector('[data-everflow-admin]');
    if (!root) return;

    const csrf = root.getAttribute('data-csrf') || '';
    const search = root.querySelector('[data-ef-search]');
    const table = root.querySelector('[data-ef-table]');
    const dialog = document.querySelector('[data-ef-dialog]');
    const urlEl = dialog && dialog.querySelector('[data-ef-url]');
    const payloadEl = dialog && dialog.querySelector('[data-ef-payload]');
    const httpEl = dialog && dialog.querySelector('[data-ef-http]');
    const responseEl = dialog && dialog.querySelector('[data-ef-response]');
    const errorEl = dialog && dialog.querySelector('[data-ef-error]');

    const filterRows = () => {
      if (!table || !search) return;
      const q = search.value.trim().toLowerCase();
      table.querySelectorAll('[data-ef-row]').forEach((row) => {
        const hay = (row.getAttribute('data-search') || '') + ' ' + row.textContent.toLowerCase();
        row.hidden = q !== '' && !hay.includes(q);
      });
    };
    if (search) search.addEventListener('input', filterRows);

    const fill = (pre, value) => {
      if (!pre) return;
      const text = value == null || value === '' ? '—' : String(value);
      pre.textContent = text;
    };

    const openDetail = (raw) => {
      if (!dialog) return;
      let data = {};
      try { data = JSON.parse(raw || '{}') || {}; } catch (err) { data = {}; }
      fill(urlEl, data.url || '');
      fill(payloadEl, JSON.stringify(data.payload || {}, null, 2));
      if (httpEl) httpEl.textContent = data.http_status != null && data.http_status !== '' ? String(data.http_status) : '—';
      fill(responseEl, data.response || '');
      fill(errorEl, data.error || '');
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
    };

    const closeDetail = () => {
      if (!dialog) return;
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
    };

    root.addEventListener('click', (event) => {
      const view = event.target.closest('[data-ef-view]');
      if (view) {
        event.preventDefault();
        openDetail(view.getAttribute('data-detail') || '{}');
      }
    });
    document.addEventListener('click', (event) => {
      if (event.target.closest('[data-ef-close]')) {
        event.preventDefault();
        closeDetail();
      }
    });
    if (dialog) {
      dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDetail();
      });
    }

    root.addEventListener('submit', async (event) => {
      const form = event.target.closest('[data-ef-retry]');
      if (!form) return;
      event.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Retrying…';
      }
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: new FormData(form),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          window.location.reload();
          return;
        }
        const row = form.closest('[data-ef-row]');
        if (row) {
          row.setAttribute('data-status', data.status || 'success');
          const badge = row.querySelector('[data-ef-status]');
          if (badge) {
            badge.textContent = data.status || 'success';
            badge.className = 'badge ' + (data.status === 'success' ? 'badge-won' : data.status === 'failed' ? 'badge-lost' : 'badge-push');
          }
          const httpCell = row.querySelector('[data-label="HTTP"]');
          if (httpCell && data.http_status != null) httpCell.textContent = String(data.http_status);
          form.remove();
        }
      } catch (err) {
        window.location.reload();
      } finally {
        if (btn && btn.isConnected) {
          btn.disabled = false;
          btn.textContent = 'Retry';
        }
      }
    });
  })();

  document.querySelectorAll('[data-admin-table]').forEach((wrap) => {
    const table = wrap.querySelector('table');
    const input = wrap.querySelector('[data-table-filter]');
    if (!table) return;
    const body = table.tBodies[0];
    if (!body) return;

    if (input) {
      input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        body.querySelectorAll('tr[data-row], tr').forEach((row) => {
          row.hidden = q !== '' && !row.textContent.toLowerCase().includes(q);
        });
      });
    }

    table.querySelectorAll('th[data-sort]').forEach((th, col) => {
      th.addEventListener('click', () => {
        const rows = Array.from(body.querySelectorAll('tr'));
        const current = th.getAttribute('aria-sort') === 'asc' ? 'desc' : 'asc';
        table.querySelectorAll('th[data-sort]').forEach((h) => h.removeAttribute('aria-sort'));
        th.setAttribute('aria-sort', current);
        rows.sort((a, b) => {
          const av = (a.children[col]?.textContent || '').trim().toLowerCase();
          const bv = (b.children[col]?.textContent || '').trim().toLowerCase();
          return current === 'asc' ? av.localeCompare(bv, undefined, { numeric: true }) : bv.localeCompare(av, undefined, { numeric: true });
        });
        rows.forEach((row) => body.appendChild(row));
      });
    });
  });

  document.querySelectorAll('form[data-toggle-active]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const csrf = form.querySelector('[name="_csrf"]')?.value || '';
      const checkbox = form.querySelector('input[type="checkbox"]');
      const label = form.querySelector('.vis-label');
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: new FormData(form),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          window.location.reload();
          return;
        }
        if (checkbox) checkbox.checked = !!data.is_active;
        if (label) label.textContent = data.label || (data.is_active ? 'Active' : 'Hidden');
      } catch (err) {
        window.location.reload();
      }
    });
  });

  (() => {
    const modal = document.getElementById('an-sync-modal');
    if (!modal) return;

    const titleEl = modal.querySelector('#an-sync-title');
    const kickerEl = modal.querySelector('[data-an-sync-kicker]');
    const copyEl = modal.querySelector('[data-an-sync-copy]');
    const metaEl = modal.querySelector('[data-an-sync-meta]');
    const fillEl = modal.querySelector('[data-an-sync-fill]');
    const barEl = modal.querySelector('[data-an-sync-bar]');
    const pauseBtn = modal.querySelector('[data-an-sync-pause]');
    const closeBtn = modal.querySelector('[data-an-sync-close]');
    const scrim = modal.querySelector('[data-an-sync-scrim]');

    let jobId = 0;
    let running = false;
    let canClose = false;
    let reloadOnClose = false;
    let abortLoop = false;
    let sawWrite = false;

    const csrf = () => modal.getAttribute('data-csrf')
      || document.querySelector('form[data-an-sync] [name="_csrf"]')?.value
      || '';

    const post = async (url, extra = {}) => {
      const body = new FormData();
      body.append('_csrf', csrf());
      if (jobId) body.append('job_id', String(jobId));
      Object.entries(extra).forEach(([key, value]) => {
        if (value != null && value !== '') body.append(key, String(value));
      });
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf(),
        },
        body,
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok && data.ok === false) {
        throw new Error(data.error || 'Sync request failed.');
      }
      return data;
    };

    const setButtons = (mode) => {
      const paused = mode === 'paused' || mode === 'done' || mode === 'error';
      canClose = paused;
      if (pauseBtn) pauseBtn.hidden = paused;
      if (closeBtn) closeBtn.hidden = !paused;
    };

    const apply = (data) => {
      if (data.job_id) jobId = data.job_id;
      const done = Number(data.completed_steps || 0);
      const left = Number(data.remaining || 0);
      const total = Number(data.total_steps || 0);
      const pct = Number(data.percent || 0);
      if (fillEl) fillEl.style.width = Math.max(0, Math.min(100, pct)) + '%';
      if (barEl) barEl.setAttribute('aria-valuenow', String(pct));
      if (metaEl) {
        metaEl.textContent = done + ' done · ' + left + ' left'
          + (total ? ' · ' + total + ' total' : '')
          + (data.items_synced ? ' · ' + data.items_synced + ' saved' : '');
      }
      if (Number(data.changed_steps || 0) > 0 || Number(data.items_synced || 0) > 0) {
        sawWrite = true;
      }
      const label = data.current_label || data.step_label || '';
      if (copyEl && label) copyEl.textContent = label;
      if (data.status === 'paused') {
        modal.classList.add('is-paused');
        if (titleEl) titleEl.textContent = 'Paused';
        if (kickerEl) kickerEl.textContent = 'Resume anytime';
        if (copyEl) copyEl.textContent = left + ' batch' + (left === 1 ? '' : 'es') + ' left. The next Sync continues from here.';
        setButtons('paused');
      } else if (data.status === 'failed') {
        modal.classList.add('is-error');
        if (titleEl) titleEl.textContent = 'Sync stopped';
        if (copyEl) copyEl.textContent = data.error || data.step_error || 'A batch failed.';
        setButtons('error');
      } else if (data.status === 'completed' && !data.already_synced) {
        modal.classList.add('is-done');
        if (titleEl) titleEl.textContent = 'Sync complete';
        if (copyEl) copyEl.textContent = (data.items_synced || 0) + ' record' + (data.items_synced === 1 ? '' : 's') + ' saved.';
        setButtons('done');
        reloadOnClose = true;
      } else if (running) {
        if (titleEl) titleEl.textContent = sawWrite ? 'Syncing' : 'Checking for new data';
        if (kickerEl) kickerEl.textContent = sawWrite ? 'Writing updates' : 'Comparing sources';
        setButtons('running');
      }
    };

    const closeModal = () => {
      if (!canClose) return;
      modal.hidden = true;
      document.body.classList.remove('nav-lock');
      running = false;
      abortLoop = true;
      jobId = 0;
      sawWrite = false;
      modal.classList.remove('is-paused', 'is-done', 'is-error');
      if (reloadOnClose) {
        reloadOnClose = false;
        window.location.reload();
      }
    };

    const openModal = () => {
      abortLoop = false;
      canClose = false;
      sawWrite = false;
      reloadOnClose = false;
      jobId = 0;
      modal.classList.remove('is-paused', 'is-done', 'is-error');
      if (titleEl) titleEl.textContent = 'Checking for new data';
      if (kickerEl) kickerEl.textContent = 'Action Network';
      if (copyEl) copyEl.textContent = 'Comparing Action Network with the local cache…';
      if (fillEl) fillEl.style.width = '0%';
      if (metaEl) metaEl.textContent = '0 done · 0 left';
      setButtons('running');
      modal.hidden = false;
      document.body.classList.add('nav-lock');
    };

    const alreadySynced = () => {
      canClose = true;
      modal.hidden = true;
      document.body.classList.remove('nav-lock');
      running = false;
      abortLoop = true;
      if (typeof window.orionAlert === 'function') {
        window.orionAlert('Already synced', 'Action Network and the local database already match. Nothing new to import.');
      } else {
        window.alert('Already synced');
      }
    };

    const pauseNow = async () => {
      abortLoop = true;
      running = false;
      try {
        const data = await post(modal.getAttribute('data-pause-url'));
        apply(data);
      } catch (err) {
        modal.classList.add('is-paused');
        if (titleEl) titleEl.textContent = 'Paused';
        if (copyEl) copyEl.textContent = 'Progress is saved. Click Sync again to resume.';
        setButtons('paused');
      }
    };

    const loopTicks = async () => {
      let retries = 0;
      running = true;
      while (running && !abortLoop) {
        try {
          const data = await post(modal.getAttribute('data-tick-url'));
          apply(data);
          if (data.already_synced && data.status === 'completed') {
            alreadySynced();
            return;
          }
          if (data.status === 'completed') {
            running = false;
            return;
          }
          if (data.status === 'paused') {
            running = false;
            return;
          }
          if (data.step_ok === false) {
            retries += 1;
            if (retries > 3) {
              await pauseNow();
              modal.classList.add('is-error');
              if (titleEl) titleEl.textContent = 'Batch failed';
              if (copyEl) copyEl.textContent = data.step_error || data.error || 'Could not finish this batch. Pause saved — click Sync to retry.';
              setButtons('error');
              return;
            }
            continue;
          }
          retries = 0;
        } catch (err) {
          retries += 1;
          if (retries > 3) {
            await pauseNow();
            modal.classList.add('is-error');
            if (titleEl) titleEl.textContent = 'Sync interrupted';
            if (copyEl) copyEl.textContent = err.message || 'Network error. Progress is saved.';
            setButtons('error');
            return;
          }
        }
      }
    };

    const paintHints = (data) => {
      if (!data || !data.job_id || !['paused', 'running'].includes(data.status)) return;
      const left = Number(data.remaining || 0);
      const text = (data.status === 'paused' ? 'Paused' : 'In progress')
        + ' · ' + left + ' left'
        + (data.started_at ? ' · started ' + data.started_at : '');
      document.querySelectorAll('[data-an-sync-hint]').forEach((el) => {
        el.hidden = false;
        el.textContent = text;
      });
      document.querySelectorAll('form[data-an-sync] button[type="submit"]').forEach((btn) => {
        if (btn.dataset.anSyncOriginal == null) btn.dataset.anSyncOriginal = btn.textContent;
        btn.textContent = left > 0 ? 'Resume sync (' + left + ' left)' : (btn.dataset.anSyncOriginal || btn.textContent);
      });
    };

    pauseBtn?.addEventListener('click', () => { pauseNow(); });
    closeBtn?.addEventListener('click', () => closeModal());
    scrim?.addEventListener('click', () => closeModal());
    modal.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      event.preventDefault();
      if (canClose) closeModal();
    });

    window.addEventListener('beforeunload', () => {
      if (!running || !jobId) return;
      const body = new FormData();
      body.append('_csrf', csrf());
      body.append('job_id', String(jobId));
      try {
        navigator.sendBeacon(modal.getAttribute('data-pause-url'), body);
      } catch (err) { /* ignore */ }
    });

    document.addEventListener('submit', async (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-an-sync')) return;
      event.preventDefault();
      const token = form.querySelector('[name="_csrf"]')?.value;
      if (token) modal.setAttribute('data-csrf', token);
      const extra = {};
      const days = form.querySelector('[name="days"]')?.value;
      if (days) extra.days = days;
      openModal();
      try {
        const data = await post(form.action, extra);
        apply(data);
        if (data.already_synced && data.status === 'completed') {
          alreadySynced();
          return;
        }
        await loopTicks();
      } catch (err) {
        modal.classList.add('is-error');
        if (titleEl) titleEl.textContent = 'Could not start';
        if (copyEl) copyEl.textContent = err.message || 'Sync failed to start.';
        setButtons('error');
      }
    });

    const statusUrl = modal.getAttribute('data-status-url');
    if (statusUrl && document.querySelector('form[data-an-sync]')) {
      fetch(statusUrl, { headers: { Accept: 'application/json' } })
        .then((res) => res.json())
        .then(paintHints)
        .catch(() => {});
    }
  })();

  // Interactive DataTable Sort Handler
  document.querySelectorAll('table[data-interactive-table], table.data-table').forEach((table) => {
    const headers = table.querySelectorAll('th[data-sort]');
    const tbody = table.querySelector('tbody');
    if (!headers.length || !tbody) return;

    headers.forEach((th, colIndex) => {
      th.addEventListener('click', () => {
        const type = th.getAttribute('data-sort') || 'text';
        const currentSort = th.getAttribute('aria-sort');
        const asc = currentSort !== 'asc';

        headers.forEach((h) => h.removeAttribute('aria-sort'));
        th.setAttribute('aria-sort', asc ? 'asc' : 'desc');

        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
          const cellA = a.children[colIndex]?.innerText.trim() || '';
          const cellB = b.children[colIndex]?.innerText.trim() || '';

          if (type === 'number') {
            const numA = parseFloat(cellA.replace(/[^0-9.-]/g, '')) || 0;
            const numB = parseFloat(cellB.replace(/[^0-9.-]/g, '')) || 0;
            return asc ? numA - numB : numB - numA;
          }

          if (type === 'date') {
            const dateA = Date.parse(cellA) || 0;
            const dateB = Date.parse(cellB) || 0;
            return asc ? dateA - dateB : dateB - dateA;
          }

          return asc
            ? cellA.localeCompare(cellB, undefined, { numeric: true, sensitivity: 'base' })
            : cellB.localeCompare(cellA, undefined, { numeric: true, sensitivity: 'base' });
        });

        rows.forEach((row) => tbody.appendChild(row));
      });
    });
  });
})();

