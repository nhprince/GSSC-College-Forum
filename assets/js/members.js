'use strict';

const Members = {
  _loaded: false,
  _q: '',

  load() {
    if (this._loaded) return;
    this._loaded = true;
    this.fetch();
  },

  search(q) { this._q = q; this._loaded = false; this.fetch(); },

  async fetch() {
    const maleList   = document.getElementById('member-list-male');
    const femaleList = document.getElementById('member-list-female');
    if (!maleList || !femaleList) return;
    maleList.innerHTML = femaleList.innerHTML = this.skelHTML();

    try {
      const params = this._q ? '?search=' + encodeURIComponent(this._q) : '';
      const data = await api('members/index.php' + params);
      const male   = data.male   || [];
      const female = data.female || [];

      const oc = document.getElementById('online-count');
      const mc = document.getElementById('member-count');
      if (oc) oc.textContent = data.online_count || 0;
      if (mc) mc.textContent = data.total || 0;
      const mc2 = document.getElementById('male-count');
      const fc2 = document.getElementById('female-count');
      if (mc2) mc2.textContent = male.length + ' member' + (male.length !== 1 ? 's' : '');
      if (fc2) fc2.textContent = female.length + ' member' + (female.length !== 1 ? 's' : '');

      maleList.innerHTML   = male.length   ? male.map(m => this.renderRow(m)).join('')   : '<p style="padding:12px 14px;font-size:13px;color:var(--txt-3)">No male members found.</p>';
      femaleList.innerHTML = female.length ? female.map(m => this.renderRow(m)).join('') : '<p style="padding:12px 14px;font-size:13px;color:var(--txt-3)">No female members found.</p>';

      this.updateOnlineStrip([...male, ...(data.female || []), ...(data.other || [])].filter(m => m.is_online));

    } catch(err) {
      const empty = `<div class="empty-state"><div class="empty-icon">&#x26A0;</div><div class="empty-title">Could not load members</div><div class="empty-sub">${err.message || 'Please refresh.'}</div></div>`;
      if (maleList)   maleList.innerHTML   = empty;
      if (femaleList) femaleList.innerHTML = '';
    }
  },

  renderRow(m) {
    const name   = m.nickname || m.full_name || '?';
    const init   = name[0].toUpperCase();
    const gender = m.gender === 'female' ? 'female' : 'male';
    const ring   = m.is_online ? '<div class="member-online-ring"></div>' : '';
    const avatar = m.avatar
      ? `<img src="/uploads/avatars/${esc(m.avatar)}" alt="">`
      : init;
    const roleBadge = (m.role === 'moderator' || m.role === 'admin')
      ? `<span style="font-size:9px;background:var(--red-lt);color:var(--red);padding:1px 6px;border-radius:999px;font-weight:600;margin-left:4px">${m.role}</span>`
      : '';
    return `<div class="member-row" onclick="window.location='/profile.php?id=${m.id}'">
      <div class="member-avatar ${gender}" style="position:relative">${avatar}${ring}</div>
      <div style="min-width:0">
        <div class="member-name">${esc(name)} (${esc(m.roll_no || '')})${roleBadge}</div>
        <div class="member-full">${esc(m.full_name || '')}</div>
      </div>
    </div>`;
  },

  updateOnlineStrip(onlineMembers) {
    const strip = document.getElementById('online-strip');
    if (!strip) return;
    if (!onlineMembers.length) {
      strip.innerHTML = '<span style="font-size:11px;color:rgba(255,255,255,.3)">None online</span>';
      return;
    }
    strip.innerHTML = onlineMembers.slice(0, 8).map(m => {
      const init = (m.nickname || m.full_name || '?')[0].toUpperCase();
      const img  = m.avatar ? `<img src="/uploads/avatars/${esc(m.avatar)}" alt="">` : init;
      return `<div class="o-avatar" title="${esc(m.nickname || m.full_name)}" onclick="window.location='/profile.php?id=${m.id}'">${img}</div>`;
    }).join('');
  },

  skelHTML() {
    return [1,2,3,4].map(() => `<div class="member-row" style="pointer-events:none">
      <div class="skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
      <div style="flex:1;display:flex;flex-direction:column;gap:4px">
        <div class="skeleton" style="width:55%;height:12px;border-radius:6px"></div>
        <div class="skeleton" style="width:75%;height:10px;border-radius:6px"></div>
      </div>
    </div>`).join('');
  }
};

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}