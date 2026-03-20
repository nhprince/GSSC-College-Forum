<!--  PAGE CONTENT ENDS  -->
    </div><!-- /.a-content -->
  </div><!-- /.a-main -->
</div><!-- /.a-shell -->

<div id="toast-container"></div>

<script>

/* ═══════════════════════════════════════════════
   DIALOG SYSTEM — replaces alert() and confirm()
   ═══════════════════════════════════════════════ */
(function() {

  /* Inject CSS once */
  const style = document.createElement('style');
  style.textContent = `
    .dlg-backdrop{
      position:fixed;inset:0;z-index:9900;
      background:rgba(0,0,0,0.46);
      display:flex;align-items:center;justify-content:center;
      padding:20px;
      opacity:0;pointer-events:none;
      transition:opacity .2s;
    }
    .dlg-backdrop.open{opacity:1;pointer-events:all}
    .dlg{
      background:#fff;
      border-radius:20px;
      padding:28px 24px 22px;
      max-width:320px;width:100%;
      box-shadow:0 12px 48px rgba(0,0,0,0.22);
      transform:scale(0.9) translateY(12px);
      transition:transform .24s cubic-bezier(.34,1.56,.64,1);
      text-align:center;
    }
    .dlg-backdrop.open .dlg{transform:scale(1) translateY(0)}
    .dlg-icon{
      width:54px;height:54px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 14px;
    }
    .dlg-icon svg{flex-shrink:0}
    .dlg-icon--warn{background:#FEF3C7}
    .dlg-icon--danger{background:#FFF0F0}
    .dlg-icon--info{background:#EEF4FF}
    .dlg-title{
      font-size:16px;font-weight:700;
      color:#111;margin-bottom:8px;
      font-family:'Poppins',sans-serif;
    }
    .dlg-msg{
      font-size:13.5px;color:#555;
      line-height:1.55;margin-bottom:22px;
    }
    .dlg-actions{display:flex;gap:10px;justify-content:center}
    .dlg-btn{
      flex:1;padding:11px 10px;
      border-radius:999px;
      font-family:'Poppins',sans-serif;
      font-size:13px;font-weight:600;
      cursor:pointer;border:none;
      transition:background .15s,transform .12s;
      max-width:130px;
    }
    .dlg-btn:active{transform:scale(0.97)}
    .dlg-btn--cancel{background:#F2F2F2;color:#555}
    .dlg-btn--cancel:hover{background:#e4e4e4}
    .dlg-btn--confirm{background:#C0000C;color:#fff}
    .dlg-btn--confirm:hover{background:#8B0000}
    .dlg-btn--confirm.dlg-btn--safe{background:#16a34a}
    .dlg-btn--confirm.dlg-btn--safe:hover{background:#15803d}
    .dlg-btn--confirm.dlg-btn--blue{background:#1B6FD8}
    .dlg-btn--confirm.dlg-btn--blue:hover{background:#155db0}
    .dlg-btn--only{background:#C0000C;color:#fff;max-width:160px}
    .dlg-btn--only:hover{background:#8B0000}
  `;
  document.head.appendChild(style);

  /* Build DOM once */
  const backdrop = document.createElement('div');
  backdrop.className = 'dlg-backdrop';
  backdrop.innerHTML = `
    <div class="dlg" role="dialog" aria-modal="true">
      <div class="dlg-icon" id="dlg-icon"></div>
      <div class="dlg-title" id="dlg-title"></div>
      <div class="dlg-msg"   id="dlg-msg"></div>
      <div class="dlg-actions" id="dlg-actions"></div>
    </div>
  `;
  document.body.appendChild(backdrop);

  let _resolve = null;

  function close(val) {
    backdrop.classList.remove('open');
    setTimeout(() => { backdrop.style.display = ''; }, 250);
    if (_resolve) { _resolve(val); _resolve = null; }
  }

  /* Close on backdrop click */
  backdrop.addEventListener('click', e => { if (e.target === backdrop) close(false); });

  /* Close on Escape */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && backdrop.classList.contains('open')) close(false);
  });

  /* Icons */
  const ICONS = {
    warn:   `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    danger: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C0000C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
    info:   `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1B6FD8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    logout: `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C0000C" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>`,
  };

  /**
   * dialog.confirm(options) → Promise<boolean>
   *
   * options: {
   *   type:        'warn' | 'danger' | 'info'  (default 'warn')
   *   title:       string
   *   message:     string
   *   confirmText: string  (default 'Confirm')
   *   cancelText:  string  (default 'Cancel')
   *   confirmClass: ''|'safe'|'blue'  (default '' = red)
   * }
   */
  function confirm(opts) {
    opts = typeof opts === 'string' ? { message: opts } : opts;
    const type    = opts.type    || 'warn';
    const title   = opts.title   || (type === 'danger' ? 'Are you sure?' : 'Confirm');
    const msg     = opts.message || '';
    const confTxt = opts.confirmText || 'Confirm';
    const cancTxt = opts.cancelText  || 'Cancel';
    const confCls = opts.confirmClass ? ' dlg-btn--' + opts.confirmClass : '';

    document.getElementById('dlg-icon').innerHTML = ICONS[opts.icon || type] || ICONS.warn;
    document.getElementById('dlg-icon').className = 'dlg-icon dlg-icon--' + type;
    document.getElementById('dlg-title').textContent = title;
    document.getElementById('dlg-msg').textContent   = msg;

    const actions = document.getElementById('dlg-actions');
    actions.innerHTML = `
      <button class="dlg-btn dlg-btn--cancel"  id="dlg-cancel">${cancTxt}</button>
      <button class="dlg-btn dlg-btn--confirm${confCls}" id="dlg-confirm">${confTxt}</button>
    `;
    document.getElementById('dlg-cancel').onclick  = () => close(false);
    document.getElementById('dlg-confirm').onclick = () => close(true);

    backdrop.style.display = 'flex';
    requestAnimationFrame(() => backdrop.classList.add('open'));
    setTimeout(() => document.getElementById('dlg-confirm')?.focus(), 250);

    return new Promise(r => { _resolve = r; });
  }

  /**
   * dialog.alert(options) → Promise<void>  (single OK button)
   */
  function alert(opts) {
    opts = typeof opts === 'string' ? { message: opts } : opts;
    const type  = opts.type  || 'info';
    const title = opts.title || 'Notice';
    const msg   = opts.message || '';
    const okTxt = opts.okText || 'OK';

    document.getElementById('dlg-icon').innerHTML = ICONS[opts.icon || type] || ICONS.info;
    document.getElementById('dlg-icon').className = 'dlg-icon dlg-icon--' + type;
    document.getElementById('dlg-title').textContent = title;
    document.getElementById('dlg-msg').textContent   = msg;

    const actions = document.getElementById('dlg-actions');
    actions.innerHTML = `<button class="dlg-btn dlg-btn--only" id="dlg-ok">${okTxt}</button>`;
    document.getElementById('dlg-ok').onclick = () => close(true);

    backdrop.style.display = 'flex';
    requestAnimationFrame(() => backdrop.classList.add('open'));
    setTimeout(() => document.getElementById('dlg-ok')?.focus(), 250);

    return new Promise(r => { _resolve = r; });
  }

  window.dialog = { confirm, alert };

})();

const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function api(endpoint, options = {}) {
  const isForm = options.body instanceof FormData;
  const headers = { 'X-CSRF-Token': CSRF };
  if (!isForm) headers['Content-Type'] = 'application/json';
  const res  = await fetch('/api/' + endpoint, { credentials:'same-origin', headers:{...headers,...(options.headers||{})}, ...options });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Error');
  return data.data;
}

function showToast(msg, type='info') {
  const el = document.createElement('div');
  el.className = 'toast toast--' + type;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

/* confirm_() now returns a Promise via our dialog system */
async function confirm_(msg) {
  return await dialog.confirm({ message: msg });
}

/* ── Hamburger / drawer ──────────────────────────────────── */
(function() {
  var sidebar   = document.getElementById('a-sidebar');
  var overlay   = document.getElementById('a-overlay');
  var hamburger = document.getElementById('a-hamburger');
  if (!sidebar || !hamburger) return;

  function openDrawer() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeDrawer() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  hamburger.addEventListener('click', function(e) {
    e.stopPropagation();
    sidebar.classList.contains('open') ? closeDrawer() : openDrawer();
  });

  overlay.addEventListener('click', closeDrawer);

  // Close when a nav link is tapped on mobile
  sidebar.querySelectorAll('.a-nav, .a-back').forEach(function(el) {
    el.addEventListener('click', function() {
      if (window.innerWidth <= 768) closeDrawer();
    });
  });
})();

/* ── Logout ──────────────────────────────────────────────── */
document.getElementById('admin-logout-btn').addEventListener('click', async function() {
  const ok = await dialog.confirm({
    type: 'danger', icon: 'logout',
    title: 'Log out?',
    message: 'You will be returned to the login page.',
    confirmText: 'Log out', cancelText: 'Stay'
  });
  if (!ok) return;
  try {
    await fetch('/api/auth/logout.php', {
      method: 'POST', headers: {'X-CSRF-Token': CSRF}, credentials: 'same-origin'
    });
  } catch(_) {}
  window.location.href = '/login.php';
});
</script>
</body>
</html>