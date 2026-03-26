'use strict';


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


/* CSRF */
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* API wrapper */
async function api(endpoint, options = {}) {
  const isForm = options.body instanceof FormData;
  const headers = { 'X-CSRF-Token': getCsrf() };
  if (!isForm) headers['Content-Type'] = 'application/json';
  const res = await fetch('/api/' + endpoint, {
    credentials: 'same-origin',
    headers: { ...headers, ...(options.headers || {}) },
    ...options,
  });
  let data;
  try { data = await res.json(); } catch(e) { throw new Error('Server error (invalid response)'); }
  if (!data.success) throw new Error(data.error || 'Something went wrong');
  return data.data;
}

/* Toast */
function showToast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const el = document.createElement('div');
  el.className = 'toast toast--' + type;
  el.textContent = msg;
  container.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

/* Navigation */
const pages   = document.querySelectorAll('.page');
const navBtns  = document.querySelectorAll('.nav-btn[data-page]');
const bnavBtns = document.querySelectorAll('.bnav-btn[data-page]');

function goTo(pageId) {
  pages.forEach(p => p.classList.toggle('active', p.id === 'page-' + pageId));
  navBtns.forEach(b => b.classList.toggle('active', b.dataset.page === pageId));
  bnavBtns.forEach(b => b.classList.toggle('active', b.dataset.page === pageId));

  if (pageId === 'chat'    && typeof Chat    !== 'undefined') Chat.init();
  if (pageId === 'notices' && typeof Notices !== 'undefined') Notices.load();
  if (pageId === 'storage' && typeof Storage_ !== 'undefined') Storage_.load();
  if (pageId === 'members' && typeof Members !== 'undefined') Members.load();
}

navBtns.forEach(b  => b.addEventListener('click', () => goTo(b.dataset.page)));
bnavBtns.forEach(b => b.addEventListener('click', () => goTo(b.dataset.page)));

/* Search */
const searchBar   = document.getElementById('search-bar');
const searchInput = document.getElementById('search-input');

document.getElementById('btn-search')?.addEventListener('click', () => {
  searchBar?.classList.add('open');
  searchInput?.focus();
});
document.getElementById('search-cancel')?.addEventListener('click', () => {
  searchBar?.classList.remove('open');
  if (searchInput) searchInput.value = '';
  // Clear any active chat filter
  const area = document.getElementById('chat-messages');
  if (area) area.querySelectorAll('.msg-row, .date-divider').forEach(el => el.style.display = '');
});
searchInput?.addEventListener('input', () => {
  const q = searchInput.value.trim();
  const active = document.querySelector('.page.active')?.id;
  if (active === 'page-chat') {
    // Client-side filter: show/hide messages containing the search term
    const area = document.getElementById('chat-messages');
    if (!area) return;
    if (!q) {
      // Clear search: show everything
      area.querySelectorAll('.msg-row, .date-divider').forEach(el => el.style.display = '');
      return;
    }
    const lower = q.toLowerCase();
    // Hide/show individual message rows based on text content
    area.querySelectorAll('.msg-row').forEach(row => {
      const text = row.querySelector('.msg-bubble')?.textContent || '';
      row.style.display = text.toLowerCase().includes(lower) ? '' : 'none';
    });
    // Hide date dividers that have no visible messages after them
    area.querySelectorAll('.date-divider').forEach(divider => {
      let next = divider.nextElementSibling;
      let hasVisible = false;
      while (next && !next.classList.contains('date-divider')) {
        if (next.classList.contains('msg-row') && next.style.display !== 'none') {
          hasVisible = true; break;
        }
        next = next.nextElementSibling;
      }
      divider.style.display = hasVisible ? '' : 'none';
    });
  }
  if (active === 'page-notices' && typeof Notices  !== 'undefined') Notices.search(q);
  if (active === 'page-storage' && typeof Storage_ !== 'undefined') Storage_.search(q);
  if (active === 'page-members' && typeof Members  !== 'undefined') Members.search(q);
});

/* Settings modal */
const backdrop = document.getElementById('settings-backdrop');

function openSettings() { backdrop?.classList.add('open'); showView('main'); }
function closeSettings() { backdrop?.classList.remove('open'); }

document.getElementById('btn-settings')?.addEventListener('click', openSettings);
document.getElementById('btn-settings-mob')?.addEventListener('click', openSettings);
backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeSettings(); });

function showView(name) {
  document.querySelectorAll('.settings-view').forEach(v => v.classList.remove('open'));
  document.getElementById('sv-' + name)?.classList.add('open');
}
document.querySelectorAll('[data-settings-view]').forEach(row => {
  row.addEventListener('click', () => showView(row.dataset.settingsView));
});
document.querySelectorAll('.sv-back').forEach(btn => {
  btn.addEventListener('click', () => showView('main'));
});

/* Notification toggles */
['notif', 'sound'].forEach(key => {
  document.getElementById('toggle-' + key)?.addEventListener('change', async () => {
    try {
      await api('settings/notifications.php', {
        method: 'POST',
        body: JSON.stringify({
          notif_enabled: document.getElementById('toggle-notif')?.checked,
          sound_enabled: document.getElementById('toggle-sound')?.checked,
        }),
      });
      showToast('Saved', 'success');
    } catch(e) { showToast(e.message, 'error'); }
  });
});

/* Members header button */
document.getElementById('btn-members')?.addEventListener('click', () => goTo('members'));

/* Logout */
async function doLogout() {
  const ok = await dialog.confirm({
    type: 'danger', icon: 'logout',
    title: 'Log out?',
    message: 'You will be returned to the login page.',
    confirmText: 'Log out', cancelText: 'Stay'
  });
  if (!ok) return;
  try {
    await fetch('/api/auth/logout.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': getCsrf() },
      credentials: 'same-origin'
    });
  } catch(_) {}
  window.location.href = '/login.php';
}
document.getElementById('btn-logout')?.addEventListener('click', doLogout);
document.getElementById('btn-logout-modal')?.addEventListener('click', doLogout);

/* Badge refresh */
async function refreshBadges() {
  try {
    const d = await api('posts/unread.php');
    const nb = document.querySelector('.nav-btn[data-page="notices"] .nav-badge');
    const bd = document.querySelector('.bnav-btn[data-page="notices"] .bnav-dot');
    const n  = d.unread_posts || 0;
    if (nb) { nb.textContent = n || ''; nb.style.display = n ? 'flex' : 'none'; }
    if (bd) bd.style.display = n ? 'block' : 'none';
  } catch(_) {}
}

/* Online count refresh */
async function refreshOnlineCount() {
  try {
    const d = await api('members/index.php?counts_only=1');

    // Update header counts
    const oc = document.getElementById('online-count');
    const mc = document.getElementById('member-count');
    if (oc && d.online_count !== undefined) oc.textContent = d.online_count;
    if (mc && d.total !== undefined) mc.textContent = d.total;
  } catch(_) {}
}

/* PWA Service Worker Registration and Push Notifications */
async function registerSW() {
  if ('serviceWorker' in navigator) {
    try {
      const reg = await navigator.serviceWorker.register('/sw.js');
      console.log('SW registered:', reg);

      // Request notification permission and subscribe
      if ('PushManager' in window) {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
          subscribeUserToPush(reg);
        }
      }
    } catch (e) {
      console.error('SW registration failed:', e);
    }
  }
}

async function subscribeUserToPush(registration) {
  try {
    const subscribeOptions = {
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
    };

    const subscription = await registration.pushManager.subscribe(subscribeOptions);
    console.log('User subscribed:', subscription);

    // Send subscription to server
    const subJson = subscription.toJSON();
    await api('settings/push_subscribe.php', {
      method: 'POST',
      body: JSON.stringify({
        endpoint: subJson.endpoint,
        p256dh: subJson.keys.p256dh,
        auth: subJson.keys.auth
      })
    });
  } catch (e) {
    console.error('Failed to subscribe to push:', e);
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

/* Init */
document.addEventListener('DOMContentLoaded', () => {
  goTo('chat');
  refreshBadges();
  refreshOnlineCount();
  setInterval(refreshBadges, 20000);
  setInterval(refreshOnlineCount, 30000);
  registerSW();
});