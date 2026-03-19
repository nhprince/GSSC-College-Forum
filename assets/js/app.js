'use strict';

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
});
searchInput?.addEventListener('input', () => {
  const q = searchInput.value.trim();
  const active = document.querySelector('.page.active')?.id;
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
  if (!confirm('Are you sure you want to log out?')) return;
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
    const oc = document.getElementById('online-count');
    const mc = document.getElementById('member-count');
    if (oc && d.online_count !== undefined) oc.textContent = d.online_count;
    if (mc && d.total !== undefined) mc.textContent = d.total;
  } catch(_) {}
}

/* Init */
document.addEventListener('DOMContentLoaded', () => {
  goTo('chat');
  refreshBadges();
  refreshOnlineCount();
  setInterval(refreshBadges, 20000);
  setInterval(refreshOnlineCount, 30000);
});