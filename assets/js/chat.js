'use strict';

const Chat = {
  lastId: 0,
  eventSource: null,
  pollTimer: null,
  replyTo: null,
  _initialized: false,

  init() {
    if (this._initialized) return;
    this._initialized = true;
    this.loadHistory();
    this.bindInput();
    this.bindContextMenu();
  },

  async loadHistory() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    area.innerHTML = this.skelHTML();
    try {
      const data = await api('chat/messages.php?limit=50');
      area.innerHTML = '';
      if (!data.chat_enabled) {
        this.showDisabled(); return;
      }
      if (!data.messages || !data.messages.length) {
        area.innerHTML = `<div class="empty-state">
          <div class="empty-icon">&#x1F4AC;</div>
          <div class="empty-title">No messages yet</div>
          <div class="empty-sub">Be the first to say something!</div>
        </div>`;
      } else {
        let prevDate = null;
        data.messages.forEach(m => {
          const d = (m.created_at || '').substring(0, 10);
          if (d && d !== prevDate) { area.insertAdjacentHTML('beforeend', this.dateDivider(m.created_at)); prevDate = d; }
          area.insertAdjacentHTML('beforeend', this.renderMsg(m));
        });
        this.lastId = data.last_id || 0;
        this.scrollBottom();
      }
      // Use polling as the primary real-time method on this server.
      // SSE holds a PHP-FPM worker open for minutes and starves other requests.
      // Polling fires short ~50ms requests and frees the worker immediately.
      this.startPoll();
    } catch(e) {
      const area2 = document.getElementById('chat-messages');
      if (area2) area2.innerHTML = `<div class="empty-state">
        <div class="empty-icon">&#x26A0;</div>
        <div class="empty-title">Could not load messages</div>
        <div class="empty-sub">${e.message || 'Please refresh the page.'}</div>
      </div>`;
    }
  },

  showDisabled() {
    const bar = document.getElementById('chat-input-bar');
    const dis = document.getElementById('chat-disabled-bar');
    if (bar) bar.style.display = 'none';
    if (dis) dis.style.display = 'block';
  },

  // Polling: fires a short HTTP request every 3 seconds, completes instantly,
  // frees the PHP worker. New messages appear within 3 seconds max.
  startPoll() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    this.pollTimer = setInterval(async () => {
      try {
        const d = await api('chat/messages.php?since_id=' + this.lastId);
        if (!d.chat_enabled) { this.showDisabled(); clearInterval(this.pollTimer); return; }
        (d.messages || []).forEach(m => { this.appendMsg(m); this.lastId = m.id; });
      } catch(_) {}
    }, 3000);
  },

  stopPoll() {
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
  },

  appendMsg(msg) {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const empty = area.querySelector('.empty-state');
    if (empty) area.innerHTML = '';

    const msgDate = (msg.created_at || '').substring(0, 10);
    const lastDiv = area.querySelector('.date-divider:last-of-type');
    if (msgDate && (!lastDiv || lastDiv.dataset.date !== msgDate)) {
      area.insertAdjacentHTML('beforeend', this.dateDivider(msg.created_at));
    }
    area.insertAdjacentHTML('beforeend', this.renderMsg(msg));
    this.autoScroll();
  },

  renderMsg(msg) {
    const isOwn = msg.user && msg.user.id === CURRENT_USER.id;
    const time  = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';
    const name  = (msg.user && (msg.user.nickname || msg.user.full_name || '?'));
    const init  = name ? name[0].toUpperCase() : '?';
    const uid   = msg.user ? msg.user.id : 0;

    const avatarCol = isOwn ? '' : `
      <div class="msg-avatar-col">
        <div class="msg-avatar">${msg.user && msg.user.avatar
          ? `<img src="/uploads/avatars/${e(msg.user.avatar)}" alt="">`
          : init}</div>
      </div>`;

    const senderLine = (!isOwn && name) ? `<div class="msg-sender">${e(name)}</div>` : '';
    const body = msg.is_deleted
      ? `<em style="opacity:.45;font-size:12px">Message deleted</em>`
      : this.renderBody(msg);

    const reactions = this.renderReactions(msg.reactions || {}, msg.id);

    return `<div class="msg-row${isOwn ? ' own' : ''}" data-id="${msg.id}" data-uid="${uid}">
      ${avatarCol}
      <div class="msg-content">
        ${senderLine}
        <div class="msg-bubble" data-id="${msg.id}">${body}</div>
        ${reactions}
        <div class="msg-time">${time}</div>
      </div>
    </div>`;
  },

  renderBody(msg) {
    if (!msg.type || msg.type === 'text') return e(msg.body || '').replace(/\n/g, '<br>');
    if (msg.type === 'image') return `<img src="/uploads/${e(msg.file_path)}" alt="" style="max-width:200px;border-radius:10px;display:block;cursor:pointer" onclick="window.open(this.src)">`;
    return `<a href="/api/storage/download.php?id=${msg.id}" style="color:inherit;display:flex;align-items:center;gap:6px">&#x1F4CE; ${e(msg.file_name || 'File')}</a>`;
  },

  renderReactions(reactions, msgId) {
    const entries = Object.entries(reactions || {});
    const pills = entries.map(([emoji, count]) =>
      `<button class="reaction-pill" onclick="Chat.toggleReact(${msgId},'${emoji}')">${emoji} <span class="r-count">${count}</span></button>`
    ).join('');
    return `<div class="reaction-bar" data-msg="${msgId}">${pills}</div>`;
  },

  async toggleReact(msgId, emoji) {
    try {
      const { reactions } = await api('chat/react.php', {
        method: 'POST', body: JSON.stringify({ message_id: msgId, emoji })
      });
      const bar = document.querySelector(`.reaction-bar[data-msg="${msgId}"]`);
      if (bar) {
        bar.innerHTML = Object.entries(reactions || {}).map(([em, cnt]) =>
          `<button class="reaction-pill" onclick="Chat.toggleReact(${msgId},'${em}')">${em} <span class="r-count">${cnt}</span></button>`
        ).join('');
      }
    } catch(err) { showToast(err.message, 'error'); }
  },

  dateDivider(dateStr) {
    const date = (dateStr || '').substring(0, 10);
    const d    = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - d) / 86400000);
    const label = diff === 0 ? 'Today' : diff === 1 ? 'Yesterday'
      : d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'2-digit'});
    return `<div class="date-divider" data-date="${date}"><span>${label}</span></div>`;
  },

  autoScroll() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    const atBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 150;
    if (atBottom) this.scrollBottom();
    const btn = document.getElementById('scroll-down-btn');
    if (btn) btn.classList.toggle('show', !atBottom);
  },

  scrollBottom() {
    const area = document.getElementById('chat-messages');
    if (area) area.scrollTop = area.scrollHeight;
  },

  setReply(msgId, text) {
    this.replyTo = msgId;
    let preview = document.getElementById('reply-preview');
    if (!preview) {
      preview = document.createElement('div');
      preview.id = 'reply-preview';
      preview.className = 'reply-preview';
      const bar = document.getElementById('chat-input-bar');
      bar?.parentNode.insertBefore(preview, bar);
    }
    preview.innerHTML = `<div class="reply-preview-text">&#x21A9; ${e(text).substring(0, 80)}</div>
      <span class="reply-preview-close" onclick="Chat.clearReply()">&#x2715;</span>`;
  },

  clearReply() {
    this.replyTo = null;
    document.getElementById('reply-preview')?.remove();
  },

  bindInput() {
    const input = document.getElementById('chat-input');
    const send  = document.getElementById('chat-send');
    send?.addEventListener('click', () => this.submit());
    input?.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.submit(); }
    });
    input?.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 100) + 'px';
    });
    document.getElementById('scroll-down-btn')?.addEventListener('click', () => this.scrollBottom());
    document.getElementById('chat-messages')?.addEventListener('scroll', () => this.autoScroll());
    document.getElementById('chat-attach')?.addEventListener('click', () => document.getElementById('file-input')?.click());
    document.getElementById('chat-image')?.addEventListener('click', () => document.getElementById('image-input')?.click());
    document.getElementById('file-input')?.addEventListener('change', e => { const f = e.target.files[0]; if (f) this.sendFile(f); });
    document.getElementById('image-input')?.addEventListener('change', e => { const f = e.target.files[0]; if (f) this.sendFile(f); });
  },

  bindContextMenu() {
    const area = document.getElementById('chat-messages');
    if (!area) return;
    area.addEventListener('contextmenu', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      ev.preventDefault();
      const row = bubble.closest('.msg-row');
      this.showCtxMenu(ev.clientX, ev.clientY, parseInt(row?.dataset.id), parseInt(row?.dataset.uid), bubble.innerText);
    });
    let pressTimer;
    area.addEventListener('touchstart', ev => {
      const bubble = ev.target.closest('.msg-bubble');
      if (!bubble) return;
      pressTimer = setTimeout(() => {
        const row = bubble.closest('.msg-row');
        const t = ev.changedTouches[0];
        this.showCtxMenu(t.clientX, t.clientY, parseInt(row?.dataset.id), parseInt(row?.dataset.uid), bubble.innerText);
      }, 500);
    });
    area.addEventListener('touchend', () => clearTimeout(pressTimer));
    document.addEventListener('click', () => this.hideCtxMenu());
  },

  showCtxMenu(x, y, msgId, userId, text) {
    this.hideCtxMenu();
    if (!msgId) return;
    const isOwn = userId === CURRENT_USER.id;
    const isMod = CURRENT_USER.role === 'moderator' || CURRENT_USER.role === 'admin';
    const menu  = document.createElement('div');
    menu.id = 'ctx-menu'; menu.className = 'ctx-menu';
    const items = [
      { icon: '&#x21A9;', label: 'Reply',  action: () => this.setReply(msgId, text) },
      { icon: '&#x1F60A;',label: 'React',  action: () => this.showEmojiPicker(msgId, menu) },
    ];
    if (isOwn || isMod) items.push({ icon: '&#x1F5D1;', label: 'Delete', danger: true, action: async () => {
      try {
        await api('chat/delete.php', { method:'POST', body: JSON.stringify({message_id: msgId}) });
        const row = document.querySelector(`.msg-row[data-id="${msgId}"]`);
        if (row) row.querySelector('.msg-bubble').innerHTML = `<em style="opacity:.45;font-size:12px">Message deleted</em>`;
      } catch(err) { showToast(err.message, 'error'); }
    }});
    items.forEach(item => {
      const el = document.createElement('div');
      el.className = 'ctx-item' + (item.danger ? ' ctx-item--danger' : '');
      el.innerHTML = `<span>${item.icon}</span> ${item.label}`;
      el.addEventListener('click', ev => { ev.stopPropagation(); this.hideCtxMenu(); item.action(); });
      menu.appendChild(el);
    });
    menu.style.left = Math.min(x, window.innerWidth - 170) + 'px';
    menu.style.top  = Math.min(y, window.innerHeight - 150) + 'px';
    document.body.appendChild(menu);
  },

  showEmojiPicker(msgId, anchor) {
    const emojis = ['&#x1F44D;','&#x2764;&#xFE0F;','&#x1F602;','&#x1F62E;','&#x1F622;','&#x1F64F;','&#x1F525;','&#x1F44F;','&#x1F389;'];
    const picker = document.createElement('div');
    picker.className = 'emoji-picker';
    picker.style.cssText = 'position:fixed;z-index:200';
    const rect = anchor.getBoundingClientRect();
    picker.style.left = rect.left + 'px';
    picker.style.top  = Math.max(10, rect.top - 60) + 'px';
    emojis.forEach(em => {
      const btn = document.createElement('span');
      btn.className = 'emoji-opt'; btn.innerHTML = em;
      btn.addEventListener('click', ev => { ev.stopPropagation(); picker.remove(); this.toggleReact(msgId, btn.textContent); });
      picker.appendChild(btn);
    });
    document.body.appendChild(picker);
    setTimeout(() => document.addEventListener('click', () => picker.remove(), {once: true}), 100);
  },

  hideCtxMenu() { document.getElementById('ctx-menu')?.remove(); },

  async submit() {
    const input = document.getElementById('chat-input');
    const body  = input?.value.trim();
    if (!body) return;
    input.value = ''; input.style.height = 'auto';
    try {
      const resp = await api('chat/messages.php', {
        method: 'POST',
        body: JSON.stringify({ body, type: 'text', reply_to_id: this.replyTo || null })
      });
      this.clearReply();
      // Show own message immediately without waiting for the next poll
      if (resp && resp.message) {
        this.appendMsg(resp.message);
        this.lastId = resp.message.id;
      }
    } catch(err) {
      showToast(err.message, 'error');
      input.value = body;
    }
  },

  async sendFile(file) {
    const fd = new FormData();
    fd.append('file', file);
    if (this.replyTo) fd.append('reply_to_id', this.replyTo);
    showToast('Uploading...');
    try {
      const res  = await fetch('/api/chat/messages.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': getCsrf() },
        body: fd, credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.success) {
        this.clearReply();
        if (data.data && data.data.message) {
          this.appendMsg(data.data.message);
          this.lastId = data.data.message.id;
        }
      } else { showToast(data.error || 'Upload failed', 'error'); }
    } catch(_) { showToast('Upload failed', 'error'); }
  },

  skelHTML() {
    return `<div style="padding:10px 12px">
      <div class="date-divider"><span>Today</span></div>
      <div class="msg-row" style="margin-bottom:10px">
        <div class="msg-avatar-col"><div class="skeleton" style="width:30px;height:30px;border-radius:50%"></div></div>
        <div style="display:flex;flex-direction:column;gap:5px">
          <div class="skeleton" style="width:80px;height:11px;border-radius:6px"></div>
          <div class="skeleton" style="width:200px;height:38px;border-radius:18px 18px 18px 4px"></div>
        </div>
      </div>
      <div class="msg-row own" style="margin-bottom:10px">
        <div style="margin-left:auto"><div class="skeleton" style="width:160px;height:38px;border-radius:18px 18px 4px 18px;background:#e8c0c0"></div></div>
      </div>
    </div>`;
  }
};

function e(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}