<style>
.ob-confirm{position:fixed;inset:0;z-index:80;display:grid;place-items:center;padding:1.2rem}
.ob-confirm[hidden]{display:none!important}
.ob-confirm__scrim{position:absolute;inset:0;background:color-mix(in srgb,var(--ink,#0B0D10) 74%,transparent);backdrop-filter:blur(10px)}
.ob-confirm__panel{position:relative;width:min(440px,100%);background:var(--color-surface,#12151A);border:1px solid var(--color-border,#262B33);box-shadow:0 18px 50px rgba(0,0,0,.35);padding:1.55rem 1.45rem 1.3rem}
.ob-confirm__panel::before{content:"";position:absolute;left:0;top:0;right:0;height:3px;background:var(--color-primary,#EAE6DC)}
.ob-confirm.is-restore .ob-confirm__panel::before{background:var(--color-success,#4CC27E)}
.ob-confirm.is-danger .ob-confirm__panel::before{background:var(--color-danger,#E07A72)}
.ob-confirm__kicker{font-family:var(--font-mono,ui-monospace,monospace);font-size:.68rem;letter-spacing:.18em;text-transform:uppercase;color:var(--color-text-muted,#A8A499);margin:0 0 .55rem}
.ob-confirm.is-restore .ob-confirm__kicker{color:var(--color-success,#4CC27E)}
.ob-confirm.is-danger .ob-confirm__kicker{color:var(--color-danger,#E07A72)}
.ob-confirm__title,body.is-app .ob-confirm__title{font-family:var(--font-display,Anton,Impact,sans-serif);font-weight:400;text-transform:uppercase;letter-spacing:.02em;font-size:clamp(1.55rem,3.4vw,2.15rem);line-height:.95;margin:0 0 .55rem;color:var(--color-text,#EAE6DC)}
.ob-confirm__copy{margin:0;color:var(--color-text-muted,#A8A499);font-size:.95rem}
.ob-confirm__copy[hidden]{display:none}
.ob-confirm__actions{display:flex;justify-content:flex-end;gap:.55rem;margin-top:1.35rem}
.ob-confirm.is-alert .ob-confirm__cancel{display:none}
</style>
<script>
(() => {
  if (window.__orionDialog) return;
  window.__orionDialog = true;

  const confirmRe = /confirm\s*\(\s*(['"])([\s\S]*?)\1/;
  let root = null;
  let lastFocus = null;
  let resolver = null;

  const mount = () => {
    if (root) return root;
    root = document.createElement('div');
    root.className = 'ob-confirm';
    root.hidden = true;
    root.innerHTML = '<div class="ob-confirm__scrim" data-confirm-dismiss></div>'
      + '<div class="ob-confirm__panel" role="dialog" aria-modal="true" aria-labelledby="ob-confirm-title" aria-describedby="ob-confirm-copy">'
      + '<p class="ob-confirm__kicker" id="ob-confirm-kicker">Confirm</p>'
      + '<h2 class="ob-confirm__title" id="ob-confirm-title"></h2>'
      + '<p class="ob-confirm__copy" id="ob-confirm-copy"></p>'
      + '<div class="ob-confirm__actions">'
      + '<button type="button" class="btn btn-ghost ob-confirm__cancel" data-confirm-cancel>Cancel</button>'
      + '<button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>'
      + '</div></div>';
    document.body.appendChild(root);
    root.querySelector('[data-confirm-dismiss]').addEventListener('click', () => close(false));
    root.querySelector('[data-confirm-cancel]').addEventListener('click', () => close(false));
    root.querySelector('[data-confirm-ok]').addEventListener('click', () => close(true));
    root.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.preventDefault(); close(false); }
    });
    return root;
  };

  const close = (ok) => {
    if (!root || root.hidden) return;
    root.hidden = true;
    document.body.classList.remove('nav-lock');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
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

  const nativeMessage = (el) => {
    const raw = el.getAttribute('onsubmit') || el.getAttribute('onclick') || '';
    const match = raw.match(confirmRe);
    return match ? match[2] : '';
  };

  const inferTone = (el, title) => {
    const set = el.getAttribute('data-confirm-tone');
    if (set) return set;
    const hay = ((el.getAttribute('action') || '') + ' ' + title).toLowerCase();
    if (hay.includes('restore')) return 'restore';
    if (/delete|deactivat|cancel/.test(hay)) return 'danger';
    return 'default';
  };

  const inferOk = (el, title, tone) => {
    if (el.getAttribute('data-confirm-ok')) return el.getAttribute('data-confirm-ok');
    if (tone === 'restore') return 'Restore';
    if (/archive/i.test(title)) return 'Archive';
    if (/deactivat/i.test(title)) return 'Deactivate';
    if (/delete/i.test(title)) return 'Delete';
    return 'Confirm';
  };

  const fromNode = (node) => {
    const title = node.getAttribute('data-confirm') || nativeMessage(node) || 'Are you sure?';
    const tone = inferTone(node, title);
    return open({
      title,
      copy: node.getAttribute('data-confirm-copy') || '',
      ok: inferOk(node, title, tone),
      cancel: node.getAttribute('data-confirm-cancel') || 'Cancel',
      tone,
      kicker: node.getAttribute('data-confirm-kicker') || '',
    });
  };

  const disarmNative = (el) => {
    if (!el) return;
    el.removeAttribute('onsubmit');
    el.onsubmit = null;
    el.removeAttribute('onclick');
    el.onclick = null;
  };

  const needsConfirm = (el) => {
    if (!el) return false;
    if (el.hasAttribute('data-confirm')) return true;
    return confirmRe.test(el.getAttribute('onsubmit') || '') || confirmRe.test(el.getAttribute('onclick') || '');
  };

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') return;
    const source = (e.submitter && needsConfirm(e.submitter)) ? e.submitter : form;
    if (!needsConfirm(source) && !needsConfirm(form)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const pending = fromNode(needsConfirm(source) ? source : form);
    disarmNative(form);
    disarmNative(e.submitter);
    pending.then((ok) => {
      if (!ok) return;
      form.dataset.confirmed = '1';
      if (typeof form.requestSubmit === 'function') form.requestSubmit(e.submitter || undefined);
      else form.submit();
    });
  }, true);

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm], [onclick]');
    if (!btn || btn.closest('form') || btn.tagName === 'FORM') return;
    if (!needsConfirm(btn)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    const pending = fromNode(btn);
    disarmNative(btn);
    pending.then((ok) => {
      if (!ok) return;
      if (btn.tagName === 'A' && btn.href) window.location.href = btn.href;
    });
  }, true);

  window.orionConfirm = (title, copy, opts = {}) => open({ title, copy, ...opts });
  window.orionAlert = (title, copy, opts = {}) => open({ title, copy, alert: true, ...opts });
})();
</script>
