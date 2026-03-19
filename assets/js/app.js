/* ============================================================
   GSSC SCIENCE OFFICIAL  app.js
   ============================================================ */
'use strict';

/*  CSRF token  */
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/*  API wrapper  */
async function api(endpoint, options = {}) {
  const isFormData = options.body instanceof FormData;
  const headers = { 'X-CSRF-Token': getCsrf() };
  if (!isFormData) headers['Content-Type'] = 'application/json';

  const res = await fetch('/api/' + endpoint, {
    credentials: 'same-origin',
    headers: { ...headers, ...(options.headers || {}) },
    ...options,
  });

  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Something went wrong');
  return data.data;
}

/*  Toast  */
function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

/*  Navigation  */
const pages    = document.querySelectorAll('.page');
const navBtns  = document.querySelectorAll('.nav-btn[data-page]');
const bnavBtns = document.querySelectorAll('.bnav-btn[data-page]');

function goTo(pageId) {
  pages.forEach(p => p.classList.toggle('active', p.id === 'page-' + pageId));

  navBtns.forEach(b => b.classList.toggle('active', b.dataset.page === pageId));
  bnavBtns.forEach(b => b.classList.toggle('active', b.dataset.page === pageId));

  // fire page-specific init
  if (pageId === 'chat' && typeof Chat !== 'undefined') Chat.init();
  if (pageId === 'notices' && typeof Notices !== 'undefined') Notices.load();
  if (pageId === 'storage' && typeof Storage_ !== 'undefined') Storage_.load();
  if (pageId === 'members' && typeof Members !== 'undefined') Members.load();
}

navBtns.forEach(b => b.addEventListener('click', () => goTo(b.dataset.page)));
bnavBtns.forEach(b => b.addEventListener('click', () => goTo(b.dataset.page)));

/*  Search bar  */
const searchBar    = document.getElementById('search-bar');
const searchInput  = document.getElementById('search-input');
const searchBtn    = document.getElementById('btn-search');
const searchCancel = document.getElementById('search-cancel');

searchBtn?.addEventListener('click', () => {
  searchBar?.classList.add('open');
  searchInput?.focus();
});
searchCancel?.addEventListener('click', () => {
  searchBar?.classList.remove('open');
  if (searchInput) searchInput.value = '';
});
searchInput?.addEventListener('input', () => {
  const q = searchInput.value.trim();
  const activePage = document.querySelector('.page.active')?.id;
  if (activePage === 'page-notices' && typeof Notices !== 'undefined') Notices.search(q);
  if (activePage === 'page-storage' && typeof Storage_ !== 'undefined') Storage_.search(q);
  if (activePage === 'page-members' && typeof Members !== 'undefined') Members.search(q);
});

/*  Settings Modal  */
const backdrop      = document.getElementById('settings-backdrop');
const settingsModal = document.getElementById('settings-modal');
const mainView      = document.getElementById('sv-main');

function openSettings() {
  backdrop?.classList.add('open');
  showView('main');
}
function closeSettings() {
  backdrop?.classList.remove('open');
}

document.getElementById('btn-settings')?.addEventListener('click', openSettings);
document.getElementById('btn-settings-mob')?.addEventListener('click', openSettings);
backdrop?.addEventListener('click', e => {
  if (e.target === backdrop) closeSettings();
});

function showView(name) {
  document.querySelectorAll('.settings-view').forEach(v => v.classList.remove('open'));
  document.getElementById('sv-' + name)?.classList.add('open');
}

// Settings rows  sub-views
document.querySelectorAll('[data-settings-view]').forEach(row => {
  row.addEventListener('click', () => showView(row.dataset.settingsView));
});
document.querySelectorAll('.sv-back').forEach(btn => {
  btn.addEventListener('click', () => showView('main'));
});

/*  Notification toggles  */
['notif', 'sound'].forEach(key => {
  const el = document.getElementById('toggle-' + key);
  el?.addEventListener('change', async () => {
    try {
      await api('settings/notifications.php', {
        method: 'POST',
        body: JSON.stringify({
          notif_enabled: document.getElementById('toggle-notif')?.checked,
          sound_enabled: document.getElementById('toggle-sound')?.checked,
        }),
      });
    } catch (e) {
      showToast(e.message, 'error');
      el.checked = !el.checked; // revert
    }
  });
});

/*  Members header button  */
document.getElementById('btn-members')?.addEventListener('click', () => goTo('members'));

/*  Online count polling  */
async function refreshOnlineCount() {
  try {
    const { online_count, total } = await api('members/index.php?counts_only=1');
    const el = document.getElementById('online-count');
    const t  = document.getElementById('member-count');
    if (el) el.textContent = online_count;
    if (t)  t.textContent  = total;
  } catch (_) {}
}
setInterval(refreshOnlineCount, 30000);

/*  Unread badge polling  */
async function refreshBadges() {
  try {
    const { unread_posts, pending_storage } = await api('posts/unread.php');
    const nb = document.querySelector('[data-page="notices"] .nav-badge');
    const bb = document.querySelector('[data-page="notices"].bnav-btn .bnav-dot');

    if (nb) {
      nb.textContent = unread_posts || '';
      nb.style.display = unread_posts ? 'flex' : 'none';
    }
    if (bb) bb.style.display = unread_posts ? 'block' : 'none';
  } catch (_) {}
}
setInterval(refreshBadges, 15000);

/*  Init  */
document.addEventListener('DOMContentLoaded', () => {
  // default to chat page
  goTo('chat');
  refreshBadges();
});
